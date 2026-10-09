// GreenLoop Net Zero — สคริปต์ฝั่งผู้ใช้ (ไม่พึ่งไลบรารีอื่นนอกจาก Bootstrap และ Chart.js)
(function () {
  'use strict';

  var PALETTE = ['#0f7b3f', '#3fae5a', '#8fd19e', '#f0ad1c', '#2b8cbe', '#9b6bd3', '#e0663c'];

  // ---------- กราฟ: <canvas data-chart='{"type","labels","data","label"}'> ----------
  var charts = [];
  document.querySelectorAll('canvas[data-chart]').forEach(function (el) {
    if (typeof Chart === 'undefined') { return; }
    var c = JSON.parse(el.getAttribute('data-chart'));
    var round = c.type === 'doughnut' || c.type === 'pie';
    charts.push(new Chart(el, {
      type: c.type,
      data: {
        labels: c.labels,
        datasets: [{
          label: c.label,
          data: c.data,
          backgroundColor: round ? PALETTE : (c.type === 'line' ? 'rgba(63,174,90,.2)' : '#3fae5a'),
          borderColor: round ? '#fff' : '#0f7b3f',
          borderWidth: c.type === 'line' ? 2 : (round ? 2 : 0),
          borderRadius: c.type === 'bar' ? 6 : 0,
          fill: c.type === 'line',
          tension: .3
        }]
      },
      options: {
        responsive: true,
        animation: false,
        maintainAspectRatio: false,
        plugins: { legend: { display: round || !!c.label, position: 'bottom' } },
        scales: round ? {} : { y: { beginAtZero: true } }
      }
    }));
  });
  // กราฟต้องวาดใหม่ให้พอดีความกว้างกระดาษ A4 ก่อนพิมพ์
  window.addEventListener('beforeprint', function () { charts.forEach(function (c) { c.resize(620, 250); }); });
  window.addEventListener('afterprint', function () { charts.forEach(function (c) { c.resize(); }); });

  // ---------- ตรวจสอบฟอร์มก่อนส่ง (Bootstrap validation) ----------
  document.querySelectorAll('form.needs-validation').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      var pw = form.querySelector('[data-match]');
      if (pw) {
        var other = form.querySelector(pw.getAttribute('data-match'));
        pw.setCustomValidity(other && other.value !== pw.value ? 'mismatch' : '');
      }
      if (!form.checkValidity()) {
        ev.preventDefault();
        ev.stopPropagation();
      }
      form.classList.add('was-validated');
    });
  });

  // ---------- ยืนยันก่อนทำรายการสำคัญ ----------
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener(el.tagName === 'FORM' ? 'submit' : 'click', function (ev) {
      if (!window.confirm(el.getAttribute('data-confirm'))) { ev.preventDefault(); }
    });
  });

  // ---------- ค้นหาในตารางแบบทันที: <input data-filter="#tableId"> ----------
  document.querySelectorAll('input[data-filter]').forEach(function (input) {
    var table = document.querySelector(input.getAttribute('data-filter'));
    if (!table) { return; }
    input.addEventListener('input', function () {
      var kw = input.value.trim().toLowerCase();
      table.querySelectorAll('tbody tr').forEach(function (tr) {
        tr.style.display = tr.textContent.toLowerCase().indexOf(kw) === -1 ? 'none' : '';
      });
    });
  });

  // ---------- เรียงลำดับตารางเมื่อคลิกหัวคอลัมน์: <th data-sort> ----------
  function cellValue(tr, i) {
    var td = tr.children[i];
    if (!td) { return ''; }
    return td.hasAttribute('data-value') ? td.getAttribute('data-value') : td.textContent.trim();
  }
  document.querySelectorAll('th[data-sort]').forEach(function (th) {
    th.addEventListener('click', function () {
      var tbody = th.closest('table').querySelector('tbody');
      var i = Array.prototype.indexOf.call(th.parentNode.children, th);
      var asc = !th.classList.contains('asc');
      th.parentNode.querySelectorAll('th').forEach(function (o) { o.classList.remove('asc', 'desc'); });
      th.classList.add(asc ? 'asc' : 'desc');
      Array.prototype.slice.call(tbody.querySelectorAll('tr')).sort(function (a, b) {
        var x = cellValue(a, i), y = cellValue(b, i);
        var nx = parseFloat(x.replace(/,/g, '')), ny = parseFloat(y.replace(/,/g, ''));
        var r = (!isNaN(nx) && !isNaN(ny) && th.getAttribute('data-sort') === 'num') ? nx - ny : x.localeCompare(y, 'th');
        return asc ? r : -r;
      }).forEach(function (tr) { tbody.appendChild(tr); });
    });
  });

  // ---------- คำนวณคาร์บอนฟุตพริ้นท์แบบทันทีระหว่างกรอก ----------
  var fp = document.getElementById('footprint-form');
  if (fp) {
    var out = document.getElementById('fp-live-total');
    var outTon = document.getElementById('fp-live-ton');
    var update = function () {
      var total = 0;
      fp.querySelectorAll('input[data-factor]').forEach(function (inp) {
        var v = parseFloat(inp.value);
        if (!isNaN(v) && v > 0) {
          total += v * parseFloat(inp.getAttribute('data-factor')) * parseFloat(inp.getAttribute('data-periods'));
        }
      });
      out.textContent = total.toLocaleString('th-TH', { maximumFractionDigits: 0 });
      outTon.textContent = (total / 1000).toLocaleString('th-TH', { maximumFractionDigits: 2 });
    };
    fp.addEventListener('input', update);
    update();
  }

  // ---------- แสดงตัวอย่างรูปก่อนอัปโหลด ----------
  document.querySelectorAll('input[type=file][data-preview]').forEach(function (inp) {
    inp.addEventListener('change', function () {
      var img = document.querySelector(inp.getAttribute('data-preview'));
      if (img && inp.files && inp.files[0]) {
        img.src = URL.createObjectURL(inp.files[0]);
        img.classList.remove('d-none');
      }
    });
  });
})();
