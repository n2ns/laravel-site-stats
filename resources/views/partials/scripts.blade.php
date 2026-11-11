<script>
(function () {
  var root = document.querySelector('.stats-dash');
  if (!root) return;
  root.classList.add('js');
  var each = function (selector, fn) { Array.prototype.forEach.call(root.querySelectorAll(selector), fn); };

  each('form[data-autosubmit] select', function (s) {
    s.addEventListener('change', function () { s.form.submit(); });
  });
  var select = document.getElementById('range-select'), custom = document.getElementById('custom-range');
  if (select && custom) {
    var toggle = function () { var on = select.value === 'custom'; custom.hidden = !on; custom.querySelectorAll('input').forEach(function (i) { i.disabled = !on; }); };
    select.addEventListener('change', function () { toggle(); if (select.value !== 'custom') select.form.submit(); });
    toggle();
  }

  // Tabs (arrow keys, Home and End move between them).
  var list = root.querySelector('[role=tablist]');
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
        var next = e.key === 'ArrowRight' ? tabs[(i + 1) % tabs.length] : e.key === 'ArrowLeft' ? tabs[(i - 1 + tabs.length) % tabs.length]
          : e.key === 'Home' ? tabs[0] : e.key === 'End' ? tabs[tabs.length - 1] : null;
        if (next) { e.preventDefault(); show(next); next.focus(); }
      });
    });
    list.hidden = false;
    show(tabs[0]);
  }

  // Compact report tables show their top rows; "show all" reveals the rest (without JS every row shows).
  var markTop = function (table) {
    var top = parseInt(table.getAttribute('data-top'), 10);
    Array.prototype.forEach.call(table.tBodies[0].rows, function (row, i) { row.classList.toggle('more', i >= top); });
  };
  each('.report .more-toggle', function (button) {
    var card = button.closest('.report');
    button.hidden = false;
    button.addEventListener('click', function () {
      var open = !card.classList.contains('expanded');
      card.classList.toggle('expanded', open);
      button.setAttribute('aria-expanded', open ? 'true' : 'false');
      button.textContent = button.getAttribute(open ? 'data-less' : 'data-more');
    });
  });

  // Sortable tables: numeric cells carry data-value; the server order stays until a header is clicked.
  var sortLabel = @json(__('stats::dashboard.sort', ['column' => '%s']));
  each('table[data-sortable]', function (table) {
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
            var xv = parseFloat(x.getAttribute('data-value')), yv = parseFloat(y.getAttribute('data-value'));
            if (isNaN(xv) || isNaN(yv)) return isNaN(xv) - isNaN(yv); // cells without a value (shown as –) stay last
            r = xv - yv;
          } else {
            r = x.textContent.localeCompare(y.textContent);
          }
          return asc ? r : -r;
        });
        rows.forEach(function (row) { body.appendChild(row); });
        if (table.hasAttribute('data-top')) markTop(table);
      });
    });
  });

  // Chart tooltips: pointer hover, or focus the plot and use the arrow keys (Home, End, Escape).
  var number = new Intl.NumberFormat('en-US');
  each('.chart[data-chart]', function (chart) {
    var data = JSON.parse(chart.getAttribute('data-chart'));
    var count = data.dates.length;
    var plot = chart.querySelector('.plot'), tip = plot.querySelector('.tip'), guide = plot.querySelector('.guide'), dot = plot.querySelector('.dot');
    if (!count) return;
    var index = count - 1;
    var selected = function () { var r = chart.querySelector('.metric-radio:checked'); return r ? r.value : Object.keys(data.metrics)[0]; };
    var x = function (i) { return count > 1 ? i / (count - 1) * 100 : 50; };
    var render = function (i) {
      index = i;
      var metric = selected(), m = data.metrics[metric];
      var left = x(i);
      guide.style.left = left + '%';
      dot.style.left = left + '%';
      dot.style.top = (100 - m.values[i] / m.top * 100) + '%';
      tip.textContent = '';
      var day = document.createElement('div');
      day.className = 'day';
      day.textContent = data.dates[i];
      var dl = document.createElement('dl');
      Object.keys(data.metrics).forEach(function (key) {
        var dt = document.createElement('dt'), dd = document.createElement('dd');
        dt.textContent = data.metrics[key].label;
        dd.textContent = number.format(data.metrics[key].values[i]);
        if (key === metric) { dt.className = 'on'; dd.className = 'on'; }
        dl.appendChild(dt); dl.appendChild(dd);
      });
      tip.appendChild(day);
      tip.appendChild(dl);
      guide.hidden = dot.hidden = tip.hidden = false;
      // Keep the tooltip inside the plot: right of the guide in the left half, left of it in the right half.
      if (left > 50) { tip.style.left = ''; tip.style.right = 'calc(' + (100 - left) + '% + 12px)'; }
      else { tip.style.right = ''; tip.style.left = 'calc(' + left + '% + 12px)'; }
    };
    var hide = function () { guide.hidden = dot.hidden = tip.hidden = true; };
    var fromPointer = function (e) {
      var box = plot.getBoundingClientRect();
      var ratio = Math.min(1, Math.max(0, (e.clientX - box.left) / box.width));
      render(count > 1 ? Math.round(ratio * (count - 1)) : 0);
    };
    plot.addEventListener('pointermove', fromPointer);
    plot.addEventListener('pointerdown', fromPointer);
    plot.addEventListener('pointerleave', function () { if (document.activeElement !== plot) hide(); });
    plot.addEventListener('focus', function () { render(index); });
    plot.addEventListener('blur', hide);
    plot.addEventListener('keydown', function (e) {
      var next = e.key === 'ArrowRight' ? index + 1 : e.key === 'ArrowLeft' ? index - 1 : e.key === 'Home' ? 0 : e.key === 'End' ? count - 1 : null;
      if (e.key === 'Escape') { hide(); return; }
      if (next === null) return;
      e.preventDefault();
      render(Math.min(count - 1, Math.max(0, next)));
    });
    Array.prototype.forEach.call(chart.querySelectorAll('.metric-radio'), function (radio) {
      radio.addEventListener('change', function () { if (!tip.hidden) render(index); });
    });
  });
})();
</script>
