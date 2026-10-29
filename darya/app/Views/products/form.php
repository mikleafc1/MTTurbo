<?php /** فرم ایجاد/ویرایش کالا */ $isEdit = $row !== null; $old = fn(string $k, $d = '') => e($_POST[$k] ?? ($row[$k] ?? $d)); ?>
<div class="page-head">
    <h2><?= $isEdit ? 'ویرایش کالا' : 'کالای جدید' ?></h2>
    <a class="btn btn-outline" href="<?= url('products') ?>">← بازگشت</a>
</div>

<div class="card">
    <form method="post" action="<?= url($isEdit ? 'products/update/' . $row['id'] : 'products/store') ?>">
        <?= csrf_field() ?>
        <div class="form-row">
            <div>
                <label class="form-label required">نام کالا</label>
                <input class="form-input" name="name" required value="<?= $old('name') ?>">
            </div>
            <div>
                <label class="form-label">کد کالا (SKU)</label>
                <input class="form-input" name="sku" dir="ltr" value="<?= $old('sku') ?>">
            </div>
            <div>
                <label class="form-label">دسته‌بندی</label>
                <select class="form-input" name="category_id">
                    <option value="">— بدون دسته —</option>
                    <?php $selCat = (int) ($_POST['category_id'] ?? ($row['category_id'] ?? 0)); ?>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= $selCat === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div>
                <label class="form-label">واحد شمارش</label>
                <input class="form-input" name="unit" list="unitSuggestions" value="<?= $old('unit', 'عدد') ?>">
                <datalist id="unitSuggestions">
                    <option value="عدد"></option>
                    <option value="کیلوگرم"></option>
                    <option value="متر"></option>
                    <option value="لیتر"></option>
                    <option value="بسته"></option>
                    <option value="جعبه"></option>
                </datalist>
            </div>
            <div>
                <label class="form-label">قیمت خرید</label>
                <input class="form-input" name="purchase_price" data-money dir="ltr" value="<?= $old('purchase_price', '0') ?>">
            </div>
            <div>
                <label class="form-label">قیمت فروش</label>
                <input class="form-input" name="sale_price" data-money dir="ltr" value="<?= $old('sale_price', '0') ?>">
            </div>
        </div>
        <div class="form-row">
            <div>
                <label class="form-label">حداقل موجودی (نقطه سفارش)</label>
                <input class="form-input" type="number" name="min_stock" min="0" step="0.001" dir="ltr" value="<?= $old('min_stock', '0') ?>">
            </div>
            <div>
                <label class="form-label">وضعیت</label>
                <label style="display:flex;align-items:center;gap:6px;margin-top:8px">
                    <input type="checkbox" name="is_active" value="1" <?= ($_POST['is_active'] ?? ($row['is_active'] ?? 1)) ? 'checked' : '' ?>> کالا فعال باشد
                </label>
            </div>
        </div>
        <label class="form-label">توضیحات</label>
        <textarea class="form-input" name="description" rows="2"><?= $old('description') ?></textarea>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">ذخیره</button>
            <a class="btn btn-outline" href="<?= url('products') ?>">انصراف</a>
        </div>
    </form>
</div>
