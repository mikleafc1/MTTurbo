<?php /** فرم ایجاد/ویرایش مبلغ */ $isEdit = $row !== null; $old = fn(string $k, $d = '') => e($_POST[$k] ?? ($row[$k] ?? $d)); ?>
<div class="page-head">
    <h2><?= $isEdit ? 'ویرایش مبلغ' : 'مبلغ جدید' ?></h2>
    <a class="btn btn-outline" href="<?= url('promoters') ?>">← بازگشت</a>
</div>

<div class="card">
    <form method="post" action="<?= url($isEdit ? 'promoters/update/' . $row['id'] : 'promoters/store') ?>">
        <?= csrf_field() ?>
        <div class="form-row">
            <div>
                <label class="form-label required">نام و نام‌خانوادگی</label>
                <input class="form-input" name="full_name" required value="<?= $old('full_name') ?>">
            </div>
            <div>
                <label class="form-label">کد (اختیاری)</label>
                <input class="form-input" name="code" dir="ltr" value="<?= $old('code') ?>" placeholder="خالی = تولید خودکار">
            </div>
            <div>
                <label class="form-label">درصد کمیسیون</label>
                <input class="form-input" name="commission_rate" dir="ltr" value="<?= $old('commission_rate', '0') ?>" placeholder="مثال: 2.5">
            </div>
        </div>
        <div class="form-row">
            <div>
                <label class="form-label required">موبایل (نام کاربری اپ موبوآردر)</label>
                <input class="form-input" name="mobile" dir="ltr" required value="<?= $old('mobile') ?>" placeholder="09xxxxxxxxx">
            </div>
            <div>
                <label class="form-label">رمز ورود (PIN) — ۴ تا ۶ رقم</label>
                <input class="form-input" name="pin" dir="ltr" inputmode="numeric" maxlength="6" value="<?= $old('pin') ?>" placeholder="<?= $isEdit ? 'خالی = بدون تغییر' : 'مثال: 1234' ?>">
            </div>
            <div>
                <label class="form-label">وضعیت</label>
                <select class="form-input" name="is_active">
                    <option value="1" <?= (int) ($_POST['is_active'] ?? ($row['is_active'] ?? 1)) === 1 ? 'selected' : '' ?>>فعال</option>
                    <option value="0" <?= (int) ($_POST['is_active'] ?? ($row['is_active'] ?? 1)) === 0 ? 'selected' : '' ?>>غیرفعال</option>
                </select>
            </div>
        </div>
        <label class="form-label">یادداشت</label>
        <textarea class="form-input" name="notes" rows="2"><?= $old('notes') ?></textarea>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">ذخیره</button>
            <a class="btn btn-outline" href="<?= url('promoters') ?>">انصراف</a>
        </div>
    </form>
</div>
