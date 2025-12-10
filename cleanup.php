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
 * Cleanup page for question cleaner.
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
require_capability('local/questioncleaner:cleanup', context_system::instance());

// Get default batch size from settings
$batch_size = get_config('local_questioncleaner', 'batchsize');
if (empty($batch_size)) {
    $batch_size = 1000;
}
$batch_size = max(1, min(10000, (int)$batch_size));

// Set up page
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/questioncleaner/cleanup.php'));
$PAGE->set_title(get_string('cleanup', 'local_questioncleaner'));
$PAGE->set_heading(get_string('cleanup', 'local_questioncleaner'));
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
$tabs[] = new tabobject('cleanup',
    new moodle_url('/local/questioncleaner/cleanup.php'),
    get_string('cleanup', 'local_questioncleaner')
);

echo $OUTPUT->tabtree($tabs, 'cleanup');

// Warning
echo html_writer::start_tag('div', ['class' => 'alert alert-danger']);
echo html_writer::tag('strong', get_string('warningdeletion', 'local_questioncleaner'));
echo html_writer::tag('p', get_string('backuprecommended', 'local_questioncleaner'));
echo html_writer::end_tag('div');

// Interactive Cleanup Interface
echo html_writer::start_tag('div', ['class' => 'card mb-4']);
echo html_writer::start_tag('div', ['class' => 'card-body']);
echo html_writer::tag('h5', get_string('cleanup', 'local_questioncleaner'), ['class' => 'card-title']);

// Cleanup type selection
echo html_writer::start_tag('div', ['class' => 'mb-4']);
echo html_writer::tag('label', get_string('cleanuptype', 'local_questioncleaner'), ['for' => 'cleanup_type', 'class' => 'form-label fw-bold mb-2']);
echo html_writer::start_tag('select', ['id' => 'cleanup_type', 'class' => 'form-select form-select-lg']);
echo html_writer::tag('option', get_string('selectcleanuptype', 'local_questioncleaner'), ['value' => '', 'disabled' => true, 'selected' => true]);
echo html_writer::tag('option', get_string('cleanuptype_duplicate', 'local_questioncleaner'), ['value' => 'duplicate_questions']);
echo html_writer::tag('option', get_string('cleanuptype_unused', 'local_questioncleaner'), ['value' => 'unused_questions']);
echo html_writer::tag('option', get_string('cleanuptype_orphaned', 'local_questioncleaner'), ['value' => 'orphaned_answers']);
echo html_writer::tag('option', get_string('cleanuptype_unusedanswers', 'local_questioncleaner'), ['value' => 'unused_answers']);
echo html_writer::end_tag('select');
echo html_writer::end_tag('div');

// Batch size setting
echo html_writer::start_tag('div', ['class' => 'mb-3']);
echo html_writer::tag('label', get_string('batchsize', 'local_questioncleaner') . ': ', ['for' => 'batch_size', 'class' => 'form-label']);
echo html_writer::empty_tag('input', [
    'type' => 'number',
    'id' => 'batch_size',
    'name' => 'batch_size',
    'value' => $batch_size,
    'min' => 1,
    'max' => 10000,
    'class' => 'form-control',
    'style' => 'width: 150px;'
]);
echo html_writer::tag('p', get_string('batchsize_desc', 'local_questioncleaner'), ['class' => 'text-muted small mt-1']);
echo html_writer::end_tag('div');

// Number of batches
echo html_writer::start_tag('div', ['class' => 'mb-3']);
echo html_writer::tag('label', get_string('numberofbatches', 'local_questioncleaner') . ': ', ['for' => 'num_batches', 'class' => 'form-label']);
echo html_writer::empty_tag('input', [
    'type' => 'number',
    'id' => 'num_batches',
    'name' => 'num_batches',
    'value' => '',
    'min' => 0,
    'class' => 'form-control',
    'style' => 'width: 150px;',
    'placeholder' => get_string('processall', 'local_questioncleaner')
]);
echo html_writer::tag('p', get_string('processall', 'local_questioncleaner') . ' - ' . get_string('processall', 'local_questioncleaner'), ['class' => 'text-muted small mt-1']);
echo html_writer::end_tag('div');

// Action buttons
echo html_writer::start_tag('div', ['class' => 'mb-3']);
echo html_writer::tag('button', get_string('startcleanup', 'local_questioncleaner'), [
    'id' => 'btn_start_cleanup',
    'class' => 'btn btn-primary me-2',
    'type' => 'button'
]);
echo html_writer::tag('button', get_string('stopcleanup', 'local_questioncleaner'), [
    'id' => 'btn_stop_cleanup',
    'class' => 'btn btn-danger',
    'type' => 'button',
    'style' => 'display: none;'
]);
echo html_writer::end_tag('div');

// Progress bar
echo html_writer::start_tag('div', ['id' => 'cleanup_progress_container', 'style' => 'display: none;']);
echo html_writer::start_tag('div', ['class' => 'mb-2']);
echo html_writer::start_tag('div', [
    'id' => 'cleanup_progress_bar',
    'class' => 'progress',
    'style' => 'height: 30px;'
]);
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');
echo html_writer::start_tag('div', ['id' => 'cleanup_status', 'class' => 'small text-muted mb-2']);
echo html_writer::end_tag('div');
echo html_writer::start_tag('div', ['id' => 'cleanup_stats', 'class' => 'small']);
echo html_writer::tag('span', get_string('deleted', 'local_questioncleaner') . ': ', ['class' => 'me-3']);
echo html_writer::tag('span', '0', ['id' => 'cleanup_deleted', 'class' => 'text-success fw-bold']);
echo html_writer::tag('span', get_string('remaining', 'local_questioncleaner') . ': ', ['class' => 'ms-4 me-2']);
echo html_writer::tag('span', '0', ['id' => 'cleanup_remaining', 'class' => 'text-warning fw-bold']);
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

// Alert container for custom notifications
echo html_writer::start_tag('div', ['id' => 'cleanup_alert_container', 'class' => 'mt-3']);
echo html_writer::end_tag('div');

// JavaScript for interactive cleanup
echo html_writer::start_tag('script', ['type' => 'text/javascript']);
?>
(function() {
    var cleanupInProgress = false;
    var cleanupData = null;
    var currentBatch = 0;
    var totalDeleted = 0;
    var totalFailed = 0;
    var ajaxUrl = '<?php echo $CFG->wwwroot; ?>/local/questioncleaner/ajax/cleanup_process.php';
    var sesskey = '<?php echo sesskey(); ?>';
    
    var btnStart = document.getElementById('btn_start_cleanup');
    var btnStop = document.getElementById('btn_stop_cleanup');
    var cleanupType = document.getElementById('cleanup_type');
    var batchSize = document.getElementById('batch_size');
    var numBatches = document.getElementById('num_batches');
    var progressContainer = document.getElementById('cleanup_progress_container');
    var progressBar = document.getElementById('cleanup_progress_bar');
    var statusDiv = document.getElementById('cleanup_status');
    var deletedSpan = document.getElementById('cleanup_deleted');
    var remainingSpan = document.getElementById('cleanup_remaining');
    var alertContainer = document.getElementById('cleanup_alert_container');
    
    function showAlert(message, type) {
        if (!alertContainer) return;
        
        // Remove existing alerts
        alertContainer.innerHTML = '';
        
        // Create alert element
        var alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-' + (type || 'info') + ' alert-dismissible fade show';
        alertDiv.setAttribute('role', 'alert');
        alertDiv.style.position = 'relative';
        
        var messageDiv = document.createElement('div');
        messageDiv.innerHTML = message.replace(/\n/g, '<br>');
        alertDiv.appendChild(messageDiv);
        
        var closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'btn-close';
        closeButton.setAttribute('aria-label', 'Close');
        closeButton.setAttribute('data-bs-dismiss', 'alert');
        closeButton.onclick = function(e) {
            e.preventDefault();
            alertDiv.style.transition = 'opacity 0.3s';
            alertDiv.style.opacity = '0';
            setTimeout(function() {
                if (alertDiv && alertDiv.parentNode) {
                    alertDiv.parentNode.removeChild(alertDiv);
                }
            }, 300);
        };
        alertDiv.appendChild(closeButton);
        
        alertContainer.appendChild(alertDiv);
        
        // Scroll to alert
        alertContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        
        // Auto-hide after 5 seconds for success/info, 10 seconds for errors
        var hideDelay = (type === 'danger' || type === 'warning') ? 10000 : 5000;
        setTimeout(function() {
            if (alertDiv && alertDiv.parentNode) {
                alertDiv.style.opacity = '0';
                setTimeout(function() {
                    if (alertDiv && alertDiv.parentNode) {
                        alertDiv.parentNode.removeChild(alertDiv);
                    }
                }, 300);
            }
        }, hideDelay);
    }
    
    function updateProgress(percent, statusText) {
        if (!progressBar) return;
        
        var progressBarInner = progressBar.querySelector('.progress-bar');
        if (!progressBarInner) {
            progressBarInner = document.createElement('div');
            progressBarInner.className = 'progress-bar progress-bar-striped progress-bar-animated';
            progressBarInner.setAttribute('role', 'progressbar');
            progressBarInner.setAttribute('aria-valuenow', percent);
            progressBarInner.setAttribute('aria-valuemin', 0);
            progressBarInner.setAttribute('aria-valuemax', 100);
            progressBarInner.style.width = percent + '%';
            progressBar.appendChild(progressBarInner);
        } else {
            progressBarInner.style.width = percent + '%';
            progressBarInner.setAttribute('aria-valuenow', percent);
        }
        
        // Set color based on progress
        progressBarInner.classList.remove('bg-primary', 'bg-success', 'bg-info', 'bg-warning');
        if (percent >= 100) {
            progressBarInner.classList.add('bg-success');
        } else if (percent >= 75) {
            progressBarInner.classList.add('bg-primary');
        } else if (percent >= 50) {
            progressBarInner.classList.add('bg-info');
        } else {
            progressBarInner.classList.add('bg-primary');
        }
        
        if (statusDiv) {
            statusDiv.textContent = statusText;
        }
    }
    
    function updateStats(deleted, remaining) {
        if (deletedSpan) {
            deletedSpan.textContent = deleted.toLocaleString();
            deletedSpan.style.display = 'inline-block';
        }
        if (remainingSpan) {
            remainingSpan.textContent = remaining.toLocaleString();
            remainingSpan.style.display = 'inline-block';
        }
        // Force browser to repaint
        if (progressContainer && progressContainer.offsetHeight) {
            progressContainer.offsetHeight;
        }
    }
    
    function showProgress() {
        if (progressContainer) {
            progressContainer.style.display = 'block';
        }
    }
    
    function hideProgress() {
        if (progressContainer) {
            progressContainer.style.display = 'none';
        }
    }
    
    function makeAjaxCall(action, params, callback) {
        var formData = new FormData();
        formData.append('sesskey', sesskey);
        formData.append('action', action);
        
        for (var key in params) {
            if (params.hasOwnProperty(key)) {
                formData.append(key, params[key]);
            }
        }
        
        fetch(ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (callback) {
                callback(data);
            }
        })
        .catch(function(error) {
            console.error('AJAX Error:', error);
            if (callback) {
                callback({success: false, error: error.message});
            }
        });
    }
    
    function processBatch() {
        if (!cleanupInProgress || !cleanupData) {
            return;
        }
        
        // Update stats immediately to show current progress
        var currentRemaining = Math.max(0, cleanupData.total - totalDeleted);
        var currentPercent = cleanupData.total > 0 ? Math.round((totalDeleted / cleanupData.total) * 100) : 0;
        updateProgress(currentPercent, '<?php echo get_string('processing', 'local_questioncleaner'); ?> - ' + 
            '<?php echo get_string('currentbatch', 'local_questioncleaner'); ?>: ' + (currentBatch + 1) + 
            ' / ' + cleanupData.total_batches);
        updateStats(totalDeleted, currentRemaining);
        
        // Check if we should stop
        makeAjaxCall('status', {}, function(statusData) {
            if (statusData.stopped) {
                stopCleanup(true);
                return;
            }
            
            // Update status before processing
            var estimatedDeleted = totalDeleted;
            var estimatedRemaining = Math.max(0, cleanupData.total - estimatedDeleted);
            var estimatedPercent = cleanupData.total > 0 ? Math.round((estimatedDeleted / cleanupData.total) * 100) : 0;
            updateProgress(estimatedPercent, '<?php echo get_string('processing', 'local_questioncleaner'); ?> - ' + 
                '<?php echo get_string('currentbatch', 'local_questioncleaner'); ?>: ' + (currentBatch + 1) + 
                ' / ' + cleanupData.total_batches);
            
            // Process current batch
            makeAjaxCall('process', {
                cleanuptype: cleanupData.cleanuptype,
                batch_size: cleanupData.batch_size,
                batch_number: currentBatch
            }, function(result) {
                if (!result.success) {
                    if (result.stopped) {
                        stopCleanup(true);
                    } else {
                        showAlert('Error: ' + (result.error || 'Unknown error'), 'danger');
                        stopCleanup(false);
                    }
                    return;
                }
                
                // Update totals immediately
                totalDeleted += result.deleted || 0;
                totalFailed += result.failed || 0;
                currentBatch++;
                
                var remaining = Math.max(0, cleanupData.total - totalDeleted);
                var percent = cleanupData.total > 0 ? Math.round((totalDeleted / cleanupData.total) * 100) : 0;
                
                // Update progress and stats immediately
                updateProgress(percent, '<?php echo get_string('processing', 'local_questioncleaner'); ?> - ' + 
                    '<?php echo get_string('currentbatch', 'local_questioncleaner'); ?>: ' + currentBatch + 
                    ' / ' + cleanupData.total_batches);
                updateStats(totalDeleted, remaining);
                
                // Force UI update
                if (deletedSpan) {
                    deletedSpan.textContent = totalDeleted.toLocaleString();
                }
                if (remainingSpan) {
                    remainingSpan.textContent = remaining.toLocaleString();
                }
                
                // Check if we should continue
                if (result.stopped || currentBatch >= cleanupData.total_batches || remaining <= 0) {
                    stopCleanup(false);
                    var completionMessage = '<?php echo get_string('cleanupcompleted', 'local_questioncleaner'); ?>!<br>' +
                        '<strong><?php echo get_string('deleted', 'local_questioncleaner'); ?>:</strong> ' + totalDeleted.toLocaleString();
                    if (totalFailed > 0) {
                        completionMessage += '<br><strong><?php echo get_string('failed', 'local_questioncleaner'); ?>:</strong> ' + totalFailed.toLocaleString();
                    }
                    showAlert(completionMessage, 'success');
                } else {
                    // Process next batch after a short delay
                    setTimeout(processBatch, 100);
                }
            });
        });
    }
    
    function startCleanup() {
        var type = cleanupType.value;
        var batch = parseInt(batchSize.value) || 1000;
        var batches = parseInt(numBatches.value) || 0;
        
        if (!type) {
            showAlert('<?php echo get_string('selectcleanuptype', 'local_questioncleaner'); ?>', 'warning');
            return;
        }
        
        if (batch < 1 || batch > 10000) {
            showAlert('<?php echo get_string('invalidbatchsize', 'local_questioncleaner'); ?>', 'warning');
            return;
        }
        
        if (batches < 0) {
            showAlert('<?php echo get_string('invalidnumberofbatches', 'local_questioncleaner'); ?>', 'warning');
            return;
        }
        
        cleanupInProgress = true;
        currentBatch = 0;
        totalDeleted = 0;
        totalFailed = 0;
        
        btnStart.style.display = 'none';
        btnStop.style.display = 'inline-block';
        showProgress();
        updateProgress(0, '<?php echo get_string('cleanupinprogress', 'local_questioncleaner'); ?>...');
        updateStats(0, 0);
        
        // Initialize cleanup
        makeAjaxCall('start', {
            cleanuptype: type,
            batch_size: batch,
            num_batches: batches
        }, function(result) {
            if (!result.success) {
                showAlert('Error: ' + (result.error || 'Unknown error'), 'danger');
                stopCleanup(false);
                return;
            }
            
            cleanupData = result;
            updateStats(0, result.total);
            
            // Start processing batches
            processBatch();
        });
    }
    
    function stopCleanup(userStopped) {
        cleanupInProgress = false;
        
        if (userStopped) {
            makeAjaxCall('stop', {}, function(result) {
                // Stop flag set
            });
        }
        
        btnStart.style.display = 'inline-block';
        btnStop.style.display = 'none';
        
        if (userStopped) {
            updateProgress(0, '<?php echo get_string('cleanupstopped', 'local_questioncleaner'); ?>');
        } else {
            updateProgress(100, '<?php echo get_string('cleanupcompleted', 'local_questioncleaner'); ?>');
        }
    }
    
    if (btnStart) {
        btnStart.addEventListener('click', startCleanup);
    }
    
    if (btnStop) {
        btnStop.addEventListener('click', function() {
            stopCleanup(true);
        });
    }
})();
<?php
echo html_writer::end_tag('script');

echo $OUTPUT->footer();

