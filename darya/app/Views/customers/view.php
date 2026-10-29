<?php /** پروفایل مشتری */ ?>
<div class="page-head">
    <h2><?= e($row['full_name']) ?> <?= $row['company'] ? '<small class="muted">(' . e($row['company']) . ')</small>' : '' ?></h2>
    <div class="page-actions">
        <?php if (can('customers.edit')): ?><a class="btn btn-outline" href="<?= url('customers/edit/' . $row['id']) ?>">ویرایش</a><?php endif; ?>
        <?php if (can('invoices.edit')): ?><a class="btn btn-primary" href="<?= url('invoices/create?customer_id=' . $row['id']) ?>">+ فاکتور فروش</a><?php endif; ?>
        <a class="btn btn-outline" href="<?= url('customers') ?>">← بازگشت</a>
    </div>
</div>

<div class="stat-grid">
    <div class="card stat">
        <div class="stat-label">مانده حساب</div>
        <div class="stat-value <?= (float) $row['balance'] > 0 ? 'text-danger' : '' ?>"><?= money($row['balance']) ?></div>
        <div class="stat-hint">بدهی مشتری (<?= e(setting('currency', 'تومان')) ?>)</div>
    </div>
    <div class="card stat">
        <div class="stat-label">مجموع فروش</div>
        <div class="stat-value"><?= money($totalSales) ?></div>
        <div class="stat-hint">همه فاکتورهای معتبر</div>
    </div>
    <div class="card stat">
        <div class="stat-label">ارتباط</div>
        <div class="stat-value" style="font-size:14px"><?= e($row['mobile'] ?? '—') ?><?= $row['email'] ? '<br>' . e($row['email']) : '' ?></div>
        <div class="stat-hint">موبایل / ایمیل</div>
    </div>
    <div class="card stat">
        <div class="stat-label">کاربر سایت</div>
        <div class="stat-value" style="font-size:14px"><?= $row['wp_user_id'] ? 'وصل به وردپرس ✅' : '—' ?></div>
        <div class="stat-hint">نقش‌ها: <?= e($row['wp_roles'] ?? '—') ?></div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-head"><h3>فاکتورها</h3></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>شماره</th><th>مبلغ</th><th>پرداخت</th><th>وضعیت</th><th>تاریخ</th></tr></thead>
                <tbody>
                <?php if (!$invoices): ?><tr><td colspan="5" class="empty">فاکتوری ندارد.</td></tr><?php endif; ?>
                <?php foreach ($invoices as $i): ?>
                    <tr>
                        <td><a href="<?= url('invoices/view/' . $i['id']) ?>"><?= e($i['invoice_no']) ?></a></td>
                        <td><?= money($i['total']) ?></td>
                        <td><?= money($i['paid_amount']) ?></td>
                        <td><?= status_label($i['status']) ?></td>
                        <td><?= jdate($i['invoice_date']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><h3>دریافت و پرداخت</h3></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>شماره</th><th>نوع</th><th>مبلغ</th><th>روش</th><th>تاریخ</th></tr></thead>
                <tbody>
                <?php if (!$transactions): ?><tr><td colspan="5" class="empty">تراکنشی ثبت نشده.</td></tr><?php endif; ?>
                <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td><?= e($t['trx_no']) ?></td>
                        <td><?= $t['type'] === 'receipt' ? '<span class="badge badge-green">دریافت</span>' : '<span class="badge badge-red">پرداخت</span>' ?></td>
                        <td><?= money($t['amount']) ?></td>
                        <td><?= e($t['method']) ?></td>
                        <td><?= jdate($t['trx_date']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($row['address'] || $row['notes']): ?>
<div class="card">
    <h3 style="margin-bottom:8px">اطلاعات تکمیلی</h3>
    <?php if ($row['address']): ?><p><b>آدرس:</b> <?= e($row['address']) ?></p><?php endif; ?>
    <?php if ($row['notes']): ?><p><b>یادداشت:</b> <?= e($row['notes']) ?></p><?php endif; ?>
</div>
<?php endif; ?>
