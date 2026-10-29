<?php /** لیست کالاها */ ?>
<div class="page-head">
    <h2>کالاها <small class="muted">(<?= fa_num($total) ?> قلم)</small></h2>
    <div class="page-actions">
        <?php if (can('products.edit')): ?><a class="btn btn-primary" href="<?= url('products/create') ?>">+ کالای جدید</a><?php endif; ?>
    </div>
</div>

<form method="get" action="<?= url('products') ?>" class="search-bar">
    <input type="hidden" name="route" value="products">
    <input type="text" name="q" class="form-input" placeholder="جستجو: نام یا کد کالا..." value="<?= e($q) ?>">
    <select name="cat" class="form-input">
        <option value="">همه دسته‌ها</option>
        <?php foreach ($categories as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (int) $cat === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-outline">جستجو</button>
    <a class="btn <?= $low ? 'btn-primary' : 'btn-outline' ?>" href="<?= url('products') . ($low ? '' : '?low=1') ?>">کالاهای کم‌موجود</a>
</form>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>کد</th><th>نام</th><th>دسته</th><th>واحد</th>
                    <th>قیمت خرید</th><th>قیمت فروش</th><th>موجودی</th><th>وضعیت</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="9" class="empty">کالایی یافت نشد.</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <?php $isLow = (float) $r['stock_qty'] <= (float) $r['min_stock']; ?>
                <tr>
                    <td class="muted" dir="ltr"><?= $r['sku'] !== null ? e($r['sku']) : '—' ?></td>
                    <td><?= e($r['name']) ?></td>
                    <td><?= e($catNames[(int) $r['category_id']] ?? '—') ?></td>
                    <td><?= e($r['unit']) ?></td>
                    <td><?= money($r['purchase_price']) ?></td>
                    <td><?= money($r['sale_price']) ?></td>
                    <td class="<?= $isLow ? 'text-danger' : '' ?>">
                        <?= fa_num(rtrim(rtrim((string) $r['stock_qty'], '0'), '.')) ?>
                        <small class="muted"><?= e($r['unit']) ?></small>
                        <?php if ($isLow): ?><small class="muted">(حداقل: <?= fa_num(rtrim(rtrim((string) $r['min_stock'], '0'), '.')) ?>)</small><?php endif; ?>
                    </td>
                    <td><?= (int) $r['is_active'] === 1 ? '<span class="badge badge-green">فعال</span>' : '<span class="badge badge-gray">غیرفعال</span>' ?></td>
                    <td>
                        <?php if (can('products.edit')): ?><a class="btn btn-sm btn-outline" href="<?= url('products/edit/' . $r['id']) ?>">ویرایش</a><?php endif; ?>
                        <?php if (can('products.manage')): ?>
                        <form method="post" action="<?= url('products/delete/' . $r['id']) ?>" style="display:inline" data-confirm="کالای «<?= e($r['name']) ?>» حذف شود؟">
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
<?php $this->render('partials/pagination', ['page' => $page, 'pages' => $pages, 'base' => url('products') . '?' . http_build_query(array_filter(['q' => $q, 'cat' => $cat, 'low' => $low])) . '&']); ?>
