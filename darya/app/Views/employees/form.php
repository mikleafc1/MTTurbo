<?php /** فرم ایجاد/ویرایش کارمند */ $isEdit = $row !== null; $old = fn(string $k, $d = '') => e($_POST[$k] ?? ($row[$k] ?? $d)); ?>
<div class="page-head">
    <h2><?= $isEdit ? 'ویرایش کارمند' : 'کارمند جدید' ?></h2>
    <a class="btn btn-outline" href="<?= url('employees') ?>">← بازگشت به کارمندان</a>
</div>

<div class="card">
    <form method="post" action="<?= url($isEdit ? 'employees/update/' . $row['id'] : 'employees/store') ?>">
        <?= csrf_field() ?>
        <div class="form-row">
            <div>
                <label class="form-label required">نام و نام‌خانوادگی</label>
                <input class="form-input" name="full_name" required value="<?= $old('full_name') ?>">
            </div>
            <div>
                <label class="form-label">کد پرسنلی</label>
                <input class="form-input" name="code" dir="ltr" value="<?= $old('code') ?>" <?= $isEdit && $row['code'] ? '' : '' ?>>
                <div class="muted" style="font-size:12px">خالی بگذارید تا خودکار تولید شود.</div>
            </div>
            <div>
                <label class="form-label">کد ملی</label>
                <input class="form-input" name="national_id" dir="ltr" value="<?= $old('national_id') ?>">
            </div>
        </div>
        <div class="form-row">
            <div>
                <label class="form-label">موبایل</label>
                <input class="form-input" name="mobile" dir="ltr" value="<?= $old('mobile') ?>">
            </div>
            <div>
                <label class="form-label">سمت</label>
                <input class="form-input" name="position" value="<?= $old('position') ?>">
            </div>
            <div>
                <label class="form-label">واحد سازمانی</label>
                <input class="form-input" name="department" value="<?= $old('department') ?>">
            </div>
        </div>
        <div class="form-row">
            <div>
                <label class="form-label">تاریخ استخدام (شمسی)</label>
                <input class="form-input" name="hire_date" dir="ltr" placeholder="1403/05/12"
                       value="<?= e($_POST['hire_date'] ?? (($row['hire_date'] ?? null) ? jdate($row['hire_date']) : '')) ?>">
            </div>
            <div>
                <label class="form-label">حقوق پایه</label>
                <input class="form-input" name="base_salary" data-money dir="ltr" value="<?= $old('base_salary', '0') ?>">
            </div>
            <div>
                <label class="form-label">شماره بیمه</label>
                <input class="form-input" name="insurance_no" dir="ltr" value="<?= $old('insurance_no') ?>">
            </div>
        </div>
        <div class="form-row">
            <div>
                <label class="form-label">بانک</label>
                <input class="form-input" name="bank_name" value="<?= $old('bank_name') ?>">
            </div>
            <div>
                <label class="form-label">شماره حساب / شبا</label>
                <input class="form-input" name="bank_account" dir="ltr" value="<?= $old('bank_account') ?>">
            </div>
            <div>
                <label class="form-label">وضعیت</label>
                <select class="form-input" name="status">
                    <option value="active" <?= ($_POST['status'] ?? ($row['status'] ?? 'active')) === 'active' ? 'selected' : '' ?>>فعال</option>
                    <option value="inactive" <?= ($_POST['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>غیرفعال</option>
                </select>
            </div>
        </div>
        <label class="form-label">یادداشت</label>
        <textarea class="form-input" name="notes" rows="2"><?= $old('notes') ?></textarea>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">ذخیره</button>
            <a class="btn btn-outline" href="<?= url('employees') ?>">انصراف</a>
        </div>
    </form>
</div>
