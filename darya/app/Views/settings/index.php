<?php /** تنظیمات — متغیرها: values */ ?>
<div class="page-head">
    <h2>تنظیمات</h2>
    <div class="page-actions">
        <?php if (can('sync.manage')): ?>
            <a class="btn btn-outline" href="<?= url('settings/api-keys') ?>">کلیدهای API</a>
            <a class="btn btn-outline" href="<?= url('settings/role-map') ?>">نگاشت نقش‌ها</a>
        <?php endif; ?>
    </div>
</div>

<form method="post" action="<?= url('settings/store') ?>">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-head"><h3>اطلاعات شرکت</h3></div>
        <div class="form-row">
            <div>
                <label class="form-label">نام شرکت</label>
                <input class="form-input" name="company_name" value="<?= e($values['company_name']) ?>">
            </div>
            <div>
                <label class="form-label">تلفن</label>
                <input class="form-input" name="company_phone" dir="ltr" value="<?= e($values['company_phone']) ?>">
            </div>
            <div>
                <label class="form-label">کد اقتصادی</label>
                <input class="form-input" name="company_economic_code" dir="ltr" value="<?= e($values['company_economic_code']) ?>">
            </div>
        </div>
        <label class="form-label">آدرس</label>
        <textarea class="form-input" name="company_address" rows="2"><?= e($values['company_address']) ?></textarea>
    </div>

    <div class="card">
        <div class="card-head"><h3>فاکتور و پیش‌شماره‌ها</h3></div>
        <div class="form-row">
            <div>
                <label class="form-label">واحد پول</label>
                <input class="form-input" name="currency" value="<?= e($values['currency']) ?>">
            </div>
            <div>
                <label class="form-label">درصد مالیات بر ارزش افزوده</label>
                <input class="form-input" name="tax_rate" dir="ltr" value="<?= e($values['tax_rate']) ?>">
            </div>
        </div>
        <div class="form-row">
            <div>
                <label class="form-label">پیش‌شماره فاکتور فروش</label>
                <input class="form-input" name="invoice_prefix" dir="ltr" value="<?= e($values['invoice_prefix']) ?>">
            </div>
            <div>
                <label class="form-label">پیش‌شماره فاکتور خرید</label>
                <input class="form-input" name="purchase_prefix" dir="ltr" value="<?= e($values['purchase_prefix']) ?>">
            </div>
            <div>
                <label class="form-label">پیش‌شماره دریافت/پرداخت</label>
                <input class="form-input" name="trx_prefix" dir="ltr" value="<?= e($values['trx_prefix']) ?>">
            </div>
            <div>
                <label class="form-label">پیش‌شماره سند حسابداری</label>
                <input class="form-input" name="voucher_prefix" dir="ltr" value="<?= e($values['voucher_prefix']) ?>">
            </div>
        </div>
        <div class="hint-box">پیش‌شماره‌ها فقط حروف انگلیسی، عدد و خط تیره؛ شماره‌گذاری خودکار به‌صورت «پیش‌شماره-۰۰۰۱» انجام می‌شود.</div>
    </div>

    <div class="card">
        <div class="card-head"><h3>همگام‌سازی</h3></div>
        <label style="display:flex;gap:8px;align-items:center;font-size:13.5px">
            <input type="checkbox" name="sync_enabled" value="1" <?= $values['sync_enabled'] === '1' ? 'checked' : '' ?>>
            همگام‌سازی با فروشگاه (ووکامرس) فعال باشد
        </label>
        <div class="muted" style="font-size:12.5px;margin-top:6px">
            با غیرفعال کردن، همه اندپوینت‌های API به‌جز ping پاسخ خطا می‌دهند و صف پردازش نمی‌شود.
        </div>
    </div>

    <div class="form-actions">
        <button class="btn btn-primary" type="submit">ذخیره تنظیمات</button>
    </div>
</form>
