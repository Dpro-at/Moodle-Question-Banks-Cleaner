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
 * AJAX endpoint for deleting duplicate questions.
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

// Increase execution time and memory limit
@set_time_limit(300);
@ini_set('memory_limit', '512M');

$questionids = optional_param_array('question_ids', [], PARAM_INT);
$batch_size = optional_param('batch_size', 1000, PARAM_INT);
$batch_size = max(1, min(10000, $batch_size));

$result = ['success' => false];

try {
    if (empty($questionids)) {
        $result['error'] = 'No questions selected';
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Delete duplicate questions
    $delete_result = cleaner::delete_duplicate_questions($questionids, $batch_size);
    
    $result = [
        'success' => true,
        'deleted' => $delete_result['deleted'],
        'failed' => $delete_result['failed'],
        'errors' => $delete_result['errors']
    ];
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

