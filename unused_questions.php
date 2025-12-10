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
 * Unused questions page.
 *
 * @package     local_questioncleaner
 * @copyright   2024
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_questioncleaner\cleaner;

// Check permissions
require_login();
require_capability('local/questioncleaner:view', context_system::instance());

$courseid = optional_param('courseid', 0, PARAM_INT);
$limit = optional_param('limit', 100, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$limit = max(1, min(10000, $limit));

// Handle actions
if ($action === 'verify' && confirm_sesskey()) {
    $questionids = optional_param_array('question_ids', [], PARAM_INT);
    if (!empty($questionids)) {
        // Get question bank entry IDs
        global $DB;
        list($insql, $params) = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
        $sql = "SELECT DISTINCT qbe.id
                  FROM {question} q
                  JOIN {question_versions} qv ON qv.questionid = q.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                 WHERE q.id $insql";
        $entryids = $DB->get_fieldset_sql($sql, $params);
        
        $verified = cleaner::verify_question_usage($entryids);
        redirect(new moodle_url('/local/questioncleaner/unused_questions.php', ['courseid' => $courseid, 'limit' => $limit]),
                 get_string('questionsverified', 'local_questioncleaner') . ': ' . count($verified),
                 null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

// Set up page
$PAGE->set_context(context_system::instance());
$url = new moodle_url('/local/questioncleaner/unused_questions.php', ['limit' => $limit]);
if ($courseid) {
    $url->param('courseid', $courseid);
}
$PAGE->set_url($url);
$PAGE->set_title(get_string('unusedquestions', 'local_questioncleaner'));
$PAGE->set_heading(get_string('unusedquestions', 'local_questioncleaner'));
$PAGE->set_pagelayout('admin');

// Get unused questions
$questions = cleaner::check_unused_questions($courseid, $limit);

// Output
echo $OUTPUT->header();

// Tabs
$tabs = [];
$tabs[] = new tabobject('index',
    new moodle_url('/local/questioncleaner/index.php'),
    get_string('reports', 'local_questioncleaner')
);
$tabs[] = new tabobject('duplicate',
    new moodle_url('/local/questioncleaner/duplicate_questions.php'),
    get_string('duplicatequestions', 'local_questioncleaner')
);
$tabs[] = new tabobject('unused',
    new moodle_url('/local/questioncleaner/unused_questions.php'),
    get_string('unusedquestions', 'local_questioncleaner')
);
$tabs[] = new tabobject('used',
    new moodle_url('/local/questioncleaner/used_questions.php'),
    get_string('usedquestions', 'local_questioncleaner')
);
$tabs[] = new tabobject('answers',
    new moodle_url('/local/questioncleaner/unused_answers.php'),
    get_string('unusedanswers', 'local_questioncleaner')
);
$tabs[] = new tabobject('cleanup',
    new moodle_url('/local/questioncleaner/cleanup.php'),
    get_string('cleanup', 'local_questioncleaner')
);

echo $OUTPUT->tabtree($tabs, 'unused');

// Course filter
echo html_writer::start_tag('div', ['class' => 'mb-3']);
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url]);
echo html_writer::tag('label', get_string('filterbycourse', 'local_questioncleaner') . ': ', ['for' => 'courseid', 'class' => 'me-2']);
$courses_raw = $DB->get_records('course', [], 'fullname', 'id,fullname');
// Get counts and sort courses
$courses_with_counts = [];
foreach ($courses_raw as $course) {
    $count = cleaner::get_unused_questions_count_by_course($course->id);
    $courses_with_counts[] = [
        'id' => $course->id,
        'fullname' => $course->fullname,
        'count' => $count
    ];
}
// Sort: courses with questions first (descending by count), then courses with 0
usort($courses_with_counts, function($a, $b) {
    if ($a['count'] > 0 && $b['count'] == 0) {
        return -1; // a comes first
    }
    if ($a['count'] == 0 && $b['count'] > 0) {
        return 1; // b comes first
    }
    // Both have same status (both > 0 or both == 0), sort by count descending, then by name
    if ($a['count'] != $b['count']) {
        return $b['count'] - $a['count']; // Descending by count
    }
    return strcmp($a['fullname'], $b['fullname']); // Ascending by name
});

echo html_writer::start_tag('select', [
    'name' => 'courseid',
    'id' => 'courseid',
    'class' => 'form-control d-inline-block',
    'style' => 'width: 300px; margin: 0 10px;'
]);
echo html_writer::tag('option', get_string('allcourses', 'local_questioncleaner'), [
    'value' => '0',
    'selected' => $courseid == 0 ? true : false
]);
foreach ($courses_with_counts as $course) {
    $optiontext = $course['fullname'];
    if ($course['count'] > 0) {
        $optiontext .= ' (' . number_format($course['count']) . ')';
        $style = 'color: green; font-weight: bold;';
    } else {
        $optiontext .= ' (' . number_format($course['count']) . ')';
        $style = '';
    }
    echo html_writer::tag('option', $optiontext, [
        'value' => $course['id'],
        'selected' => $courseid == $course['id'] ? true : false,
        'style' => $style
    ]);
}
echo html_writer::end_tag('select');
echo html_writer::tag('label', get_string('numberofquestions', 'local_questioncleaner') . ': ', ['for' => 'limit', 'class' => 'me-2']);
echo html_writer::empty_tag('input', [
    'type' => 'number',
    'id' => 'limit',
    'name' => 'limit',
    'value' => $limit,
    'min' => 1,
    'max' => 10000,
    'class' => 'form-control d-inline-block',
    'style' => 'width: 150px; margin: 0 10px;'
]);
echo html_writer::tag('button', get_string('load', 'local_questioncleaner'), [
    'type' => 'submit',
    'class' => 'btn btn-primary'
]);
echo html_writer::end_tag('form');
echo html_writer::end_tag('div');

// Unused Questions table
echo html_writer::start_tag('div', ['class' => 'card mb-4']);
echo html_writer::start_tag('div', ['class' => 'card-body']);
echo html_writer::tag('h5', get_string('unusedquestions', 'local_questioncleaner') . ' (' . count($questions) . ')', ['class' => 'card-title']);

if (!empty($questions)) {
    $table = new html_table();
    $table->head = [
        html_writer::tag('input', '', [
            'type' => 'checkbox',
            'id' => 'select-all-unused',
            'class' => 'form-check-input'
        ]) . ' ' . get_string('selectall', 'local_questioncleaner'),
        get_string('questionid', 'local_questioncleaner'),
        get_string('questionname', 'local_questioncleaner'),
        get_string('questiontype', 'local_questioncleaner'),
        get_string('category', 'local_questioncleaner')
    ];
    $table->attributes['class'] = 'generaltable';
    $table->attributes['id'] = 'unused-questions-table';
    
    foreach ($questions as $question) {
        $checkbox = html_writer::tag('input', '', [
            'type' => 'checkbox',
            'name' => 'question_ids[]',
            'value' => $question->id,
            'class' => 'form-check-input question-checkbox'
        ]);
        
        $table->data[] = [
            $checkbox,
            $question->id ?? '',
            $question->name ?? '',
            $question->qtype ?? '',
            $question->categoryname ?? ''
        ];
    }
    
    echo html_writer::table($table);
    
    // Action buttons
    echo html_writer::start_tag('div', ['class' => 'mt-3']);
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'verify']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'limit', 'value' => $limit]);
    echo html_writer::tag('button', get_string('verify', 'local_questioncleaner'), [
        'type' => 'button',
        'class' => 'btn btn-secondary',
        'id' => 'verify-btn'
    ]);
    echo html_writer::end_tag('form');
    echo html_writer::end_tag('div');
} else {
    echo html_writer::tag('p', get_string('nounusedquestions', 'local_questioncleaner'), ['class' => 'text-muted']);
}

echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

// JavaScript
$js = 'require([\'jquery\'], function($) {
    $(document).ready(function() {
        $("#select-all-unused").on("change", function() {
            var checked = $(this).is(":checked");
            $(".question-checkbox").prop("checked", checked);
        });
        
        $("#verify-btn").on("click", function(e) {
            e.preventDefault();
            var selected = [];
            $(".question-checkbox:checked").each(function() {
                selected.push($(this).val());
            });
            
            if (selected.length === 0) {
                alert("' . get_string('noselected', 'local_questioncleaner') . '");
                return false;
            }
            
            var form = $(this).closest("form");
            selected.forEach(function(id) {
                form.append($("<input>", {
                    type: "hidden",
                    name: "question_ids[]",
                    value: id
                }));
            });
            
            form.submit();
            return true;
        });
    });
});';

$PAGE->requires->js_init_code($js);

echo $OUTPUT->footer();

