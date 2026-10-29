<?php /** فرم ایجاد/ویرایش لیست حقوق */ $isEdit = $row !== null; $old = fn(string $k, $d = '') => e($_POST[$k] ?? ($row[$k] ?? $d)); ?>
<div class="page-head">
    <h2><?= $isEdit ? 'ویرایش لیست حقوق' : 'لیست حقوق جدید' ?></h2>
    <a class="btn btn-outline" href="<?= url('payroll') ?>">← بازگشت</a>
</div>

<div class="card">
    <form method="post" action="<?= url($isEdit ? 'payroll/store/' . $row['id'] : 'payroll/store') ?>">
        <?= csrf_field() ?>
        <div class="form-row">
            <div>
                <label class="form-label required">کارمند</label>
                <select class="form-input" name="employee_id" required>
                    <option value="">— انتخاب کارمند —</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?= (int) $emp['id'] ?>" <?= (int) ($_POST['employee_id'] ?? ($row['employee_id'] ?? $prefillEmployeeId)) === (int) $emp['id'] ? 'selected' : '' ?>>
                            <?= e(($emp['code'] ? $emp['code'] . ' — ' : '') . $emp['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label required">سال دوره</label>
                <select class="form-input" name="period_year">
                    <?= jyear_options((int) ($_POST['period_year'] ?? ($row['period_year'] ?? (int) jdate(today(), 'Y')))) ?>
                </select>
            </div>
            <div>
                <label class="form-label required">ماه دوره</label>
                <?= jmonth_select('period_month', (int) ($_POST['period_month'] ?? ($row['period_month'] ?? (int) jdate(today(), 'n')))) ?>
            </div>
        </div>

        <div class="form-row">
            <div>
                <label class="form-label">حقوق پایه</label>
                <input class="form-input" name="base_salary" data-money dir="ltr" value="<?= $old('base_salary', $baseSalaryDefault) ?>" placeholder="0">
            </div>
            <div>
                <label class="form-label">اضافه‌کاری</label>
                <input class="form-input" name="overtime" data-money dir="ltr" value="<?= $old('overtime') ?>" placeholder="0">
            </div>
            <div>
                <label class="form-label">پاداش</label>
                <input class="form-input" name="bonus" data-money dir="ltr" value="<?= $old('bonus') ?>" placeholder="0">
            </div>
        </div>
        <div class="form-row">
            <div>
                <label class="form-label">کسور بیمه</label>
                <input class="form-input" name="insurance_deduction" data-money dir="ltr" value="<?= $old('insurance_deduction') ?>" placeholder="0">
            </div>
            <div>
                <label class="form-label">کسور مالیات</label>
                <input class="form-input" name="tax_deduction" data-money dir="ltr" value="<?= $old('tax_deduction') ?>" placeholder="0">
            </div>
            <div>
                <label class="form-label">سایر کسورات</label>
                <input class="form-input" name="other_deduction" data-money dir="ltr" value="<?= $old('other_deduction') ?>" placeholder="0">
            </div>
        </div>

        <label class="form-label">شرح / یادداشت</label>
        <textarea class="form-input" name="description" rows="2"><?= $old('description') ?></textarea>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">ذخیره</button>
            <a class="btn btn-outline" href="<?= url('payroll') ?>">انصراف</a>
        </div>
    </form>
</div>
<div class="hint-box">خالص پرداختی به‌صورت خودکار محاسبه می‌شود: حقوق پایه + اضافه‌کاری + پاداش − بیمه − مالیات − سایر کسورات. برای هر کارمند در هر دوره (سال/ماه) فقط یک لیست حقوق قابل ثبت است.</div>
