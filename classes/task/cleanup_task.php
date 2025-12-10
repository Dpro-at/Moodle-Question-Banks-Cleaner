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
 * Scheduled task for automatic question cleanup.
 *
 * @package     local_questioncleaner
 * @copyright   2024
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_questioncleaner\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');

/**
 * Cleanup task class
 */
class cleanup_task extends \core\task\scheduled_task {

    /**
     * Get the name of the task
     *
     * @return string
     */
    public function get_name() {
        return get_string('cleanuptask', 'local_questioncleaner');
    }

    /**
     * Execute the task
     */
    public function execute() {
        global $CFG;

        // Check if auto cleanup is enabled
        if (!get_config('local_questioncleaner', 'enable_auto_cleanup')) {
            mtrace('Auto cleanup is disabled. Skipping task.');
            return;
        }

        mtrace('Starting question cleanup task...');

        $batchsize = get_config('local_questioncleaner', 'batch_size') ?: 1000;
        $batchsize = max(1, min(10000, (int)$batchsize));

        // Get unused questions (limited batch)
        $unusedquestions = \local_questioncleaner\cleaner::check_unused_questions(null, $batchsize);
        
        if (empty($unusedquestions)) {
            mtrace('No unused questions found.');
            return;
        }

        $questionids = array_column($unusedquestions, 'id');
        mtrace('Found ' . count($questionids) . ' unused questions. Starting deletion...');

        // Delete unused questions (with verification)
        $result = \local_questioncleaner\cleaner::delete_unused_questions_safe($questionids, true, $batchsize);

        mtrace('Deletion completed:');
        mtrace('  - Deleted: ' . $result['deleted']);
        mtrace('  - Failed: ' . $result['failed']);

        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $error) {
                mtrace('  - Error: ' . $error);
            }
        }

        // Get orphaned answers (100% safe to delete)
        $orphanedanswers = \local_questioncleaner\cleaner::check_orphaned_answers($batchsize);
        
        if (!empty($orphanedanswers)) {
            $answerids = array_column($orphanedanswers, 'id');
            mtrace('Found ' . count($answerids) . ' orphaned answers. Starting deletion...');

            // Orphaned answers are 100% safe - question is already deleted
            $answerresult = \local_questioncleaner\cleaner::delete_orphaned_answers($answerids, $batchsize);
            
            mtrace('Orphaned answers deletion completed:');
            mtrace('  - Deleted: ' . $answerresult['deleted']);
            mtrace('  - Failed: ' . $answerresult['failed']);
        }
        
        // Get answers for unused questions (verify first)
        $unusedanswers = \local_questioncleaner\cleaner::check_unused_question_answers($batchsize);
        
        if (!empty($unusedanswers)) {
            $answerids = array_column($unusedanswers, 'id');
            mtrace('Found ' . count($answerids) . ' answers for unused questions. Starting deletion...');

            // Verify question is not used before deleting answers
            $answerresult = \local_questioncleaner\cleaner::delete_unused_answers($answerids, $batchsize);
            
            mtrace('Unused question answers deletion completed:');
            mtrace('  - Deleted: ' . $answerresult['deleted']);
            mtrace('  - Failed: ' . $answerresult['failed']);
        }

        mtrace('Question cleanup task completed.');
    }
}

