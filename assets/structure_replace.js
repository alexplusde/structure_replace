/* structure_replace — moderne Strukturverwaltung (Drag & Drop, Modal-Auto-Open) */
(function () {
    'use strict';

    function getBridge() {
        var el = document.getElementById('structure-replace-bridge');
        if (!el) return null;
        try { return JSON.parse(el.textContent); } catch (e) { return null; }
    }

    function postReorder(bridge, kind, id, priority) {
        var fd = new FormData();
        fd.append('kind', kind);
        fd.append('id', id);
        fd.append('priority', priority);
        fd.append('clang', bridge.clang);
        if (bridge.csrf) {
            Object.keys(bridge.csrf).forEach(function (k) { fd.append(k, bridge.csrf[k]); });
        }
        return fetch(bridge.reorderUrl, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
    }

    function postReorderWithParent(bridge, kind, id, priority, parentId) {
        var fd = new FormData();
        fd.append('kind', kind);
        fd.append('id', id);
        fd.append('priority', priority);
        fd.append('parent_id', parentId);
        fd.append('clang', bridge.clang);
        if (bridge.csrf) {
            Object.keys(bridge.csrf).forEach(function (k) { fd.append(k, bridge.csrf[k]); });
        }
        return fetch(bridge.reorderUrl, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
    }

    function postBulk(bridge, kind, action, ids, extra) {
        var fd = new FormData();
        fd.append('kind', kind);
        fd.append('action', action);
        fd.append('clang', bridge.clang);
        ids.forEach(function (id) { fd.append('ids[]', id); });
        if (extra && typeof extra === 'object') {
            Object.keys(extra).forEach(function (k) { fd.append(k, extra[k]); });
        }
        if (bridge.csrf) {
            Object.keys(bridge.csrf).forEach(function (k) { fd.append(k, bridge.csrf[k]); });
        }
        return fetch(bridge.bulkUrl, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) {
            if (!r.ok) throw new Error('http ' + r.status);
            return r.json();
        });
    }

    function postUpdate(bridge, kind, id, field, value, clang) {
        var fd = new FormData();
        fd.append('kind', kind);
        fd.append('id', id);
        fd.append('clang', clang || bridge.clang);
        fd.append(field, value);
        if (bridge.csrf) {
            Object.keys(bridge.csrf).forEach(function (k) { fd.append(k, bridge.csrf[k]); });
        }
        return fetch(bridge.reorderUrl, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
    }

    function flashStatus(el, ok) {
        el.classList.remove('rex-sr-saved', 'rex-sr-error');
        el.classList.add(ok ? 'rex-sr-saved' : 'rex-sr-error');
        setTimeout(function () { el.classList.remove('rex-sr-saved', 'rex-sr-error'); }, 1500);
    }

    function showToast(bridge, ok, customMsg) {
        var container = document.getElementById('rex-sr-toasts');
        if (!container) return;
        var msg = customMsg || (ok ? (bridge.i18n && bridge.i18n.saved) : (bridge.i18n && bridge.i18n.saveFailed)) || (ok ? 'OK' : 'Error');
        var toast = document.createElement('div');
        toast.className = 'toast align-items-center border-0 text-bg-' + (ok ? 'success' : 'danger');
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');
        toast.innerHTML = '<div class="d-flex"><div class="toast-body"><i class="rex-icon ' + (ok ? 'fa-circle-check' : 'fa-circle-exclamation') + '"></i> ' + msg.replace(/[<>&]/g, function (c) { return ({'<':'&lt;','>':'&gt;','&':'&amp;'})[c]; }) + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>';
        container.appendChild(toast);
        if (window.bootstrap && window.bootstrap.Toast) {
            var t = new window.bootstrap.Toast(toast, { delay: 2500 });
            t.show();
            toast.addEventListener('hidden.bs.toast', function () { toast.remove(); });
        } else {
            setTimeout(function () { toast.remove(); }, 2500);
        }
    }

    function flashError(msg) {
        var box = document.createElement('div');
        box.className = 'alert alert-danger';
        box.textContent = msg;
        var c = document.querySelector('#rex-message-container') || document.querySelector('#rex-js-page-main');
        if (c) c.prepend(box);
        setTimeout(function () { box.remove(); }, 4000);
    }

    function reindexPriorities(items, kind, bridge, parentId) {
        var requests = [];
        items.forEach(function (li, idx) {
            var newPrio = idx + 1;
            var oldPrio = parseInt(li.getAttribute('data-priority'), 10);
            var id = parseInt(li.getAttribute(kind === 'cat' ? 'data-cat-id' : 'data-art-id'), 10);
            if (!id) return;
            li.setAttribute('data-priority', String(newPrio));
            // Wenn parentId uebergeben: pro Item einen "with parent" reorder-call schicken,
            // damit der Server ggf. parent_id aktualisieren kann (Drag in andere Hierarchie).
            if (typeof parentId === 'number' && parentId >= 0) {
                requests.push(postReorderWithParent(bridge, kind, id, newPrio, parentId));
                return;
            }
            if (newPrio === oldPrio) return;
            requests.push(postReorder(bridge, kind, id, newPrio));
        });
        return Promise.all(requests);
    }

    function initSortableForList(ul, kind, bridge) {
        if (!window.Sortable || !ul) return;
        // Kategorien teilen jetzt eine gemeinsame Drop-Gruppe ("sr-cats"),
        // damit man Ordner per DnD in andere Eltern-Ordner verschieben kann.
        var groupCfg;
        if (kind === 'art') {
            groupCfg = { name: 'arts', pull: true, put: true };
        } else if (kind === 'cat') {
            groupCfg = { name: 'sr-cats', pull: true, put: true };
        }
        new window.Sortable(ul, {
            handle: '.rex-sr-handle',
            ghostClass: 'rex-sr-ghost',
            dragClass: 'rex-sr-drag',
            animation: 150,
            filter: '.is-startarticle, .is-locked',
            group: groupCfg,
            onEnd: function (ev) {
                var targetUl = ev.to;
                var items = Array.from(targetUl.children).filter(function (el) {
                    return el.matches(kind === 'cat' ? '[data-cat-id]' : '[data-art-id]')
                        && !el.classList.contains('is-startarticle');
                });
                var parentId = -1;
                if (kind === 'cat' && targetUl.hasAttribute('data-parent-id')) {
                    parentId = parseInt(targetUl.getAttribute('data-parent-id'), 10);
                } else if (kind === 'art' && targetUl.hasAttribute('data-category-id')) {
                    parentId = parseInt(targetUl.getAttribute('data-category-id'), 10);
                }
                var sourceChanged = ev.from !== ev.to;
                reindexPriorities(items, kind, bridge, sourceChanged ? parentId : -1).then(function () {
                    showToast(bridge, true);
                    if (sourceChanged && bridge.reloadUrl) {
                        // Reload, da sich Hierarchie geaendert hat (URLs/Permissions/etc.).
                        setTimeout(function () { window.location.href = bridge.reloadUrl; }, 400);
                    }
                }).catch(function () {
                    flashError(bridge.i18n && bridge.i18n.reorderError || 'Reorder failed');
                    showToast(bridge, false, bridge.i18n && bridge.i18n.reorderError);
                });
            }
        });
    }

    function initStartArticleDropZone(bridge) {
        if (!window.Sortable || !bridge || !bridge.toStartUrl) return;
        document.querySelectorAll('[data-sortable="startart"]').forEach(function (zone) {
            if (zone.dataset.srBound === '1') return;
            zone.dataset.srBound = '1';
            new window.Sortable(zone, {
                group: { name: 'arts', pull: false, put: ['arts'] },
                animation: 150,
                filter: '.is-startarticle',
                onAdd: function (ev) {
                    var dragged = ev.item;
                    // Visuelle Aenderung sofort verwerfen — nach Erfolg laden wir die Seite neu.
                    if (dragged && dragged.parentNode === zone) zone.removeChild(dragged);
                    var artId = dragged ? parseInt(dragged.getAttribute('data-art-id'), 10) : 0;
                    if (!artId) return;
                    var msg = bridge.i18n && bridge.i18n.tostartConfirm;
                    if (msg && !window.confirm(msg)) {
                        // Drag rueckgaengig machen ueber Reload — Daten sind serverseitig nicht veraendert.
                        if (bridge.reloadUrl) window.location.href = bridge.reloadUrl;
                        return;
                    }
                    convertToStartarticle(bridge, artId);
                }
            });
        });
    }

    function convertToStartarticle(bridge, articleId) {
        var fd = new FormData();
        fd.append('article_id', articleId);
        fd.append('clang', bridge.clang);
        if (bridge.csrf) {
            Object.keys(bridge.csrf).forEach(function (k) { fd.append(k, bridge.csrf[k]); });
        }
        fetch(bridge.toStartUrl, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) {
            if (!r.ok) throw new Error('http ' + r.status);
            showToast(bridge, true, bridge.i18n && bridge.i18n.tostartOk);
            // Reload, da sich Metadaten/Reihenfolge aendern.
            setTimeout(function () {
                if (bridge.reloadUrl) window.location.href = bridge.reloadUrl;
                else window.location.reload();
            }, 400);
        }).catch(function () {
            showToast(bridge, false, bridge.i18n && bridge.i18n.tostartFailed);
            if (bridge.reloadUrl) window.location.href = bridge.reloadUrl;
        });
    }

    function initSortable(bridge) {
        if (!bridge) return;
        // Categories: every <ul.rex-sr-tree> ist eigene Drop-Zone (geteilt nach parent_id)
        document.querySelectorAll('ul.rex-sr-tree').forEach(function (ul) {
            initSortableForList(ul, 'cat', bridge);
        });
        // Articles: tbody[data-sortable="articles"] in der Tabelle.
        // Nur wenn DnD aktiv (Sort=priority asc).
        document.querySelectorAll('tbody[data-sortable="articles"]').forEach(function (tb) {
            var table = tb.closest('table');
            if (table && table.getAttribute('data-dnd-active') !== '1') return;
            initSortableForList(tb, 'art', bridge);
        });
    }

    function autoOpenModal() {
        var wrap = document.getElementById('rex-structure-replace');
        if (!wrap || !window.bootstrap) return;
        var fn = wrap.getAttribute('data-auto-open');
        if (!fn) return;
        var sel = (fn === 'add_cat' || fn === 'edit_cat') ? '#rex-sr-cat-modal' : '#rex-sr-art-modal';
        var el = document.querySelector(sel);
        if (!el) return;
        var modal = window.bootstrap.Modal.getOrCreateInstance(el);
        modal.show();
    }

    function initIframeModal() {
        var modal = document.getElementById('rex-sr-iframe-modal');
        if (!modal) return;
        modal.addEventListener('show.bs.modal', function (ev) {
            var trigger = ev.relatedTarget;
            if (!trigger) return;
            var url = trigger.getAttribute('data-iframe-url');
            var title = trigger.getAttribute('data-modal-title');
            var iframe = modal.querySelector('[data-iframe-target]');
            var titleEl = modal.querySelector('[data-modal-title-target]');
            if (iframe && url) iframe.src = url;
            if (titleEl && title) titleEl.textContent = title;
        });
        modal.addEventListener('hidden.bs.modal', function () {
            var iframe = modal.querySelector('[data-iframe-target]');
            if (iframe) iframe.src = 'about:blank';
        });
    }

    function initMaximize() {
        var wrap = document.getElementById('rex-structure-replace');
        if (!wrap) return;
        wrap.addEventListener('click', function (ev) {
            var btn = ev.target.closest('[data-sr-toggle="maximize"]');
            if (!btn) return;
            ev.preventDefault();
            wrap.classList.toggle('is-maximized');
        });
    }

    function init() {
        var bridge = getBridge();
        initSortable(bridge);
        initStartArticleDropZone(bridge);
        initInlineEdit(bridge);
        initTreeChevron();
        autoOpenModal();
        initIframeModal();
        initDuplicateModal();
        initMaximize();
        initSplitter();
        initBulk(bridge);
    }

    function initBulk(bridge) {
        if (!bridge || !bridge.bulkUrl) return;
        var wrap = document.getElementById('rex-structure-replace');
        if (!wrap || wrap.dataset.srBulkBound === '1') return;
        wrap.dataset.srBulkBound = '1';

        var bulkbar = wrap.querySelector('[data-rex-sr-bulkbar]');
        var countEl = wrap.querySelector('[data-rex-sr-bulkcount]');
        var toggleAll = wrap.querySelector('[data-rex-sr-bulk-toggle-all]');
        if (!bulkbar) return;

        function getRowChecks() {
            return Array.from(wrap.querySelectorAll('[data-rex-sr-bulk-row]'));
        }
        function getSelected() {
            return getRowChecks().filter(function (cb) { return cb.checked; });
        }
        function refresh() {
            var sel = getSelected();
            var n = sel.length;
            if (countEl) {
                var tpl = (bridge.i18n && bridge.i18n.bulkSelected) || '{n} selected';
                countEl.textContent = tpl.replace('{n}', String(n));
            }
            bulkbar.classList.toggle('is-active', n > 0);
            // Zeile visuell markieren
            getRowChecks().forEach(function (cb) {
                var tr = cb.closest('tr');
                if (tr) tr.classList.toggle('is-bulk-selected', cb.checked);
            });
            if (toggleAll) {
                var all = getRowChecks();
                toggleAll.checked = n > 0 && n === all.length;
                toggleAll.indeterminate = n > 0 && n < all.length;
            }
        }
        function clearSelection() {
            getRowChecks().forEach(function (cb) { cb.checked = false; });
            refresh();
        }

        wrap.addEventListener('change', function (ev) {
            var cb = ev.target.closest('[data-rex-sr-bulk-row]');
            if (cb) { refresh(); return; }
            if (ev.target === toggleAll) {
                var checked = !!toggleAll.checked;
                getRowChecks().forEach(function (c) { c.checked = checked; });
                refresh();
            }
        });

        wrap.addEventListener('click', function (ev) {
            var btn = ev.target.closest('[data-rex-sr-bulk]');
            if (!btn) return;
            ev.preventDefault();
            var action = btn.getAttribute('data-rex-sr-bulk');
            if (action === 'clear') { clearSelection(); return; }
            var ids = getSelected().map(function (cb) {
                return parseInt(cb.getAttribute('data-art-id'), 10);
            }).filter(function (n) { return n > 0; });
            if (ids.length === 0) return;

            if (action === 'delete') {
                var tpl = (bridge.i18n && bridge.i18n.bulkDeleteConfirm) || 'Delete {n} items?';
                if (!window.confirm(tpl.replace('{n}', String(ids.length)))) return;
                postBulk(bridge, 'art', 'delete', ids).then(function (res) {
                    showToast(bridge, !!res.ok);
                    if (res.failed && res.failed.length && bridge.i18n && bridge.i18n.bulkSomeFailed) {
                        flashError(bridge.i18n.bulkSomeFailed + ' (' + res.failed.join(', ') + ')');
                    }
                    setTimeout(function () {
                        if (bridge.reloadUrl) window.location.href = bridge.reloadUrl;
                    }, 400);
                }).catch(function () {
                    showToast(bridge, false);
                });
                return;
            }
            if (action === 'status') {
                var status = parseInt(btn.getAttribute('data-rex-sr-status'), 10);
                if (isNaN(status)) return;
                postBulk(bridge, 'art', 'status', ids, { status: status }).then(function (res) {
                    showToast(bridge, !!res.ok);
                    setTimeout(function () {
                        if (bridge.reloadUrl) window.location.href = bridge.reloadUrl;
                    }, 400);
                }).catch(function () {
                    showToast(bridge, false);
                });
            }
        });

        refresh();
    }

    function initSplitter() {
        var wrap = document.getElementById('rex-structure-replace');
        if (!wrap || wrap.dataset.srSplitterBound === '1') return;
        wrap.dataset.srSplitterBound = '1';

        var splitter = wrap.querySelector('.rex-sr-splitter');
        if (!splitter) return;

        var STORAGE_KEY = 'rex_structure_replace_sidebar_width';
        var COLLAPSE_THRESHOLD_REM = 9;
        var MIN_REM = 3;
        var MAX_PX_FACTOR = 0.6;
        var rem = parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;

        function applyWidth(px, persist) {
            var minPx = MIN_REM * rem;
            var maxPx = wrap.clientWidth * MAX_PX_FACTOR;
            if (px < minPx) px = minPx;
            if (px > maxPx) px = maxPx;
            wrap.style.setProperty('--rex-sr-sidebar-width', px + 'px');
            wrap.classList.toggle('is-narrow', px < COLLAPSE_THRESHOLD_REM * rem);
            if (persist) {
                try { localStorage.setItem(STORAGE_KEY, String(Math.round(px))); } catch (e) {}
            }
        }

        // Initialwert aus localStorage
        try {
            var saved = parseInt(localStorage.getItem(STORAGE_KEY) || '', 10);
            if (saved > 0) applyWidth(saved, false);
            else applyWidth(22 * rem, false);
        } catch (e) { applyWidth(22 * rem, false); }

        var dragging = false;
        var startX = 0;
        var startW = 0;

        function onMove(ev) {
            if (!dragging) return;
            var x = ev.touches ? ev.touches[0].clientX : ev.clientX;
            var rect = wrap.getBoundingClientRect();
            applyWidth(x - rect.left, false);
            ev.preventDefault();
        }
        function onUp() {
            if (!dragging) return;
            dragging = false;
            splitter.classList.remove('is-dragging');
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            // persist
            var px = parseFloat(getComputedStyle(wrap).getPropertyValue('--rex-sr-sidebar-width')) || 0;
            try { localStorage.setItem(STORAGE_KEY, String(Math.round(px))); } catch (e) {}
            window.removeEventListener('mousemove', onMove);
            window.removeEventListener('mouseup', onUp);
            window.removeEventListener('touchmove', onMove);
            window.removeEventListener('touchend', onUp);
        }
        function onDown(ev) {
            dragging = true;
            startX = ev.touches ? ev.touches[0].clientX : ev.clientX;
            var rect = wrap.querySelector('.rex-sr-sidebar').getBoundingClientRect();
            startW = rect.width;
            splitter.classList.add('is-dragging');
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
            window.addEventListener('mousemove', onMove);
            window.addEventListener('mouseup', onUp);
            window.addEventListener('touchmove', onMove, { passive: false });
            window.addEventListener('touchend', onUp);
            ev.preventDefault();
        }
        splitter.addEventListener('mousedown', onDown);
        splitter.addEventListener('touchstart', onDown, { passive: false });
        splitter.addEventListener('keydown', function (ev) {
            var step = ev.shiftKey ? 4 * rem : rem;
            var cur = parseFloat(getComputedStyle(wrap).getPropertyValue('--rex-sr-sidebar-width')) || (22 * rem);
            if (ev.key === 'ArrowLeft') { applyWidth(cur - step, true); ev.preventDefault(); }
            else if (ev.key === 'ArrowRight') { applyWidth(cur + step, true); ev.preventDefault(); }
            else if (ev.key === 'Home') { applyWidth(MIN_REM * rem, true); ev.preventDefault(); }
            else if (ev.key === 'End') { applyWidth(22 * rem, true); ev.preventDefault(); }
        });
        // Doppelklick: zwischen kollabiert und Default toggeln
        splitter.addEventListener('dblclick', function () {
            var cur = parseFloat(getComputedStyle(wrap).getPropertyValue('--rex-sr-sidebar-width')) || 0;
            if (cur < COLLAPSE_THRESHOLD_REM * rem) applyWidth(22 * rem, true);
            else applyWidth(MIN_REM * rem, true);
        });
    }

    function initTreeChevron() {
        // Bootstrap-Collapse-Events steuern Chevron-Rotation und LI-Klasse
        document.querySelectorAll('.rex-sr-sidebar .collapse').forEach(function (col) {
            if (col.dataset.srBound === '1') return;
            col.dataset.srBound = '1';
            col.addEventListener('show.bs.collapse', function () {
                var li = col.closest('.rex-sr-tree-item');
                if (li) li.classList.add('is-expanded');
            });
            col.addEventListener('hide.bs.collapse', function () {
                var li = col.closest('.rex-sr-tree-item');
                if (li) li.classList.remove('is-expanded');
            });
        });
    }

    function initDuplicateModal() {
        var modal = document.getElementById('rex-sr-dup-cat-modal');
        if (!modal || modal.dataset.srBound === '1') return;
        modal.dataset.srBound = '1';
        modal.addEventListener('show.bs.modal', function (ev) {
            var trigger = ev.relatedTarget;
            if (!trigger) return;
            var srcId = trigger.getAttribute('data-source-id') || '0';
            var srcName = trigger.getAttribute('data-source-name') || '';
            var idInput = modal.querySelector('[data-source-id-target]');
            if (idInput) idInput.value = srcId;
            var nameSpan = modal.querySelector('[data-source-name]');
            if (nameSpan) nameSpan.textContent = srcName ? '— ' + srcName + ' (#' + srcId + ')' : '#' + srcId;
            var nameField = modal.querySelector('#rex-sr-dup-newname');
            if (nameField) nameField.value = '';
        });
    }

    function initInlineEdit(bridge) {
        if (!bridge) return;
        document.querySelectorAll('.rex-sr-inline').forEach(function (el) {
            if (el.dataset.srBound === '1') return;
            el.dataset.srBound = '1';
            var kind = el.getAttribute('data-sr-inline');
            var field = el.getAttribute('data-sr-field');
            var id = el.getAttribute('data-sr-id');
            var row = el.closest('[data-clang]');
            var clang = row ? row.getAttribute('data-clang') : (bridge.clang);
            var lastValue = el.value;

            var save = function () {
                if (el.value === lastValue) return;
                var val = el.value;
                postUpdate(bridge, kind, id, field, val, clang).then(function (r) {
                    if (!r.ok) throw new Error('http ' + r.status);
                    return r.text();
                }).then(function () {
                    lastValue = val;
                    flashStatus(el, true);
                    showToast(bridge, true);
                }).catch(function () {
                    el.value = lastValue;
                    flashStatus(el, false);
                    showToast(bridge, false);
                });
            };

            if (el.tagName === 'SELECT') {
                el.addEventListener('change', save);
            } else {
                el.addEventListener('blur', save);
                el.addEventListener('keydown', function (ev) {
                    if (ev.key === 'Enter') { ev.preventDefault(); el.blur(); }
                    if (ev.key === 'Escape') { el.value = lastValue; el.blur(); }
                });
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    document.addEventListener('rex:ready', init); // PJAX
})();
