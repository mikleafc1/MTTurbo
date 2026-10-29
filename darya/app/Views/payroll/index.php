<?php /** لیست حقوق کارمندان */
$months = [1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان', 9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'];
$qs = array_filter(['year' => $year, 'month' => $month], fn($v) => $v !== 0 && $v !== null);
$base = url('payroll') . '?' . http_build_query($qs) . ($qs ? '&' : '');
?>
<div class="page-head">
    <h2>حقوق و دستمزد <small class="muted">(<?= fa_num($total) ?> رکورد)</small></h2>
    <div class="page-actions">
        <?php if (can('payroll.edit')): ?><a class="btn btn-primary" href="<?= url('payroll/form') ?>">+ لیست حقوق جدید</a><?php endif; ?>
    </div>
</div>

<form method="get" action="<?= url('payroll') ?>" class="search-bar">
    <input type="hidden" name="route" value="payroll">
    <select class="form-input" name="year">
        <option value="0">همه سال‌ها</option>
        <?= jyear_options($year > 0 ? $year : (int) jdate(today(), 'Y')) ?>
    </select>
    <select class="form-input" name="month">
        <option value="0">همه ماه‌ها</option>
        <?php foreach ($months as $i => $label): ?>
            <option value="<?= $i ?>" <?= $month === $i ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-outline">فیلتر</button>
</form>

<div class="stat-grid">
    <div class="card stat">
        <div class="stat-label">جمع خالص پرداختی (فیلتر جاری)</div>
        <div class="stat-value"><?= money($sumNet) ?></div>
        <div class="stat-hint"><?= e(setting('currency', 'تومان')) ?></div>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>کارمند</th><th>دوره</th><th>حقوق پایه</th><th>اضافه‌کاری + پاداش</th><th>کسورات</th><th>خالص پرداختی</th><th>وضعیت</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="8" class="empty">لیست حقوقی یافت نشد.</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <?php
                $deductions = (float) $r['insurance_deduction'] + (float) $r['tax_deduction'] + (float) $r['other_deduction'];
                ?>
                <tr>
                    <td><b><?= e($r['employee_name'] ?? '—') ?></b> <?= $r['employee_position'] ? '<small class="muted">' . e($r['employee_position']) . '</small>' : '' ?></td>
                    <td><?= fa_num($r['period_year'] . '/' . str_pad((string) $r['period_month'], 2, '0', STR_PAD_LEFT)) ?></td>
                    <td><?= money($r['base_salary']) ?></td>
                    <td><?= money((float) $r['overtime'] + (float) $r['bonus']) ?></td>
                    <td class="text-danger"><?= money($deductions) ?></td>
                    <td><b><?= money($r['net_pay']) ?></b></td>
                    <td>
                        <?= status_label($r['status']) ?>
                        <?php if ($r['status'] === 'paid' && $r['paid_at']): ?><br><small class="muted"><?= jdate($r['paid_at'], 'Y/m/d H:i') ?></small><?php endif; ?>
                    </td>
                    <td style="white-space:nowrap">
                        <?php if ($r['status'] === 'draft' && can('payroll.edit')): ?>
                        <form method="post" action="<?= url('payroll/pay/' . $r['id']) ?>" style="display:inline" data-confirm="حقوق «<?= e($r['employee_name'] ?? '') ?>» برای دوره <?= fa_num($r['period_year'] . '/' . $r['period_month']) ?> پرداخت شود؟">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-primary">پرداخت</button>
                        </form>
                        <a class="btn btn-sm btn-outline" href="<?= url('payroll/form/' . $r['id']) ?>">ویرایش</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->render('partials/pagination', ['page' => $page, 'pages' => $pages, 'base' => $base]); ?>
<div class="hint-box">با زدن «پرداخت»، وضعیت به پرداخت‌شده تغییر می‌کند، تراکنش خزانه (انتقالی) ثبت و مانده اولین صندوق فعال کم می‌شود و سند حسابداری خودکار صادر می‌گردد.</div>
