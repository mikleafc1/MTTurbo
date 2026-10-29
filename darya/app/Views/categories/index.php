<?php /** دسته‌بندی کالاها — فرم درج/ویرایش درون‌خطی */ $isEdit = $editRow !== null; ?>
<div class="page-head">
    <h2>دسته‌بندی‌ها <small class="muted">(<?= fa_num($total) ?> دسته)</small></h2>
</div>

<div class="card">
    <form method="post" action="<?= url($isEdit ? 'categories/update/' . $editRow['id'] : 'categories/store') ?>">
        <?= csrf_field() ?>
        <div class="form-row" style="align-items:end">
            <div>
                <label class="form-label required"><?= $isEdit ? 'نام دسته (ویرایش «' . e($editRow['name']) . '»)' : 'نام دسته‌بندی جدید' ?></label>
                <input class="form-input" name="name" required value="<?= e($_POST['name'] ?? ($editRow['name'] ?? '')) ?>">
            </div>
            <div>
                <label class="form-label">دسته والد</label>
                <select class="form-input" name="parent_id">
                    <option value="">— بدون والد —</option>
                    <?php $selParent = (int) ($_POST['parent_id'] ?? ($editRow['parent_id'] ?? 0)); ?>
                    <?php foreach ($allCategories as $c): ?>
                        <?php if ($isEdit && (int) $c['id'] === (int) $editRow['id']) continue; ?>
                        <option value="<?= (int) $c['id'] ?>" <?= $selParent === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button class="btn btn-primary" type="submit"><?= $isEdit ? 'ذخیره تغییرات' : '+ افزودن دسته' ?></button>
                <?php if ($isEdit): ?><a class="btn btn-outline" href="<?= url('categories') ?>">انصراف</a><?php endif; ?>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>نام دسته</th><th>دسته والد</th><th>تعداد کالا</th><th></th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="4" class="empty">دسته‌ای ثبت نشده است.</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e($r['name']) ?></td>
                    <td><?= e($catNames[(int) $r['parent_id']] ?? '—') ?></td>
                    <td><?= fa_num($productCounts[(int) $r['id']] ?? 0) ?></td>
                    <td>
                        <?php if (can('products.edit')): ?>
                            <a class="btn btn-sm btn-outline" href="<?= url('categories/edit/' . $r['id']) ?>">ویرایش</a>
                            <form method="post" action="<?= url('categories/delete/' . $r['id']) ?>" style="display:inline" data-confirm="دسته «<?= e($r['name']) ?>» حذف شود؟">
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
<?php $this->render('partials/pagination', ['page' => $page, 'pages' => $pages, 'base' => url('categories') . '?']); ?>
