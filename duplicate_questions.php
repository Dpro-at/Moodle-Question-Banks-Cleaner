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
 * Duplicate questions page.
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

// Get default batch size from settings
$batch_size = get_config('local_questioncleaner', 'batchsize');
if (empty($batch_size)) {
    $batch_size = 1000;
}
$batch_size = max(1, min(10000, (int)$batch_size));

$limit = optional_param('limit', 100, PARAM_INT);
$limit = max(1, min(10000, $limit));

// Set up page
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/questioncleaner/duplicate_questions.php', ['limit' => $limit]));
$PAGE->set_title(get_string('duplicatequestions', 'local_questioncleaner'));
$PAGE->set_heading(get_string('duplicatequestions', 'local_questioncleaner'));
$PAGE->set_pagelayout('admin');

// Get duplicate questions
$duplicates = cleaner::check_duplicate_questions($limit);

// Group by duplicate_key
$grouped = [];
foreach ($duplicates as $question) {
    $key = $question->duplicate_key;
    if (!isset($grouped[$key])) {
        $grouped[$key] = [];
    }
    $grouped[$key][] = $question;
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

echo $OUTPUT->tabtree($tabs, 'duplicate');

// Limit selector
echo html_writer::start_tag('div', ['class' => 'mb-3']);
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url]);
echo html_writer::tag('label', get_string('numberofquestions', 'local_questioncleaner') . ': ', ['for' => 'limit']);
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

// Duplicate Questions
echo html_writer::start_tag('div', ['class' => 'card mb-4']);
echo html_writer::start_tag('div', ['class' => 'card-body']);
echo html_writer::tag('h5', get_string('duplicatequestions', 'local_questioncleaner') . ' (' . count($grouped) . ' groups)', ['class' => 'card-title']);

if (!empty($grouped)) {
    // Global Select All and Action buttons
    echo html_writer::start_tag('div', ['class' => 'mb-3']);
    echo html_writer::start_tag('div', ['class' => 'mb-2']);
    echo html_writer::tag('label', html_writer::tag('input', '', [
        'type' => 'checkbox',
        'id' => 'select-all-duplicates',
        'class' => 'form-check-input me-2'
    ]) . get_string('selectall', 'local_questioncleaner'), ['class' => 'form-check-label fw-bold']);
    echo html_writer::end_tag('div');
    echo html_writer::tag('button', get_string('delete', 'local_questioncleaner'), [
        'id' => 'btn-delete-selected',
        'class' => 'btn btn-danger',
        'type' => 'button'
    ]);
    echo html_writer::end_tag('div');
    
    // Progress bar container
    echo html_writer::start_tag('div', ['id' => 'delete-progress-container', 'style' => 'display: none;', 'class' => 'mb-3']);
    echo html_writer::start_tag('div', ['class' => 'progress', 'style' => 'height: 30px;']);
    echo html_writer::tag('div', '', [
        'id' => 'delete-progress-bar',
        'class' => 'progress-bar progress-bar-striped progress-bar-animated bg-primary',
        'role' => 'progressbar',
        'style' => 'width: 0%',
        'aria-valuenow' => '0',
        'aria-valuemin' => '0',
        'aria-valuemax' => '100'
    ]);
    echo html_writer::end_tag('div');
    echo html_writer::tag('div', '', ['id' => 'delete-progress-text', 'class' => 'mt-2 text-muted']);
    echo html_writer::end_tag('div');
    
    // Alert container
    echo html_writer::start_tag('div', ['id' => 'delete-alert-container', 'class' => 'mb-3']);
    echo html_writer::end_tag('div');
    
    foreach ($grouped as $key => $group) {
        echo html_writer::start_tag('div', ['class' => 'mb-4']);
        
        // Sort group by ID to keep the oldest (lowest ID)
        usort($group, function($a, $b) {
            return $a->id - $b->id;
        });
        
        echo html_writer::tag('h6', 'Group: ' . count($group) . ' duplicates (keeping ID: ' . $group[0]->id . ')', ['class' => 'text-muted']);
        
        $table = new html_table();
        $table->head = [
            html_writer::tag('input', '', [
                'type' => 'checkbox',
                'class' => 'form-check-input group-select-all',
                'data-group-key' => $key
            ]) . ' ' . get_string('selectall', 'local_questioncleaner') . ' (Group)',
            get_string('questionid', 'local_questioncleaner'),
            get_string('questionname', 'local_questioncleaner'),
            get_string('questiontype', 'local_questioncleaner'),
            'Status'
        ];
        $table->attributes['class'] = 'generaltable';
        $table->attributes['data-group-key'] = $key;
        
        foreach ($group as $index => $question) {
            $isKeep = ($index === 0); // Keep the first (oldest)
            $checkbox = '';
            if (!$isKeep) {
                $checkbox = html_writer::tag('input', '', [
                    'type' => 'checkbox',
                    'name' => 'question_ids[]',
                    'value' => $question->id,
                    'class' => 'form-check-input question-checkbox',
                    'data-group-key' => $key
                ]);
            }
            
            $keepBadge = $isKeep ? html_writer::tag('span', 'Keep', ['class' => 'badge bg-success']) : '';
            
            $table->data[] = [
                $checkbox,
                $question->id,
                $question->name,
                $question->qtype,
                $keepBadge
            ];
        }
        
        echo html_writer::table($table);
        echo html_writer::end_tag('div');
    }
} else {
    echo html_writer::tag('p', get_string('noduplicatedquestions', 'local_questioncleaner'), ['class' => 'text-muted']);
}

echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

// JavaScript for selection and deletion
$js = 'require([\'jquery\'], function($) {
    $(document).ready(function() {
        var batchSize = ' . $batch_size . ';
        var ajaxUrl = "' . $CFG->wwwroot . '/local/questioncleaner/ajax/delete_duplicates.php";
        var sesskey = "' . sesskey() . '";
        
        // Global select all
        $("#select-all-duplicates").on("change", function() {
            var checked = $(this).is(":checked");
            $(".question-checkbox").prop("checked", checked);
            $(".group-select-all").prop("checked", checked);
        });
        
        // Group select all
        $(".group-select-all").on("change", function() {
            var checked = $(this).is(":checked");
            var groupKey = $(this).data("group-key");
            $(".question-checkbox[data-group-key=\"" + groupKey + "\"]").prop("checked", checked);
            
            // Update global select all state
            var totalCheckboxes = $(".question-checkbox").length;
            var checkedCheckboxes = $(".question-checkbox:checked").length;
            $("#select-all-duplicates").prop("checked", totalCheckboxes === checkedCheckboxes);
        });
        
        // Individual checkbox change - update group and global select all
        $(document).on("change", ".question-checkbox", function() {
            var groupKey = $(this).data("group-key");
            var groupCheckboxes = $(".question-checkbox[data-group-key=\"" + groupKey + "\"]");
            var groupChecked = $(".question-checkbox[data-group-key=\"" + groupKey + "\"]:checked").length;
            $(".group-select-all[data-group-key=\"" + groupKey + "\"]").prop("checked", groupCheckboxes.length === groupChecked);
            
            // Update global select all state
            var totalCheckboxes = $(".question-checkbox").length;
            var checkedCheckboxes = $(".question-checkbox:checked").length;
            $("#select-all-duplicates").prop("checked", totalCheckboxes === checkedCheckboxes);
        });
        
        // Delete selected
        $("#btn-delete-selected").on("click", function() {
            var selected = [];
            $(".question-checkbox:checked").each(function() {
                selected.push($(this).val());
            });
            
            if (selected.length === 0) {
                showAlert("' . get_string('noselected', 'local_questioncleaner') . '", "warning");
                return;
            }
            
            if (!confirm("' . get_string('confirmdeletion', 'local_questioncleaner') . '")) {
                return;
            }
            
            // Disable button
            $(this).prop("disabled", true);
            
            // Show progress
            $("#delete-progress-container").show();
            updateProgress(0, "Starting deletion...");
            
            // Process in batches
            var total = selected.length;
            var deleted = 0;
            var failed = 0;
            var currentIndex = 0;
            
            function processBatch() {
                if (currentIndex >= selected.length) {
                    // Done
                    updateProgress(100, "Deletion completed!");
                    $("#btn-delete-selected").prop("disabled", false);
                    
                    var message = "' . get_string('deleted', 'local_questioncleaner') . ': " + deleted.toLocaleString();
                    if (failed > 0) {
                        message += "<br>' . get_string('failed', 'local_questioncleaner') . ': " + failed.toLocaleString();
                    }
                    showAlert(message, "success");
                    
                    // Reload page after 2 seconds
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                    return;
                }
                
                var batch = selected.slice(currentIndex, currentIndex + batchSize);
                var batchNum = Math.floor(currentIndex / batchSize) + 1;
                var totalBatches = Math.ceil(total / batchSize);
                
                updateProgress(
                    Math.round((currentIndex / total) * 100),
                    "Processing batch " + batchNum + " / " + totalBatches + " (" + deleted.toLocaleString() + " deleted)"
                );
                
                $.ajax({
                    url: ajaxUrl,
                    type: "POST",
                    data: {
                        sesskey: sesskey,
                        question_ids: batch
                    },
                    dataType: "json",
                    timeout: 300000,
                    success: function(data) {
                        if (data.success) {
                            deleted += data.deleted || 0;
                            failed += data.failed || 0;
                            currentIndex += batch.length;
                            processBatch();
                        } else {
                            showAlert("Error: " + (data.error || "Unknown error"), "danger");
                            $("#btn-delete-selected").prop("disabled", false);
                        }
                    },
                    error: function(xhr, status, error) {
                        showAlert("Error: " + error, "danger");
                        $("#btn-delete-selected").prop("disabled", false);
                    }
                });
            }
            
            processBatch();
        });
        
        function updateProgress(percent, text) {
            $("#delete-progress-bar").css("width", percent + "%").attr("aria-valuenow", percent);
            $("#delete-progress-text").html("<strong>" + text + "</strong>");
        }
        
        function showAlert(message, type) {
            var alertContainer = $("#delete-alert-container");
            alertContainer.html("");
            
            var alertDiv = $("<div>", {
                class: "alert alert-" + (type || "info") + " alert-dismissible fade show",
                role: "alert",
                html: message.replace(/\\n/g, "<br>") + 
                    "<button type=\\"button\\" class=\\"btn-close\\" data-bs-dismiss=\\"alert\\" aria-label=\\"Close\\"></button>"
            });
            
            alertContainer.append(alertDiv);
            
            // Auto-hide
            var hideDelay = (type === "danger" || type === "warning") ? 10000 : 5000;
            setTimeout(function() {
                alertDiv.fadeOut(300, function() {
                    $(this).remove();
                });
            }, hideDelay);
        }
    });
});';

$PAGE->requires->js_init_code($js);

echo $OUTPUT->footer();

