<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * AJAX endpoint to get statistics step by step.
 *
 * @package     local_questioncleaner
 * @copyright   2024
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');

use local_questioncleaner\cleaner;
use local_questioncleaner\cache_helper;

// Check permissions
require_login();
require_capability('local/questioncleaner:view', context_system::instance());

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

// Increase execution time and memory limit for large databases
@set_time_limit(300); // 5 minutes
@ini_set('memory_limit', '512M');

$step = optional_param('step', '', PARAM_ALPHANUMEXT);
$force = optional_param('force', 0, PARAM_INT); // Force refresh
$result = ['success' => true];

// Check cache first (unless forcing refresh)
if (!$force) {
    $cached = cache_helper::get_cached_statistics();
    if ($cached && isset($cached['statistics']) && is_array($cached['statistics']) && isset($cached['statistics'][$step])) {
        // Return cached value for this step
        $result['value'] = $cached['statistics'][$step];
        $result['step'] = $step;
        $result['cached'] = true;
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

try {
    global $DB;
    
    switch ($step) {
        case 'used_questions_count':
            // Step 0: Used questions count (needed for comparison)
            $result['value'] = cleaner::get_used_questions_count();
            $result['step'] = 'used_questions_count';
            break;
            
        case 'total_questions':
            // Step 1: Total questions
            $result['value'] = (int)$DB->count_records('question', ['parent' => 0]);
            $result['step'] = 'total_questions';
            break;
            
        case 'duplicated_questions':
            // Step 2: Duplicated questions
            cleaner::delay();
            $sql = "SELECT COUNT(*) - COUNT(DISTINCT CONCAT(name, '-', qtype, '-', MD5(questiontext))) AS duplicated
                      FROM {question}
                     WHERE parent = 0";
            $record = $DB->get_record_sql($sql);
            $result['value'] = (int)($record->duplicated ?? 0);
            $result['step'] = 'duplicated_questions';
            break;
            
        case 'unused_questions':
            // Step 3: Unused questions
            cleaner::delay();
            $usedentryids = cleaner::get_used_question_bank_entry_ids();
            $latestversion = 'qv.version = (SELECT MAX(v.version)
                                              FROM {question_versions} v
                                              JOIN {question_bank_entries} be
                                                ON be.id = v.questionbankentryid
                                             WHERE be.id = qbe.id AND v.status <> :substatus)';

            $params = [
                'status' => \core_question\local\bank\question_version_status::QUESTION_STATUS_READY,
                'substatus' => \core_question\local\bank\question_version_status::QUESTION_STATUS_HIDDEN,
            ];

            $sql = "SELECT COUNT(DISTINCT q.id) AS unused
                      FROM {question} q
                      JOIN {question_versions} qv ON qv.questionid = q.id
                      JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                     WHERE q.parent = 0
                       AND {$latestversion}
                       AND qv.status = :status";

            if (!empty($usedentryids)) {
                list($insql, $inparams) = $DB->get_in_or_equal($usedentryids, SQL_PARAMS_NAMED, 'used', false);
                $sql .= " AND qbe.id $insql";
                $params = array_merge($params, $inparams);
            }

            $record = $DB->get_record_sql($sql, $params);
            $result['value'] = (int)($record->unused ?? 0);
            $result['step'] = 'unused_questions';
            break;
            
        case 'orphaned_answers':
            // Step 4: Orphaned answers
            cleaner::delay();
            $sql = "SELECT COUNT(*) AS orphaned
                      FROM {question_answers} qa
                 LEFT JOIN {question} q ON q.id = qa.question
                     WHERE q.id IS NULL";
            $record = $DB->get_record_sql($sql);
            $result['value'] = (int)($record->orphaned ?? 0);
            $result['step'] = 'orphaned_answers';
            break;
            
        case 'unused_question_answers':
            // Step 5: Unused question answers
            cleaner::delay();
            $usedentryids = cleaner::get_used_question_bank_entry_ids();
            $latestversion = 'qv.version = (SELECT MAX(v.version)
                                              FROM {question_versions} v
                                              JOIN {question_bank_entries} be
                                                ON be.id = v.questionbankentryid
                                             WHERE be.id = qbe.id AND v.status <> :substatus)';

            $params = [
                'status' => \core_question\local\bank\question_version_status::QUESTION_STATUS_READY,
                'substatus' => \core_question\local\bank\question_version_status::QUESTION_STATUS_HIDDEN,
            ];

            $sql = "SELECT COUNT(*) AS unused
                      FROM {question_answers} qa
                      JOIN {question} q ON q.id = qa.question
                      JOIN {question_versions} qv ON qv.questionid = q.id
                      JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                     WHERE {$latestversion}
                       AND qv.status = :status";

            if (!empty($usedentryids)) {
                list($insql, $inparams) = $DB->get_in_or_equal($usedentryids, SQL_PARAMS_NAMED, 'used', false);
                $sql .= " AND qbe.id $insql";
                $params = array_merge($params, $inparams);
            }

            $record = $DB->get_record_sql($sql, $params);
            $result['value'] = (int)($record->unused ?? 0);
            $result['step'] = 'unused_question_answers';
            break;
            
        default:
            $result['success'] = false;
            $result['error'] = 'Invalid step';
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            exit;
    }
    
    // Cache the result (update existing cache or create new)
    $cached = cache_helper::get_cached_statistics();
    $stats = [];
    if ($cached && isset($cached['statistics']) && is_array($cached['statistics'])) {
        $stats = $cached['statistics'];
    }
    $stats[$step] = $result['value'];
    cache_helper::set_cached_statistics($stats);
    
    $result['cached'] = false;
    
    // Return JSON
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
