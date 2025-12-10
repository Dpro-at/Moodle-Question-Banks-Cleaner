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
 * Used questions page (read-only display).
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
$perpage = optional_param('perpage', 50, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);

$perpage = max(10, min(500, $perpage));
$page = max(0, $page);
$offset = $page * $perpage;

// Set up page
$PAGE->set_context(context_system::instance());
$url = new moodle_url('/local/questioncleaner/used_questions.php', ['perpage' => $perpage, 'page' => $page]);
if ($courseid) {
    $url->param('courseid', $courseid);
}
$PAGE->set_url($url);
$PAGE->set_title(get_string('usedquestions', 'local_questioncleaner'));
$PAGE->set_heading(get_string('usedquestions', 'local_questioncleaner'));
$PAGE->set_pagelayout('admin');

// Get total count
$totalcount = cleaner::get_used_questions_count_total($courseid);

// Get used questions with pagination
$questions = cleaner::check_used_questions($courseid, $perpage, $offset);

// Calculate pagination
$totalpages = $totalcount > 0 ? ceil($totalcount / $perpage) : 1;
$currentpage = $page + 1; // 1-based for display

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

echo $OUTPUT->tabtree($tabs, 'used');

// Course filter and per page selector
echo html_writer::start_tag('div', ['class' => 'mb-3']);
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url]);
echo html_writer::tag('label', get_string('filterbycourse', 'local_questioncleaner') . ': ', ['for' => 'courseid', 'class' => 'me-2']);
$courses_raw = $DB->get_records('course', [], 'fullname', 'id,fullname');
// Get counts and sort courses
$courses_with_counts = [];
foreach ($courses_raw as $course) {
    $count = cleaner::get_used_questions_count_by_course($course->id);
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
echo html_writer::tag('label', get_string('perpage', 'local_questioncleaner') . ': ', ['for' => 'perpage', 'class' => 'me-2']);
$perpageoptions = [10 => 10, 25 => 25, 50 => 50, 100 => 100, 200 => 200, 500 => 500];
echo html_writer::select($perpageoptions, 'perpage', $perpage, false, ['class' => 'form-control d-inline-block', 'style' => 'width: 100px; margin: 0 10px;', 'onchange' => 'this.form.submit();']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'page', 'value' => '0']);
echo html_writer::tag('button', get_string('load', 'local_questioncleaner'), [
    'type' => 'submit',
    'class' => 'btn btn-primary'
]);
echo html_writer::end_tag('form');
echo html_writer::end_tag('div');

// Used Questions table (read-only, no checkboxes or delete buttons)
echo html_writer::start_tag('div', ['class' => 'card mb-4']);
echo html_writer::start_tag('div', ['class' => 'card-body']);
echo html_writer::tag('h5', get_string('usedquestions', 'local_questioncleaner'), ['class' => 'card-title']);
echo html_writer::tag('p', get_string('usedquestionsinfo', 'local_questioncleaner'), ['class' => 'text-muted small mb-3']);

// Pagination info
$start = $offset + 1;
$end = min($offset + $perpage, $totalcount);
if ($totalcount > 0) {
    echo html_writer::tag('p', get_string('showing', 'local_questioncleaner') . ' ' . $start . ' ' . 
        get_string('to', 'local_questioncleaner') . ' ' . $end . ' ' . 
        get_string('of', 'local_questioncleaner') . ' ' . $totalcount . ' ' . 
        get_string('results', 'local_questioncleaner'), ['class' => 'text-muted mb-3']);
}

if (!empty($questions)) {
    $table = new html_table();
    $table->head = [
        get_string('questionid', 'local_questioncleaner'),
        get_string('questionname', 'local_questioncleaner'),
        get_string('questiontype', 'local_questioncleaner'),
        get_string('category', 'local_questioncleaner')
    ];
    $table->attributes['class'] = 'generaltable';
    $table->attributes['id'] = 'used-questions-table';
    
    foreach ($questions as $question) {
        $table->data[] = [
            $question->id ?? '',
            $question->name ?? '',
            $question->qtype ?? '',
            $question->categoryname ?? ''
        ];
    }
    
    echo html_writer::table($table);
    
    // Pagination controls
    if ($totalpages > 1) {
        echo html_writer::start_tag('div', ['class' => 'mt-3']);
        echo html_writer::start_tag('nav');
        echo html_writer::start_tag('ul', ['class' => 'pagination justify-content-center']);
        
        // First page
        if ($page > 0) {
            $firsturl = clone $PAGE->url;
            $firsturl->param('page', 0);
            echo html_writer::start_tag('li', ['class' => 'page-item']);
            echo html_writer::tag('a', get_string('first', 'local_questioncleaner'), [
                'href' => $firsturl,
                'class' => 'page-link'
            ]);
            echo html_writer::end_tag('li');
        } else {
            echo html_writer::start_tag('li', ['class' => 'page-item disabled']);
            echo html_writer::tag('span', get_string('first', 'local_questioncleaner'), ['class' => 'page-link']);
            echo html_writer::end_tag('li');
        }
        
        // Previous page
        if ($page > 0) {
            $prevurl = clone $PAGE->url;
            $prevurl->param('page', $page - 1);
            echo html_writer::start_tag('li', ['class' => 'page-item']);
            echo html_writer::tag('a', get_string('previous', 'local_questioncleaner'), [
                'href' => $prevurl,
                'class' => 'page-link'
            ]);
            echo html_writer::end_tag('li');
        } else {
            echo html_writer::start_tag('li', ['class' => 'page-item disabled']);
            echo html_writer::tag('span', get_string('previous', 'local_questioncleaner'), ['class' => 'page-link']);
            echo html_writer::end_tag('li');
        }
        
        // Page numbers (show max 10 pages around current)
        $startpage = max(0, $page - 5);
        $endpage = min($totalpages - 1, $page + 5);
        
        if ($startpage > 0) {
            echo html_writer::start_tag('li', ['class' => 'page-item disabled']);
            echo html_writer::tag('span', '...', ['class' => 'page-link']);
            echo html_writer::end_tag('li');
        }
        
        for ($i = $startpage; $i <= $endpage; $i++) {
            if ($i == $page) {
                echo html_writer::start_tag('li', ['class' => 'page-item active']);
                echo html_writer::tag('span', ($i + 1), ['class' => 'page-link']);
                echo html_writer::end_tag('li');
            } else {
                $pageurl = clone $PAGE->url;
                $pageurl->param('page', $i);
                echo html_writer::start_tag('li', ['class' => 'page-item']);
                echo html_writer::tag('a', ($i + 1), [
                    'href' => $pageurl,
                    'class' => 'page-link'
                ]);
                echo html_writer::end_tag('li');
            }
        }
        
        if ($endpage < $totalpages - 1) {
            echo html_writer::start_tag('li', ['class' => 'page-item disabled']);
            echo html_writer::tag('span', '...', ['class' => 'page-link']);
            echo html_writer::end_tag('li');
        }
        
        // Next page
        if ($page < $totalpages - 1) {
            $nexturl = clone $PAGE->url;
            $nexturl->param('page', $page + 1);
            echo html_writer::start_tag('li', ['class' => 'page-item']);
            echo html_writer::tag('a', get_string('next', 'local_questioncleaner'), [
                'href' => $nexturl,
                'class' => 'page-link'
            ]);
            echo html_writer::end_tag('li');
        } else {
            echo html_writer::start_tag('li', ['class' => 'page-item disabled']);
            echo html_writer::tag('span', get_string('next', 'local_questioncleaner'), ['class' => 'page-link']);
            echo html_writer::end_tag('li');
        }
        
        // Last page
        if ($page < $totalpages - 1) {
            $lasturl = clone $PAGE->url;
            $lasturl->param('page', $totalpages - 1);
            echo html_writer::start_tag('li', ['class' => 'page-item']);
            echo html_writer::tag('a', get_string('last', 'local_questioncleaner'), [
                'href' => $lasturl,
                'class' => 'page-link'
            ]);
            echo html_writer::end_tag('li');
        } else {
            echo html_writer::start_tag('li', ['class' => 'page-item disabled']);
            echo html_writer::tag('span', get_string('last', 'local_questioncleaner'), ['class' => 'page-link']);
            echo html_writer::end_tag('li');
        }
        
        echo html_writer::end_tag('ul');
        echo html_writer::end_tag('nav');
        echo html_writer::end_tag('div');
    }
} else {
    echo html_writer::tag('p', get_string('nousedquestions', 'local_questioncleaner'), ['class' => 'text-muted']);
}

echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

echo $OUTPUT->footer();

