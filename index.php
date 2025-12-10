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
 * Main page for question cleaner plugin.
 *
 * @package     local_questioncleaner
 * @copyright   2024
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_questioncleaner\cleaner;
use local_questioncleaner\cache_helper;

// Check permissions
require_login();
require_capability('local/questioncleaner:view', context_system::instance());

// Handle cache refresh
$refresh = optional_param('refresh', 0, PARAM_INT);
if ($refresh && confirm_sesskey()) {
    cache_helper::clear_cache();
    redirect(new moodle_url('/local/questioncleaner/index.php'),
             get_string('cacherefreshed', 'local_questioncleaner'),
             null, \core\output\notification::NOTIFY_SUCCESS);
}

// Set up page
$PAGE->set_context(context_system::instance());
$url = new moodle_url('/local/questioncleaner/index.php');
$PAGE->set_url($url);
$PAGE->set_title(get_string('pluginname', 'local_questioncleaner'));
$PAGE->set_heading(get_string('pluginname', 'local_questioncleaner'));
$PAGE->set_pagelayout('admin');

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
$tabs[] = new tabobject('detailed',
    new moodle_url('/local/questioncleaner/detailed_statistics.php'),
    get_string('detailedstatistics', 'local_questioncleaner')
);
$tabs[] = new tabobject('cleanup',
    new moodle_url('/local/questioncleaner/cleanup.php'),
    get_string('cleanup', 'local_questioncleaner')
);

echo $OUTPUT->tabtree($tabs, 'index');

// Cache info and refresh button
$cachedate = cache_helper::get_cache_date();
$cacheexists = cache_helper::is_cache_valid(86400); // 24 hours

echo html_writer::start_tag('div', ['class' => 'alert alert-info mb-3']);
if ($cacheexists && $cachedate) {
    echo html_writer::tag('strong', get_string('lastupdated', 'local_questioncleaner') . ': ' . $cachedate);
    echo ' | ';
    echo html_writer::tag('span', get_string('usingcacheddata', 'local_questioncleaner'), ['class' => 'text-muted']);
} else {
    echo html_writer::tag('strong', get_string('nocachedata', 'local_questioncleaner'));
}
echo ' ';
echo html_writer::tag('a', get_string('refreshstatistics', 'local_questioncleaner'),
    [
        'href' => new moodle_url('/local/questioncleaner/index.php', ['refresh' => 1, 'sesskey' => sesskey()]),
        'class' => 'btn btn-sm btn-primary'
    ]);
echo html_writer::end_tag('div');

// Progress bar container
echo html_writer::start_tag('div', ['id' => 'stats-progress-container', 'class' => 'card mb-4']);
echo html_writer::start_tag('div', ['class' => 'card-body']);
echo html_writer::tag('h5', get_string('loadingstatistics', 'local_questioncleaner'), ['class' => 'card-title']);
echo html_writer::start_tag('div', ['class' => 'progress', 'style' => 'height: 30px;']);
echo html_writer::tag('div', '', [
    'id' => 'stats-progress-bar',
    'class' => 'progress-bar progress-bar-striped progress-bar-animated',
    'role' => 'progressbar',
    'style' => 'width: 0%',
    'aria-valuenow' => '0',
    'aria-valuemin' => '0',
    'aria-valuemax' => '100'
]);
echo html_writer::end_tag('div');
echo html_writer::tag('div', '', ['id' => 'stats-progress-text', 'class' => 'mt-2 text-muted']);
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

// Statistics cards container (initially hidden)
echo html_writer::start_tag('div', ['id' => 'stats-container', 'style' => 'display: none;']);

// Statistics cards
echo html_writer::start_tag('div', ['class' => 'row mt-3']);

// Total Questions
echo html_writer::start_tag('div', ['class' => 'col-md-4 mb-3']);
echo html_writer::start_tag('div', ['class' => 'card']);
echo html_writer::start_tag('div', ['class' => 'card-body']);
echo html_writer::tag('h5', get_string('totalquestions', 'local_questioncleaner'), ['class' => 'card-title']);
echo html_writer::tag('h3', '<span id="stat-total-questions">-</span>', ['class' => 'text-primary']);
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

// Duplicated Questions
echo html_writer::start_tag('div', ['class' => 'col-md-4 mb-3']);
echo html_writer::start_tag('div', ['class' => 'card']);
echo html_writer::start_tag('div', ['class' => 'card-body']);
echo html_writer::tag('h5', get_string('duplicatedquestions', 'local_questioncleaner'), ['class' => 'card-title']);
echo html_writer::tag('h3', '<span id="stat-duplicated-questions">-</span>', ['class' => 'text-warning']);
echo html_writer::tag('a', get_string('viewdetails', 'local_questioncleaner'),
    ['href' => new moodle_url('/local/questioncleaner/duplicate_questions.php'), 'class' => 'btn btn-sm btn-outline-warning mt-2']);
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

// Unused Questions
echo html_writer::start_tag('div', ['class' => 'col-md-4 mb-3']);
echo html_writer::start_tag('div', ['class' => 'card']);
echo html_writer::start_tag('div', ['class' => 'card-body']);
echo html_writer::tag('h5', get_string('unusedquestionscount', 'local_questioncleaner'), ['class' => 'card-title']);
echo html_writer::tag('h3', '<span id="stat-unused-questions">-</span>', ['class' => 'text-danger']);
echo html_writer::tag('a', get_string('viewdetails', 'local_questioncleaner'),
    ['href' => new moodle_url('/local/questioncleaner/unused_questions.php'), 'class' => 'btn btn-sm btn-outline-danger mt-2']);
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

echo html_writer::end_tag('div');

// Answers statistics
echo html_writer::start_tag('div', ['class' => 'row mt-3']);

// Orphaned Answers
echo html_writer::start_tag('div', ['class' => 'col-md-6 mb-3']);
echo html_writer::start_tag('div', ['class' => 'card']);
echo html_writer::start_tag('div', ['class' => 'card-body']);
echo html_writer::tag('h5', get_string('orphanedanswers', 'local_questioncleaner'), ['class' => 'card-title']);
echo html_writer::tag('h3', '<span id="stat-orphaned-answers">-</span>', ['class' => 'text-info']);
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

// Unused Question Answers
echo html_writer::start_tag('div', ['class' => 'col-md-6 mb-3']);
echo html_writer::start_tag('div', ['class' => 'card']);
echo html_writer::start_tag('div', ['class' => 'card-body']);
echo html_writer::tag('h5', get_string('unusedquestionanswers', 'local_questioncleaner'), ['class' => 'card-title']);
echo html_writer::tag('h3', '<span id="stat-unused-question-answers">-</span>', ['class' => 'text-dark']);
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

echo html_writer::end_tag('div');
echo html_writer::end_tag('div'); // End stats-container

// Information
echo html_writer::start_tag('div', ['class' => 'card mt-4']);
echo html_writer::start_tag('div', ['class' => 'card-body']);
echo html_writer::tag('h5', get_string('information', 'local_questioncleaner'), ['class' => 'card-title']);
echo html_writer::tag('p', get_string('backuprecommended', 'local_questioncleaner'), ['class' => 'text-muted']);
echo html_writer::tag('p', get_string('verificationrequired', 'local_questioncleaner'), ['class' => 'text-muted']);
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

// JavaScript to load statistics step by step via AJAX
$js = 'require([\'jquery\'], function($) {
    $(document).ready(function() {
        var progressBar = $("#stats-progress-bar");
        var progressText = $("#stats-progress-text");
        var progressContainer = $("#stats-progress-container");
        var statsContainer = $("#stats-container");
        
        var steps = [
            { 
                step: "used_questions_count", 
                text: "' . get_string('loadingusedquestions', 'local_questioncleaner') . '", 
                progress: 5,
                element: null,
                type: "used"
            },
            { 
                step: "total_questions", 
                text: "' . get_string('loadingtotalquestions', 'local_questioncleaner') . '", 
                progress: 15,
                element: "#stat-total-questions",
                type: "total"
            },
            { 
                step: "duplicated_questions", 
                text: "' . get_string('loadingduplicatedquestions', 'local_questioncleaner') . '", 
                progress: 35,
                element: "#stat-duplicated-questions",
                type: "duplicated"
            },
            { 
                step: "unused_questions", 
                text: "' . get_string('loadingunusedquestions', 'local_questioncleaner') . '", 
                progress: 55,
                element: "#stat-unused-questions",
                type: "unused"
            },
            { 
                step: "orphaned_answers", 
                text: "' . get_string('loadingorphanedanswers', 'local_questioncleaner') . '", 
                progress: 75,
                element: "#stat-orphaned-answers",
                type: "orphaned"
            },
            { 
                step: "unused_question_answers", 
                text: "' . get_string('loadingunusedanswers', 'local_questioncleaner') . '", 
                progress: 90,
                element: "#stat-unused-question-answers",
                type: "unused_answers"
            }
        ];
        
        var currentStepIndex = 0;
        var ajaxUrl = "' . $CFG->wwwroot . '/local/questioncleaner/ajax/get_statistics.php";
        var forceRefresh = ' . ($refresh ? '1' : '0') . ';
        var usedQuestionsCount = 0;
        
        // Format large numbers (> 100,000)
        function formatLargeNumber(number) {
            number = parseInt(number) || 0;
            
            // Only format if > 100,000
            if (number > 100000) {
                if (number >= 1000000) {
                    return (number / 1000000).toFixed(1) + " ' . get_string('million', 'local_questioncleaner') . '";
                } else {
                    return Math.round(number / 1000) + " ' . get_string('thousand', 'local_questioncleaner') . '";
                }
            }
            
            // Return number as is for smaller numbers
            return number.toLocaleString();
        }
        
        function updateProgress(stepIndex, text) {
            if (stepIndex < steps.length) {
                var step = steps[stepIndex];
                progressBar.css("width", step.progress + "%");
                progressBar.attr("aria-valuenow", step.progress);
                if (text) {
                    progressText.html("<strong>" + text + "</strong>");
                } else {
                    progressText.html("<strong>" + step.text + "</strong>");
                }
            }
        }
        
        function loadNextStep() {
            if (currentStepIndex >= steps.length) {
                // All steps completed
                updateProgress(steps.length, "' . get_string('completing', 'local_questioncleaner') . '");
                progressBar.css("width", "100%");
                progressBar.attr("aria-valuenow", 100);
                
                // Hide progress, show stats
                setTimeout(function() {
                    progressContainer.fadeOut(300, function() {
                        statsContainer.fadeIn(300);
                    });
                }, 500);
                return;
            }
            
            var currentStep = steps[currentStepIndex];
            
            // Update progress bar and text
            updateProgress(currentStepIndex, currentStep.text);
            
            // Load this step via AJAX
            $.ajax({
                url: ajaxUrl,
                type: "GET",
                data: { step: currentStep.step, force: forceRefresh },
                dataType: "json",
                timeout: 300000, // 5 minutes timeout
                success: function(data) {
                    if (data.success) {
                        // Store used_questions_count for comparison
                        if (currentStep.step === "used_questions_count") {
                            usedQuestionsCount = data.value;
                        } else if (currentStep.element) {
                            // Format and display the number
                            var formatted = formatLargeNumber(data.value);
                            $(currentStep.element).text(formatted);
                        }
                        
                        // Move to next step
                        currentStepIndex++;
                        loadNextStep();
                    } else {
                        progressText.html("<span class=\"text-danger\">' . get_string('errorloadingstatistics', 'local_questioncleaner') . ': " + (data.error || "Unknown error") + "</span>");
                        progressBar.removeClass("progress-bar-animated").addClass("bg-danger");
                    }
                },
                error: function(xhr, status, error) {
                    progressText.html("<span class=\"text-danger\">' . get_string('errorloadingstatistics', 'local_questioncleaner') . ': " + error + "</span>");
                    progressBar.removeClass("progress-bar-animated").addClass("bg-danger");
                }
            });
        }
        
        // Start loading
        updateProgress(0, steps[0].text);
        loadNextStep();
    });
});';

$PAGE->requires->js_init_code($js);

echo $OUTPUT->footer();
