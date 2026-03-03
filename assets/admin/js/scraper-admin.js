jQuery(document).ready(function($) {
    if (!$('.toc-scraper-wrap').length) {
        return;
    }

    const $statusMsg = $('.toc-env-msg');
    const $statusBox = $('#toc-env-status');
    const $dashboard = $('.toc-scraper-dashboard');
    const $runBtn = $('#toc-scraper-run-btn');
    const $batchBtn = $('#toc-scraper-batch-btn');

    // 1. Validate Environment on Load
    function validateEnvironment() {
        $.post(tocScraperVars.ajax_url, {
            action: 'toc_validate_env',
            nonce: tocScraperVars.nonce
        })
        .done(function(response) {
            if (response.success && response.data.valid) {
                $statusBox.removeClass('notice-info notice-error').addClass('notice-success');
                $statusMsg.text(response.data.message);
                $runBtn.prop('disabled', false);
                $batchBtn.prop('disabled', false);
            } else {
                handleEnvError(response.data ? response.data.message : 'Unknown validation error.');
            }
        })
        .fail(function(xhr) {
            let msg = 'Failed to communicate with server.';
            if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                msg = xhr.responseJSON.data.message;
            }
            handleEnvError(msg);
        });
    }

    function handleEnvError(message) {
        $statusBox.removeClass('notice-info notice-success').addClass('notice-error');
        $statusMsg.text('Error: ' + message);
        $dashboard.addClass('disabled-mode');
        $runBtn.prop('disabled', true);
        $batchBtn.prop('disabled', true);
    }

    // 2. Start Single Scrape
    $runBtn.on('click', function() {
        const url = $('#toc-scraper-single-url').val();
        if (!url) {
            alert('Please enter a valid URL.');
            return;
        }

        lockUI();
        $('#toc-scraper-logs').text('Initializing...\n');

        $.post(tocScraperVars.ajax_url, {
            action: 'toc_start_scraper',
            nonce: tocScraperVars.nonce,
            url: url
        })
        .done(function(response) {
            if (response.success && response.data.run_id) {
                pollScraperProgress(response.data.run_id);
            } else {
                unlockUI();
                $('#toc-scraper-logs').append('\nFailed to start: ' + (response.data ? response.data.message : 'Unknown error'));
            }
        })
        .fail(function(xhr) {
            unlockUI();
            $('#toc-scraper-logs').append('\nRequest Failed.');
        });
    });

    // 4. Start Batch Scrape
    $batchBtn.on('click', function() {
        const year = $('#toc-scraper-batch-year').val();
        const month = $('#toc-scraper-batch-month').val();

        if (!year) {
            alert('Please enter a year.');
            return;
        }

        lockUI();
        $('#toc-scraper-logs').text('Initializing Batch...\n');

        $.post(tocScraperVars.ajax_url, {
            action: 'toc_start_scraper',
            nonce: tocScraperVars.nonce,
            year: year,
            month: month
        })
        .done(function(response) {
            if (response.success && response.data.run_id) {
                pollScraperProgress(response.data.run_id);
            } else {
                unlockUI();
                $('#toc-scraper-logs').append('\nFailed to start batch: ' + (response.data ? response.data.message : 'Unknown error'));
            }
        })
        .fail(function(xhr) {
            unlockUI();
            $('#toc-scraper-logs').append('\nRequest Failed.');
        });
    });

    // 5. Polling Logic
    function pollScraperProgress(run_id) {
        $.post(tocScraperVars.ajax_url, {
            action: 'toc_poll_progress',
            nonce: tocScraperVars.nonce,
            run_id: run_id
        })
        .done(function(response) {
            if (response.success) {
                if (response.data.output) {
                    $('#toc-scraper-logs').text(response.data.output);
                }

                if (response.data.status === 'running') {
                    // Poll again in 2 seconds
                    setTimeout(function() {
                        pollScraperProgress(run_id);
                    }, 2000);
                } else if (response.data.status === 'completed') {
                    $('#toc-scraper-logs').append('\n\n--- Process Completed ---');
                    cleanupRun(run_id);
                    unlockUI();
                } else if (response.data.status === 'failed') {
                    $('#toc-scraper-logs').append('\n\n--- Process Failed ---');
                    cleanupRun(run_id);
                    unlockUI();
                }
            } else {
                $('#toc-scraper-logs').append('\nError polling: ' + (response.data ? response.data.message : 'Unknown error'));
                unlockUI();
            }
        })
        .fail(function() {
            $('#toc-scraper-logs').append('\nPolling Request Failed. Will retry...');
            setTimeout(function() {
                pollScraperProgress(run_id);
            }, 5000);
        });
    }

    function cleanupRun(run_id) {
        $.post(tocScraperVars.ajax_url, {
            action: 'toc_cleanup_run',
            nonce: tocScraperVars.nonce,
            run_id: run_id
        });
    }

    function lockUI() {
        $runBtn.prop('disabled', true).text('Running...');
        $batchBtn.prop('disabled', true);
        $('#toc-scraper-single-url').prop('disabled', true);
        $('#toc-scraper-batch-year').prop('disabled', true);
        $('#toc-scraper-batch-month').prop('disabled', true);
    }

    function unlockUI() {
        $runBtn.prop('disabled', false).text('Run Scraper');
        $batchBtn.prop('disabled', false);
        $('#toc-scraper-single-url').prop('disabled', false).val('');
        $('#toc-scraper-batch-year').prop('disabled', false);
        $('#toc-scraper-batch-month').prop('disabled', false);
    }

    // Initialize
    validateEnvironment();
});
