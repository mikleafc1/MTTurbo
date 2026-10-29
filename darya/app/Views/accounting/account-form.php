<?php /** فرم ایجاد/ویرایش حساب */ $isEdit = $row !== null; $old = fn(string $k, $d = '') => e($_POST[$k] ?? ($row[$k] ?? $d)); ?>
<div class="page-head">
    <h2><?= $isEdit ? 'ویرایش حساب «' . e($row['name']) . '»' : 'حساب جدید' ?></h2>
    <a class="btn btn-outline" href="<?= url('accounting/accounts') ?>">← بازگشت</a>
</div>

<div class="card">
    <form method="post" action="<?= url($isEdit ? 'accounting/account-store/' . $row['id'] : 'accounting/account-store') ?>">
        <?= csrf_field() ?>
        <div class="form-row">
            <div>
                <label class="form-label required">کد حساب</label>
                <input class="form-input" name="code" required dir="ltr" placeholder="مثال: 1106" value="<?= $old('code') ?>">
            </div>
            <div>
                <label class="form-label required">نام حساب</label>
                <input class="form-input" name="name" required value="<?= $old('name') ?>">
            </div>
        </div>
        <div class="form-row">
            <div>
                <label class="form-label required">نوع حساب</label>
                <select class="form-input" name="type">
                    <?php foreach ($types as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= (string) ($_POST['type'] ?? ($row['type'] ?? 'asset')) === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">حساب والد (فقط گروهی)</label>
                <select class="form-input" name="parent_id">
                    <option value="0">— بدون والد (ریشه) —</option>
                    <?php foreach ($parents as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= (int) ($_POST['parent_id'] ?? ($row['parent_id'] ?? 0)) === (int) $p['id'] ? 'selected' : '' ?>>
                            <?= e($p['code'] . ' — ' . $p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div>
                <label class="form-label">نوع حساب سرگروه</label>
                <label style="display:flex;align-items:center;gap:8px;margin-top:6px">
                    <input type="checkbox" name="is_group" value="1" <?= (string) ($_POST['is_group'] ?? ((int) ($row['is_group'] ?? 0) ? '1' : '')) === '1' ? 'checked' : '' ?>>
                    این حساب سرگروه است (قابل استفاده در اسناد نیست)
                </label>
            </div>
        </div>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">ذخیره</button>
            <a class="btn btn-outline" href="<?= url('accounting/accounts') ?>">انصراف</a>
        </div>
    </form>
</div>
