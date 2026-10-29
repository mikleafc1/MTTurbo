<?php /** کلیدهای API — کلید تازه فقط یک‌بار نمایش داده می‌شود */ ?>
<div class="page-head">
    <h2>کلیدهای API همگام‌سازی</h2>
    <a class="btn btn-outline" href="<?= url('settings') ?>">← تنظیمات</a>
</div>

<?php if (!empty($_SESSION['new_api_key'])): ?>
    <div class="alert alert-success" style="border-width:2px">
        <b>کلید جدید ساخته شد!</b> این کلید فقط همین یک بار نمایش داده می‌شود — همین حالا کپی کنید:<br>
        <code dir="ltr" style="display:inline-block;margin-top:8px;padding:10px 14px;background:#0f172a;color:#a7f3d0;border-radius:8px;font-size:14px;user-select:all"><?= e($_SESSION['new_api_key']) ?></code>
        <div class="muted" style="margin-top:8px">در افزونه یا نرم‌افزار ویندوزی در هدر <code dir="ltr">X-Api-Key</code> قرار دهید. تنها هش کلید ذخیره می‌شود و امکان بازیابی آن وجود ندارد.</div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-head"><h3>کلید جدید</h3></div>
    <form method="post" action="<?= url('settings/store-api-key') ?>" class="search-bar" style="margin-bottom:0">
        <?= csrf_field() ?>
        <div style="flex:1;max-width:380px">
            <input type="text" name="name" class="form-input" placeholder="نام کلید (مثلاً: افزونه ووکامرس)" required>
        </div>
        <button class="btn btn-primary">کلید جدید</button>
    </form>
</div>

<div class="card">
    <div class="card-head"><h3>کلیدهای موجود <small class="muted">(<?= fa_num(count($rows)) ?>)</small></h3></div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>نام</th><th>کلید</th><th>وضعیت</th><th>آخرین استفاده</th><th>تاریخ ساخت</th><th></th></tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="6" class="empty">هنوز کلیدی ساخته نشده است.</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><b><?= e($r['name']) ?></b></td>
                    <td dir="ltr" class="muted"><code><?= e($r['key_prefix']) ?>…</code></td>
                    <td><?= status_label((int) $r['is_active'] === 1 ? 'active' : 'inactive') ?></td>
                    <td class="muted"><?= $r['last_used_at'] ? jdate($r['last_used_at'], 'Y/m/d H:i') : '—' ?></td>
                    <td class="muted"><?= jdate($r['created_at']) ?></td>
                    <td>
                        <form method="post" action="<?= url('settings/toggle-api-key/' . $r['id']) ?>" style="display:inline">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline"><?= (int) $r['is_active'] === 1 ? 'غیرفعال' : 'فعال' ?></button>
                        </form>
                        <form method="post" action="<?= url('settings/delete-api-key/' . $r['id']) ?>" style="display:inline" data-confirm="کلید «<?= e($r['name']) ?>» حذف شود؟ اتصال‌های فعلی با آن قطع می‌شود.">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-danger">حذف</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="hint-box">
    کلید کامل هرگز دوباره نمایش داده نمی‌شود — فقط پیشوند آن (مثلاً <code dir="ltr">dk_1a2b3c4d…</code>) برای شناسایی نگهداری می‌شود.
    احراز هویت API با هدر <code dir="ltr">X-Api-Key</code> انجام می‌شود.
</div>

<?php unset($_SESSION['new_api_key']); ?>
