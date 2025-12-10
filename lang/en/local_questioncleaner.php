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
 * Language strings for the questioncleaner plugin.
 *
 * @package     local_questioncleaner
 * @copyright   2024
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Question Bank Cleaner';
$string['questioncleaner'] = 'Question Bank Cleaner';
$string['questioncleaner:view'] = 'View question cleaner reports';
$string['questioncleaner:cleanup'] = 'Perform question cleanup operations';

// Navigation
$string['reports'] = 'Reports';
$string['duplicatequestions'] = 'Duplicate Questions';
$string['unusedquestions'] = 'Unused Questions';
$string['usedquestions'] = 'Used Questions';
$string['unusedanswers'] = 'Unused Answers';
$string['cleanup'] = 'Cleanup';

// Statistics
$string['totalquestions'] = 'Total Questions';
$string['duplicatedquestions'] = 'Duplicated Questions';
$string['unusedquestionscount'] = 'Unused Questions';
$string['usedquestionscount'] = 'Used Questions';
$string['orphanedanswers'] = 'Orphaned Answers';
$string['unusedquestionanswers'] = 'Answers for Unused Questions';

// Actions
$string['check'] = 'Check';
$string['delete'] = 'Delete';
$string['verify'] = 'Verify';
$string['cleanupselected'] = 'Cleanup Selected';
$string['selectall'] = 'Select All';
$string['deselectall'] = 'Deselect All';

// Messages
$string['noduplicatedquestions'] = 'No duplicated questions found';
$string['nounusedquestions'] = 'No unused questions found';
$string['nousedquestions'] = 'No used questions found';
$string['usedquestionsinfo'] = 'These questions are currently used in quizzes and cannot be deleted.';
$string['nounusedanswers'] = 'No unused answers found';
$string['questionsdeleted'] = 'Questions deleted';
$string['answersdeleted'] = 'Answers deleted';
$string['deletionsuccess'] = 'Deletion completed successfully';
$string['deletionfailed'] = 'Deletion failed';
$string['verificationrequired'] = 'Verification required before deletion';
$string['questionsverified'] = 'Questions verified as unused';

// Warnings
$string['warningdeletion'] = 'Warning: This will permanently delete the selected items. This action cannot be undone!';
$string['confirmdeletion'] = 'Are you sure you want to delete these items?';
$string['backuprecommended'] = 'It is strongly recommended to backup your database before proceeding.';

// Course filter
$string['filterbycourse'] = 'Filter by Course';
$string['allcourses'] = 'All Courses';
$string['selectcourse'] = 'Select Course';

// Details
$string['questionid'] = 'Question ID';
$string['questionname'] = 'Question Name';
$string['questiontype'] = 'Type';
$string['category'] = 'Category';
$string['answerid'] = 'Answer ID';
$string['answertext'] = 'Answer Text';
$string['relatedquestion'] = 'Related Question';
$string['viewdetails'] = 'View Details';
$string['load'] = 'Load';
$string['numberofquestions'] = 'Number of Questions';
$string['perpage'] = 'Per Page';
$string['page'] = 'Page';
$string['showing'] = 'Showing';
$string['to'] = 'to';
$string['of'] = 'of';
$string['results'] = 'results';
$string['first'] = 'First';
$string['previous'] = 'Previous';
$string['next'] = 'Next';
$string['last'] = 'Last';
$string['noselected'] = 'No items selected';
$string['failed'] = 'Failed';
$string['settings'] = 'Settings';
$string['batchsize'] = 'Batch Size';
$string['batchsize_desc'] = 'Number of records to process in each batch during cleanup operations';
$string['enableautocleanup'] = 'Enable Auto Cleanup';
$string['enableautocleanup_desc'] = 'Enable automatic cleanup tasks';
$string['information'] = 'Information';
$string['quicklinks'] = 'Quick Links';
$string['cleanuptask'] = 'Question Bank Cleanup Task';

// Progress bar
$string['loadingstatistics'] = 'Loading Statistics...';
$string['loadingtotalquestions'] = 'Loading total questions...';
$string['loadingduplicatedquestions'] = 'Checking duplicated questions...';
$string['loadingunusedquestions'] = 'Checking unused questions...';
$string['loadingorphanedanswers'] = 'Checking orphaned answers...';
$string['loadingunusedanswers'] = 'Checking unused question answers...';
$string['loadingusedquestions'] = 'Loading used questions count...';
$string['completing'] = 'Completing...';
$string['errorloadingstatistics'] = 'Error loading statistics';

// Detailed statistics
$string['detailedstatistics'] = 'Detailed Statistics';
$string['totaltables'] = 'Total Tables';
$string['totalrecords'] = 'Total Records';
$string['totalsize'] = 'Total Size';
$string['largesttable'] = 'Largest Table';
$string['tabledetails'] = 'Table Details';
$string['tablename'] = 'Table Name';
$string['rows'] = 'Rows';
$string['size'] = 'Size';

// Cache
$string['lastupdated'] = 'Last Updated';
$string['usingcacheddata'] = 'Using cached data';
$string['nocachedata'] = 'No cached data available';
$string['refreshstatistics'] = 'Refresh Statistics';
$string['cacherefreshed'] = 'Cache cleared. Statistics will be recalculated.';

// Cleanup process
$string['whatgetsdeleted'] = 'What Gets Deleted';
$string['unusedquestionsdeletion'] = 'When deleting unused questions, the following data is removed in order:';
$string['orphanedanswersdeletion'] = 'Orphaned answers are 100% safe to delete because they are linked to questions that no longer exist. Only the answer records are deleted.';
$string['unusedanswersdeletion'] = 'Answers for unused questions are deleted only after verifying that the question is not used in any quiz.';
$string['deletionstep1'] = 'Question answers (question_answers)';
$string['deletionstep2'] = 'Question type options (qtype_*_options)';
$string['deletionstep3'] = 'Question versions (question_versions)';
$string['deletionstep4'] = 'Question bank entries (question_bank_entries) - only if no versions remain';
$string['deletionstep5'] = 'Questions themselves (question)';
$string['safetychecks'] = 'Safety Checks';
$string['safetycheck1'] = 'Questions are verified against question_references before deletion';
$string['safetycheck2'] = 'Double verification: checked before and during deletion';
$string['safetycheck3'] = 'Orphaned answers are verified to ensure the question is deleted';
$string['safetycheck4'] = 'Answers for unused questions are verified to ensure the question is not used';
$string['orphanedanswerssafe'] = '100% Safe to Delete';
$string['orphanedanswerssafedesc'] = 'These answers are linked to questions that no longer exist. They can be safely deleted without any risk.';
$string['unusedanswerswarning'] = 'Verification Required';
$string['unusedanswerswarningdesc'] = 'These answers belong to unused questions. They will be verified before deletion to ensure the question is not used in any quiz.';

// Number formatting
$string['million'] = 'million';
$string['thousand'] = 'thousand';

// Interactive cleanup
$string['startcleanup'] = 'Start Cleanup';
$string['stopcleanup'] = 'Stop Cleanup';
$string['cleanuptype'] = 'Cleanup Type';
$string['cleanuptype_unused'] = 'Unused Questions';
$string['cleanuptype_orphaned'] = 'Orphaned Answers';
$string['cleanuptype_unusedanswers'] = 'Answers for Unused Questions';
$string['cleanuptype_duplicate'] = 'Duplicate Questions';
$string['numberofbatches'] = 'Number of Batches';
$string['processall'] = 'Process All';
$string['deleted'] = 'Deleted';
$string['remaining'] = 'Remaining';
$string['processing'] = 'Processing...';
$string['cleanupinprogress'] = 'Cleanup in progress';
$string['cleanupstopped'] = 'Cleanup stopped by user';
$string['cleanupcompleted'] = 'Cleanup completed';
$string['cleanupfailed'] = 'Cleanup failed';
$string['currentbatch'] = 'Current Batch';
$string['totalbatches'] = 'Total Batches';
$string['selectcleanuptype'] = 'Please select a cleanup type';
$string['invalidbatchsize'] = 'Invalid batch size';
$string['invalidnumberofbatches'] = 'Invalid number of batches';

