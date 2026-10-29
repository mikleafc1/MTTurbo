<?php /** فرم ایجاد/ویرایش نقش + گرید چک‌باکس دسترسی‌ها — متغیرها: row, catalog, selected, isAdmin, isEdit */
$isEdit = $isEdit ?? ($row !== null);
$old = fn(string $k, $d = '') => e($_POST[$k] ?? ($row[$k] ?? $d));
$selected = (array) $selected;
?>
<div class="page-head">
    <h2><?= $isEdit ? 'ویرایش نقش' : 'نقش جدید' ?></h2>
    <a class="btn btn-outline" href="<?= url('roles') ?>">← بازگشت به نقش‌ها</a>
</div>

<div class="card">
    <form method="post" action="<?= url($isEdit ? 'roles/update/' . $row['id'] : 'roles/store') ?>">
        <?= csrf_field() ?>
        <div class="form-row">
            <div>
                <label class="form-label required">نام نقش</label>
                <input class="form-input" name="name" required value="<?= $old('name') ?>">
            </div>
            <div>
                <label class="form-label required">شناسه (اسلاگ)</label>
                <input class="form-input" name="slug" dir="ltr"
                       value="<?= $old('slug') ?>"
                       <?= $isEdit ? 'readonly' : 'required' ?>>
                <?php if ($isEdit): ?><div class="muted" style="font-size:12px">شناسه پس از ایجاد قابل تغییر نیست.</div><?php endif; ?>
            </div>
        </div>

        <label class="form-label">دسترسی‌ها</label>
        <?php if ($isAdmin): ?>
            <div class="alert alert-success">نقش مدیر کل همیشه به <b>همه بخش‌ها</b> دسترسی دارد و قابل محدود کردن نیست.</div>
        <?php endif; ?>
        <div class="permissions-grid">
            <?php foreach ($catalog as $perm => $label): ?>
                <label>
                    <input type="checkbox" name="perms[]" value="<?= e($perm) ?>"
                           <?= in_array($perm, $selected, true) ? 'checked' : '' ?>
                           <?= $isAdmin ? 'disabled' : '' ?>>
                    <?= e($label) ?> <small dir="ltr" class="muted"><?= e($perm) ?></small>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">ذخیره</button>
            <a class="btn btn-outline" href="<?= url('roles') ?>">انصراف</a>
        </div>
    </form>
</div>
