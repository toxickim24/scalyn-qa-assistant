/**
 * QA Inspector.
 *
 * Frontend visual inspection panel that renders existing Page Audit
 * data as a docked sidebar or floating panel with element highlighting.
 *
 * @package Scalyn\QA\Assets
 * @since   1.4.0
 */

'use strict';

(function () {

    // Bail early if data is missing.
    if (typeof scalynInspector === 'undefined' || typeof scalynQA === 'undefined') return;

    var data      = scalynInspector;
    var panel     = null;
    var mode      = 'closed'; // closed | docked | floating
    var highlights = [];
    var textMarks = [];
    var activeTooltip = null;
    var highlightsEnabled = true;
    var dragState = null;

    // Status icons (unicode).
    var STATUS_ICONS = { pass: '\u2713', warning: '!', fail: '\u2717' };

    // Issue-to-DOM selector mapping.
    var DOM_SELECTORS = {
        image_alt_text:      function (d) { return (d.missing_alt_images || []).map(function (src) { return 'img[src*="' + CSS.escape(src.split('/').pop()) + '"]'; }); },
        heading_hierarchy:   function () { return ['h1', 'h2', 'h3', 'h4', 'h5', 'h6']; },
        internal_links:      function (d) { return (d.internal_links || []).map(function (l) { return 'a[href*="' + CSS.escape(typeof l === 'string' ? l : l.url || '') + '"]'; }); },
        external_links:      function (d) { return (d.external_links || []).map(function (l) { return 'a[href*="' + CSS.escape(typeof l === 'string' ? l : l.url || '') + '"]'; }); },
    };

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    function esc(str) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str || '')));
        return d.innerHTML;
    }

    function getStatus(score) {
        var s = scalynQA.settings || {};
        if (score >= (s.score_green || 80)) return 'green';
        if (score >= (s.score_yellow || 50)) return 'yellow';
        return 'red';
    }

    function countIssues(checks) {
        var c = { pass: 0, warning: 0, fail: 0, total: 0 };
        if (!checks) return c;
        checks.forEach(function (item) {
            c.total++;
            if (c[item.status] !== undefined) c[item.status]++;
        });
        return c;
    }

    // -------------------------------------------------------------------------
    // Panel Rendering
    // -------------------------------------------------------------------------

    function buildPanel() {
        var el = document.createElement('div');
        el.id = 'sqi-panel';
        el.className = 'sqi-panel';
        el.innerHTML = buildHeader() + '<div class="sqi-body">' + buildBody() + '</div>' + buildFooter();
        document.body.appendChild(el);
        return el;
    }

    function buildHeader() {
        return '<div class="sqi-header">' +
            '<span class="sqi-header__title">QA Inspector</span>' +
            '<div class="sqi-header__actions">' +
            '<button class="sqi-header__btn" id="sqi-btn-mode" title="Toggle dock/float">\u25a1</button>' +
            '<button class="sqi-header__btn" id="sqi-btn-close" title="Close">\u2715</button>' +
            '</div></div>';
    }

    function buildBody() {
        if (!data.hasScan || !data.results) {
            return '<div class="sqi-empty"><div class="sqi-empty__icon">\uD83D\uDD0D</div>' +
                '<div class="sqi-empty__text">No scan data.<br>Click Rescan in the toolbar.</div></div>';
        }

        var r = data.results;
        var scores = r.scores || {};
        var overall = scores.overall || 0;
        var status = getStatus(overall);
        var results = r.results || {};

        var totalPass = 0, totalWarn = 0, totalFail = 0, totalAll = 0;

        var categories = [
            { key: 'seo', label: 'SEO', score: scores.seo || 0 },
            { key: 'content', label: 'Content', score: scores.content || 0 },
            { key: 'functionality', label: 'Functionality', score: scores.functionality || 0 },
        ];

        categories.forEach(function (cat) {
            var checks = results[cat.key] || [];
            var c = countIssues(checks);
            cat.counts = c;
            cat.checks = checks;
            totalPass += c.pass;
            totalWarn += c.warning;
            totalFail += c.fail;
            totalAll += c.total;
        });

        // Overview
        var html = '<div class="sqi-overview">' +
            '<div class="sqi-overview__score">' +
            '<div class="sqi-overview__circle sqi-overview__circle--' + status + '">' + overall + '%</div>' +
            '<div class="sqi-overview__meta">' +
            '<span class="sqi-overview__label">Page Score</span>' +
            '<span class="sqi-overview__stats">' + totalPass + '/' + totalAll + ' passed' +
            (totalFail > 0 ? ' &middot; <span style="color:var(--sqi-red)">' + totalFail + ' failed</span>' : '') +
            (totalWarn > 0 ? ' &middot; <span style="color:var(--sqi-yellow)">' + totalWarn + ' warnings</span>' : '') +
            '</span></div></div>' +
            '<div class="sqi-overview__bar"><div class="sqi-overview__bar-fill sqi-overview__bar-fill--' + status + '" style="width:' + overall + '%"></div></div>' +
            '</div>';

        // Categories
        categories.forEach(function (cat) {
            var catStatus = getStatus(cat.score);
            var issueCount = cat.counts.fail + cat.counts.warning;
            var isOpen = issueCount > 0;

            html += '<div class="sqi-category' + (isOpen ? ' sqi-category--open' : '') + '" data-category="' + cat.key + '">';
            html += '<div class="sqi-category__header">';
            html += '<span class="sqi-category__title"><span class="sqi-category__arrow">\u25B6</span> ' + esc(cat.label);
            if (issueCount > 0) {
                html += ' <span class="sqi-category__badge sqi-category__badge--' + (cat.counts.fail > 0 ? 'red' : 'yellow') + '">' + issueCount + '</span>';
            }
            html += '</span>';
            html += '<span class="sqi-category__score">' + cat.score + '%</span>';
            html += '</div>';

            html += '<div class="sqi-category__list">';
            cat.checks.forEach(function (item) {
                var icon = STATUS_ICONS[item.status] || '?';
                html += '<div class="sqi-check" data-check-id="' + esc(item.id) + '" data-status="' + esc(item.status) + '" data-category="' + cat.key + '">';
                html += '<span class="sqi-check__icon sqi-check__icon--' + item.status + '">' + icon + '</span>';
                html += '<div class="sqi-check__content">';
                html += '<span class="sqi-check__label">' + esc(item.label) + '</span>';
                if (item.message) html += '<span class="sqi-check__message">' + esc(item.message) + '</span>';

                // Inline AI result (if exists from generation).
                var inlineResult = getInlineResult(item);
                if (inlineResult) {
                    html += '<div class="sqi-check__result">' + esc(inlineResult) + '</div>';
                }

                // Action buttons — show for failing checks AND for pass checks that have AI data.
                var hasAiForCheck = !!aiGenerated[item.id];
                if (item.status !== 'pass' || hasAiForCheck) {
                    html += buildCheckActions(item);
                }

                html += '</div></div>';
            });
            html += '</div></div>';
        });

        // AI Content Review section.
        html += buildContentReview();

        return html;
    }

    /**
     * Get inline AI result text for a check (from saved drafts).
     */
    function getInlineResult(item) {
        if (!data.results) return null;

        // Meta title/description: read from saved AI drafts passed via localized data.
        if (item.id === 'meta_title_exists' && aiDrafts && aiDrafts.title) {
            return aiDrafts.title;
        }
        if (item.id === 'meta_description_exists' && aiDrafts && aiDrafts.description) {
            return aiDrafts.description;
        }
        return null;
    }

    // Load saved AI drafts from PHP-localized data.
    var aiDrafts = data.aiDrafts || null;
    var aiGenerated = {}; // Track which checks have been generated this session.

    // Pre-populate aiGenerated from existing server-side AI data.
    if (aiDrafts) {
        if (aiDrafts.title) aiGenerated['meta_title_exists'] = true;
        if (aiDrafts.description) aiGenerated['meta_description_exists'] = true;
    }
    if (data.aiAltTexts) aiGenerated['image_alt_text'] = true;
    if (data.aiKeywords) aiGenerated['focus_keyword'] = true;
    if (data.aiFeatured) aiGenerated['featured_image_exists'] = true;

    /**
     * Build action buttons for a failing check.
     */
    function buildCheckActions(item) {
        var actions = [];
        var checkId = item.id;

        // Determine which checks support AI generation.
        var aiChecks = {
            'meta_title_exists': 'ai/generate/',
            'meta_description_exists': 'ai/generate/',
            'image_alt_text': 'ai/generate-alt/',
            'focus_keyword': 'ai/generate-keywords/',
            'featured_image_exists': 'ai/generate-featured-image/',
        };

        if (aiChecks[checkId]) {
            var isRegen = !!aiGenerated[checkId] || !!getInlineResult(item);
            var genLabel = isRegen ? 'Regenerate' : 'Generate';
            actions.push('<button class="sqi-action-btn sqi-action-btn--ai sqi-gen-btn" data-check-id="' + esc(checkId) + '" data-endpoint="' + aiChecks[checkId] + '">' + genLabel + '</button>');
        }

        // Copy + Apply for meta title/description.
        var result = getInlineResult(item);
        if (result) {
            actions.push('<button class="sqi-action-btn sqi-copy-btn" data-text="' + esc(result) + '">Copy</button>');
            actions.push('<button class="sqi-action-btn sqi-action-btn--apply sqi-apply-btn" data-check-id="' + esc(checkId) + '" data-text="' + esc(result) + '">Apply</button>');
        }

        // Apply for featured image (from existing AI options).
        if (checkId === 'featured_image_exists' && data.aiFeatured && data.aiFeatured.length > 0) {
            actions.push('<button class="sqi-action-btn sqi-action-btn--apply sqi-apply-fi-btn" data-attachment-id="' + data.aiFeatured[0].id + '">Apply</button>');
        }

        // Copy + Apply for focus keyword.
        if (checkId === 'focus_keyword' && data.aiKeywords && data.aiKeywords.primary) {
            var kw = data.aiKeywords.primary;
            actions.push('<button class="sqi-action-btn sqi-copy-btn" data-text="' + esc(kw) + '">Copy</button>');
            actions.push('<button class="sqi-action-btn sqi-action-btn--apply sqi-apply-kw-btn" data-primary="' + esc(kw) + '">Apply</button>');
        }

        if (actions.length === 0) return '';

        var html = '<div class="sqi-check__actions">' + actions.join('') + '</div>';

        // Show keyword suggestions if available.
        if (checkId === 'focus_keyword' && data.aiKeywords) {
            var kwData = data.aiKeywords;
            var kwList = kwData.keywords || [];
            if (kwData.primary && kwList.length === 0) {
                kwList = [kwData.primary].concat(kwData.secondary || []);
            }
            if (kwList.length > 0) {
                var isPro = !!kwData.has_pro;
                var inputType = isPro ? 'checkbox' : 'radio';
                var inputName = isPro ? 'sqi-kw[]' : 'sqi-kw';

                html += '<div class="sqi-kw-options">';
                kwList.forEach(function (kw, idx) {
                    var isPrimary = isPro && idx === 0;
                    var isSecondary = isPro && idx > 0;
                    // Pro: all checked by default. Free: first checked.
                    var isChecked = isPro ? true : (idx === 0);

                    html += '<label class="sqi-kw-opt' + (isChecked ? ' sqi-kw-opt--selected' : '') + '">';
                    html += '<input type="' + inputType + '" name="' + inputName + '" value="' + esc(kw) + '"' + (isChecked ? ' checked' : '') + '>';
                    html += '<span>' + esc(kw) + '</span>';
                    if (isPrimary) html += '<span class="sqi-kw-opt__badge">primary</span>';
                    if (isSecondary) html += '<span class="sqi-kw-opt__badge" style="color:var(--sqi-text-muted);">secondary</span>';
                    html += '</label>';
                });
                html += '</div>';
            }
        }

        // Show featured image thumbnails if available.
        if (checkId === 'featured_image_exists' && data.aiFeatured && data.aiFeatured.length > 0) {
            html += '<div class="sqi-fi-options">';
            data.aiFeatured.forEach(function (fi, idx) {
                var isCurrent = fi.id === data.currentThumbnail;
                html += '<label class="sqi-fi-opt' + (isCurrent ? ' sqi-fi-opt--current' : (idx === 0 ? ' sqi-fi-opt--selected' : '')) + '">';
                html += '<input type="radio" name="sqi-fi" value="' + fi.id + '"' + (isCurrent || (!data.currentThumbnail && idx === 0) ? ' checked' : '') + '>';
                html += '<img src="' + esc(fi.url) + '" alt="' + esc(fi.filename) + '" title="' + esc(fi.filename) + '">';
                if (isCurrent) html += '<span class="sqi-fi-opt__badge">current</span>';
                html += '</label>';
            });
            html += '</div>';
        }

        return html;
    }

    function buildContentReview() {
        var review = data.contentReview;
        if (!review || !review.summary) return '';

        var score = review.score || 0;
        var reviewStatus = score >= 80 ? 'green' : (score >= 50 ? 'yellow' : 'red');
        var issues = review.issues || [];
        var activeIssues = issues.filter(function (i) { return i.status !== 'resolved' && i.status !== 'ignored'; });
        var hasIssues = activeIssues.length > 0;

        var html = '<div class="sqi-category' + (hasIssues ? ' sqi-category--open' : '') + '" data-category="ai-review">';
        html += '<div class="sqi-category__header">';
        html += '<span class="sqi-category__title"><span class="sqi-category__arrow">\u25B6</span> Writing Quality';
        if (hasIssues) {
            html += ' <span class="sqi-category__badge sqi-category__badge--' + reviewStatus + '">' + activeIssues.length + '</span>';
        }
        html += '</span>';
        html += '<span class="sqi-category__score">' + score + '/100</span>';
        html += '</div>';

        html += '<div class="sqi-category__list">';

        // Summary.
        html += '<div style="padding:4px 8px;margin-bottom:6px;font-size:11px;color:var(--sqi-text-muted);line-height:1.5;">' + esc(review.summary) + '</div>';

        if (hasIssues) {
            activeIssues.forEach(function (issue) {
                var sevIcon = issue.severity === 'error' ? '\u2717' : (issue.severity === 'warning' ? '!' : '\u2139');
                var sevClass = issue.severity === 'error' ? 'fail' : (issue.severity === 'warning' ? 'warning' : 'pass');
                var typeLabel = (issue.type || 'issue').replace(/_/g, ' ');
                typeLabel = typeLabel.charAt(0).toUpperCase() + typeLabel.slice(1);

                html += '<div class="sqi-check sqi-review-issue" data-issue-text="' + esc(issue.text || '') + '">';
                html += '<span class="sqi-check__icon sqi-check__icon--' + sevClass + '">' + sevIcon + '</span>';
                html += '<div class="sqi-check__content">';
                html += '<span class="sqi-check__label">' + esc(typeLabel) + '</span>';
                if (issue.text) html += '<span class="sqi-check__message" style="color:var(--sqi-red);text-decoration:line-through;">' + esc(issue.text) + '</span>';
                if (issue.suggestion) html += '<span class="sqi-check__message" style="color:var(--sqi-green);">\u2192 ' + esc(issue.suggestion) + '</span>';
                html += '</div></div>';
            });
        } else {
            html += '<div style="padding:4px 8px;font-size:11px;color:var(--sqi-green);">\u2713 No writing issues found.</div>';
        }

        // Actions row.
        html += '<div style="padding:6px 8px;display:flex;gap:6px;align-items:center;">';
        html += '<button class="sqi-footer__btn" id="sqi-btn-review-current" style="font-size:10px;padding:3px 8px;">Review Current</button>';
        html += '<button class="sqi-footer__btn" id="sqi-btn-review-regenerate" style="font-size:10px;padding:3px 8px;background:var(--sqi-border);color:var(--sqi-text);">Regenerate</button>';
        if (review.provider) {
            html += '<span style="font-size:10px;color:var(--sqi-text-muted);opacity:0.7;margin-left:auto;">' + esc(review.provider) + '</span>';
        }
        html += '</div>';

        html += '</div></div>';
        return html;
    }

    function buildFooter() {
        var scannedAt = '';
        if (data.results && data.results.scanned_at) {
            var d = new Date(data.results.scanned_at);
            scannedAt = d.toLocaleString();
        }

        return '<div class="sqi-footer">' +
            '<div class="sqi-footer__row">' +
            '<label class="sqi-footer__toggle"><input type="checkbox" id="sqi-highlight-toggle" checked> Highlight issues</label>' +
            '<button class="sqi-footer__btn" id="sqi-btn-rescan">Rescan</button>' +
            '</div>' +
            (scannedAt ? '<div class="sqi-footer__meta" style="margin-top:4px;">Last scan: ' + esc(scannedAt) + '</div>' : '') +
            '</div>';
    }

    // -------------------------------------------------------------------------
    // Panel Modes
    // -------------------------------------------------------------------------

    function openInspector(newMode) {
        if (!panel) panel = buildPanel();
        mode = newMode || 'docked';
        panel.style.display = '';

        // Clear any inline styles from floating/dragging/resizing.
        panel.style.width = '';
        panel.style.height = '';
        panel.style.left = '';
        panel.style.top = '';
        panel.style.right = '';

        if (mode === 'docked') {
            panel.className = 'sqi-panel sqi-panel--docked';
            document.body.classList.add('sqi-docked');
            document.getElementById('sqi-btn-mode').title = 'Undock to floating';
            document.getElementById('sqi-btn-mode').textContent = '\u25a1';
        } else {
            panel.className = 'sqi-panel sqi-panel--floating';
            document.body.classList.remove('sqi-docked');
            document.getElementById('sqi-btn-mode').title = 'Dock to sidebar';
            document.getElementById('sqi-btn-mode').textContent = '\u25eb';
        }

        bindPanelEvents();
        if (highlightsEnabled) showHighlights();
    }

    function closeInspector() {
        if (panel) panel.style.display = 'none';
        document.body.classList.remove('sqi-docked');
        mode = 'closed';
        clearHighlights();
        clearTooltip();
    }

    function toggleMode() {
        if (mode === 'docked') {
            openInspector('floating');
        } else {
            openInspector('docked');
        }
    }

    // -------------------------------------------------------------------------
    // Panel Events
    // -------------------------------------------------------------------------

    function bindPanelEvents() {
        // Close button.
        var closeBtn = document.getElementById('sqi-btn-close');
        if (closeBtn) closeBtn.onclick = closeInspector;

        // Mode toggle.
        var modeBtn = document.getElementById('sqi-btn-mode');
        if (modeBtn) modeBtn.onclick = toggleMode;

        // Highlight toggle.
        var hlToggle = document.getElementById('sqi-highlight-toggle');
        if (hlToggle) {
            hlToggle.checked = highlightsEnabled;
            hlToggle.onchange = function () {
                highlightsEnabled = hlToggle.checked;
                if (highlightsEnabled) showHighlights();
                else clearHighlights();
            };
        }

        // Rescan button.
        var rescanBtn = document.getElementById('sqi-btn-rescan');
        if (rescanBtn) {
            rescanBtn.onclick = function () {
                rescanBtn.disabled = true;
                rescanBtn.textContent = 'Scanning...';

                fetch(scalynQA.restUrl + 'scan/' + data.postId, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': scalynQA.nonce },
                    credentials: 'same-origin',
                })
                .then(function (r) { return r.json(); })
                .then(function (response) {
                    if (response.success && response.data) {
                        data.hasScan = true;
                        data.results = response.data;
                        refreshPanel();
                    }
                    rescanBtn.disabled = false;
                    rescanBtn.textContent = 'Rescan';
                })
                .catch(function () {
                    rescanBtn.disabled = false;
                    rescanBtn.textContent = 'Rescan';
                });
            };
        }

        // Generate/Regenerate AI buttons per check.
        panel.querySelectorAll('.sqi-gen-btn').forEach(function (btn) {
            btn.onclick = function (e) {
                e.stopPropagation();
                var checkId = btn.getAttribute('data-check-id');
                var endpoint = btn.getAttribute('data-endpoint');
                if (!checkId || !endpoint) return;

                btn.disabled = true;
                btn.textContent = 'Generating...';

                fetch(scalynQA.restUrl + endpoint + data.postId, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': scalynQA.nonce },
                    credentials: 'same-origin',
                })
                .then(function (r) { return r.json(); })
                .then(function (response) {
                    if (response.success && response.data) {
                        aiGenerated[checkId] = true;

                        // Store results for inline display.
                        if (checkId === 'meta_title_exists' || checkId === 'meta_description_exists') {
                            aiDrafts = aiDrafts || {};
                            if (response.data.title) aiDrafts.title = response.data.title;
                            if (response.data.description) aiDrafts.description = response.data.description;
                            // Mark both as generated.
                            aiGenerated['meta_title_exists'] = true;
                            aiGenerated['meta_description_exists'] = true;
                        }

                        refreshPanel();
                    }
                    btn.disabled = false;
                    btn.textContent = 'Regenerate';
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.textContent = 'Regenerate';
                });
            };
        });

        // Copy buttons.
        panel.querySelectorAll('.sqi-copy-btn').forEach(function (btn) {
            btn.onclick = function (e) {
                e.stopPropagation();
                var text = btn.getAttribute('data-text');
                if (text && navigator.clipboard) {
                    navigator.clipboard.writeText(text);
                    btn.textContent = 'Copied!';
                    setTimeout(function () { btn.textContent = 'Copy'; }, 1500);
                }
            };
        });

        // Apply buttons (meta title/description to SEO plugin).
        panel.querySelectorAll('.sqi-apply-btn').forEach(function (btn) {
            btn.onclick = function (e) {
                e.stopPropagation();
                var checkId = btn.getAttribute('data-check-id');
                var text = btn.getAttribute('data-text');
                if (!checkId || !text) return;

                var applyData = {};
                if (checkId === 'meta_title_exists') applyData.title = text;
                if (checkId === 'meta_description_exists') applyData.description = text;

                btn.disabled = true;
                btn.textContent = 'Applying...';

                fetch(scalynQA.restUrl + 'ai/apply/' + data.postId, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': scalynQA.nonce },
                    credentials: 'same-origin',
                    body: JSON.stringify(applyData),
                })
                .then(function (r) { return r.json(); })
                .then(function (response) {
                    if (response.success) {
                        btn.textContent = 'Applied!';
                        btn.classList.add('sqi-action-btn--done');
                        // Rescan to update scores.
                        fetch(scalynQA.restUrl + 'scan/' + data.postId, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': scalynQA.nonce },
                            credentials: 'same-origin',
                        })
                        .then(function (r) { return r.json(); })
                        .then(function (scanResp) {
                            if (scanResp.success && scanResp.data) {
                                data.hasScan = true;
                                data.results = scanResp.data;
                                refreshPanel();
                            }
                        });
                    } else {
                        btn.disabled = false;
                        btn.textContent = 'Apply';
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.textContent = 'Apply';
                });
            };
        });

        // Keyword Apply button.
        panel.querySelectorAll('.sqi-apply-kw-btn').forEach(function (btn) {
            btn.onclick = function (e) {
                e.stopPropagation();
                var isPro = data.aiKeywords && data.aiKeywords.has_pro;
                var primary = '';
                var secondary = [];

                if (isPro) {
                    // Checkbox mode: collect all checked values. First checked = primary, rest = secondary.
                    var checked = panel.querySelectorAll('input[name="sqi-kw[]"]:checked');
                    checked.forEach(function (cb, idx) {
                        if (idx === 0) primary = cb.value;
                        else secondary.push(cb.value);
                    });
                } else {
                    // Radio mode: single selected keyword.
                    var selected = panel.querySelector('input[name="sqi-kw"]:checked');
                    primary = selected ? selected.value : btn.getAttribute('data-primary');
                }

                if (!primary) return;

                btn.disabled = true;
                btn.textContent = 'Applying...';

                fetch(scalynQA.restUrl + 'ai/apply-keyword/' + data.postId, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': scalynQA.nonce },
                    credentials: 'same-origin',
                    body: JSON.stringify({ primary: primary, secondary: secondary }),
                })
                .then(function (r) { return r.json(); })
                .then(function (response) {
                    if (response.success) {
                        btn.textContent = 'Applied!';
                        btn.classList.add('sqi-action-btn--done');
                        // Rescan.
                        fetch(scalynQA.restUrl + 'scan/' + data.postId, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': scalynQA.nonce },
                            credentials: 'same-origin',
                        })
                        .then(function (r) { return r.json(); })
                        .then(function (scanResp) {
                            if (scanResp.success && scanResp.data) {
                                data.hasScan = true;
                                data.results = scanResp.data;
                                refreshPanel();
                            }
                        });
                    } else {
                        btn.disabled = false;
                        btn.textContent = 'Apply';
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.textContent = 'Apply';
                });
            };
        });

        // Keyword radio/checkbox selection.
        panel.querySelectorAll('input[name="sqi-kw"], input[name="sqi-kw[]"]').forEach(function (input) {
            input.onchange = function () {
                if (input.type === 'radio') {
                    // Radio: single selection highlight.
                    panel.querySelectorAll('.sqi-kw-opt').forEach(function (opt) { opt.classList.remove('sqi-kw-opt--selected'); });
                    input.closest('.sqi-kw-opt').classList.add('sqi-kw-opt--selected');
                    // Update Copy button.
                    var copyBtn = panel.querySelector('.sqi-check[data-check-id="focus_keyword"] .sqi-copy-btn');
                    if (copyBtn) copyBtn.setAttribute('data-text', input.value);
                } else {
                    // Checkbox: toggle highlight per item.
                    var opt = input.closest('.sqi-kw-opt');
                    if (input.checked) opt.classList.add('sqi-kw-opt--selected');
                    else opt.classList.remove('sqi-kw-opt--selected');
                }
            };
        });

        // Featured image Apply button.
        panel.querySelectorAll('.sqi-apply-fi-btn').forEach(function (btn) {
            btn.onclick = function (e) {
                e.stopPropagation();
                // Get selected radio value.
                var selected = panel.querySelector('input[name="sqi-fi"]:checked');
                var attachmentId = selected ? parseInt(selected.value, 10) : parseInt(btn.getAttribute('data-attachment-id'), 10);
                if (!attachmentId) return;

                btn.disabled = true;
                btn.textContent = 'Applying...';

                fetch(scalynQA.restUrl + 'ai/apply-featured-image/' + data.postId, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': scalynQA.nonce },
                    credentials: 'same-origin',
                    body: JSON.stringify({ attachment_id: attachmentId }),
                })
                .then(function (r) { return r.json(); })
                .then(function (response) {
                    if (response.success) {
                        btn.textContent = 'Applied!';
                        btn.classList.add('sqi-action-btn--done');
                        data.currentThumbnail = attachmentId;
                        // Rescan to update.
                        fetch(scalynQA.restUrl + 'scan/' + data.postId, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': scalynQA.nonce },
                            credentials: 'same-origin',
                        })
                        .then(function (r) { return r.json(); })
                        .then(function (scanResp) {
                            if (scanResp.success && scanResp.data) {
                                data.hasScan = true;
                                data.results = scanResp.data;
                                refreshPanel();
                            }
                        });
                    } else {
                        btn.disabled = false;
                        btn.textContent = 'Apply';
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.textContent = 'Apply';
                });
            };
        });

        // Featured image radio selection highlight.
        panel.querySelectorAll('input[name="sqi-fi"]').forEach(function (radio) {
            radio.onchange = function () {
                panel.querySelectorAll('.sqi-fi-opt').forEach(function (opt) { opt.classList.remove('sqi-fi-opt--selected'); });
                radio.closest('.sqi-fi-opt').classList.add('sqi-fi-opt--selected');
                // Update Apply button attachment ID.
                var applyBtn = panel.querySelector('.sqi-apply-fi-btn');
                if (applyBtn) applyBtn.setAttribute('data-attachment-id', radio.value);
            };
        });

        // Review Current button — recheck existing issues.
        var reviewBtn = document.getElementById('sqi-btn-review-current');
        if (reviewBtn) {
            reviewBtn.onclick = function () {
                reviewBtn.disabled = true;
                reviewBtn.textContent = 'Checking...';

                fetch(scalynQA.restUrl + 'ai/review/' + data.postId + '/recheck', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': scalynQA.nonce },
                    credentials: 'same-origin',
                })
                .then(function (r) { return r.json(); })
                .then(function (response) {
                    if (response.success && response.data) {
                        // Update local data with new review state.
                        data.contentReview = data.contentReview || {};
                        data.contentReview.issues = response.data.issues || [];
                        data.contentReview.score = response.data.score || data.contentReview.score;
                        data.contentReview.summary = response.data.summary || data.contentReview.summary;
                        refreshPanel();
                    }
                    reviewBtn.disabled = false;
                    reviewBtn.textContent = 'Review Current';
                })
                .catch(function () {
                    reviewBtn.disabled = false;
                    reviewBtn.textContent = 'Review Current';
                });
            };
        }

        // Regenerate button — full AI re-review.
        var regenBtn = document.getElementById('sqi-btn-review-regenerate');
        if (regenBtn) {
            regenBtn.onclick = function () {
                regenBtn.disabled = true;
                regenBtn.textContent = 'Reviewing...';

                fetch(scalynQA.restUrl + 'ai/review/' + data.postId, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': scalynQA.nonce },
                    credentials: 'same-origin',
                })
                .then(function (r) { return r.json(); })
                .then(function (response) {
                    if (response.success && response.data) {
                        data.contentReview = response.data;
                        refreshPanel();
                    }
                    regenBtn.disabled = false;
                    regenBtn.textContent = 'Regenerate';
                })
                .catch(function () {
                    regenBtn.disabled = false;
                    regenBtn.textContent = 'Regenerate';
                });
            };
        }

        // Category collapse/expand.
        panel.querySelectorAll('.sqi-category__header').forEach(function (header) {
            header.onclick = function () {
                header.parentElement.classList.toggle('sqi-category--open');
            };
        });

        // Check item click — scroll to element + highlight.
        panel.querySelectorAll('.sqi-check[data-check-id]').forEach(function (check) {
            check.onclick = function () {
                panel.querySelectorAll('.sqi-check--active').forEach(function (c) { c.classList.remove('sqi-check--active'); });
                check.classList.add('sqi-check--active');

                var checkId = check.getAttribute('data-check-id');
                var status = check.getAttribute('data-status');
                scrollToIssueElement(checkId, status);
            };
        });

        // Writing issue click — find text in page and scroll to it.
        panel.querySelectorAll('.sqi-review-issue').forEach(function (issue) {
            issue.onclick = function () {
                panel.querySelectorAll('.sqi-check--active').forEach(function (c) { c.classList.remove('sqi-check--active'); });
                issue.classList.add('sqi-check--active');

                var text = issue.getAttribute('data-issue-text');
                if (!text) return;
                scrollToTextInPage(text);
            };
        });

        // Dragging for floating mode.
        var header = panel.querySelector('.sqi-header');
        if (header) {
            header.onmousedown = function (e) {
                if (mode !== 'floating' || e.target.closest('.sqi-header__btn')) return;
                dragState = {
                    startX: e.clientX,
                    startY: e.clientY,
                    startLeft: panel.offsetLeft,
                    startTop: panel.offsetTop,
                };
                e.preventDefault();
            };
        }
    }

    // Global drag handlers.
    document.addEventListener('mousemove', function (e) {
        if (!dragState || mode !== 'floating') return;
        panel.style.left = (dragState.startLeft + e.clientX - dragState.startX) + 'px';
        panel.style.top = (dragState.startTop + e.clientY - dragState.startY) + 'px';
        panel.style.right = 'auto';
    });

    document.addEventListener('mouseup', function () {
        dragState = null;
    });

    function refreshPanel() {
        if (!panel) return;
        var body = panel.querySelector('.sqi-body');
        if (body) body.innerHTML = buildBody();
        clearHighlights();
        bindPanelEvents();
        if (highlightsEnabled) showHighlights();
    }

    // -------------------------------------------------------------------------
    // Visual Highlighting
    // -------------------------------------------------------------------------

    function showHighlights() {
        clearHighlights();
        if (!data.hasScan || !data.results || !data.results.results) return;

        var results = data.results.results;
        var categories = ['seo', 'content', 'functionality'];

        categories.forEach(function (cat) {
            var checks = results[cat] || [];
            checks.forEach(function (item) {
                if (item.status === 'pass') return;
                var elements = findDomElements(item);
                elements.forEach(function (el) {
                    createHighlight(el, item);
                });
            });
        });

        // Highlight writing issues from AI content review.
        highlightReviewIssues();
    }

    function highlightReviewIssues() {
        var review = data.contentReview;
        if (!review || !review.issues) return;

        var issues = review.issues.filter(function (i) { return i.status !== 'resolved' && i.status !== 'ignored' && i.text; });

        issues.forEach(function (issue) {
            var mark = wrapTextWithMark(issue.text, issue);
            if (mark) {
                textMarks.push(mark);
            }
        });
    }

    /**
     * Find a text string in the page and wrap it with a <mark> highlight.
     * Returns the mark element or null.
     */
    function wrapTextWithMark(searchText, issue) {
        if (!searchText || searchText.length < 2) return null;

        var walker = document.createTreeWalker(
            document.body,
            NodeFilter.SHOW_TEXT,
            {
                acceptNode: function (node) {
                    if (isInInspector(node.parentElement)) return NodeFilter.FILTER_REJECT;
                    if (node.parentElement.closest('.sqi-text-highlight')) return NodeFilter.FILTER_REJECT;
                    var idx = node.textContent.toLowerCase().indexOf(searchText.toLowerCase());
                    if (idx !== -1) return NodeFilter.FILTER_ACCEPT;
                    return NodeFilter.FILTER_REJECT;
                }
            }
        );

        var textNode = walker.nextNode();
        if (!textNode) return null;

        var idx = textNode.textContent.toLowerCase().indexOf(searchText.toLowerCase());
        if (idx === -1) return null;

        // Split the text node and wrap the matched portion.
        var before = textNode.textContent.substring(0, idx);
        var matched = textNode.textContent.substring(idx, idx + searchText.length);
        var after = textNode.textContent.substring(idx + searchText.length);

        var sevClass = issue.severity === 'error' ? 'sqi-text-highlight--error' : 'sqi-text-highlight--warning';
        var typeLabel = (issue.type || 'issue').replace(/_/g, ' ');
        typeLabel = typeLabel.charAt(0).toUpperCase() + typeLabel.slice(1);

        var mark = document.createElement('mark');
        mark.className = 'sqi-text-highlight ' + sevClass;
        mark.textContent = matched;
        mark.setAttribute('data-sqi-issue', typeLabel);
        mark.setAttribute('data-sqi-suggestion', issue.suggestion || '');
        mark.setAttribute('data-sqi-text', issue.text || '');

        // Tooltip on hover.
        mark.addEventListener('mouseenter', function () {
            showTooltip(mark, {
                id: '_review',
                status: issue.severity === 'error' ? 'fail' : 'warning',
                label: typeLabel,
                message: issue.suggestion ? 'Suggestion: ' + issue.suggestion : '',
                tooltip: issue.context || '',
            });
        });
        mark.addEventListener('mouseleave', clearTooltip);

        var parent = textNode.parentNode;
        var frag = document.createDocumentFragment();
        if (before) frag.appendChild(document.createTextNode(before));
        frag.appendChild(mark);
        if (after) frag.appendChild(document.createTextNode(after));
        parent.replaceChild(frag, textNode);

        return mark;
    }

    function findDomElements(item) {
        var elements = [];
        var selectorFn = DOM_SELECTORS[item.id];

        if (selectorFn) {
            var selectors = selectorFn(item.details || {});
            selectors.forEach(function (sel) {
                try {
                    document.querySelectorAll(sel).forEach(function (el) {
                        if (!isInInspector(el)) elements.push(el);
                    });
                } catch (e) { /* invalid selector */ }
            });
        }

        // Special case: multiple H1 check.
        if (item.id === 'heading_hierarchy' || (item.id === 'meta_title_exists' && item.status !== 'pass')) {
            // Only highlight h1 if there are multiple.
            var h1s = document.querySelectorAll('h1');
            if (h1s.length > 1) {
                h1s.forEach(function (el) {
                    if (!isInInspector(el)) elements.push(el);
                });
            }
        }

        return elements;
    }

    function isInInspector(el) {
        return el.closest('#sqi-panel') || el.closest('#wpadminbar');
    }

    function createHighlight(targetEl, item) {
        var rect = targetEl.getBoundingClientRect();
        var scrollX = window.scrollX || window.pageXOffset;
        var scrollY = window.scrollY || window.pageYOffset;

        var overlay = document.createElement('div');
        overlay.className = 'sqi-highlight sqi-highlight--' + item.status;
        overlay.style.left = (rect.left + scrollX - 2) + 'px';
        overlay.style.top = (rect.top + scrollY - 2) + 'px';
        overlay.style.width = (rect.width + 4) + 'px';
        overlay.style.height = (rect.height + 4) + 'px';

        overlay.addEventListener('mouseenter', function () {
            showTooltip(targetEl, item);
        });
        overlay.addEventListener('mouseleave', function () {
            clearTooltip();
        });

        // Store the original pointer-events so we can allow click-through.
        overlay.style.pointerEvents = 'auto';

        document.body.appendChild(overlay);
        highlights.push({ overlay: overlay, target: targetEl, item: item });
    }

    function clearHighlights() {
        highlights.forEach(function (h) {
            if (h.overlay.parentNode) h.overlay.parentNode.removeChild(h.overlay);
        });
        highlights = [];

        // Unwrap text marks — restore original text nodes.
        textMarks.forEach(function (mark) {
            if (!mark.parentNode) return;
            var text = document.createTextNode(mark.textContent);
            mark.parentNode.replaceChild(text, mark);
            // Merge adjacent text nodes.
            text.parentNode.normalize();
        });
        textMarks = [];

        clearTooltip();
    }

    // Reposition highlights on scroll/resize.
    var repositionTimer = null;
    function repositionHighlights() {
        if (repositionTimer) return;
        repositionTimer = requestAnimationFrame(function () {
            repositionTimer = null;
            var scrollX = window.scrollX || window.pageXOffset;
            var scrollY = window.scrollY || window.pageYOffset;
            highlights.forEach(function (h) {
                var rect = h.target.getBoundingClientRect();
                h.overlay.style.left = (rect.left + scrollX - 2) + 'px';
                h.overlay.style.top = (rect.top + scrollY - 2) + 'px';
                h.overlay.style.width = (rect.width + 4) + 'px';
                h.overlay.style.height = (rect.height + 4) + 'px';
            });
        });
    }

    window.addEventListener('scroll', repositionHighlights, { passive: true });
    window.addEventListener('resize', repositionHighlights);

    // -------------------------------------------------------------------------
    // Tooltips
    // -------------------------------------------------------------------------

    function showTooltip(targetEl, item) {
        clearTooltip();

        var rect = targetEl.getBoundingClientRect();
        var scrollX = window.scrollX || window.pageXOffset;
        var scrollY = window.scrollY || window.pageYOffset;

        var tip = document.createElement('div');
        tip.className = 'sqi-tooltip';

        var iconClass = item.status === 'fail' ? 'sqi-tooltip__title-icon--fail' : 'sqi-tooltip__title-icon--warning';
        var icon = STATUS_ICONS[item.status] || '?';

        tip.innerHTML = '<div class="sqi-tooltip__title"><span class="' + iconClass + '">' + icon + '</span> ' + esc(item.label) + '</div>';

        if (item.message) {
            tip.innerHTML += '<div class="sqi-tooltip__why">' + esc(item.message) + '</div>';
        }

        if (item.tooltip) {
            tip.innerHTML += '<div class="sqi-tooltip__fix">' + esc(item.tooltip) + '</div>';
        }

        document.body.appendChild(tip);

        // Position: prefer above the element, fall back to below.
        var tipRect = tip.getBoundingClientRect();
        var top = rect.top + scrollY - tipRect.height - 8;
        if (top < scrollY + 40) {
            top = rect.bottom + scrollY + 8;
        }
        var left = rect.left + scrollX;
        if (left + tipRect.width > window.innerWidth + scrollX - 20) {
            left = window.innerWidth + scrollX - tipRect.width - 20;
        }

        tip.style.top = top + 'px';
        tip.style.left = Math.max(10, left) + 'px';

        activeTooltip = tip;
    }

    function clearTooltip() {
        if (activeTooltip && activeTooltip.parentNode) {
            activeTooltip.parentNode.removeChild(activeTooltip);
        }
        activeTooltip = null;
    }

    // -------------------------------------------------------------------------
    // Scroll to Issue Element
    // -------------------------------------------------------------------------

    function scrollToIssueElement(checkId, status) {
        // Find the first highlight for this check ID.
        var target = null;
        highlights.forEach(function (h) {
            if (!target && h.item.id === checkId) {
                target = h;
            }
        });

        if (target) {
            target.target.scrollIntoView({ behavior: 'smooth', block: 'center' });

            // Flash highlight.
            target.overlay.classList.add('sqi-highlight--active');
            setTimeout(function () {
                target.overlay.classList.remove('sqi-highlight--active');
            }, 2000);

            showTooltip(target.target, target.item);
            setTimeout(clearTooltip, 4000);
            return;
        }

        // No DOM element — try to find one.
        if (!data.results || !data.results.results) return;

        var categories = ['seo', 'content', 'functionality'];
        var item = null;
        for (var i = 0; i < categories.length; i++) {
            var checks = data.results.results[categories[i]] || [];
            for (var j = 0; j < checks.length; j++) {
                if (checks[j].id === checkId) {
                    item = checks[j];
                    break;
                }
            }
            if (item) break;
        }

        if (!item) return;

        var elements = findDomElements(item);
        if (elements.length > 0) {
            elements[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            // Create temporary highlight.
            createHighlight(elements[0], item);
            var lastH = highlights[highlights.length - 1];
            if (lastH) {
                lastH.overlay.classList.add('sqi-highlight--active');
                setTimeout(function () {
                    lastH.overlay.classList.remove('sqi-highlight--active');
                }, 2000);
            }
        }
    }

    /**
     * Find text in the page and scroll to its highlighted mark.
     */
    function scrollToTextInPage(searchText) {
        if (!searchText || searchText.length < 2) return;

        // Find existing mark for this text.
        var mark = null;
        textMarks.forEach(function (m) {
            if (!mark && m.getAttribute('data-sqi-text') === searchText) {
                mark = m;
            }
        });

        if (mark) {
            mark.scrollIntoView({ behavior: 'smooth', block: 'center' });
            mark.classList.add('sqi-text-highlight--active');
            setTimeout(function () { mark.classList.remove('sqi-text-highlight--active'); }, 2500);
            return;
        }

        // No existing mark — try to create one on the fly.
        var review = data.contentReview;
        var issue = null;
        if (review && review.issues) {
            review.issues.forEach(function (i) {
                if (!issue && i.text === searchText) issue = i;
            });
        }

        if (issue) {
            var newMark = wrapTextWithMark(searchText, issue);
            if (newMark) {
                textMarks.push(newMark);
                newMark.scrollIntoView({ behavior: 'smooth', block: 'center' });
                newMark.classList.add('sqi-text-highlight--active');
                setTimeout(function () { newMark.classList.remove('sqi-text-highlight--active'); }, 2500);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Toolbar Integration
    // -------------------------------------------------------------------------

    function initToolbarToggle() {
        document.addEventListener('click', function (e) {
            var toggle = e.target.closest('#wp-admin-bar-scalyn-qa-score > a, .scalyn-qa-inspector-toggle > a, .scalyn-qa-inspector-toggle');
            if (!toggle) return;
            e.preventDefault();
            e.stopPropagation();

            if (mode === 'closed') {
                openInspector('docked');
            } else {
                closeInspector();
            }
        });
    }

    // -------------------------------------------------------------------------
    // Init
    // -------------------------------------------------------------------------

    function init() {
        initToolbarToggle();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
