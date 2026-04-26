/* F1 Management System — Main JS */

// ============================================================ AUTO-DISMISS ALERTS
document.addEventListener('DOMContentLoaded', function() {
    const flash = document.getElementById('flash-msg');
    if (flash && flash.classList.contains('alert-success')) {
        setTimeout(() => {
            flash.style.transition = 'opacity 0.5s';
            flash.style.opacity = '0';
            setTimeout(() => flash.remove(), 500);
        }, 4000);
    }
});

// ============================================================ SIDEBAR TOGGLE
document.addEventListener('DOMContentLoaded', function() {
    const toggle  = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const main    = document.querySelector('.main-content');

    if (toggle && sidebar) {
        toggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            sidebar.classList.toggle('open');
            if (main) main.classList.toggle('sidebar-collapsed');
        });
    }
});

// ============================================================ TABLE SORT
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('table.sortable').forEach(function(table) {
        const headers = table.querySelectorAll('thead th[data-sort]');
        let sortCol = null, sortDir = 1;

        headers.forEach(function(th, idx) {
            th.style.cursor = 'pointer';
            th.addEventListener('click', function() {
                const colIdx = parseInt(th.dataset.sort ?? idx);
                if (sortCol === colIdx) {
                    sortDir = -sortDir;
                } else {
                    sortCol = colIdx;
                    sortDir = 1;
                }

                headers.forEach(h => { h.classList.remove('sort-asc', 'sort-desc'); });
                th.classList.add(sortDir === 1 ? 'sort-asc' : 'sort-desc');

                const tbody = table.querySelector('tbody');
                const rows  = Array.from(tbody.querySelectorAll('tr'));

                rows.sort(function(a, b) {
                    const aText = (a.cells[colIdx]?.textContent ?? '').trim();
                    const bText = (b.cells[colIdx]?.textContent ?? '').trim();
                    const aNum  = parseFloat(aText.replace(/[^0-9.-]/g, ''));
                    const bNum  = parseFloat(bText.replace(/[^0-9.-]/g, ''));

                    if (!isNaN(aNum) && !isNaN(bNum)) {
                        return (aNum - bNum) * sortDir;
                    }
                    return aText.localeCompare(bText) * sortDir;
                });

                rows.forEach(r => tbody.appendChild(r));
            });
        });
    });
});

// ============================================================ TABLE SEARCH/FILTER
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.table-search-input').forEach(function(input) {
        const tableId = input.dataset.table;
        const table   = document.getElementById(tableId);
        if (!table) return;

        input.addEventListener('input', function() {
            const q    = input.value.toLowerCase();
            const rows = table.querySelectorAll('tbody tr');
            let visible = 0;

            rows.forEach(function(row) {
                const text = row.textContent.toLowerCase();
                const show = text.includes(q);
                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });

            // Update empty state
            let empty = table.querySelector('.empty-row');
            if (!visible) {
                if (!empty) {
                    empty = document.createElement('tr');
                    empty.className = 'empty-row';
                    const cells = table.querySelectorAll('thead th').length;
                    empty.innerHTML = `<td colspan="${cells}" class="text-center" style="padding:2rem;color:var(--text-muted)">No results found.</td>`;
                    table.querySelector('tbody').appendChild(empty);
                }
                empty.style.display = '';
            } else if (empty) {
                empty.style.display = 'none';
            }

            // Update pagination count if present
            const counter = document.getElementById(tableId + '-count');
            if (counter) counter.textContent = visible;
        });
    });
});

// ============================================================ PAGINATION
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.paginated-table').forEach(function(table) {
        const perPage  = parseInt(table.dataset.perPage ?? 20);
        const tbody    = table.querySelector('tbody');
        if (!tbody) return;

        let currentPage = 1;
        const allRows   = Array.from(tbody.querySelectorAll('tr:not(.empty-row)'));
        const total     = allRows.length;
        const pages     = Math.max(1, Math.ceil(total / perPage));

        const containerId = table.dataset.pagination;
        const container   = containerId ? document.getElementById(containerId) : null;

        function render() {
            const start = (currentPage - 1) * perPage;
            const end   = start + perPage;

            allRows.forEach(function(row, idx) {
                row.style.display = (idx >= start && idx < end) ? '' : 'none';
            });

            if (container) {
                const info = container.querySelector('.page-info');
                if (info) info.textContent = `Page ${currentPage} of ${pages} (${total} total)`;

                const prevBtn = container.querySelector('.prev-page');
                const nextBtn = container.querySelector('.next-page');
                if (prevBtn) prevBtn.disabled = currentPage <= 1;
                if (nextBtn) nextBtn.disabled = currentPage >= pages;
            }
        }

        if (container) {
            container.querySelector('.prev-page')?.addEventListener('click', function() {
                if (currentPage > 1) { currentPage--; render(); }
            });
            container.querySelector('.next-page')?.addEventListener('click', function() {
                if (currentPage < pages) { currentPage++; render(); }
            });
        }

        if (total > perPage) render();
    });
});

// ============================================================ CONFIRM DIALOG
function confirmAction(message, callback) {
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.innerHTML = `
        <div class="modal">
            <div class="modal-title">Confirm Action</div>
            <div class="modal-body">${escapeHtml(message)}</div>
            <div class="modal-actions">
                <button class="btn btn-outline" id="confirmNo">Cancel</button>
                <button class="btn btn-danger" id="confirmYes">Confirm</button>
            </div>
        </div>`;
    document.body.appendChild(overlay);

    overlay.querySelector('#confirmNo').addEventListener('click', function() {
        overlay.remove();
    });
    overlay.querySelector('#confirmYes').addEventListener('click', function() {
        overlay.remove();
        callback();
    });

    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) overlay.remove();
    });
}

// Wire up all delete/danger forms with data-confirm attribute
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('form[data-confirm]').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const msg = form.dataset.confirm || 'Are you sure?';
            confirmAction(msg, function() { form.submit(); });
        });
    });

    // Also wire buttons with data-confirm
    document.querySelectorAll('button[data-confirm], a[data-confirm]').forEach(function(el) {
        el.addEventListener('click', function(e) {
            e.preventDefault();
            const msg = el.dataset.confirm;
            const href = el.getAttribute('href');
            const form = el.closest('form');

            confirmAction(msg, function() {
                if (href) window.location.href = href;
                else if (form) form.submit();
            });
        });
    });
});

// ============================================================ CSV DOWNLOAD
function downloadCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;

    const rows = table.querySelectorAll('tr');
    const csv  = [];

    rows.forEach(function(row) {
        const cells = row.querySelectorAll('th, td');
        const line  = Array.from(cells).map(function(cell) {
            let text = cell.textContent.trim().replace(/\s+/g, ' ');
            if (text.includes(',') || text.includes('"') || text.includes('\n')) {
                text = '"' + text.replace(/"/g, '""') + '"';
            }
            return text;
        });
        csv.push(line.join(','));
    });

    const blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = filename || 'export.csv';
    a.click();
    URL.revokeObjectURL(url);
}

// Wire up CSV export buttons
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-export-csv]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const tableId  = btn.dataset.exportCsv;
            const filename = btn.dataset.filename || 'export.csv';
            downloadCSV(tableId, filename);
        });
    });
});

// ============================================================ CLICKABLE ROWS
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('tr.clickable-row').forEach(function(row) {
        const href = row.dataset.href;
        if (!href) return;
        row.style.cursor = 'pointer';
        row.addEventListener('click', function(e) {
            // Skip if click is inside a no-row-click cell, a link, button, or form
            if (e.target.closest('.no-row-click, a, button, form, input, select, textarea')) return;
            window.location.href = href;
        });
    });
});

// ============================================================ HELPERS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}

// Season selector auto-submit
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.season-selector select').forEach(function(sel) {
        sel.addEventListener('change', function() {
            sel.closest('form').submit();
        });
    });
});
