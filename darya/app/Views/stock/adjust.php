<?php /** اصلاح موجودی (انبار پیش‌فرض) */ ?>
<div class="page-head">
    <h2>اصلاح موجودی</h2>
    <a class="btn btn-outline" href="<?= url('stock/kardex') ?>">← بازگشت به کاردکس</a>
</div>

<div class="card" style="max-width:640px">
    <form method="post" action="<?= url('stock/store-adjust') ?>">
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
                <label class="form-label required">نوع اصلاح</label>
                <select class="form-input" name="type">
                    <option value="in">اصلاح مثبت (افزایش موجودی)</option>
                    <option value="out">اصلاح منفی (کاهش موجودی)</option>
                </select>
            </div>
            <div>
                <label class="form-label required">مقدار</label>
                <input class="form-input" type="number" name="qty" min="0.001" step="0.001" dir="ltr" required>
            </div>
        </div>
        <label class="form-label">دلیل / یادداشت</label>
        <textarea class="form-input" name="note" rows="2" placeholder="مثلاً: شمارش فیزیکی انبار"></textarea>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">ثبت اصلاح</button>
            <a class="btn btn-outline" href="<?= url('stock/kardex') ?>">انصراف</a>
        </div>
    </form>
    <div class="hint-box">
        اصلاح موجودی در انبار «<?= e($defaultWarehouseName) ?>» ثبت می‌شود، موجودی کالا بروزرسانی می‌گردد و حرکت با نوع «اصلاح» در کاردکس نمایش داده می‌شود.
    </div>
</div>
