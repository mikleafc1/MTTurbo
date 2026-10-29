<?php /** انبارها — فرم درج/ویرایش درون‌خطی */ $isEdit = $editRow !== null; ?>
<div class="page-head">
    <h2>انبارها <small class="muted">(<?= fa_num($total) ?> انبار)</small></h2>
</div>

<div class="card">
    <form method="post" action="<?= url($isEdit ? 'warehouses/update/' . $editRow['id'] : 'warehouses/store') ?>">
        <?= csrf_field() ?>
        <div class="form-row" style="align-items:end">
            <div>
                <label class="form-label required"><?= $isEdit ? 'نام انبار (ویرایش «' . e($editRow['name']) . '»)' : 'نام انبار جدید' ?></label>
                <input class="form-input" name="name" required value="<?= e($_POST['name'] ?? ($editRow['name'] ?? '')) ?>">
            </div>
            <div>
                <label class="form-label">موقعیت</label>
                <input class="form-input" name="location" value="<?= e($_POST['location'] ?? ($editRow['location'] ?? '')) ?>">
            </div>
            <div>
                <label class="form-label">نقش</label>
                <label style="display:flex;align-items:center;gap:6px;margin-top:8px">
                    <input type="checkbox" name="is_default" value="1" <?= ($_POST['is_default'] ?? ($editRow['is_default'] ?? 0)) ? 'checked' : '' ?>> انبار پیش‌فرض
                </label>
            </div>
            <div>
                <button class="btn btn-primary" type="submit"><?= $isEdit ? 'ذخیره تغییرات' : '+ افزودن انبار' ?></button>
                <?php if ($isEdit): ?><a class="btn btn-outline" href="<?= url('warehouses') ?>">انصراف</a><?php endif; ?>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>نام</th><th>موقعیت</th><th>نقش</th><th>تعداد حرکت کاردکس</th><th></th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="5" class="empty">انباری ثبت نشده است.</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e($r['name']) ?></td>
                    <td><?= $r['location'] !== null ? e($r['location']) : '—' ?></td>
                    <td><?= (int) $r['is_default'] === 1 ? '<span class="badge badge-green">پیش‌فرض</span>' : '<span class="badge badge-gray">عادی</span>' ?></td>
                    <td><?= fa_num($movementCounts[(int) $r['id']] ?? 0) ?></td>
                    <td>
                        <?php if (can('products.edit')): ?>
                            <a class="btn btn-sm btn-outline" href="<?= url('warehouses/edit/' . $r['id']) ?>">ویرایش</a>
                            <form method="post" action="<?= url('warehouses/delete/' . $r['id']) ?>" style="display:inline" data-confirm="انبار «<?= e($r['name']) ?>» حذف شود؟">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger">حذف</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->render('partials/pagination', ['page' => $page, 'pages' => $pages, 'base' => url('warehouses') . '?']); ?>
