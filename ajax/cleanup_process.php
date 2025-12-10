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
 * AJAX endpoint for interactive cleanup process.
 *
 * @package     local_questioncleaner
 * @copyright   2024
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');

use local_questioncleaner\cleaner;

// Check permissions
require_login();
require_capability('local/questioncleaner:cleanup', context_system::instance());
require_sesskey();

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

// Increase execution time and memory limit for large databases
@set_time_limit(300); // 5 minutes
@ini_set('memory_limit', '512M');

$action = optional_param('action', '', PARAM_ALPHA); // 'start', 'process', 'stop', 'status'
$cleanuptype = optional_param('cleanuptype', '', PARAM_ALPHANUMEXT);
$batch_size = optional_param('batch_size', 1000, PARAM_INT);
$num_batches = optional_param('num_batches', 0, PARAM_INT); // 0 means all
$batch_number = optional_param('batch_number', 0, PARAM_INT);

$batch_size = max(1, min(10000, $batch_size));
$num_batches = max(0, $num_batches);

$result = ['success' => false];

// Session-based stop flag
$stopflag = 'local_questioncleaner_stop_' . $USER->id;

try {
    switch ($action) {
        case 'start':
            // Initialize cleanup session
            $SESSION->$stopflag = false;
            
            global $DB;
            
            // Get total count based on cleanup type
            $total = 0;
            switch ($cleanuptype) {
                case 'duplicate_questions':
                    // Count duplicate questions (excluding one from each group)
                    $duplicates = cleaner::check_duplicate_questions(100000);
                    $grouped = [];
                    foreach ($duplicates as $question) {
                        $key = $question->duplicate_key;
                        if (!isset($grouped[$key])) {
                            $grouped[$key] = [];
                        }
                        $grouped[$key][] = $question;
                    }
                    // Count questions to delete (all except one from each group)
                    foreach ($grouped as $key => $group) {
                        if (count($group) > 1) {
                            $total += count($group) - 1; // All except one
                        }
                    }
                    break;
                    
                case 'unused_questions':
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
                    $total = (int)($record->unused ?? 0);
                    break;
                    
                case 'orphaned_answers':
                    $sql = "SELECT COUNT(*) AS orphaned
                              FROM {question_answers} qa
                         LEFT JOIN {question} q ON q.id = qa.question
                             WHERE q.id IS NULL";
                    $record = $DB->get_record_sql($sql);
                    $total = (int)($record->orphaned ?? 0);
                    break;
                    
                case 'unused_answers':
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
                    $total = (int)($record->unused ?? 0);
                    break;
                    
                default:
                    throw new \Exception('Invalid cleanup type');
            }
            
            // Calculate actual number of batches
            $actual_batches = $num_batches > 0 ? min($num_batches, ceil($total / $batch_size)) : ceil($total / $batch_size);
            
            $result = [
                'success' => true,
                'total' => $total,
                'batch_size' => $batch_size,
                'total_batches' => $actual_batches,
                'cleanuptype' => $cleanuptype
            ];
            break;
            
        case 'process':
            // Check if stopped
            if (!empty($SESSION->$stopflag)) {
                $result = [
                    'success' => false,
                    'stopped' => true,
                    'message' => get_string('cleanupstopped', 'local_questioncleaner')
                ];
                break;
            }
            
            global $DB;
            
            // Process one batch
            $deleted = 0;
            $failed = 0;
            $errors = [];
            
            $offset = $batch_number * $batch_size;
            
            switch ($cleanuptype) {
                case 'duplicate_questions':
                    // Get batch of duplicate question IDs to delete
                    $duplicates = cleaner::check_duplicate_questions(100000);
                    $grouped = [];
                    foreach ($duplicates as $question) {
                        $key = $question->duplicate_key;
                        if (!isset($grouped[$key])) {
                            $grouped[$key] = [];
                        }
                        $grouped[$key][] = $question;
                    }
                    
                    // Collect IDs to delete (all except lowest ID from each group)
                    $todelete = [];
                    foreach ($grouped as $key => $group) {
                        if (count($group) > 1) {
                            usort($group, function($a, $b) {
                                return $a->id - $b->id;
                            });
                            // Keep first (lowest ID), delete others
                            for ($i = 1; $i < count($group); $i++) {
                                $todelete[] = $group[$i]->id;
                            }
                        }
                    }
                    
                    // Get batch
                    $batch = array_slice($todelete, $offset, $batch_size);
                    
                    if (!empty($batch)) {
                        $delete_result = cleaner::delete_duplicate_questions($batch, $batch_size);
                        $deleted = $delete_result['deleted'];
                        $failed = $delete_result['failed'];
                        $errors = $delete_result['errors'];
                    }
                    break;
                    
                case 'unused_questions':
                    // Get batch of unused question IDs directly from database
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
                    $sql = "SELECT DISTINCT q.id
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
                    $sql .= " ORDER BY q.id LIMIT " . (int)$batch_size . " OFFSET " . (int)$offset;
                    $questionids = $DB->get_fieldset_sql($sql, $params);
                    
                    if (!empty($questionids)) {
                        $delete_result = cleaner::delete_unused_questions_safe($questionids, true, $batch_size);
                        $deleted = $delete_result['deleted'];
                        $failed = $delete_result['failed'];
                        $errors = $delete_result['errors'];
                    }
                    break;
                    
                case 'orphaned_answers':
                    // Get batch of orphaned answer IDs directly from database
                    $sql = "SELECT qa.id
                              FROM {question_answers} qa
                         LEFT JOIN {question} q ON q.id = qa.question
                             WHERE q.id IS NULL
                             ORDER BY qa.id
                             LIMIT " . (int)$batch_size . " OFFSET " . (int)$offset;
                    $answerids = $DB->get_fieldset_sql($sql);
                    
                    if (!empty($answerids)) {
                        $delete_result = cleaner::delete_orphaned_answers($answerids, $batch_size);
                        $deleted = $delete_result['deleted'];
                        $failed = $delete_result['failed'];
                        $errors = $delete_result['errors'];
                    }
                    break;
                    
                case 'unused_answers':
                    // Get batch of unused answer IDs directly from database
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
                    $sql = "SELECT qa.id
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
                    $sql .= " ORDER BY qa.id LIMIT " . (int)$batch_size . " OFFSET " . (int)$offset;
                    $answerids = $DB->get_fieldset_sql($sql, $params);
                    
                    if (!empty($answerids)) {
                        $delete_result = cleaner::delete_unused_answers($answerids, $batch_size);
                        $deleted = $delete_result['deleted'];
                        $failed = $delete_result['failed'];
                        $errors = $delete_result['errors'];
                    }
                    break;
                    
                default:
                    throw new \Exception('Invalid cleanup type');
            }
            
            // Check if stopped during processing
            $stopped = !empty($SESSION->$stopflag);
            
            $result = [
                'success' => true,
                'batch_number' => $batch_number,
                'deleted' => $deleted,
                'failed' => $failed,
                'errors' => $errors,
                'stopped' => $stopped
            ];
            break;
            
        case 'stop':
            // Set stop flag
            $SESSION->$stopflag = true;
            $result = [
                'success' => true,
                'message' => get_string('cleanupstopped', 'local_questioncleaner')
            ];
            break;
            
        case 'status':
            // Check status
            $result = [
                'success' => true,
                'stopped' => !empty($SESSION->$stopflag)
            ];
            break;
            
        default:
            $result['error'] = 'Invalid action';
    }
    
    // Return JSON
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

