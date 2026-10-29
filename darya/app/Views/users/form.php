<?php /** فرم ایجاد/ویرایش کاربر — متغیرها: row, roles, isEdit, isSelf */
$isEdit = $isEdit ?? ($row !== null);
$old = fn(string $k, $d = '') => e($_POST[$k] ?? ($row[$k] ?? $d));
$isActiveChecked = isset($_POST['is_active']) || (!$isEdit) || (int) ($row['is_active'] ?? 0) === 1;
?>
<div class="page-head">
    <h2><?= $isEdit ? 'ویرایش کاربر' : 'کاربر جدید' ?></h2>
    <a class="btn btn-outline" href="<?= url('users') ?>">← بازگشت به کاربران</a>
</div>

<div class="card">
    <form method="post" action="<?= url($isEdit ? 'users/update/' . $row['id'] : 'users/store') ?>">
        <?= csrf_field() ?>
        <div class="form-row">
            <div>
                <label class="form-label required">نام کاربری</label>
                <input class="form-input" name="username" dir="ltr" required value="<?= $old('username') ?>">
            </div>
            <div>
                <label class="form-label required">نام کامل</label>
                <input class="form-input" name="full_name" required value="<?= $old('full_name') ?>">
            </div>
            <div>
                <label class="form-label">ایمیل</label>
                <input class="form-input" name="email" dir="ltr" type="email" value="<?= $old('email') ?>">
            </div>
        </div>
        <div class="form-row">
            <div>
                <label class="form-label">رمز عبور</label>
                <input class="form-input" name="password" dir="ltr" type="password" <?= $isEdit ? '' : 'required' ?>>
                <?php if ($isEdit): ?><div class="muted" style="font-size:12px">خالی بگذارید تا رمز فعلی حفظ شود.</div><?php endif; ?>
            </div>
            <div>
                <label class="form-label required">نقش</label>
                <select class="form-input" name="role_id" required>
                    <option value="">— انتخاب نقش —</option>
                    <?php $currentRole = $_POST['role_id'] ?? ($row['role_id'] ?? ''); ?>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= (int) $role['id'] ?>" <?= (string) $role['id'] === (string) $currentRole ? 'selected' : '' ?>><?= e($role['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">وضعیت</label>
                <label style="display:flex;gap:8px;align-items:center;font-size:13px;padding-top:8px">
                    <input type="checkbox" name="is_active" value="1" <?= $isActiveChecked ? 'checked' : '' ?> <?= $isSelf ? 'disabled' : '' ?>>
                    کاربر فعال باشد
                </label>
                <?php if ($isSelf): ?>
                    <input type="hidden" name="is_active" value="1">
                    <div class="muted" style="font-size:12px">حساب خودتان همیشه فعال می‌ماند.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">ذخیره</button>
            <a class="btn btn-outline" href="<?= url('users') ?>">انصراف</a>
        </div>
    </form>
</div>
