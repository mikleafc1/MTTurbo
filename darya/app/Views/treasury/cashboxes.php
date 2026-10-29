<?php /** لیست صندوق‌ها و حساب‌های بانکی */ ?>
<div class="page-head">
    <h2>صندوق و بانک <small class="muted">(<?= fa_num(count($rows)) ?> مورد)</small></h2>
    <div class="page-actions">
        <?php if (can('treasury.manage')): ?><a class="btn btn-primary" href="<?= url('treasury/cashbox-form') ?>">+ صندوق / حساب جدید</a><?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>نام</th><th>نوع</th><th>بانک / شماره حساب</th><th>شبا / کارت</th><th>مانده</th><th>وضعیت</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="7" class="empty">صندوق یا حسابی ثبت نشده است.</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><b><?= e($r['name']) ?></b></td>
                    <td><?= $r['type'] === 'bank' ? '<span class="badge badge-amber">حساب بانکی</span>' : '<span class="badge badge-gray">صندوق نقدی</span>' ?></td>
                    <td><?= e($r['bank_name'] ?: '—') ?> <?= $r['account_number'] ? '<small class="muted" dir="ltr">' . e(fa_num($r['account_number'])) . '</small>' : '' ?></td>
                    <td class="muted" dir="ltr"><?= e(fa_num($r['iban'] ?: ($r['card_number'] ?: '—'))) ?></td>
                    <td class="<?= (float) $r['balance'] < 0 ? 'text-danger' : '' ?>"><b><?= money($r['balance']) ?></b></td>
                    <td><?= (int) $r['is_active'] ? status_label('active') : status_label('inactive') ?></td>
                    <td>
                        <?php if (can('treasury.manage')): ?>
                        <a class="btn btn-sm btn-outline" href="<?= url('treasury/cashbox-form/' . $r['id']) ?>">ویرایش</a>
                        <form method="post" action="<?= url('treasury/cashbox-delete/' . $r['id']) ?>" style="display:inline" data-confirm="«<?= e($r['name']) ?>» حذف شود؟">
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
<div class="hint-box">مانده صندوق‌ها فقط از طریق دریافت/پرداخت و وصول چک تغییر می‌کند و مستقیماً قابل ویرایش نیست.</div>
