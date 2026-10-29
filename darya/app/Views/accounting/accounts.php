<?php /** کدینگ حساب‌ها — درخت با تودرتویی بر اساس والد */ ?>
<div class="page-head">
    <h2>کدینگ حساب‌ها <small class="muted">(<?= fa_num(count($rows)) ?> حساب)</small></h2>
    <div class="page-actions">
        <?php if (can('accounting.manage')): ?><a class="btn btn-primary" href="<?= url('accounting/account-form') ?>">+ افزودن حساب</a><?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>کد</th><th>نام حساب</th><th>نوع</th><th>والد</th><th>گروه</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="6" class="empty">حسابی ثبت نشده است.</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="muted" dir="ltr"><?= e($r['code']) ?></td>
                    <td style="padding-inline-start: <?= (int) $r['depth'] * 22 ?>px">
                        <?= $r['is_group'] ? '<b>' . e($r['name']) . '</b>' : e($r['name']) ?>
                    </td>
                    <td><?= e($types[$r['type']] ?? $r['type']) ?></td>
                    <td class="muted"><?= e($r['parent_name'] ?: '—') ?></td>
                    <td><?= $r['is_group'] ? '<span class="badge badge-gray">گروه</span>' : '<span class="badge badge-green">معین</span>' ?></td>
                    <td>
                        <?php if (can('accounting.manage')): ?>
                        <a class="btn btn-sm btn-outline" href="<?= url('accounting/account-form/' . $r['id']) ?>">ویرایش</a>
                        <form method="post" action="<?= url('accounting/account-delete/' . $r['id']) ?>" style="display:inline" data-confirm="حساب «<?= e($r['name']) ?>» حذف شود؟">
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
<div class="hint-box">حساب‌های گروهی فقط برای ساختار درختی هستند و در اسناد قابل استفاده نیستند؛ سطرهای حسابداری باید روی حساب‌های معین ثبت شوند.</div>
