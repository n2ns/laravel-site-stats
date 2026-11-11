<script>
(function () {
  document.querySelectorAll('form[data-autosubmit] select').forEach(function (s) {
    s.addEventListener('change', function () { s.form.submit(); });
  });
  var select = document.getElementById('range-select'), custom = document.getElementById('custom-range');
  if (select && custom) {
    var toggle = function () { var on = select.value === 'custom'; custom.hidden = !on; custom.querySelectorAll('input').forEach(function (i) { i.disabled = !on; }); };
    select.addEventListener('change', function () { toggle(); if (select.value !== 'custom') select.form.submit(); });
    toggle();
  }

  var list = document.querySelector('.stats-dash [role=tablist]');
  if (list) {
    var tabs = Array.prototype.slice.call(list.querySelectorAll('[role=tab]'));
    var show = function (tab) {
      tabs.forEach(function (t) {
        var on = t === tab;
        t.setAttribute('aria-selected', on ? 'true' : 'false');
        t.tabIndex = on ? 0 : -1;
        document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
      });
    };
    tabs.forEach(function (t, i) {
      t.addEventListener('click', function () { show(t); });
      t.addEventListener('keydown', function (e) {
        var n = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
        if (n) { var next = tabs[(i + n + tabs.length) % tabs.length]; show(next); next.focus(); }
      });
    });
    list.hidden = false;
    show(tabs[0]);
  }

  // Sortable tables: numeric cells carry data-value; the server order stays until a header is clicked.
  var sortLabel = @json(__('stats::dashboard.sort', ['column' => '%s']));
  document.querySelectorAll('.stats-dash table[data-sortable]').forEach(function (table) {
    var headers = Array.prototype.slice.call(table.tHead.rows[0].cells);
    headers.forEach(function (th, col) {
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'sort';
      button.textContent = th.textContent;
      button.setAttribute('aria-label', sortLabel.replace('%s', th.textContent));
      th.textContent = '';
      th.appendChild(button);
      button.addEventListener('click', function () {
        var asc = th.getAttribute('aria-sort') === 'descending';
        headers.forEach(function (h) { h.removeAttribute('aria-sort'); });
        th.setAttribute('aria-sort', asc ? 'ascending' : 'descending');
        var body = table.tBodies[0];
        var rows = Array.prototype.slice.call(body.rows);
        rows.sort(function (a, b) {
          var x = a.cells[col], y = b.cells[col], r;
          if (x.hasAttribute('data-value')) {
            r = parseFloat(x.getAttribute('data-value')) - parseFloat(y.getAttribute('data-value'));
          } else {
            r = x.textContent.localeCompare(y.textContent);
          }
          return asc ? r : -r;
        });
        rows.forEach(function (row) { body.appendChild(row); });
      });
    });
  });
})();
</script>
