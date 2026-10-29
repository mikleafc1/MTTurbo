<?php
/**
 * فرم فاکتور فروش / برگشتی فروش — سازگار با assets/js/app.js
 * (#itemRows، #itemRowTemplate، #addItemRow، data-f، data-invoice-*، #calc*)
 */
$isReturn = $isReturn ?? false;
$old = fn(string $k, $d = '') => e($_POST[$k] ?? $d);

/* گزینه‌های کالا (با قیمت فروش) — یک‌بار ساخته و در ردیف اول + قالب استفاده می‌شود */
$optionsHtml = '';
foreach (($products ?? []) as $p) {
    $optionsHtml .= '<option value="' . (int) $p['id'] . '" data-price="' . e($p['sale_price']) . '" data-unit="' . e($p['unit']) . '">'
        . e($p['name']) . ' — ' . e($p['unit']) . ($p['sku'] ? ' (' . e($p['sku']) . ')' : '')
        . '</option>';
}

/* ساختار یک ردیف اقلام — دقیقاً مطابق انتظار app.js */
$rowHtml = '<div class="item-row">'
    . '<div>'
    . '<select class="form-input" name="items[product_id][]" data-product-select><option value="">— انتخاب کالا —</option>' . $optionsHtml . '</select>'
    . '<input class="form-input" name="items[description][]" placeholder="شرح ردیف (اختیاری)" style="margin-top:5px">'
    . '</div>'
    . '<input class="form-input" name="items[qty][]" data-f="qty" dir="ltr" inputmode="decimal" value="1" placeholder="تعداد">'
    . '<input class="form-input" name="items[unit_price][]" data-f="price" data-money dir="ltr" inputmode="decimal" placeholder="قیمت واحد">'
    . '<input class="form-input" name="items[discount][]" data-f="discount" data-money dir="ltr" inputmode="decimal" placeholder="تخفیف">'
    . '<div style="text-align:center;font-weight:700"><span data-f="total">0</span><input type="hidden" name="items[total][]" data-f="total-hidden" value="0"></div>'
    . '<button type="button" class="btn btn-sm btn-outline-danger remove-row" title="حذف ردیف">✕</button>'
    . '</div>';

$headStyle = 'display:grid;grid-template-columns:2fr 90px 130px 110px 110px 34px;gap:8px;font-size:12px;font-weight:700;color:var(--muted);margin-bottom:6px';
?>
<div class="page-head">
    <h2><?= e($heading) ?></h2>
    <a class="btn btn-outline" href="<?= url($backRoute) ?>">← بازگشت</a>
</div>

<div class="hint-box">
    <?php if ($isReturn): ?>
        فاکتور برگشتی فروش: با ذخیره وضعیت غیر «پیش‌نویس»، موجودی کالاها <b>افزایش</b> می‌یابد و سند برگشت فروش ثبت می‌گردد.
    <?php else: ?>
        با ذخیره وضعیت غیر «پیش‌نویس»، خروج موجودی کالاها ثبت و سند حسابداری فروش صادر می‌شود. وضعیت «پرداخت شده» مبلغ را کامل تسویه‌شده در نظر می‌گیرد.
    <?php endif; ?>
</div>

<div class="card">
    <form method="post" action="<?= url($formAction) ?>">
        <?= csrf_field() ?>
        <div class="form-row">
            <div>
                <label class="form-label required">مشتری</label>
                <select class="form-input" name="customer_id" required>
                    <option value="">— انتخاب مشتری —</option>
                    <?php foreach (($customers ?? []) as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (string) ($_POST['customer_id'] ?? '') === (string) $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['full_name']) ?><?= $c['code'] ? ' (' . e($c['code']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label required">تاریخ فاکتور</label>
                <input class="form-input" type="text" name="invoice_date" dir="ltr" required
                       data-jdate-today="<?= e($todayJ) ?>" value="<?= $old('invoice_date') ?>" placeholder="۱۴۰۳/۰۱/۰۱">
            </div>
            <div>
                <label class="form-label">سررسید (اختیاری)</label>
                <input class="form-input" type="text" name="due_date" dir="ltr" value="<?= $old('due_date') ?>" placeholder="۱۴۰۳/۰۲/۰۱">
            </div>
        </div>
        <div class="form-row">
            <div>
                <label class="form-label">وضعیت</label>
                <select class="form-input" name="status">
                    <option value="draft" <?= ($_POST['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>پیش‌نویس (بدون ثبت انبار و سند)</option>
                    <option value="unpaid" <?= ($_POST['status'] ?? '') === 'unpaid' ? 'selected' : '' ?>>پرداخت نشده</option>
                    <option value="paid" <?= ($_POST['status'] ?? '') === 'paid' ? 'selected' : '' ?>>پرداخت شده</option>
                </select>
            </div>
            <div>
                <label class="form-label">روش پرداخت</label>
                <select class="form-input" name="payment_method">
                    <option value="">— نامشخص —</option>
                    <?php foreach (\App\Controllers\InvoiceController::PAY_METHODS as $k => $lbl): ?>
                        <option value="<?= e($k) ?>" <?= ($_POST['payment_method'] ?? '') === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <label class="form-label required">اقلام فاکتور</label>
        <div style="<?= $headStyle ?>">
            <div>کالا / شرح</div><div>تعداد</div><div>قیمت واحد</div><div>تخفیف</div><div style="text-align:center">جمع</div><div></div>
        </div>
        <div id="itemRows"><?= $rowHtml ?></div>
        <template id="itemRowTemplate"><?= $rowHtml ?></template>
        <button type="button" class="btn btn-outline btn-sm" id="addItemRow">+ افزودن ردیف</button>

        <div class="grid-2" style="margin-top:18px;align-items:start">
            <div>
                <div class="form-row">
                    <div>
                        <label class="form-label">تخفیف کلی فاکتور</label>
                        <input class="form-input" name="discount" data-invoice-discount data-money dir="ltr" value="<?= $old('discount', '0') ?>">
                    </div>
                    <div>
                        <label class="form-label">نرخ مالیات بر ارزش افزوده (٪)</label>
                        <input class="form-input" name="tax_rate" data-invoice-tax dir="ltr" value="<?= $old('tax_rate', (string) ($taxRate ?? '9')) ?>">
                    </div>
                    <div>
                        <label class="form-label">هزینه حمل</label>
                        <input class="form-input" name="shipping" data-invoice-shipping data-money dir="ltr" value="<?= $old('shipping', '0') ?>">
                    </div>
                </div>
                <label class="form-label">توضیحات فاکتور</label>
                <textarea class="form-input" name="description" rows="2"><?= $old('description') ?></textarea>
            </div>
            <div class="card" style="background:#f9fafb;margin:0">
                <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px dashed var(--border)"><span>جمع اقلام</span><b id="calcSubtotal">0</b></div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px dashed var(--border)"><span>تخفیف</span><b id="calcDiscount">0</b></div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px dashed var(--border)"><span>مالیات</span><b id="calcTax">0</b></div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px dashed var(--border)"><span>هزینه حمل</span><b id="calcShipping">0</b></div>
                <div style="display:flex;justify-content:space-between;padding:10px 0 2px;font-size:16px"><span><b>مبلغ نهایی</b></span><b id="calcTotal" style="color:var(--primary)">0</b></div>
                <div class="muted" style="font-size:11.5px;text-align:left"><?= e(setting('currency', 'تومان')) ?> — محاسبه نهایی سمت سرور انجام می‌شود</div>
            </div>
        </div>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">ذخیره فاکتور</button>
            <a class="btn btn-outline" href="<?= url($backRoute) ?>">انصراف</a>
        </div>
    </form>
</div>

<script>
/* انتخاب کالا در هر ردیف ← قیمت پیش‌فرض از data-price */
(function () {
  'use strict';
  document.addEventListener('change', function (e) {
    var sel = e.target;
    if (!sel || !sel.matches || !sel.matches('[data-product-select]')) return;
    var row = sel.closest('.item-row');
    if (!row) return;
    var opt = sel.options[sel.selectedIndex];
    var price = opt ? (opt.getAttribute('data-price') || '') : '';
    var priceInput = row.querySelector('[data-f="price"]');
    if (priceInput && price !== '') {
      priceInput.value = price;
      priceInput.dispatchEvent(new Event('input', { bubbles: true }));
    }
  });
})();
</script>
