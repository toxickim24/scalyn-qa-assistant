/**
 * Admin Audit JS.
 *
 * Handles both the audit list page (filtering, rescanning, batch scans)
 * and the single audit page (rescan, notes, snapshots, AI meta, ignore rules,
 * quick fixes).
 *
 * @package Scalyn\QA\Assets
 * @since   1.0.0
 */

'use strict';

(function () {

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Get post_id from URL query parameter.
     * @returns {string|null}
     */
    function getPostIdFromUrl() {
        var params = new URLSearchParams(window.location.search);
        return params.get('post_id') || null;
    }

    /**
     * Wrapper for fetch() that adds the REST nonce header and base URL.
     *
     * @param {string} endpoint - Relative endpoint path.
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
     * Calculate the traffic-light status for a given score.
     *
     * @param {number} score - Score (0-100).
     * @returns {string} 'green', 'yellow', or 'red'.
     */
    function getStatus(score) {
        var settings = scalynQA.settings || {};
        var greenThreshold = parseInt(settings.green_threshold, 10) || 80;
        var yellowThreshold = parseInt(settings.yellow_threshold, 10) || 50;

        if (score >= greenThreshold) return 'green';
        if (score >= yellowThreshold) return 'yellow';
        return 'red';
    }

    /**
     * Convert an ISO date string to a human-readable "X minutes ago" format.
     *
     * @param {string} dateString - ISO 8601 date string.
     * @returns {string}
     */
    function formatTimeAgo(dateString) {
        if (!dateString) return 'Never';

        var now = new Date();
        var date = new Date(dateString);
        var seconds = Math.floor((now - date) / 1000);

        if (seconds < 0) return 'Just now';

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
     * Escape HTML special characters in a string.
     *
     * @param {string} str - The string to escape.
     * @returns {string}
     */
    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // -------------------------------------------------------------------------
    // Render Helpers
    // -------------------------------------------------------------------------

    /**
     * Render a check item row as an HTML string.
     *
     * @param {Object} item - Check item data.
     * @returns {string} HTML string.
     */
    function renderCheckItem(item) {
        var statusIcons = {
            pass: 'dashicons-yes-alt',
            warning: 'dashicons-warning',
            fail: 'dashicons-dismiss',
        };
        var iconClass = statusIcons[item.status] || 'dashicons-marker';
        var severity = item.severity || 'info';
        var quickFix = item.quick_fix || '';
        var tooltip = item.tooltip || '';
        var postId = scalynQA.currentPostId || 0;

        var html = '<div class="scalyn-check-item scalyn-check-item--' + escapeHtml(item.status) + ' scalyn-check-item--' + escapeHtml(severity) + '"'
            + ' data-check-id="' + escapeHtml(item.id) + '"'
            + ' data-status="' + escapeHtml(item.status) + '"'
            + ' data-severity="' + escapeHtml(severity) + '">';
        html += '<span class="scalyn-check-icon" aria-hidden="true">';
        html += '<span class="dashicons ' + escapeHtml(iconClass) + '"></span>';
        html += '</span>';
        html += '<div class="scalyn-check-content">';
        html += '<strong class="scalyn-check-label">' + escapeHtml(item.label) + '</strong>';
        if (item.message) {
            html += ' <span class="scalyn-check-message">' + escapeHtml(item.message) + '</span>';
        }
        html += '</div>';
        html += '<div class="scalyn-check-actions">';

        if (quickFix) {
            html += '<button type="button" class="scalyn-btn scalyn-btn--small scalyn-btn--secondary scalyn-quick-fix"'
                + ' data-action="' + escapeHtml(quickFix) + '"'
                + ' data-post-id="' + postId + '">Fix</button>';
        }

        if (tooltip) {
            html += '<span class="scalyn-tooltip" tabindex="0" role="button" aria-label="More information">';
            html += '<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>';
            html += '<span class="scalyn-tooltip__content">' + escapeHtml(tooltip) + '</span>';
            html += '</span>';
        }

        html += '<button type="button" class="scalyn-btn scalyn-btn--small scalyn-btn--ghost scalyn-ignore-check"'
            + ' data-check-id="' + escapeHtml(item.id) + '"'
            + ' data-post-id="' + postId + '"'
            + ' title="Ignore this check">';
        html += '<span class="dashicons dashicons-hidden" aria-hidden="true"></span>';
        html += '</button>';
        html += '</div></div>';

        return html;
    }

    /**
     * Render AI suggestion results panel.
     *
     * @param {Object} data - AI result data with title, description, provider, model.
     * @returns {string} HTML string.
     */
    function renderAiResults(data) {
        var postId = scalynQA.currentPostId || 0;

        var html = '<div class="scalyn-ai-results">';
        html += '<h4 class="scalyn-ai-results__heading">AI Suggestions</h4>';
        html += '<p class="scalyn-ai-results__meta">Generated by ' + escapeHtml(data.provider) + ' (' + escapeHtml(data.model) + ')</p>';

        if (data.title) {
            html += '<div class="scalyn-ai-field">';
            html += '<label class="scalyn-ai-field__label">Meta Title</label>';
            html += '<div class="scalyn-ai-field__value" data-field="title">' + escapeHtml(data.title) + '</div>';
            html += '<div class="scalyn-ai-field__actions">';
            html += '<button type="button" class="scalyn-btn scalyn-btn--small scalyn-copy-meta" data-field="title">Copy</button>';
            html += '<button type="button" class="scalyn-btn scalyn-btn--small scalyn-btn--secondary scalyn-edit-ai-meta" data-field="title">Edit</button>';
            html += '</div></div>';
        }

        if (data.description) {
            html += '<div class="scalyn-ai-field">';
            html += '<label class="scalyn-ai-field__label">Meta Description</label>';
            html += '<div class="scalyn-ai-field__value" data-field="description">' + escapeHtml(data.description) + '</div>';
            html += '<div class="scalyn-ai-field__actions">';
            html += '<button type="button" class="scalyn-btn scalyn-btn--small scalyn-copy-meta" data-field="description">Copy</button>';
            html += '<button type="button" class="scalyn-btn scalyn-btn--small scalyn-btn--secondary scalyn-edit-ai-meta" data-field="description">Edit</button>';
            html += '</div></div>';
        }

        html += '<div class="scalyn-ai-actions">';
        html += '<button type="button" class="scalyn-btn scalyn-apply-ai-meta" data-post-id="' + postId + '">Apply to SEO Plugin</button>';
        html += '<button type="button" class="scalyn-btn scalyn-btn--secondary scalyn-regenerate-ai" data-post-id="' + postId + '">Regenerate</button>';
        html += '</div></div>';

        return html;
    }

    /**
     * Render a note as HTML.
     *
     * @param {Object} note  - Note data with content, author, created_at.
     * @param {number} index - Index of the note in the list.
     * @returns {string} HTML string.
     */
    function renderNote(note, index) {
        var postId = scalynQA.currentPostId || 0;

        var html = '<div class="scalyn-note" data-index="' + index + '">';
        html += '<div class="scalyn-note__content">' + escapeHtml(note.content) + '</div>';
        html += '<div class="scalyn-note__meta">';
        html += '<span class="scalyn-note__author">' + escapeHtml(note.author) + '</span>';
        html += '<span class="scalyn-note__date">' + formatTimeAgo(note.created_at) + '</span>';
        html += '</div>';
        html += '<button type="button" class="scalyn-btn scalyn-btn--small scalyn-btn--ghost scalyn-delete-note"'
            + ' data-post-id="' + postId + '"'
            + ' data-index="' + index + '"'
            + ' title="Delete note">';
        html += '<span class="dashicons dashicons-trash" aria-hidden="true"></span>';
        html += '</button>';
        html += '</div>';

        return html;
    }

    /**
     * Render a snapshot entry as HTML.
     *
     * @param {Object} snapshot - Snapshot data.
     * @returns {string} HTML string.
     */
    function renderSnapshot(snapshot) {
        var scores = snapshot.scores || {};
        var status = getStatus(scores.overall || 0);

        var html = '<div class="scalyn-snapshot">';
        html += '<div class="scalyn-snapshot__score">';
        html += '<span class="scalyn-badge scalyn-badge--' + status + '">' + (scores.overall || 0) + '</span>';
        html += '</div>';
        html += '<div class="scalyn-snapshot__details">';
        html += '<span class="scalyn-snapshot__date">' + formatTimeAgo(snapshot.created_at) + '</span>';
        html += '<span class="scalyn-snapshot__breakdown">SEO: ' + (scores.seo || 0) + ' | Content: ' + (scores.content || 0) + ' | Func: ' + (scores.functionality || 0) + '</span>';
        html += '</div></div>';

        return html;
    }

    // -------------------------------------------------------------------------
    // List Page Functionality
    // -------------------------------------------------------------------------

    /**
     * Initialize the audit list page.
     */
    function initListPage() {
        initStatusFilter();
        initTypeFilter();
        initRescanButtons();
        initScanAll();
    }

    /**
     * Initialize client-side status filtering.
     */
    function initStatusFilter() {
        var filterSelect = document.getElementById('scalyn-filter-status');
        if (!filterSelect) return;

        filterSelect.addEventListener('change', function () {
            var params = new URLSearchParams(window.location.search);

            if (this.value) {
                params.set('status', this.value);
            } else {
                params.delete('status');
            }

            params.delete('paged');
            window.location.search = params.toString();
        });
    }

    /**
     * Initialize post type filtering — navigates with query param.
     */
    function initTypeFilter() {
        var filterSelect = document.getElementById('scalyn-filter-type');
        if (!filterSelect) return;

        filterSelect.addEventListener('change', function () {
            var params = new URLSearchParams(window.location.search);

            if (this.value) {
                params.set('filter_type', this.value);
            } else {
                params.delete('filter_type');
            }

            params.delete('paged');
            window.location.search = params.toString();
        });
    }

    /**
     * Initialize individual rescan buttons on the list page.
     */
    function initRescanButtons() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-rescan') || e.target.closest('#scalyn-rescan');
            if (!btn) return;

            var postId = btn.getAttribute('data-post-id') || getPostIdFromUrl();
            if (!postId) return;

            btn.disabled = true;
            btn.textContent = 'Scanning...';

            fetchApi('scan/' + postId, { method: 'POST' })
                .then(function (response) {
                    if (response.success) {
                        if (typeof ScalynAlert !== 'undefined') {
                            ScalynAlert.toast('Scan complete');
                        }
                        window.location.reload();
                    }
                })
                .catch(function (err) {
                    console.error('Scalyn QA: Rescan failed.', err);
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.error('Scan Failed', err.message || 'An error occurred while scanning.');
                    }
                })
                .finally(function () {
                    btn.disabled = false;
                    btn.textContent = 'Rescan';
                });
        });
    }

    /**
     * Update a table row with new scan data.
     *
     * @param {string|number} postId - The post ID.
     * @param {Object}        data   - Scan result data.
     */
    function updateTableRow(postId, data) {
        var row = document.querySelector('tr[data-post-id="' + postId + '"]');
        if (!row) return;

        var scores = data.scores || {};
        var seoScore = scores.seo || 0;
        var contentScore = scores.content || 0;
        var funcScore = scores.functionality || 0;
        var overallScore = scores.overall || 0;

        var cells = row.querySelectorAll('td');
        if (cells.length < 8) return;

        // Update SEO score (index 3).
        cells[3].innerHTML = '<span class="scalyn-badge scalyn-badge--' + getStatus(seoScore) + '">' + seoScore + '</span>';
        // Update Content score (index 4).
        cells[4].innerHTML = '<span class="scalyn-badge scalyn-badge--' + getStatus(contentScore) + '">' + contentScore + '</span>';
        // Update Func score (index 5).
        cells[5].innerHTML = '<span class="scalyn-badge scalyn-badge--' + getStatus(funcScore) + '">' + funcScore + '</span>';
        // Update Overall score (index 6).
        cells[6].innerHTML = '<span class="scalyn-badge scalyn-badge--' + getStatus(overallScore) + '">' + overallScore + '</span>';
        // Update Last Scan (index 7).
        cells[7].innerHTML = '<span>' + formatTimeAgo(data.scanned_at) + '</span>';
    }

    /**
     * Initialize "Scan All" button on the list page.
     */
    function initScanAll() {
        var scanAllBtn = document.getElementById('scalyn-scan-all');
        if (!scanAllBtn) return;

        scanAllBtn.addEventListener('click', function () {
            if (typeof ScalynAlert === 'undefined') return;

            ScalynAlert.confirm(
                'Scan All Pages',
                'This will scan all visible pages for QA issues. This may take a moment.',
                'Start Scan'
            ).then(function (result) {
                if (!result.isConfirmed) return;
                runListBatchScan();
            });
        });
    }

    /**
     * Run batch scan for all posts visible in the table.
     */
    function runListBatchScan() {
        var rows = document.querySelectorAll('.scalyn-table tbody tr[data-post-id]');
        var postIds = [];

        rows.forEach(function (row) {
            var id = parseInt(row.getAttribute('data-post-id'), 10);
            if (id > 0) postIds.push(id);
        });

        if (postIds.length === 0) {
            if (typeof ScalynAlert !== 'undefined') {
                ScalynAlert.warning('No Pages', 'No pages found to scan.');
            }
            return;
        }

        // Show progress bar.
        var progressContainer = document.getElementById('scalyn-scan-progress');
        var progressBar = progressContainer ? progressContainer.querySelector('.scalyn-progress__bar') : null;
        var countEl = document.getElementById('scalyn-scan-count');
        var totalEl = document.getElementById('scalyn-scan-total');
        var percentEl = document.getElementById('scalyn-scan-percent');

        var completed = 0;
        var total = postIds.length;

        if (progressContainer) {
            progressContainer.style.display = '';
            if (totalEl) totalEl.textContent = total;
            if (countEl) countEl.textContent = '0';
            if (percentEl) percentEl.textContent = '0';
            if (progressBar) progressBar.style.width = '0%';
        }

        function updateProgress() {
            var percent = Math.round((completed / total) * 100);
            if (progressBar) progressBar.style.width = percent + '%';
            if (countEl) countEl.textContent = completed;
            if (percentEl) percentEl.textContent = percent;
        }

        // Scan one page at a time for visible progress.
        function scanNext(index) {
            if (index >= postIds.length) {
                // Show 100% briefly before the success alert.
                updateProgress();
                setTimeout(function () {
                    if (progressContainer) progressContainer.style.display = 'none';
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.success('Scan Complete', 'Successfully scanned ' + total + ' page(s).');
                    }
                }, 500);
                return;
            }

            fetchApi('scan', {
                method: 'POST',
                body: JSON.stringify({ post_id: postIds[index] }),
            })
                .then(function (response) {
                    if (response.success && response.data) {
                        updateTableRow(postIds[index], response.data);
                    }
                    completed++;
                    updateProgress();
                    scanNext(index + 1);
                })
                .catch(function (err) {
                    console.error('Scalyn QA: Scan error for post ' + postIds[index], err);
                    completed++;
                    updateProgress();
                    scanNext(index + 1);
                });
        }

        scanNext(0);
    }

    // -------------------------------------------------------------------------
    // Single Audit Page Functionality
    // -------------------------------------------------------------------------

    /**
     * Initialize single audit page.
     */
    function initSinglePage() {
        initSingleRescan();
        initAddNote();
        initDeleteNote();
        initCreateSnapshot();
        initGenerateAiMeta();
        initCopyMeta();
        initApplyAiMeta();
        initEditAiMeta();
        initRegenerateAi();
        initAiContentReview();
        initIgnoreCheck();
        initRemoveIgnore();
        initQuickFixes();
    }

    /**
     * Handle rescan button on single audit page.
     */
    function initSingleRescan() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('#scalyn-rescan, #scalyn-rescan-btn, .scalyn-rescan-single, .scalyn-rescan');
            if (!btn) return;

            var postId = btn.getAttribute('data-post-id') || getPostIdFromUrl() || scalynQA.currentPostId;
            if (!postId) return;

            btn.disabled = true;

            if (typeof ScalynAlert !== 'undefined') {
                ScalynAlert.loading('Scanning page...');
            }

            fetchApi('scan/' + postId, { method: 'POST' })
                .then(function (response) {
                    if (response.success) {
                        ScalynAlert && ScalynAlert.toast('Scan complete');
                        window.location.reload();
                    }
                })
                .catch(function (err) {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.close();
                        ScalynAlert.error('Scan Failed', err.message || 'An error occurred.');
                    }
                })
                .finally(function () {
                    btn.disabled = false;
                });
        });
    }

    /**
     * Update the single audit page with new scan results.
     *
     * @param {Object} data - Scan result data.
     */
    function updateSinglePageResults(data) {
        // Update score display if present.
        var scoreCircle = document.querySelector('.scalyn-score-circle');
        if (scoreCircle && data.scores) {
            var score = data.scores.overall || 0;
            var status = data.scores.status || getStatus(score);
            var valueEl = scoreCircle.querySelector('.scalyn-score-circle__value');
            if (valueEl) valueEl.textContent = score;

            scoreCircle.classList.remove('scalyn-score-circle--green', 'scalyn-score-circle--yellow', 'scalyn-score-circle--red');
            scoreCircle.classList.add('scalyn-score-circle--' + status);
            scoreCircle.style.setProperty('--score-percent', score + '%');
        }

        // Update check items if the results container exists.
        var resultsContainer = document.querySelector('.scalyn-check-results, .scalyn-audit-checks');
        if (resultsContainer && data.results) {
            var html = '';
            var categories = Object.keys(data.results);
            categories.forEach(function (category) {
                var items = data.results[category];
                if (Array.isArray(items)) {
                    html += '<div class="scalyn-check-category">';
                    html += '<h3 class="scalyn-check-category__title">' + escapeHtml(category) + '</h3>';
                    items.forEach(function (item) {
                        html += renderCheckItem(item);
                    });
                    html += '</div>';
                }
            });
            resultsContainer.innerHTML = html;
        }
    }

    /**
     * Handle "Add Note" button.
     */
    function initAddNote() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-add-note') || e.target.closest('#scalyn-add-note');
            if (!btn) return;

            var postId = btn.getAttribute('data-post-id') || getPostIdFromUrl() || scalynQA.currentPostId;
            if (!postId) return;

            if (typeof Swal === 'undefined') return;

            Swal.fire({
                title: 'Add Note',
                input: 'textarea',
                inputPlaceholder: 'Enter your note...',
                showCancelButton: true,
                confirmButtonText: 'Save Note',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#4a90d9',
                inputValidator: function (value) {
                    if (!value || !value.trim()) {
                        return 'Note content cannot be empty.';
                    }
                },
                customClass: { popup: 'scalyn-swal-popup' },
            }).then(function (result) {
                if (!result.isConfirmed || !result.value) return;

                fetchApi('notes/' + postId, {
                    method: 'POST',
                    body: JSON.stringify({ content: result.value.trim() }),
                })
                    .then(function (response) {
                        if (response.success) {
                            ScalynAlert && ScalynAlert.toast('Note added');
                            window.location.reload();
                        }
                    })
                    .catch(function (err) {
                        ScalynAlert && ScalynAlert.error('Error', err.message || 'Failed to add note.');
                    });
            });
        });
    }

    /**
     * Handle "Delete Note" button.
     */
    function initDeleteNote() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-delete-note');
            if (!btn) return;

            var postId = btn.getAttribute('data-post-id') || scalynQA.currentPostId;
            var index = btn.getAttribute('data-index');
            if (!postId || index === null) return;

            if (typeof ScalynAlert === 'undefined') return;

            ScalynAlert.confirm(
                'Delete Note',
                'Are you sure you want to delete this note?',
                'Delete'
            ).then(function (result) {
                if (!result.isConfirmed) return;

                fetchApi('notes/' + postId + '/' + index, { method: 'DELETE' })
                    .then(function (response) {
                        if (response.success) {
                            ScalynAlert.toast('Note deleted');
                            window.location.reload();
                        }
                    })
                    .catch(function (err) {
                        ScalynAlert.error('Error', err.message || 'Failed to delete note.');
                    });
            });
        });
    }

    /**
     * Update the notes section with new notes data.
     *
     * @param {Array} notes - Array of note objects.
     */
    function updateNotesSection(notes) {
        var container = document.querySelector('.scalyn-notes-list');
        if (!container) return;

        if (!notes || notes.length === 0) {
            container.innerHTML = '<p class="scalyn-empty">No notes yet.</p>';
            return;
        }

        var html = '';
        notes.forEach(function (note, index) {
            html += renderNote(note, index);
        });
        container.innerHTML = html;
    }

    /**
     * Handle "Create Snapshot" button.
     */
    function initCreateSnapshot() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-create-snapshot') || e.target.closest('#scalyn-create-snapshot');
            if (!btn) return;

            var postId = btn.getAttribute('data-post-id') || getPostIdFromUrl() || scalynQA.currentPostId;
            if (!postId) return;

            btn.disabled = true;

            fetchApi('snapshots/' + postId, { method: 'POST' })
                .then(function (response) {
                    if (response.success) {
                        ScalynAlert && ScalynAlert.toast('Snapshot saved');
                        window.location.reload();
                    }
                })
                .catch(function (err) {
                    ScalynAlert && ScalynAlert.error('Error', err.message || 'Failed to create snapshot.');
                    btn.disabled = false;
                });
        });
    }

    /**
     * Append a new snapshot to the snapshots list.
     *
     * @param {Object} snapshot - Snapshot data.
     */
    function appendSnapshot(snapshot) {
        var container = document.querySelector('.scalyn-snapshots-list');
        if (!container) return;

        // Remove empty message if present.
        var emptyMsg = container.querySelector('.scalyn-empty');
        if (emptyMsg) emptyMsg.remove();

        container.insertAdjacentHTML('afterbegin', renderSnapshot(snapshot));
    }

    /**
     * Handle "Generate AI Meta" button.
     */
    function initGenerateAiMeta() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-generate-ai-meta') || e.target.closest('#scalyn-generate-ai');
            if (!btn) return;

            var postId = btn.getAttribute('data-post-id') || getPostIdFromUrl() || scalynQA.currentPostId;
            if (!postId) return;

            btn.disabled = true;
            if (typeof ScalynAlert !== 'undefined') {
                ScalynAlert.loading('Generating AI suggestions...');
            }

            fetchApi('ai/generate/' + postId, { method: 'POST' })
                .then(function (response) {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.close();
                    }

                    if (response.success && response.data) {
                        displayAiResults(response.data);
                    }
                })
                .catch(function (err) {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.close();
                        ScalynAlert.error('AI Generation Failed', err.message || 'An error occurred.');
                    }
                })
                .finally(function () {
                    btn.disabled = false;
                });
        });
    }

    /**
     * Display AI results in the AI section.
     *
     * @param {Object} data - AI response data.
     */
    function displayAiResults(data) {
        var resultsEl = document.getElementById('scalyn-ai-results');
        if (!resultsEl) return;

        var titleText = document.getElementById('scalyn-ai-title-text');
        var titleLength = document.getElementById('scalyn-ai-title-length');
        var descText = document.getElementById('scalyn-ai-description-text');
        var descLength = document.getElementById('scalyn-ai-description-length');

        if (titleText) {
            titleText.textContent = data.title || '';
            if (titleLength) titleLength.textContent = (data.title || '').length + ' characters';
        }
        if (descText) {
            descText.textContent = data.description || '';
            if (descLength) descLength.textContent = (data.description || '').length + ' characters';
        }

        resultsEl.style.display = '';
    }

    /**
     * Handle "Copy to Clipboard" buttons for AI meta.
     */
    function initCopyMeta() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-copy-meta');
            if (!btn) return;

            var field = btn.getAttribute('data-field');
            var valueEl = btn.closest('.scalyn-ai-field').querySelector('.scalyn-ai-field__value');
            if (!valueEl) return;

            var text = valueEl.textContent.trim();

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function () {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.toast('Copied to clipboard');
                    }
                }).catch(function () {
                    fallbackCopy(text);
                });
            } else {
                fallbackCopy(text);
            }
        });
    }

    /**
     * Fallback clipboard copy using textarea.
     *
     * @param {string} text - Text to copy.
     */
    function fallbackCopy(text) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            if (typeof ScalynAlert !== 'undefined') {
                ScalynAlert.toast('Copied to clipboard');
            }
        } catch (err) {
            if (typeof ScalynAlert !== 'undefined') {
                ScalynAlert.error('Copy Failed', 'Unable to copy to clipboard.');
            }
        }
        document.body.removeChild(textarea);
    }

    /**
     * Handle "Apply to SEO Plugin" button.
     */
    function initApplyAiMeta() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-apply-ai-meta');
            if (!btn) return;

            var postId = btn.getAttribute('data-post-id') || scalynQA.currentPostId;
            if (!postId) return;

            var resultsContainer = btn.closest('.scalyn-ai-results');
            if (!resultsContainer) return;

            var titleEl = resultsContainer.querySelector('[data-field="title"]');
            var descEl = resultsContainer.querySelector('[data-field="description"]');

            var title = titleEl ? titleEl.textContent.trim() : '';
            var description = descEl ? descEl.textContent.trim() : '';

            if (!title && !description) {
                if (typeof ScalynAlert !== 'undefined') {
                    ScalynAlert.warning('No Data', 'No AI-generated meta to apply.');
                }
                return;
            }

            btn.disabled = true;

            fetchApi('ai/apply/' + postId, {
                method: 'POST',
                body: JSON.stringify({ title: title, description: description }),
            })
                .then(function (response) {
                    if (response.success) {
                        if (typeof ScalynAlert !== 'undefined') {
                            ScalynAlert.toast('Meta applied to SEO plugin');
                        }
                    }
                })
                .catch(function (err) {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.error('Apply Failed', err.message || 'Failed to apply meta.');
                    }
                })
                .finally(function () {
                    btn.disabled = false;
                });
        });
    }

    /**
     * Handle "Edit AI Meta" buttons.
     */
    function initEditAiMeta() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-edit-ai-meta');
            if (!btn) return;

            var field = btn.getAttribute('data-field');
            var fieldContainer = btn.closest('.scalyn-ai-field');
            var valueEl = fieldContainer ? fieldContainer.querySelector('.scalyn-ai-field__value') : null;
            if (!valueEl) return;

            var currentValue = valueEl.textContent.trim();
            var fieldLabel = field === 'title' ? 'Meta Title' : 'Meta Description';

            if (typeof Swal === 'undefined') return;

            Swal.fire({
                title: 'Edit ' + fieldLabel,
                input: field === 'description' ? 'textarea' : 'text',
                inputValue: currentValue,
                showCancelButton: true,
                confirmButtonText: 'Save',
                confirmButtonColor: '#4a90d9',
                inputValidator: function (value) {
                    if (!value || !value.trim()) {
                        return fieldLabel + ' cannot be empty.';
                    }
                },
                customClass: { popup: 'scalyn-swal-popup' },
            }).then(function (result) {
                if (result.isConfirmed && result.value) {
                    valueEl.textContent = result.value.trim();
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.toast(fieldLabel + ' updated');
                    }
                }
            });
        });
    }

    /**
     * Handle "Regenerate" button.
     */
    function initRegenerateAi() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-regenerate-ai');
            if (!btn) return;

            var postId = btn.getAttribute('data-post-id') || scalynQA.currentPostId;
            if (!postId) return;

            btn.disabled = true;
            if (typeof ScalynAlert !== 'undefined') {
                ScalynAlert.loading('Regenerating AI suggestions...');
            }

            fetchApi('ai/generate/' + postId, { method: 'POST' })
                .then(function (response) {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.close();
                    }

                    if (response.success && response.data) {
                        displayAiResults(response.data);
                        if (typeof ScalynAlert !== 'undefined') {
                            ScalynAlert.toast('AI suggestions regenerated');
                        }
                    }
                })
                .catch(function (err) {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.close();
                        ScalynAlert.error('Regeneration Failed', err.message || 'An error occurred.');
                    }
                })
                .finally(function () {
                    btn.disabled = false;
                });
        });
    }

    /**
     * Initialize AI Content Review button and regenerate.
     */
    function initAiContentReview() {
        function runReview(btn) {
            var postId = btn.getAttribute('data-post-id') || scalynQA.currentPostId;
            if (!postId) return;

            var spinner = document.getElementById('scalyn-review-spinner');
            var resultsEl = document.getElementById('scalyn-review-results');
            var errorEl = document.getElementById('scalyn-review-error');

            btn.disabled = true;
            if (spinner) spinner.style.display = '';
            if (resultsEl) resultsEl.style.display = 'none';
            if (errorEl) errorEl.style.display = 'none';

            fetchApi('ai/review/' + postId, { method: 'POST' })
                .then(function (response) {
                    if (response.success && response.data) {
                        displayReviewResults(response.data);
                    }
                })
                .catch(function (err) {
                    if (errorEl) {
                        var errorText = document.getElementById('scalyn-review-error-text');
                        if (errorText) errorText.textContent = err.message || 'Content review failed.';
                        errorEl.style.display = '';
                    }
                })
                .finally(function () {
                    btn.disabled = false;
                    if (spinner) spinner.style.display = 'none';
                });
        }

        var reviewBtn = document.getElementById('scalyn-review-content');
        if (reviewBtn) {
            reviewBtn.addEventListener('click', function () { runReview(reviewBtn); });
        }

        var regenBtn = document.getElementById('scalyn-review-regenerate');
        if (regenBtn) {
            regenBtn.addEventListener('click', function () { runReview(regenBtn); });
        }
    }

    /**
     * Display AI content review results.
     */
    function displayReviewResults(data) {
        var resultsEl = document.getElementById('scalyn-review-results');
        var summaryText = document.getElementById('scalyn-review-summary-text');
        var scoreBadge = document.getElementById('scalyn-review-score-badge');
        var issuesWrap = document.getElementById('scalyn-review-issues-wrap');
        var issuesBody = document.getElementById('scalyn-review-issues-body');

        if (!resultsEl) return;

        if (summaryText) summaryText.textContent = data.summary || 'No summary available.';

        if (scoreBadge) {
            var score = data.score || 0;
            var status = score >= 80 ? 'green' : (score >= 50 ? 'yellow' : 'red');
            scoreBadge.className = 'scalyn-badge scalyn-badge--' + status;
            scoreBadge.textContent = score + '/100';
        }

        if (issuesBody) {
            issuesBody.innerHTML = '';

            var issues = data.issues || [];
            if (issues.length > 0 && issuesWrap) {
                issuesWrap.style.display = '';

                var severityBadge = { error: 'red', warning: 'yellow', suggestion: 'neutral' };

                issues.forEach(function (issue) {
                    var row = document.createElement('tr');
                    row.innerHTML =
                        '<td><span class="scalyn-badge scalyn-badge--neutral">' + escHtml(issue.type || '') + '</span></td>' +
                        '<td><span class="scalyn-badge scalyn-badge--' + (severityBadge[issue.severity] || 'neutral') + '">' + escHtml(issue.severity || '') + '</span></td>' +
                        '<td>' +
                            '<strong>' + escHtml(issue.text || '') + '</strong>' +
                            (issue.context ? '<br><small style="color:var(--scalyn-text-muted)">' + escHtml(issue.context) + '</small>' : '') +
                        '</td>' +
                        '<td>' + escHtml(issue.suggestion || '') + '</td>';
                    issuesBody.appendChild(row);
                });
            } else if (issuesWrap) {
                issuesWrap.style.display = 'none';
            }
        }

        resultsEl.style.display = '';
    }

    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    /**
     * Handle "Ignore Check" buttons.
     */
    function initIgnoreCheck() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-ignore-check');
            if (!btn) return;

            var checkId = btn.getAttribute('data-check-id');
            var postId = btn.getAttribute('data-post-id') || scalynQA.currentPostId;
            if (!checkId) return;

            if (typeof Swal === 'undefined') return;

            Swal.fire({
                title: 'Ignore This Check',
                text: 'Provide a reason for ignoring this check (optional):',
                input: 'text',
                inputPlaceholder: 'Reason (optional)',
                showCancelButton: true,
                confirmButtonText: 'Ignore',
                confirmButtonColor: '#f0ad4e',
                customClass: { popup: 'scalyn-swal-popup' },
            }).then(function (result) {
                if (!result.isConfirmed) return;

                var reason = result.value || '';

                fetchApi('ignore', {
                    method: 'POST',
                    body: JSON.stringify({
                        type: postId ? 'check' : 'global',
                        check_id: checkId,
                        post_id: postId ? parseInt(postId, 10) : null,
                        reason: reason,
                        context: 'audit',
                    }),
                })
                    .then(function (response) {
                        if (response.success) {
                            ScalynAlert && ScalynAlert.toast('Check ignored — rescanning...');
                            // Rescan to recalculate scores without ignored checks.
                            return fetchApi('scan/' + postId, { method: 'POST' });
                        }
                    })
                    .then(function () {
                        window.location.reload();
                    })
                    .catch(function (err) {
                        ScalynAlert && ScalynAlert.error('Error', err.message || 'Failed to ignore check.');
                    });
            });
        });
    }

    /**
     * Handle "Remove Ignore" buttons.
     */
    function initRemoveIgnore() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-remove-ignore');
            if (!btn) return;

            var ruleId = btn.getAttribute('data-rule-id');
            if (!ruleId) return;

            if (typeof ScalynAlert === 'undefined') return;

            ScalynAlert.confirm(
                'Remove Ignore Rule',
                'This check will be evaluated again in future scans.',
                'Remove'
            ).then(function (result) {
                if (!result.isConfirmed) return;

                var postId = getPostIdFromUrl();
                fetchApi('ignore/' + ruleId, { method: 'DELETE' })
                    .then(function (response) {
                        if (response.success) {
                            ScalynAlert.toast('Check restored — rescanning...');
                            if (postId) {
                                return fetchApi('scan/' + postId, { method: 'POST' });
                            }
                        }
                    })
                    .then(function () {
                        window.location.reload();
                    })
                    .catch(function (err) {
                        ScalynAlert.error('Error', err.message || 'Failed to remove ignore rule.');
                    });
            });
        });
    }

    /**
     * Handle quick fix buttons.
     */
    function initQuickFixes() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-quick-fix');
            if (!btn) return;

            var action = btn.getAttribute('data-action');
            var postId = btn.getAttribute('data-post-id') || scalynQA.currentPostId;

            switch (action) {
                case 'generate-ai-meta':
                    triggerAiGeneration(postId);
                    break;

                case 'upload-featured-image':
                    openMediaLibrary(postId);
                    break;

                case 'jump-to-heading':
                    navigateToPostEditor(postId);
                    break;

                case 'edit-link':
                    navigateToPostEditor(postId);
                    break;

                default:
                    break;
            }
        });
    }

    /**
     * Trigger AI meta generation from quick fix.
     *
     * @param {string|number} postId - Post ID.
     */
    function triggerAiGeneration(postId) {
        if (!postId) return;

        if (typeof ScalynAlert !== 'undefined') {
            ScalynAlert.loading('Generating AI suggestions...');
        }

        fetchApi('ai/generate/' + postId, { method: 'POST' })
            .then(function (response) {
                if (typeof ScalynAlert !== 'undefined') {
                    ScalynAlert.close();
                }
                if (response.success && response.data) {
                    displayAiResults(response.data);
                }
            })
            .catch(function (err) {
                if (typeof ScalynAlert !== 'undefined') {
                    ScalynAlert.close();
                    ScalynAlert.error('AI Generation Failed', err.message || 'An error occurred.');
                }
            });
    }

    /**
     * Open the WordPress media library for uploading a featured image.
     *
     * @param {string|number} postId - Post ID.
     */
    function openMediaLibrary(postId) {
        if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
            // Fallback: navigate to post editor.
            navigateToPostEditor(postId);
            return;
        }

        var frame = wp.media({
            title: 'Select Featured Image',
            button: { text: 'Set Featured Image' },
            multiple: false,
            library: { type: 'image' },
        });

        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            // Set as featured image via REST API.
            fetch(scalynQA.restUrl.replace('scalyn-qa/v1/', '') + 'wp/v2/posts/' + postId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': scalynQA.nonce,
                },
                credentials: 'same-origin',
                body: JSON.stringify({ featured_media: attachment.id }),
            })
                .then(function () {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.toast('Featured image set');
                    }
                })
                .catch(function (err) {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.error('Error', 'Failed to set featured image.');
                    }
                });
        });

        frame.open();
    }

    /**
     * Navigate to the post editor in a new tab.
     *
     * @param {string|number} postId - Post ID.
     */
    function navigateToPostEditor(postId) {
        if (!postId) return;
        var editUrl = scalynQA.restUrl.split('/wp-json/')[0] + '/wp-admin/post.php?post=' + postId + '&action=edit';
        window.open(editUrl, '_blank');
    }

    // -------------------------------------------------------------------------
    // Initialization
    // -------------------------------------------------------------------------

    /**
     * Determine which page we are on and initialize accordingly.
     */
    function init() {
        // Check if we're on a single audit page (has post_id in URL).
        var urlParams = new URLSearchParams(window.location.search);
        var isInSingleView = urlParams.has('post_id') && parseInt(urlParams.get('post_id'), 10) > 0;

        if (isInSingleView) {
            initSinglePage();
        } else {
            initListPage();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
