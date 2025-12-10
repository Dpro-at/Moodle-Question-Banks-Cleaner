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
 * Detailed statistics page for question-related tables.
 *
 * @package     local_questioncleaner
 * @copyright   2024
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_questioncleaner\statistics;

// Check permissions
require_login();
require_capability('local/questioncleaner:view', context_system::instance());

// Set up page
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/questioncleaner/detailed_statistics.php'));
$PAGE->set_title(get_string('detailedstatistics', 'local_questioncleaner'));
$PAGE->set_heading(get_string('detailedstatistics', 'local_questioncleaner'));
$PAGE->set_pagelayout('admin');

// Get detailed statistics
$detailed = statistics::get_detailed_statistics();
$summary = statistics::get_summary();

// Output
echo $OUTPUT->header();

// Tabs
$tabs = [];
$tabs[] = new tabobject('index',
    new moodle_url('/local/questioncleaner/index.php'),
    get_string('reports', 'local_questioncleaner')
);
$tabs[] = new tabobject('detailed',
    new moodle_url('/local/questioncleaner/detailed_statistics.php'),
    get_string('detailedstatistics', 'local_questioncleaner')
);
$tabs[] = new tabobject('duplicate',
    new moodle_url('/local/questioncleaner/duplicate_questions.php'),
    get_string('duplicatequestions', 'local_questioncleaner')
);
$tabs[] = new tabobject('unused',
    new moodle_url('/local/questioncleaner/unused_questions.php'),
    get_string('unusedquestions', 'local_questioncleaner')
);
$tabs[] = new tabobject('answers',
    new moodle_url('/local/questioncleaner/unused_answers.php'),
    get_string('unusedanswers', 'local_questioncleaner')
);
$tabs[] = new tabobject('cleanup',
    new moodle_url('/local/questioncleaner/cleanup.php'),
    get_string('cleanup', 'local_questioncleaner')
);

echo $OUTPUT->tabtree($tabs, 'detailed');

// Summary cards
echo html_writer::start_tag('div', ['class' => 'row mt-3']);

echo html_writer::start_tag('div', ['class' => 'col-md-3 mb-3']);
echo html_writer::start_tag('div', ['class' => 'card']);
echo html_writer::start_tag('div', ['class' => 'card-body']);
echo html_writer::tag('h5', get_string('totaltables', 'local_questioncleaner'), ['class' => 'card-title']);
echo html_writer::tag('h3', number_format($summary['total_tables']), ['class' => 'text-primary']);
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'col-md-3 mb-3']);
echo html_writer::start_tag('div', ['class' => 'card']);
echo html_writer::start_tag('div', ['class' => 'card-body']);
echo html_writer::tag('h5', get_string('totalrecords', 'local_questioncleaner'), ['class' => 'card-title']);
echo html_writer::tag('h3', number_format($summary['total_records']), ['class' => 'text-info']);
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'col-md-3 mb-3']);
echo html_writer::start_tag('div', ['class' => 'card']);
echo html_writer::start_tag('div', ['class' => 'card-body']);
echo html_writer::tag('h5', get_string('totalsize', 'local_questioncleaner'), ['class' => 'card-title']);
echo html_writer::tag('h3', $summary['total_size_formatted'], ['class' => 'text-success']);
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'col-md-3 mb-3']);
echo html_writer::start_tag('div', ['class' => 'card']);
echo html_writer::start_tag('div', ['class' => 'card-body']);
echo html_writer::tag('h5', get_string('largesttable', 'local_questioncleaner'), ['class' => 'card-title']);
echo html_writer::tag('h6', $summary['largest_table'] ?? '-', ['class' => 'text-warning']);
echo html_writer::tag('small', statistics::format_bytes($summary['largest_table_size']), ['class' => 'text-muted']);
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

echo html_writer::end_tag('div');

// Detailed table
echo html_writer::start_tag('div', ['class' => 'card mt-4']);
echo html_writer::start_tag('div', ['class' => 'card-body']);
echo html_writer::tag('h5', get_string('tabledetails', 'local_questioncleaner'), ['class' => 'card-title mb-3']);

$table = new html_table();
$table->head = [
    get_string('tablename', 'local_questioncleaner'),
    get_string('rows', 'local_questioncleaner'),
    get_string('size', 'local_questioncleaner')
];
$table->attributes['class'] = 'generaltable';
$table->attributes['id'] = 'detailed-statistics-table';

// Sort by size (descending)
uasort($detailed, function($a, $b) {
    return ($b['size'] ?? 0) - ($a['size'] ?? 0);
});

foreach ($detailed as $tablename => $data) {
    $row = [];
    
    // Table name
    $row[] = html_writer::tag('strong', $data['label']) . 
             html_writer::tag('br') . 
             html_writer::tag('small', $tablename, ['class' => 'text-muted']);
    
    // Row count
    if (isset($data['error'])) {
        $row[] = html_writer::tag('span', 'N/A', ['class' => 'text-muted']);
    } else {
        $row[] = number_format($data['count']);
    }
    
    // Size
    if (isset($data['error'])) {
        $row[] = html_writer::tag('span', 'N/A', ['class' => 'text-muted']);
    } else {
        $row[] = $data['size_formatted'];
    }
    
    $table->data[] = $row;
}

echo html_writer::table($table);
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

echo $OUTPUT->footer();

