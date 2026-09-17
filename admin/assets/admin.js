(function () {
  'use strict';

  // ---- sidebar drawer (mobile) ----
  var sidebar = document.querySelector('.adm-sidebar');
  var backdrop = document.querySelector('.adm-sidebar-backdrop');
  function openSidebar() { if (sidebar) sidebar.classList.add('open'); if (backdrop) backdrop.classList.add('open'); }
  function closeSidebar() { if (sidebar) sidebar.classList.remove('open'); if (backdrop) backdrop.classList.remove('open'); }
  document.querySelectorAll('[data-adm-open-sidebar]').forEach(function (b) { b.addEventListener('click', openSidebar); });
  document.querySelectorAll('[data-adm-close-sidebar]').forEach(function (b) { b.addEventListener('click', closeSidebar); });
  if (backdrop) backdrop.addEventListener('click', closeSidebar);

  // ---- toast ----
  window.admToast = function (msg, type) {
    var existing = document.querySelectorAll('.adm-toast');
    existing.forEach(function (t) { t.remove(); });
    var el = document.createElement('div');
    el.className = 'adm-toast' + (type === 'error' ? ' error' : '');
    el.innerHTML = (type === 'error' ? '' : '<span class="chk">&#10003;</span>') + '<span>' + msg + '</span>';
    document.body.appendChild(el);
    setTimeout(function () { el.remove(); }, 4200);
  };

  // Auto-show a toast for a server-rendered flash message, if present.
  var flashEl = document.querySelector('[data-adm-flash]');
  if (flashEl) {
    window.admToast(flashEl.getAttribute('data-msg'), flashEl.getAttribute('data-type'));
  }

  // ---- generic client-side table search/filter ----
  document.querySelectorAll('[data-adm-table-search]').forEach(function (input) {
    var targetSel = input.getAttribute('data-adm-table-search');
    var table = document.querySelector(targetSel);
    if (!table) return;
    input.addEventListener('input', function () {
      var q = input.value.trim().toLowerCase();
      table.querySelectorAll('tbody tr').forEach(function (row) {
        var hay = row.getAttribute('data-search') || row.textContent;
        row.style.display = hay.toLowerCase().indexOf(q) === -1 ? 'none' : '';
      });
      updateEmptyState(table);
    });
  });

  document.querySelectorAll('[data-adm-table-filter]').forEach(function (select) {
    var targetSel = select.getAttribute('data-adm-table-filter');
    var table = document.querySelector(targetSel);
    if (!table) return;
    select.addEventListener('change', function () {
      var q = select.value;
      table.querySelectorAll('tbody tr').forEach(function (row) {
        var val = row.getAttribute('data-status') || '';
        row.style.display = (q === '' || q === 'all' || val === q) ? '' : 'none';
      });
      updateEmptyState(table);
    });
  });

  function updateEmptyState(table) {
    var emptyRow = table.querySelector('.adm-js-empty-row');
    var visible = Array.prototype.filter.call(table.querySelectorAll('tbody tr:not(.adm-js-empty-row)'), function (r) { return r.style.display !== 'none'; });
    if (emptyRow) emptyRow.style.display = visible.length ? 'none' : '';
  }
})();
