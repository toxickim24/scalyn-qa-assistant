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
                html += '</div></div>';
            });
            html += '</div></div>';
        });

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

        // Category collapse/expand.
        panel.querySelectorAll('.sqi-category__header').forEach(function (header) {
            header.onclick = function () {
                header.parentElement.classList.toggle('sqi-category--open');
            };
        });

        // Check item click — scroll to element + highlight.
        panel.querySelectorAll('.sqi-check').forEach(function (check) {
            check.onclick = function () {
                // Deselect previous.
                panel.querySelectorAll('.sqi-check--active').forEach(function (c) { c.classList.remove('sqi-check--active'); });
                check.classList.add('sqi-check--active');

                var checkId = check.getAttribute('data-check-id');
                var status = check.getAttribute('data-status');
                scrollToIssueElement(checkId, status);
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
