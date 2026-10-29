<?php /** نگاشت نقش‌های وردپرس → نقش‌های داریا — متغیرها: wpRoles, map, daryaRoles */ ?>
<div class="page-head">
    <h2>نگاشت نقش‌های وردپرس به داریا</h2>
    <a class="btn btn-outline" href="<?= url('settings') ?>">← تنظیمات</a>
</div>

<div class="card">
    <div class="card-head"><h3>نحوه همگام‌سازی نقش کاربران وردپرس به داریا</h3></div>
    <ol class="muted" style="font-size:13px;line-height:2;padding-right:18px;margin:0">
        <li>افزونه ووکامرس کاربران سایت را به اندپوینت <code dir="ltr">v1/users/sync</code> می‌فرستد (به همراه نقش‌های هر کاربر).</li>
        <li>برای هر کاربر، <b>مشتری متناظر</b> در داریا ساخته یا بروزرسانی می‌شود (نام، ایمیل، موبایل و نقش‌های سایت).</li>
        <li>بر اساس جدول زیر، اولین نقش وردپرسِ دارای نگاشت، به <b>نقش داریا</b> تبدیل می‌شود.</li>
        <li>اگر نگاشتی وجود داشته باشد، <b>کاربر داریا</b> با همان دسترسی‌ها ساخته یا نقشش بروز می‌شود؛ کاربر داریا می‌تواند وارد نرم‌افزار شود.</li>
        <li>اگر نقش وردپرس «بدون دسترسی» باشد، فقط مشتری ساخته می‌شود و کاربر داریا برای او ایجاد نمی‌شود.</li>
    </ol>
</div>

<div class="card">
    <div class="card-head"><h3>جدول نگاشت</h3></div>
    <form method="post" action="<?= url('settings/save-role-map') ?>">
        <?= csrf_field() ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>نقش وردپرس</th><th>نقش متناظر در داریا</th></tr>
                </thead>
                <tbody>
                <?php foreach ($wpRoles as $slug => $label): ?>
                    <?php $current = $map[$slug] ?? null; ?>
                    <tr>
                        <td><b dir="ltr"><?= e($slug) ?></b> <small class="muted"><?= e($label) ?></small></td>
                        <td>
                            <select class="form-input" name="map_<?= e($slug) ?>" style="max-width:280px">
                                <option value="">— بدون دسترسی —</option>
                                <?php foreach ($daryaRoles as $role): ?>
                                    <option value="<?= e($role['slug']) ?>" <?= $current === $role['slug'] ? 'selected' : '' ?>>
                                        <?= e($role['name']) ?> (<?= e($role['slug']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit">ذخیره نقشه</button>
            <a class="btn btn-outline" href="<?= url('roles') ?>">مدیریت نقش‌های داریا</a>
        </div>
    </form>
</div>

<div class="hint-box">
    این نقشه در تنظیمات با کلید <code dir="ltr">role_map</code> به‌صورت JSON ذخیره می‌شود و افزونه می‌تواند آن را از
    <code dir="ltr">GET v1/role-map</code> بخواند. نقش‌های سفارشی وردپرس اگر قبلاً نگاشت داشته باشند در همین جدول اضافه می‌شوند.
</div>
