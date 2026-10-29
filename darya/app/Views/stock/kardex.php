<?php /** کاردکس کالا */
$typeMeta = [
    'in'           => ['ورود', 'badge-green'],
    'out'          => ['خروج', 'badge-red'],
    'transfer_in'  => ['انتقال (ورود)', 'badge-amber'],
    'transfer_out' => ['انتقال (خروج)', 'badge-amber'],
    'adjustment'   => ['اصلاح', 'badge-gray'],
];
$fmtQty = fn($v) => fa_num(rtrim(rtrim((string) $v, '0'), '.'));
$qs = [];
if ((int) $productId > 0) $qs['product_id'] = (int) $productId;
if ($type !== '') $qs['type'] = $type;
if ($from !== '') $qs['from'] = $from;
if ($to !== '') $qs['to'] = $to;
?>
<div class="page-head">
    <h2>کاردکس کالا <small class="muted">(<?= fa_num($total) ?> حرکت)</small></h2>
    <div class="page-actions">
        <?php if (can('stock.edit')): ?>
            <a class="btn btn-primary" href="<?= url('stock/adjust') ?>">+ اصلاح موجودی</a>
            <a class="btn btn-outline" href="<?= url('stock/transfer') ?>">انتقال بین انبار</a>
        <?php endif; ?>
    </div>
</div>

<form method="get" action="<?= url('stock/kardex') ?>" class="search-bar">
    <input type="hidden" name="route" value="stock/kardex">
    <select name="product_id" class="form-input">
        <option value="">همه کالاها</option>
        <?php foreach ($products as $p): ?>
            <option value="<?= (int) $p['id'] ?>" <?= (int) $productId === (int) $p['id'] ? 'selected' : '' ?>>
                <?= e($p['name']) ?><?= $p['sku'] !== null && $p['sku'] !== '' ? ' (' . e($p['sku']) . ')' : '' ?>
            </option>
        <?php endforeach; ?>
    </select>
    <select name="type" class="form-input">
        <option value="">همه حرکت‌ها</option>
        <option value="in" <?= $type === 'in' ? 'selected' : '' ?>>ورود</option>
        <option value="out" <?= $type === 'out' ? 'selected' : '' ?>>خروج</option>
        <option value="transfer_in" <?= $type === 'transfer_in' ? 'selected' : '' ?>>انتقال - ورود</option>
        <option value="transfer_out" <?= $type === 'transfer_out' ? 'selected' : '' ?>>انتقال - خروج</option>
        <option value="adjustment" <?= $type === 'adjustment' ? 'selected' : '' ?>>اصلاح</option>
    </select>
    <input type="text" name="from" class="form-input" dir="ltr" placeholder="از 1403/01/01" value="<?= e($from) ?>">
    <input type="text" name="to" class="form-input" dir="ltr" placeholder="تا 1403/01/01" value="<?= e($to) ?>">
    <button class="btn btn-outline">اعمال فیلتر</button>
    <a class="btn btn-outline" href="<?= url('stock/kardex') ?>">حذف فیلترها</a>
</form>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>تاریخ</th><th>کالا</th><th>انبار</th><th>نوع</th>
                    <th>مقدار</th><th>بهای واحد</th><th>شرح</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="7" class="empty">حرکتی در کاردکس ثبت نشده است.</td></tr><?php endif; ?>
            <?php foreach ($rows as $m): ?>
                <?php [$lbl, $cls] = $typeMeta[$m['movement_type']] ?? [$m['movement_type'], 'badge-gray']; ?>
                <tr>
                    <td class="muted"><?= jdate($m['created_at'], 'Y/m/d H:i') ?></td>
                    <td><?= e($productNames[(int) $m['product_id']] ?? '—') ?></td>
                    <td><?= e($warehouseNames[(int) $m['warehouse_id']] ?? '—') ?></td>
                    <td><span class="badge <?= $cls ?>"><?= e($lbl) ?></span></td>
                    <td class="<?= in_array($m['movement_type'], ['out', 'transfer_out'], true) ? 'text-danger' : '' ?>">
                        <?= $fmtQty($m['qty']) ?> <small class="muted"><?= e($productUnits[(int) $m['product_id']] ?? '') ?></small>
                    </td>
                    <td><?= money($m['unit_cost']) ?></td>
                    <td><?= e($m['description'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->render('partials/pagination', ['page' => $page, 'pages' => $pages, 'base' => url('stock/kardex') . ($qs ? '?' . http_build_query($qs) . '&' : '?')]); ?>
