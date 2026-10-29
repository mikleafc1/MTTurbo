<?php /** فرم ایجاد/ویرایش مشتری */ $isEdit = $row !== null; $old = fn(string $k, $d = '') => e($_POST[$k] ?? ($row[$k] ?? $d)); ?>
<div class="page-head">
    <h2><?= $isEdit ? 'ویرایش مشتری' : 'مشتری جدید' ?></h2>
    <a class="btn btn-outline" href="<?= url('customers') ?>">← بازگشت</a>
</div>

<div class="card">
    <form method="post" action="<?= url($isEdit ? 'customers/update/' . $row['id'] : 'customers/store') ?>">
        <?= csrf_field() ?>
        <div class="form-row">
            <div>
                <label class="form-label required">نام و نام‌خانوادگی</label>
                <input class="form-input" name="full_name" required value="<?= $old('full_name') ?>">
            </div>
            <div>
                <label class="form-label">نوع</label>
                <select class="form-input" name="type">
                    <option value="person" <?= ($row['type'] ?? 'person') === 'person' ? 'selected' : '' ?>>شخص حقیقی</option>
                    <option value="company" <?= ($row['type'] ?? '') === 'company' ? 'selected' : '' ?>>شرکت</option>
                </select>
            </div>
            <div>
                <label class="form-label">شرکت / کسب‌وکار</label>
                <input class="form-input" name="company" value="<?= $old('company') ?>">
            </div>
        </div>
        <div class="form-row">
            <div>
                <label class="form-label">موبایل</label>
                <input class="form-input" name="mobile" dir="ltr" value="<?= $old('mobile') ?>">
            </div>
            <div>
                <label class="form-label">تلفن</label>
                <input class="form-input" name="phone" dir="ltr" value="<?= $old('phone') ?>">
            </div>
            <div>
                <label class="form-label">ایمیل</label>
                <input class="form-input" name="email" dir="ltr" type="email" value="<?= $old('email') ?>">
            </div>
        </div>
        <div class="form-row">
            <div>
                <label class="form-label">شهر</label>
                <input class="form-input" name="city" value="<?= $old('city') ?>">
            </div>
            <div>
                <label class="form-label">کد پستی</label>
                <input class="form-input" name="postal_code" dir="ltr" value="<?= $old('postal_code') ?>">
            </div>
            <div>
                <label class="form-label">سقف اعتبار</label>
                <input class="form-input" name="credit_limit" data-money dir="ltr" value="<?= $old('credit_limit', '0') ?>">
            </div>
        </div>
        <div class="form-row">
            <div>
                <label class="form-label">کد ملی</label>
                <input class="form-input" name="national_id" dir="ltr" value="<?= $old('national_id') ?>">
            </div>
            <div>
                <label class="form-label">کد اقتصادی</label>
                <input class="form-input" name="economic_code" dir="ltr" value="<?= $old('economic_code') ?>">
            </div>
        </div>
        <label class="form-label">آدرس</label>
        <textarea class="form-input" name="address" rows="2"><?= $old('address') ?></textarea>
        <label class="form-label">یادداشت</label>
        <textarea class="form-input" name="notes" rows="2"><?= $old('notes') ?></textarea>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">ذخیره</button>
            <a class="btn btn-outline" href="<?= url('customers') ?>">انصراف</a>
        </div>
    </form>
</div>
