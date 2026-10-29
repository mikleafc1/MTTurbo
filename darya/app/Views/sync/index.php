<?php /** لاگ همگام‌سازی + صف رویدادها + راهنمای API — متغیرها: rows, entityTypes, entityType, pending, failed, processing, apiKeys, apiBase, syncEnabled */
$syncStatus = fn(string $s): string => match ($s) {
    'ok', 'done' => '<span class="badge badge-green">' . e(match ($s) { 'ok' => 'موفق', 'done' => 'انجام شد', default => $s }) . '</span>',
    'failed' => '<span class="badge badge-red">ناموفق</span>',
    'pending' => '<span class="badge badge-amber">در انتظار</span>',
    'processing' => '<span class="badge badge-amber">در حال پردازش</span>',
    default => '<span class="badge badge-gray">' . e($s) . '</span>',
};
?>
<div class="page-head">
    <h2>همگام‌سازی</h2>
    <div class="page-actions">
        <?php if (can('sync.manage')): ?>
            <form method="post" action="<?= url('sync/run-queue') ?>" style="display:inline">
                <?= csrf_field() ?>
                <button class="btn btn-primary"><?= icon_svg('sync', 15) ?> اجرای صف</button>
            </form>
            <a class="btn btn-outline" href="<?= url('settings/api-keys') ?>">کلیدهای API</a>
        <?php endif; ?>
        <a class="btn btn-outline" href="<?= url('settings') ?>">تنظیمات</a>
    </div>
</div>

<div class="stat-grid">
    <div class="card stat">
        <div class="stat-label">وضعیت همگام‌سازی</div>
        <div class="stat-value" style="font-size:16px"><?= $syncEnabled ? 'فعال' : 'غیرفعال' ?></div>
        <div class="stat-hint"><?= $syncEnabled ? 'API در حال سرویس‌دهی است' : 'از تنظیمات غیرفعال شده' ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">رویدادهای در انتظار</div>
        <div class="stat-value text-amber"><?= fa_num($pending) ?></div>
        <div class="stat-hint"><?= fa_num($processing) ?> در حال پردازش</div>
    </div>
    <div class="card stat">
        <div class="stat-label">رویدادهای ناموفق</div>
        <div class="stat-value text-danger"><?= fa_num($failed) ?></div>
        <div class="stat-hint">حداکثر ۵ بار تلاش می‌شوند</div>
    </div>
</div>

<div class="card">
    <div class="card-head"><h3>راهنمای اتصال (API)</h3></div>
    <div class="muted" style="font-size:13px;line-height:2.1">
        آدرس پایه: <code dir="ltr"><?= e($apiBase) ?>?path=v1/&lt;endpoint&gt;</code><br>
        احراز هویت: هدر <code dir="ltr">X-Api-Key: dk_...</code> (یا پارامتر <code dir="ltr">api_key</code>)<br>
        اندپوینت‌ها: <code dir="ltr">v1/ping</code>، <code dir="ltr">v1/products/upsert</code>، <code dir="ltr">v1/orders</code>،
        <code dir="ltr">v1/customers/upsert</code>، <code dir="ltr">v1/users/sync</code>، <code dir="ltr">v1/abandoned-carts</code>،
        <code dir="ltr">v1/role-map</code>، <code dir="ltr">v1/sync/run</code>، <code dir="ltr">v1/sync-log</code><br>
        نمونه: <code dir="ltr">curl -H "X-Api-Key: dk_..." <?= e($apiBase) ?>?path=v1/ping</code>
    </div>
    <?php if ($apiKeys): ?>
        <div class="hint-box">
            <b>کلیدهای فعال (پیشوند):</b>
            <?php $anyActive = false; ?>
            <?php foreach ($apiKeys as $k): ?>
                <?php if ((int) $k['is_active'] === 1): $anyActive = true; ?>
                    <code dir="ltr" style="margin:0 4px"><?= e($k['key_prefix']) ?>…</code>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if (!$anyActive): ?><span class="muted">هیچ کلید فعالی وجود ندارد.</span><?php endif; ?>
        </div>
    <?php else: ?>
        <div class="hint-box">هنوز کلید API نساخته‌اید. برای اتصال افزونه یا نرم‌افزار ویندوزی ابتدا از بخش کلیدهای API یک کلید بسازید.</div>
    <?php endif; ?>
</div>

<form method="get" action="<?= url('sync') ?>" class="search-bar">
    <input type="hidden" name="route" value="sync">
    <select name="entity_type" class="form-input" style="max-width:220px">
        <option value="">همه نوع‌ها</option>
        <?php foreach ($entityTypes as $t): ?>
            <option value="<?= e($t['entity_type']) ?>" <?= $entityType === $t['entity_type'] ? 'selected' : '' ?>><?= e($t['entity_type']) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-outline">فیلتر</button>
</form>

<div class="card">
    <div class="card-head"><h3>آخرین رویدادها <small class="muted">(حداکثر ۱۰۰ مورد)</small></h3></div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>زمان</th><th>جهت</th><th>نوع</th><th>مرجع</th><th>عملیات</th><th>وضعیت</th><th>پیام</th></tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="7" class="empty">لاگی ثبت نشده است.</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="muted" style="white-space:nowrap"><?= jdate($r['created_at'], 'Y/m/d H:i') ?></td>
                    <td><?= $r['direction'] === 'out' ? 'خروجی' : 'ورودی' ?></td>
                    <td><span class="badge badge-gray"><?= e($r['entity_type'] ?? '—') ?></span></td>
                    <td dir="ltr" class="muted"><?= e($r['entity_ref'] ?? '—') ?></td>
                    <td class="muted"><?= e($r['action'] ?? '—') ?></td>
                    <td><?= $syncStatus((string) $r['status']) ?></td>
                    <td class="muted" style="max-width:340px"><?= e(mb_substr((string) ($r['message'] ?? ''), 0, 120)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
