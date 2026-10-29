/* داریا — اسکریپت‌های عمومی */
(function () {
  'use strict';

  // منوی موبایل
  var toggle = document.getElementById('menuToggle');
  var sidebar = document.getElementById('sidebar');
  if (toggle && sidebar) {
    toggle.addEventListener('click', function () { sidebar.classList.toggle('open'); });
    document.addEventListener('click', function (e) {
      if (sidebar.classList.contains('open') && !sidebar.contains(e.target) && e.target !== toggle) {
        sidebar.classList.remove('open');
      }
    });
  }

  // تأیید حذف
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (f.matches('[data-confirm]')) {
      if (!window.confirm(f.getAttribute('data-confirm') || 'مطمئن هستید؟')) {
        e.preventDefault();
      }
    }
  });

  // قالب‌بندی سه‌رقمی فیلدهای عددی
  document.addEventListener('input', function (e) {
    var el = e.target;
    if (el.matches('[data-money]')) {
      var raw = el.value.replace(/[^\d.]/g, '');
      if (raw !== el.value) {
        var parts = raw.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        el.value = parts.join('.');
      }
    }
  });

  // افزودن ردیف اقلام فاکتور
  var addBtn = document.getElementById('addItemRow');
  if (addBtn) {
    addBtn.addEventListener('click', function () {
      var tpl = document.getElementById('itemRowTemplate');
      var wrap = document.getElementById('itemRows');
      if (tpl && wrap) {
        wrap.insertAdjacentHTML('beforeend', tpl.innerHTML);
      }
    });
  }
  document.addEventListener('click', function (e) {
    if (e.target.matches('.remove-row')) {
      e.preventDefault();
      e.target.closest('.item-row').remove();
      recalcInvoice();
    }
  });
  document.addEventListener('input', function (e) {
    if (e.target.closest('.item-row')) recalcInvoice();
    if (e.target.matches('[data-invoice-discount],[data-invoice-tax],[data-invoice-shipping]')) recalcInvoice();
  });

  function num(v) { return parseFloat(String(v || '0').replace(/[^\d.]/g, '')) || 0; }

  function recalcInvoice() {
    var rows = document.querySelectorAll('.item-row');
    var subtotal = 0;
    rows.forEach(function (r) {
      var qty = num(r.querySelector('[data-f="qty"]').value);
      var price = num(r.querySelector('[data-f="price"]').value);
      var disc = num(r.querySelector('[data-f="discount"]').value);
      var total = Math.max(0, qty * price - disc);
      r.querySelector('[data-f="total"]').textContent = total.toLocaleString('en-US');
      r.querySelector('[data-f="total-hidden"]').value = total;
      subtotal += total;
    });
    var discount = num(document.querySelector('[data-invoice-discount]')?.value);
    var shipping = num(document.querySelector('[data-invoice-shipping]')?.value);
    var taxRate = num(document.querySelector('[data-invoice-tax]')?.value);
    var tax = (subtotal - discount) * taxRate / 100;
    var total = Math.max(0, subtotal - discount + tax + shipping);
    var set = function (id, v) { var el = document.getElementById(id); if (el) el.textContent = v.toLocaleString('en-US'); };
    set('calcSubtotal', subtotal);
    set('calcDiscount', discount);
    set('calcTax', Math.round(tax));
    set('calcShipping', shipping);
    set('calcTotal', Math.round(total));
  }

  // مقدار اولیه پیش‌فرض تاریخ امروز شمسی
  var jdateInput = document.querySelector('[data-jdate-today]');
  if (jdateInput && !jdateInput.value) {
    jdateInput.value = jdateInput.getAttribute('data-jdate-today');
  }
})();
