/* global Craft */
(function () {
    'use strict';

    // Only run on the Broken Links index page.
    var startBtn = document.getElementById('start-scan');
    if (!startBtn) {
        return;
    }

    var config = window.BrokenLinksConfig || {};

    function t(message) {
        return Craft.t('broken-links', message);
    }

    var loading = document.getElementById('loading');
    var spinner = document.getElementById('loading-spinner');

    function setButtonsDisabled(disabled) {
        document.querySelectorAll('.buttons button').forEach(function (btn) {
            btn.disabled = disabled;
        });
    }

    // Advanced options toggle
    var showAdvanced = document.getElementById('show-advanced');
    var toggleAdvanced = document.getElementById('toggle-advanced');
    var advancedOptions = document.getElementById('advanced-options');

    if (showAdvanced && advancedOptions) {
        showAdvanced.addEventListener('click', function () {
            advancedOptions.classList.remove('hidden');
            showAdvanced.classList.add('hidden');
        });
    }
    if (toggleAdvanced && advancedOptions && showAdvanced) {
        toggleAdvanced.addEventListener('click', function () {
            advancedOptions.classList.add('hidden');
            showAdvanced.classList.remove('hidden');
        });
    }

    startBtn.addEventListener('click', function () {
        startScan(false);
    });

    var forceBtn = document.getElementById('force-scan');
    if (forceBtn) {
        forceBtn.addEventListener('click', function () {
            startScan(true);
        });
    }

    var clearBtn = document.getElementById('clear-data');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            if (window.confirm(t('Are you sure you want to clear all broken links data? This cannot be undone.'))) {
                clearData();
            }
        });
    }

    function startScan(forceFullScan) {
        loading.textContent = t('Starting scan...');
        spinner.classList.remove('hidden');

        var batchSize = 100;
        if (advancedOptions && !advancedOptions.classList.contains('hidden')) {
            var batchInput = document.getElementById('batch-size');
            batchSize = parseInt(batchInput && batchInput.value, 10) || 100;
        }

        setButtonsDisabled(true);

        fetch(config.startScanUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': Craft.csrfTokenValue,
            },
            body: JSON.stringify({
                forceFullScan: forceFullScan,
                batchSize: batchSize,
            }),
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                if (data.success) {
                    loading.textContent = t('Scan started successfully! The process will run in the background.');
                    setTimeout(function () {
                        window.location.reload();
                    }, 3000);
                } else {
                    loading.textContent = t('Error') + ': ' + data.message;
                    spinner.classList.add('hidden');
                    setButtonsDisabled(false);
                }
            })
            .catch(function (error) {
                loading.textContent = t('Error') + ': ' + error.message;
                spinner.classList.add('hidden');
                setButtonsDisabled(false);
            });
    }

    function clearData() {
        loading.textContent = t('Clearing data...');
        spinner.classList.remove('hidden');
        setButtonsDisabled(true);

        fetch(config.clearDataUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': Craft.csrfTokenValue,
            },
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                if (data.success) {
                    loading.textContent = t('All data cleared successfully!');
                    setTimeout(function () {
                        window.location.reload();
                    }, 2000);
                } else {
                    loading.textContent = t('Error') + ': ' + data.message;
                    spinner.classList.add('hidden');
                    setButtonsDisabled(false);
                }
            })
            .catch(function (error) {
                loading.textContent = t('Error') + ': ' + error.message;
                spinner.classList.add('hidden');
                setButtonsDisabled(false);
            });
    }

    // Ignore-URL buttons — delegate to handle rows added after page load.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.ignore-url-btn');
        if (!btn || !config.ignoreUrlUrl) {
            return;
        }

        var url = btn.getAttribute('data-url');
        if (!url) {
            return;
        }

        btn.disabled = true;
        btn.textContent = t('Ignoring...');

        fetch(config.ignoreUrlUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': Craft.csrfTokenValue,
            },
            body: JSON.stringify({ url: url }),
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (data.success) {
                    // Remove every row whose URL contains the ignored domain.
                    document.querySelectorAll('.ignore-url-btn').forEach(function (b) {
                        if ((b.getAttribute('data-url') || '').indexOf(data.pattern) !== -1) {
                            var row = b.closest('tr');
                            if (row) {
                                row.remove();
                            }
                        }
                    });
                } else {
                    btn.disabled = false;
                    btn.textContent = 'Ignore';
                    alert(t('Failed to add to ignore list.') + (data.message ? ' ' + data.message : ''));
                }
            })
            .catch(function (error) {
                btn.disabled = false;
                btn.textContent = 'Ignore';
                alert(t('Failed to add to ignore list.') + ' ' + error.message);
            });
    });

    // Poll for an in-progress scan and reload when it finishes.
    if (config.activeScanId) {
        loading.textContent = t('A scan is currently in progress...');
        spinner.classList.remove('hidden');

        var statusInterval = setInterval(function () {
            fetch(config.scanStatusUrl + '?scanId=' + encodeURIComponent(config.activeScanId))
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (data.success && (data.isComplete || data.isFailed)) {
                        clearInterval(statusInterval);
                        window.location.reload();
                    }
                })
                .catch(function () {
                    // Ignore transient polling errors.
                });
        }, 5000);
    }
})();
