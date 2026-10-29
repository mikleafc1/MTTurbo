<?php /** دفتر کل و معین — مانده running بر اساس نوع حساب */ ?>
<div class="page-head">
    <h2>دفتر کل و معین</h2>
    <a class="btn btn-outline" href="<?= url('accounting/vouchers') ?>">← اسناد</a>
</div>

<div class="card">
    <form method="get" action="<?= url('accounting/ledger') ?>" class="search-bar">
        <input type="hidden" name="route" value="accounting/ledger">
        <select class="form-input" name="account_id" required>
            <option value="">— انتخاب حساب (معین) —</option>
            <?php foreach ($accounts as $a): ?>
                <option value="<?= (int) $a['id'] ?>" <?= $accountId === (int) $a['id'] ? 'selected' : '' ?>>
                    <?= e($a['code'] . ' — ' . $a['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="date_from" class="form-input" placeholder="از تاریخ" value="<?= e($from) ?>">
        <input type="text" name="date_to" class="form-input" placeholder="تا تاریخ" value="<?= e($to) ?>">
        <button class="btn btn-primary">نمایش دفتر</button>
    </form>
</div>

<?php if ($accountId && !$account): ?>
    <div class="alert alert-error">حساب انتخابی معتبر نیست یا سرگروه است.</div>
<?php elseif ($account): ?>
    <?php $debitPositive = in_array($account['type'], ['asset', 'expense'], true); ?>
    <div class="stat-grid">
        <div class="card stat">
            <div class="stat-label">جمع بدهکار</div>
            <div class="stat-value"><?= money($totalDebit) ?></div>
            <div class="stat-hint">دوره انتخابی</div>
        </div>
        <div class="card stat">
            <div class="stat-label">جمع بستانکار</div>
            <div class="stat-value"><?= money($totalCredit) ?></div>
            <div class="stat-hint">دوره انتخابی</div>
        </div>
        <div class="card stat">
            <div class="stat-label">مانده پایان دوره</div>
            <div class="stat-value <?= $finalBalance < 0 ? 'text-danger' : '' ?>"><?= money(abs($finalBalance)) ?></div>
            <div class="stat-hint"><?= $finalBalance >= 0 ? ($debitPositive ? 'بدهکار' : 'بستانکار') : ($debitPositive ? 'بستانکار' : 'بدهکار') ?></div>
        </div>
        <div class="card stat">
            <div class="stat-label">حساب</div>
            <div class="stat-value" style="font-size:14px"><?= e($account['code'] . ' — ' . $account['name']) ?></div>
            <div class="stat-hint">نوع: <?= e($types[$account['type']] ?? $account['type']) ?> — جهت: <?= $debitPositive ? 'بدهکارانه' : 'بستانکارانه' ?></div>
        </div>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>تاریخ</th><th>شماره سند</th><th>شرح</th><th>بدهکار</th><th>بستانکار</th><th>مانده</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?><tr><td colspan="6" class="empty">گردشی در این دوره یافت نشد.</td></tr><?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= jdate($r['voucher_date']) ?></td>
                        <td><a href="<?= url('accounting/voucher-view/' . $r['voucher_id']) ?>"><?= e($r['voucher_no']) ?></a></td>
                        <td><?= e($r['line_desc'] ?: $r['voucher_desc'] ?: '—') ?></td>
                        <td class="text-danger"><?= (float) $r['debit'] > 0 ? money($r['debit']) : '—' ?></td>
                        <td><?= (float) $r['credit'] > 0 ? money($r['credit']) : '—' ?></td>
                        <td><b><?= money(abs((float) $r['balance'])) ?></b> <small class="muted"><?= (float) $r['balance'] >= 0 ? ($debitPositive ? 'بد' : 'بس') : ($debitPositive ? 'بس' : 'بد') ?></small></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3" style="text-align:left">جمع کل</th>
                        <th><?= money($totalDebit) ?></th>
                        <th><?= money($totalCredit) ?></th>
                        <th><?= money(abs($finalBalance)) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="hint-box">یک حساب معین و (اختیاری) بازه تاریخ شمسی انتخاب کنید. مانده‌ها فقط از اسناد ثبت‌شده (posted) محاسبه می‌شود؛ برای دارایی/هزینه جهت مانده بدهکارانه و برای بدهی/درآمد/حقوق صاحبان سهام بستانکارانه است.</div>
<?php endif; ?>
