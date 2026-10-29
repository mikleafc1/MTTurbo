<?php /** انتقال موجودی بین دو انبار */ ?>
<div class="page-head">
    <h2>انتقال بین انبارها</h2>
    <a class="btn btn-outline" href="<?= url('stock/kardex') ?>">← بازگشت به کاردکس</a>
</div>

<div class="card" style="max-width:640px">
    <form method="post" action="<?= url('stock/store-transfer') ?>">
        <?= csrf_field() ?>
        <label class="form-label required">کالا</label>
        <select class="form-input" name="product_id" required>
            <option value="">— انتخاب کالا —</option>
            <?php foreach ($products as $p): ?>
                <option value="<?= (int) $p['id'] ?>" <?= (int) $preProductId === (int) $p['id'] ? 'selected' : '' ?>>
                    <?= e($p['name']) ?><?= $p['sku'] !== null && $p['sku'] !== '' ? ' (' . e($p['sku']) . ')' : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="form-row">
            <div>
                <label class="form-label required">از انبار</label>
                <select class="form-input" name="from_warehouse_id" required>
                    <option value="">— انتخاب —</option>
                    <?php foreach ($warehouses as $w): ?>
                        <option value="<?= (int) $w['id'] ?>"><?= e($w['name']) ?><?= (int) $w['is_default'] === 1 ? ' (پیش‌فرض)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label required">به انبار</label>
                <select class="form-input" name="to_warehouse_id" required>
                    <option value="">— انتخاب —</option>
                    <?php foreach ($warehouses as $w): ?>
                        <option value="<?= (int) $w['id'] ?>"><?= e($w['name']) ?><?= (int) $w['is_default'] === 1 ? ' (پیش‌فرض)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label required">مقدار</label>
                <input class="form-input" type="number" name="qty" min="0.001" step="0.001" dir="ltr" required>
            </div>
        </div>
        <label class="form-label">یادداشت</label>
        <textarea class="form-input" name="note" rows="2"></textarea>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">ثبت انتقال</button>
            <a class="btn btn-outline" href="<?= url('stock/kardex') ?>">انصراف</a>
        </div>
    </form>
    <div class="hint-box">
        هر انتقال دو حرکت در کاردکس ثبت می‌کند: «انتقال (خروج)» از انبار مبدأ و «انتقال (ورود)» به انبار مقصد. انبار مبدأ و مقصد نمی‌توانند یکسان باشند.
    </div>
</div>
