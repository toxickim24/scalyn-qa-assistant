/**
 * Admin Dashboard JS.
 *
 * Handles the main Scalyn QA dashboard: score circle animations,
 * project score fetching, batch scanning, auto-refresh, and tooltips.
 *
 * @package Scalyn\QA\Assets
 * @since   1.0.0
 */

'use strict';

(function () {

    /** @type {number|null} Auto-refresh interval ID. */
    var refreshIntervalId = null;

    /** Auto-refresh interval in milliseconds. */
    var REFRESH_INTERVAL = 60000;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Wrapper for fetch() that adds the REST nonce header and base URL.
     *
     * @param {string} endpoint - Relative endpoint path (e.g. 'scores/summary').
     * @param {Object} options  - Additional fetch options.
     * @returns {Promise<Object>} Parsed JSON response.
     */
    function fetchApi(endpoint, options) {
        options = options || {};

        var url = scalynQA.restUrl + endpoint.replace(/^\//, '');

        var headers = Object.assign({
            'Content-Type': 'application/json',
            'X-WP-Nonce': scalynQA.nonce,
        }, options.headers || {});

        var config = Object.assign({}, options, {
            headers: headers,
            credentials: 'same-origin',
        });

        return fetch(url, config)
            .then(function (response) {
                if (!response.ok) {
                    return response.json().then(function (err) {
                        throw new Error(err.message || 'Request failed with status ' + response.status);
                    });
                }
                return response.json();
            });
    }

    /**
     * Update a score circle element's conic-gradient and displayed value.
     *
     * @param {HTMLElement} element - The .scalyn-score-circle element.
     * @param {number}      score   - The score value (0-100).
     * @param {string}      status  - Traffic-light status: 'green', 'yellow', or 'red'.
     */
    function updateScoreCircle(element, score, status) {
        if (!element) {
            return;
        }

        var valueEl = element.querySelector('.scalyn-score-circle__value');
        if (valueEl) {
            valueEl.textContent = score;
        }

        // Remove existing status classes and add the new one.
        element.classList.remove(
            'scalyn-score-circle--green',
            'scalyn-score-circle--yellow',
            'scalyn-score-circle--red'
        );
        element.classList.add('scalyn-score-circle--' + status);

        // Animate the conic-gradient via a CSS custom property.
        animateScoreCircle(element, score);
    }

    /**
     * Animate a score circle from 0 to the target percentage.
     *
     * @param {HTMLElement} element - The .scalyn-score-circle element.
     * @param {number}      target  - The target score (0-100).
     */
    function animateScoreCircle(element, target) {
        var current = 0;
        var step = Math.max(1, Math.ceil(target / 40));
        var duration = 600;
        var stepTime = duration / (target / step || 1);

        function frame() {
            current += step;
            if (current >= target) {
                current = target;
                element.style.setProperty('--score-percent', current + '%');
                return;
            }
            element.style.setProperty('--score-percent', current + '%');
            requestAnimationFrame(function () {
                setTimeout(frame, stepTime);
            });
        }

        element.style.setProperty('--score-percent', '0%');
        if (target > 0) {
            requestAnimationFrame(function () {
                setTimeout(frame, stepTime);
            });
        }
    }

    /**
     * Convert an ISO date string to a human-readable "X minutes ago" format.
     *
     * @param {string} dateString - ISO 8601 date string.
     * @returns {string} Human-readable time ago string.
     */
    function formatTimeAgo(dateString) {
        if (!dateString) {
            return 'Never';
        }

        var now = new Date();
        var date = new Date(dateString);
        var seconds = Math.floor((now - date) / 1000);

        if (seconds < 0) {
            return 'Just now';
        }

        var intervals = [
            { label: 'year', seconds: 31536000 },
            { label: 'month', seconds: 2592000 },
            { label: 'week', seconds: 604800 },
            { label: 'day', seconds: 86400 },
            { label: 'hour', seconds: 3600 },
            { label: 'minute', seconds: 60 },
        ];

        for (var i = 0; i < intervals.length; i++) {
            var interval = intervals[i];
            var count = Math.floor(seconds / interval.seconds);
            if (count >= 1) {
                return count + ' ' + interval.label + (count > 1 ? 's' : '') + ' ago';
            }
        }

        return 'Just now';
    }

    /**
     * Calculate the traffic-light status for a given score.
     *
     * @param {number} score - The score value (0-100).
     * @returns {string} 'green', 'yellow', or 'red'.
     */
    function getStatus(score) {
        var settings = scalynQA.settings || {};
        var greenThreshold = parseInt(settings.green_threshold, 10) || 80;
        var yellowThreshold = parseInt(settings.yellow_threshold, 10) || 50;

        if (score >= greenThreshold) {
            return 'green';
        }
        if (score >= yellowThreshold) {
            return 'yellow';
        }
        return 'red';
    }

    // -------------------------------------------------------------------------
    // Dashboard Functionality
    // -------------------------------------------------------------------------

    /**
     * Fetch project scores from the REST API and update the dashboard cards.
     */
    function loadProjectScores() {
        fetchApi('scores/summary')
            .then(function (response) {
                if (!response.success || !response.data) {
                    return;
                }

                var data = response.data;
                var circles = document.querySelectorAll('.scalyn-card--score');

                // The cards are rendered in order: SEO Ready, QA Ready, Launch Ready, Overall.
                var scoreKeys = [
                    { key: 'seo_ready', label: 'SEO Ready' },
                    { key: 'qa_ready', label: 'QA Ready' },
                    { key: 'launch_ready', label: 'Launch Ready' },
                    { key: 'overall', label: 'Overall' },
                ];

                circles.forEach(function (card, index) {
                    if (!scoreKeys[index]) {
                        return;
                    }

                    var key = scoreKeys[index].key;
                    var score = parseInt(data[key], 10) || 0;
                    var status = getStatus(score);
                    var circle = card.querySelector('.scalyn-score-circle');

                    if (circle) {
                        updateScoreCircle(circle, score, status);
                    }

                    // Update the badge.
                    var badge = card.querySelector('.scalyn-badge');
                    if (badge) {
                        badge.className = 'scalyn-badge scalyn-badge--' + status;
                        var labels = { green: 'Passed', yellow: 'Needs Review', red: 'Issues Found' };
                        badge.textContent = labels[status] || status;
                    }
                });
            })
            .catch(function (err) {
                console.error('Scalyn QA: Failed to load project scores.', err);
            });
    }

    /**
     * Handle "Scan All" button click.
     * Shows a confirmation dialog, then performs a batch scan with progress.
     */
    function handleScanAll() {
        var scanAllBtn = document.getElementById('scalyn-scan-all');
        if (!scanAllBtn) {
            return;
        }

        scanAllBtn.addEventListener('click', function () {
            if (typeof ScalynAlert === 'undefined') {
                return;
            }

            ScalynAlert.confirm(
                'Scan All Pages',
                'This will scan all pages for QA issues. This may take a moment.',
                'Start Scan'
            ).then(function (result) {
                if (!result.isConfirmed) {
                    return;
                }

                runBatchScan();
            });
        });
    }

    /**
     * Run a batch scan by collecting all post IDs from the table or
     * from the pages needing attention widget and scanning in batches of 20.
     */
    function runBatchScan() {
        // Collect post IDs from the pages needing attention table.
        var rows = document.querySelectorAll('[data-post-id]');
        var postIds = [];

        rows.forEach(function (row) {
            var id = parseInt(row.getAttribute('data-post-id'), 10);
            if (id > 0 && postIds.indexOf(id) === -1) {
                postIds.push(id);
            }
        });

        if (postIds.length === 0) {
            if (typeof ScalynAlert !== 'undefined') {
                ScalynAlert.warning('No Pages', 'No pages found to scan.');
            }
            return;
        }

        if (typeof ScalynAlert !== 'undefined') {
            ScalynAlert.loading('Scanning pages...');
        }

        // Process in batches of 20 (API limit).
        var batchSize = 20;
        var batches = [];
        for (var i = 0; i < postIds.length; i += batchSize) {
            batches.push(postIds.slice(i, i + batchSize));
        }

        var completed = 0;
        var total = postIds.length;

        function processBatch(index) {
            if (index >= batches.length) {
                if (typeof ScalynAlert !== 'undefined') {
                    ScalynAlert.close();
                    ScalynAlert.success(
                        'Scan Complete',
                        'Successfully scanned ' + total + ' page(s).'
                    );
                }
                loadProjectScores();
                return;
            }

            fetchApi('scan/batch', {
                method: 'POST',
                body: JSON.stringify({ post_ids: batches[index] }),
            })
                .then(function (response) {
                    completed += batches[index].length;

                    if (typeof Swal !== 'undefined' && Swal.isVisible()) {
                        Swal.update({
                            title: 'Scanning pages... (' + completed + '/' + total + ')',
                        });
                    }

                    processBatch(index + 1);
                })
                .catch(function (err) {
                    console.error('Scalyn QA: Batch scan error.', err);
                    completed += batches[index].length;
                    processBatch(index + 1);
                });
        }

        processBatch(0);
    }

    /**
     * Handle click events on "View Audit" links.
     */
    function initAuditLinks() {
        document.addEventListener('click', function (e) {
            var link = e.target.closest('a[href*="scalyn-qa-audits"]');
            if (!link) {
                return;
            }

            // Let the browser navigate naturally; no special handling needed
            // unless we want to add a loading indicator.
        });
    }

    /**
     * Initialize tooltips: show/hide on hover via event delegation.
     */
    function initTooltips() {
        document.addEventListener('mouseenter', function (e) {
            if (!e.target || !e.target.closest) return;
            var tooltip = e.target.closest('.scalyn-tooltip');
            if (tooltip) {
                var content = tooltip.querySelector('.scalyn-tooltip__content');
                if (content) {
                    content.style.visibility = 'visible';
                    content.style.opacity = '1';
                }
            }
        }, true);

        document.addEventListener('mouseleave', function (e) {
            if (!e.target || !e.target.closest) return;
            var tooltip = e.target.closest('.scalyn-tooltip');
            if (tooltip) {
                var content = tooltip.querySelector('.scalyn-tooltip__content');
                if (content) {
                    content.style.visibility = 'hidden';
                    content.style.opacity = '0';
                }
            }
        }, true);
    }

    /**
     * Initialize score circle animations for all score cards on the page.
     */
    function animateAllScoreCircles() {
        var circles = document.querySelectorAll('.scalyn-score-circle');

        circles.forEach(function (circle) {
            var valueEl = circle.querySelector('.scalyn-score-circle__value');
            if (!valueEl) {
                return;
            }

            var score = parseInt(valueEl.textContent, 10) || 0;
            animateScoreCircle(circle, score);
        });
    }

    /**
     * Start auto-refresh of dashboard data every 60 seconds.
     * Only refreshes while the page tab is visible.
     */
    function startAutoRefresh() {
        refreshIntervalId = setInterval(function () {
            if (document.visibilityState === 'visible') {
                loadProjectScores();
            }
        }, REFRESH_INTERVAL);

        // Stop when the page is unloaded.
        window.addEventListener('beforeunload', function () {
            if (refreshIntervalId) {
                clearInterval(refreshIntervalId);
            }
        });
    }

    // -------------------------------------------------------------------------
    // Initialization
    // -------------------------------------------------------------------------

    /**
     * Initialize all dashboard functionality on DOMContentLoaded.
     */
    /**
     * Handle Launch Checklist "Run Check" button.
     */
    function initLaunchScan() {
        var btn = document.getElementById('scalyn-launch-scan');
        if (!btn) return;

        btn.addEventListener('click', function () {
            btn.disabled = true;
            var origText = btn.textContent;
            btn.textContent = 'Checking...';

            fetchApi('launch/scan', { method: 'POST' })
                .then(function (response) {
                    if (response.success) {
                        if (typeof ScalynAlert !== 'undefined') {
                            ScalynAlert.toast('Launch check complete');
                        }
                        window.location.reload();
                    }
                })
                .catch(function (err) {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.error('Error', err.message || 'Launch check failed.');
                    }
                    btn.disabled = false;
                    btn.textContent = origText;
                });
        });
    }

    /**
     * Handle individual Rescan buttons on dashboard and launch pages.
     */
    function initRescanButtons() {
        document.addEventListener('click', function (e) {
            if (!e.target || !e.target.closest) return;
            var btn = e.target.closest('.scalyn-rescan');
            if (!btn) return;

            var postId = btn.getAttribute('data-post-id');
            if (!postId) return;

            btn.disabled = true;
            var origText = btn.textContent;
            btn.textContent = 'Scanning...';

            fetchApi('scan/' + postId, { method: 'POST' })
                .then(function () {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.toast('Scan complete');
                    }
                    window.location.reload();
                })
                .catch(function (err) {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.error('Error', err.message || 'Scan failed.');
                    }
                    btn.disabled = false;
                    btn.textContent = origText;
                });
        });
    }

    /**
     * Handle "Ignore Check" buttons on launch checklist page.
     */
    function initIgnoreCheck() {
        document.addEventListener('click', function (e) {
            if (!e.target || !e.target.closest) return;
            var btn = e.target.closest('.scalyn-ignore-check');
            if (!btn) return;

            var checkId = btn.getAttribute('data-check-id');
            if (!checkId) return;

            var postId = btn.getAttribute('data-post-id') || '0';

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Ignore This Check',
                    text: 'Provide a reason (optional):',
                    input: 'text',
                    inputPlaceholder: 'Reason (optional)',
                    showCancelButton: true,
                    confirmButtonText: 'Ignore',
                    confirmButtonColor: '#F59E0B',
                }).then(function (result) {
                    if (!result.isConfirmed) return;

                    fetchApi('ignore', {
                        method: 'POST',
                        body: JSON.stringify({
                            type: parseInt(postId, 10) > 0 ? 'check' : 'global',
                            check_id: checkId,
                            post_id: parseInt(postId, 10) > 0 ? parseInt(postId, 10) : null,
                            reason: result.value || '',
                        }),
                    }).then(function (response) {
                        if (response.success) {
                            ScalynAlert && ScalynAlert.toast('Check ignored — recalculating...');
                            // Re-run launch scan to recalculate scores.
                            return fetchApi('launch/scan', { method: 'POST' });
                        }
                    }).then(function () {
                        window.location.reload();
                    }).catch(function (err) {
                        ScalynAlert && ScalynAlert.error('Error', err.message || 'Failed to ignore check.');
                    });
                });
            }
        });
    }

    /**
     * Handle "Restore" (remove ignore) buttons.
     */
    function initRemoveIgnore() {
        document.addEventListener('click', function (e) {
            if (!e.target || !e.target.closest) return;
            var btn = e.target.closest('.scalyn-remove-ignore');
            if (!btn) return;

            var ruleId = btn.getAttribute('data-rule-id');
            if (!ruleId) return;

            if (typeof ScalynAlert !== 'undefined') {
                ScalynAlert.confirm('Restore Check', 'This check will be evaluated again.', 'Restore')
                    .then(function (result) {
                        if (!result.isConfirmed) return;
                        fetchApi('ignore/' + ruleId, { method: 'DELETE' })
                            .then(function (response) {
                                if (response.success) {
                                    ScalynAlert.toast('Check restored — recalculating...');
                                    return fetchApi('launch/scan', { method: 'POST' });
                                }
                            })
                            .then(function () {
                                window.location.reload();
                            })
                            .catch(function (err) {
                                ScalynAlert.error('Error', err.message || 'Failed to restore check.');
                            });
                    });
            }
        });
    }

    function init() {
        // Animate existing score circles rendered server-side.
        animateAllScoreCircles();

        // Fetch and update project scores from the API.
        loadProjectScores();

        // Bind event handlers.
        handleScanAll();
        initAuditLinks();
        initRescanButtons();
        initTooltips();
        initLaunchScan();
        initIgnoreCheck();
        initRemoveIgnore();

        // Start auto-refresh.
        startAutoRefresh();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
