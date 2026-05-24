/**
 * File: modules/old_data_archive/old-data.js
 * Old Data Archive - AJAX, search, filter, pagination, download
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', () => init());

    function init() {
        wireEvents();
        loadCards();
        loadTableData();
    }

    function wireEvents() {
        const sessionSelect = document.getElementById('sessionSelect');
        const searchInput = document.getElementById('searchInput');
        const downloadBtn = document.getElementById('downloadBtn');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');

        if (sessionSelect) sessionSelect.addEventListener('change', onSessionChange);
        if (searchInput) searchInput.addEventListener('input', debounce(onSearch, 300));
        document.querySelectorAll('.filter-tab').forEach((tab) => tab.addEventListener('click', onFilterChange));
        if (downloadBtn) downloadBtn.addEventListener('click', onDownload);
        if (prevBtn) prevBtn.addEventListener('click', onPrev);
        if (nextBtn) nextBtn.addEventListener('click', onNext);
    }

    function onSessionChange(e) {
        state.currentSession = (e.target.value || '').trim();
        state.currentPage = 0;
        state.searchQuery = '';
        const searchInput = document.getElementById('searchInput');
        if (searchInput) searchInput.value = '';

        document.querySelectorAll('.filter-tab').forEach((tab) => tab.classList.remove('active'));
        const allTab = document.querySelector('.filter-tab[data-type="all"]');
        if (allTab) allTab.classList.add('active');
        state.currentFilter = 'all';

        loadCards();
        loadTableData();
    }

    function onFilterChange(e) {
        const btn = e.currentTarget;
        state.currentFilter = (btn.dataset.type || 'all').trim();
        state.currentPage = 0;
        document.querySelectorAll('.filter-tab').forEach((tab) => tab.classList.remove('active'));
        btn.classList.add('active');
        loadTableData();
    }

    function onSearch(e) {
        state.searchQuery = (e.target.value || '').trim();
        state.currentPage = 0;
        loadTableData();
    }

    function onPrev() {
        if (state.currentPage > 0) {
            state.currentPage -= 1;
            loadTableData();
        }
    }

    function onNext() {
        const maxPage = Math.ceil(state.totalRecords / state.recordsPerPage) - 1;
        if (state.currentPage < maxPage) {
            state.currentPage += 1;
            loadTableData();
        }
    }

    function loadCards() {
        const url = new URL('get-session-data.php', window.location.href);
        url.searchParams.set('action', 'cards');
        url.searchParams.set('session', state.currentSession);

        fetch(url.toString(), { headers: { 'Accept': 'application/json' } })
            .then((r) => r.json())
            .then((data) => {
                if (!data || !data.success || !data.cards) {
                    setCardsError();
                    return;
                }
                updateCards(data.cards);
            })
            .catch(() => setCardsError());
    }

    function updateCards(cards) {
        const mapping = {
            students: 'students',
            attendance: 'attendance',
            fee: 'fee',
            financial: 'financial',
            exam: 'exam',
        };

        Object.entries(mapping).forEach(([key, slot]) => {
            const el = document.querySelector(`[data-card="${slot}"]`);
            if (!el) return;
            el.textContent = String(cards[key] ?? 0);
        });
    }

    function setCardsError() {
        document.querySelectorAll('.card-value').forEach((el) => {
            el.textContent = '—';
        });
    }

    function loadTableData() {
        const url = new URL('get-session-data.php', window.location.href);
        url.searchParams.set('action', 'table');
        url.searchParams.set('session', state.currentSession);
        url.searchParams.set('page', String(state.currentPage));
        url.searchParams.set('type', state.currentFilter);
        url.searchParams.set('search', state.searchQuery);

        const tbody = document.getElementById('tableBody');
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:32px;"><span class="oda-spinner"></span></td></tr>`;
        }

        fetch(url.toString(), { headers: { 'Accept': 'application/json' } })
            .then((r) => r.json())
            .then((data) => {
                if (!data || !data.success) {
                    renderError();
                    return;
                }
                renderTable(data.records || []);
                updatePager(data.page ?? 0, data.total ?? 0, data.perPage ?? state.recordsPerPage);
            })
            .catch(() => renderError());
    }

    function renderTable(records) {
        const tbody = document.getElementById('tableBody');
        if (!tbody) return;

        if (!records.length) {
            tbody.innerHTML = `
                <tr><td colspan="5">
                    <div class="empty-state">
                        <div class="title">No records found</div>
                        <div class="small">Try adjusting filters or search.</div>
                    </div>
                </td></tr>
            `;
            return;
        }

        tbody.innerHTML = records.map((row) => {
            const typeRaw = String(row.type || '').toLowerCase();
            const allowedTypes = ['student', 'fee', 'attendance', 'exam', 'income', 'expense', 'transaction'];
            const safeType = allowedTypes.includes(typeRaw) ? typeRaw : 'student';
            const type = htmlEscape(typeRaw || '-');
            const nameRef = htmlEscape(row.name_ref || '-');
            const cls = htmlEscape(row.class || '-');
            const amountStatus = htmlEscape(row.amount_status || '-');
            const date = htmlEscape(row.date || '-');
            return `
                <tr>
                    <td><span class="type-badge ${safeType}">${type}</span></td>
                    <td>${nameRef}</td>
                    <td>${cls}</td>
                    <td>${amountStatus}</td>
                    <td>${date}</td>
                </tr>
            `;
        }).join('');
    }

    function renderError() {
        const tbody = document.getElementById('tableBody');
        if (!tbody) return;
        tbody.innerHTML = `
            <tr><td colspan="5">
                <div class="empty-state">
                    <div class="title">Error loading records</div>
                    <div class="small">Please try again.</div>
                </div>
            </td></tr>
        `;
    }

    function updatePager(page, total, perPage) {
        state.totalRecords = total;
        state.recordsPerPage = perPage;

        const recordsInfo = document.getElementById('recordsInfo');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');

        const start = total === 0 ? 0 : page * perPage + 1;
        const end = Math.min((page + 1) * perPage, total);

        if (recordsInfo) {
            recordsInfo.textContent = total === 0 ? 'No records found' : `Showing ${start}-${end} of ${total} records`;
        }
        if (prevBtn) prevBtn.disabled = page <= 0;
        if (nextBtn) nextBtn.disabled = end >= total;
    }

    function onDownload(e) {
        e.preventDefault();
        const btn = document.getElementById('downloadBtn');
        if (!btn) return;

        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<span class="oda-spinner" style="width:16px;height:16px;border-width:2px;"></span> Preparing...`;

        const formData = new FormData();
        formData.append('session', state.currentSession);
        formData.append('csrf_token', CONFIG.csrfToken);

        fetch('download-session.php', { method: 'POST', body: formData })
            .then((r) => {
                if (!r.ok) throw new Error('Download failed');
                return r.blob();
            })
            .then((blob) => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `school_data_${state.currentSession}.zip`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            })
            .catch(() => {
                // Silent: UI restored below
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = original;
            });
    }

    function htmlEscape(text) {
        const div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function debounce(fn, waitMs) {
        let t;
        return function (...args) {
            clearTimeout(t);
            t = setTimeout(() => fn.apply(this, args), waitMs);
        };
    }
})();
