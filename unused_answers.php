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
 * Unused answers page.
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

$limit = optional_param('limit', 100, PARAM_INT);
$type = optional_param('type', 'orphaned', PARAM_ALPHA); // 'orphaned' or 'unused'
$action = optional_param('action', '', PARAM_ALPHA);
$limit = max(1, min(10000, $limit));

// Handle delete action
if ($action === 'delete' && confirm_sesskey()) {
    require_capability('local/questioncleaner:cleanup', context_system::instance());
    $answerids = optional_param_array('answer_ids', [], PARAM_INT);
    if (!empty($answerids)) {
        if ($type === 'orphaned') {
            // Orphaned answers are 100% safe to delete
            $result = cleaner::delete_orphaned_answers($answerids, 1000);
        } else {
            // Unused question answers - verify first
            $result = cleaner::delete_unused_answers($answerids, 1000);
        }
        if ($result['deleted'] > 0) {
            $message = get_string('answersdeleted', 'local_questioncleaner') . ': ' . $result['deleted'];
            if ($result['failed'] > 0) {
                $message .= ' (' . get_string('failed', 'local_questioncleaner') . ': ' . $result['failed'] . ')';
            }
            redirect(new moodle_url('/local/questioncleaner/unused_answers.php', ['limit' => $limit, 'type' => $type]),
                     $message,
                     null, \core\output\notification::NOTIFY_SUCCESS);
        } else {
            redirect(new moodle_url('/local/questioncleaner/unused_answers.php', ['limit' => $limit, 'type' => $type]),
                     get_string('deletionfailed', 'local_questioncleaner'),
                     null, \core\output\notification::NOTIFY_ERROR);
        }
    }
}

// Set up page
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/questioncleaner/unused_answers.php', ['limit' => $limit, 'type' => $type]));
$PAGE->set_title(get_string('unusedanswers', 'local_questioncleaner'));
$PAGE->set_heading(get_string('unusedanswers', 'local_questioncleaner'));
$PAGE->set_pagelayout('admin');

// Get unused answers
if ($type === 'orphaned') {
    $answers = cleaner::check_orphaned_answers($limit);
    $title = get_string('orphanedanswers', 'local_questioncleaner');
} else {
    $answers = cleaner::check_unused_question_answers($limit);
    $title = get_string('unusedquestionanswers', 'local_questioncleaner');
}

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

echo $OUTPUT->tabtree($tabs, 'answers');

// Type selector
echo html_writer::start_tag('div', ['class' => 'mb-3']);
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url]);
echo html_writer::tag('label', 'Type: ', ['for' => 'type', 'class' => 'me-2']);
echo html_writer::select(
    ['orphaned' => get_string('orphanedanswers', 'local_questioncleaner'), 'unused' => get_string('unusedquestionanswers', 'local_questioncleaner')],
    'type',
    $type,
    false,
    ['class' => 'form-control d-inline-block', 'style' => 'width: 250px; margin: 0 10px;']
);
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

// Answers table
echo html_writer::start_tag('div', ['class' => 'card mb-4']);
echo html_writer::start_tag('div', ['class' => 'card-body']);
echo html_writer::tag('h5', $title . ' (' . count($answers) . ')', ['class' => 'card-title']);

if (!empty($answers)) {
    $table = new html_table();
    $table->head = [
        html_writer::tag('input', '', [
            'type' => 'checkbox',
            'id' => 'select-all-answers',
            'class' => 'form-check-input'
        ]) . ' ' . get_string('selectall', 'local_questioncleaner'),
        get_string('answerid', 'local_questioncleaner'),
        get_string('answertext', 'local_questioncleaner'),
        get_string('relatedquestion', 'local_questioncleaner')
    ];
    $table->attributes['class'] = 'generaltable';
    $table->attributes['id'] = 'unused-answers-table';
    
    foreach ($answers as $answer) {
        $checkbox = html_writer::tag('input', '', [
            'type' => 'checkbox',
            'name' => 'answer_ids[]',
            'value' => $answer->id,
            'class' => 'form-check-input answer-checkbox'
        ]);
        
        $questioninfo = '';
        if (isset($answer->questionid)) {
            $questioninfo = $answer->questionid . ' - ' . ($answer->questionname ?? '');
        } else if (isset($answer->question)) {
            $questioninfo = $answer->question . ' (deleted)';
        }
        
        $table->data[] = [
            $checkbox,
            $answer->id,
            substr(strip_tags($answer->answer ?? ''), 0, 100),
            $questioninfo
        ];
    }
    
    echo html_writer::table($table);
    
    // Safety info
    if ($type === 'orphaned') {
        echo html_writer::start_tag('div', ['class' => 'alert alert-info mt-3']);
        echo html_writer::tag('strong', get_string('orphanedanswerssafe', 'local_questioncleaner'));
        echo html_writer::tag('p', get_string('orphanedanswerssafedesc', 'local_questioncleaner'), ['class' => 'mb-0']);
        echo html_writer::end_tag('div');
    } else {
        echo html_writer::start_tag('div', ['class' => 'alert alert-warning mt-3']);
        echo html_writer::tag('strong', get_string('unusedanswerswarning', 'local_questioncleaner'));
        echo html_writer::tag('p', get_string('unusedanswerswarningdesc', 'local_questioncleaner'), ['class' => 'mb-0']);
        echo html_writer::end_tag('div');
    }
    
    // Delete button
    if (has_capability('local/questioncleaner:cleanup', context_system::instance())) {
        echo html_writer::start_tag('div', ['class' => 'mt-3']);
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url, 'onsubmit' => 'return confirm("' . get_string('confirmdeletion', 'local_questioncleaner') . '");']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'delete']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'type', 'value' => $type]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'limit', 'value' => $limit]);
        echo html_writer::tag('button', get_string('delete', 'local_questioncleaner'), [
            'type' => 'button',
            'class' => 'btn btn-danger',
            'id' => 'delete-answers-btn'
        ]);
        echo html_writer::end_tag('form');
        echo html_writer::end_tag('div');
    }
} else {
    echo html_writer::tag('p', get_string('nounusedanswers', 'local_questioncleaner'), ['class' => 'text-muted']);
}

echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

// JavaScript
$js = 'require([\'jquery\'], function($) {
    $(document).ready(function() {
        $("#select-all-answers").on("change", function() {
            var checked = $(this).is(":checked");
            $(".answer-checkbox").prop("checked", checked);
        });
        
        $("#delete-answers-btn").on("click", function(e) {
            e.preventDefault();
            var selected = [];
            $(".answer-checkbox:checked").each(function() {
                selected.push($(this).val());
            });
            
            if (selected.length === 0) {
                alert("' . get_string('noselected', 'local_questioncleaner') . '");
                return false;
            }
            
            if (!confirm("' . get_string('confirmdeletion', 'local_questioncleaner') . '")) {
                return false;
            }
            
            var form = $(this).closest("form");
            selected.forEach(function(id) {
                form.append($("<input>", {
                    type: "hidden",
                    name: "answer_ids[]",
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

