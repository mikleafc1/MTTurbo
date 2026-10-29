<?php $title = 'داشبورد'; $this->requirePerm('dashboard.view'); ?>
<div class="page-head">
    <h2>داشبورد</h2>
    <div class="page-actions">
        <?php if (can('invoices.edit')): ?><a class="btn btn-primary" href="<?= url('invoices/create') ?>">+ فاکتور فروش</a><?php endif; ?>
        <?php if (can('stock.edit')): ?><a class="btn btn-outline" href="<?= url('stock/adjust') ?>">اصلاح موجودی</a><?php endif; ?>
    </div>
</div>

<div class="stat-grid">
    <div class="card stat">
        <div class="stat-label">فروش امروز</div>
        <div class="stat-value"><?= money($stats['sales_today']) ?></div>
        <div class="stat-hint"><?= e(setting('currency', 'تومان')) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">فروش این ماه</div>
        <div class="stat-value"><?= money($stats['sales_month']) ?></div>
        <div class="stat-hint"><?= e(setting('currency', 'تومان')) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">مطالبات (بدهی مشتریان)</div>
        <div class="stat-value text-danger"><?= money($stats['receivable']) ?></div>
        <div class="stat-hint"><?= fa_num($stats['unpaid_count']) ?> فاکتور باز</div>
    </div>
    <div class="card stat">
        <div class="stat-label">موجودی صندوق و بانک</div>
        <div class="stat-value"><?= money($stats['cash_total']) ?></div>
        <div class="stat-hint">خزانه فعال</div>
    </div>
    <div class="card stat">
        <div class="stat-label">سفارش‌های در انتظار تأیید</div>
        <div class="stat-value text-amber"><?= fa_num($stats['pending_orders']) ?></div>
        <div class="stat-hint">موبوآردر</div>
    </div>
    <div class="card stat">
        <div class="stat-label">سبدهای رهاشده</div>
        <div class="stat-value text-amber"><?= fa_num($stats['abandoned_carts']) ?></div>
        <div class="stat-hint">از سایت</div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-head">
            <h3>فروش ۶ ماه اخیر</h3>
        </div>
        <div class="chart-bars">
            <?php foreach ($chart as $bar): ?>
                <div class="chart-col" title="<?= money($bar['total']) ?>">
                    <div class="chart-bar" style="height: <?= max(2, (int) round($bar['total'] / $chartMax * 120)) ?>px"></div>
                    <div class="chart-label"><?= e($bar['label']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h3>کمبود موجودی</h3><a class="btn btn-sm btn-outline" href="<?= url('products?low=1') ?>">همه</a></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>کالا</th><th>موجودی</th><th>حداقل</th></tr></thead>
                <tbody>
                <?php if (!$lowStockRows): ?><tr><td colspan="3" class="empty">موجودی همه کالاها مناسب است ✅</td></tr><?php endif; ?>
                <?php foreach ($lowStockRows as $p): ?>
                    <tr>
                        <td><?= e($p['name']) ?><?= $p['sku'] ? ' <small class="muted">(' . e($p['sku']) . ')</small>' : '' ?></td>
                        <td class="text-danger"><?= fa_num(rtrim(rtrim((string)$p['stock_qty'], '0'), '.')) ?> <?= e($p['unit']) ?></td>
                        <td><?= fa_num(rtrim(rtrim((string)$p['min_stock'], '0'), '.')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-head"><h3>آخرین فاکتورها</h3><a class="btn btn-sm btn-outline" href="<?= url('invoices') ?>">همه</a></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>شماره</th><th>مشتری</th><th>مبلغ</th><th>وضعیت</th><th>تاریخ</th></tr></thead>
                <tbody>
                <?php foreach ($recentInvoices as $i): ?>
                    <tr>
                        <td><a href="<?= url('invoices/view/' . $i['id']) ?>"><?= e($i['invoice_no']) ?></a></td>
                        <td><?= e($i['customer_name'] ?? $i['supplier_name'] ?? '—') ?></td>
                        <td><?= money($i['total']) ?></td>
                        <td><?= status_label($i['status']) ?></td>
                        <td><?= jdate($i['invoice_date']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h3>وضعیت همگام‌سازی</h3><a class="btn btn-sm btn-outline" href="<?= url('sync') ?>">جزئیات</a></div>
        <?php if ($lastSync): ?>
            <div class="sync-status">
                <div class="sync-dot ok"></div>
                <div>
                    <div>آخرین رویداد: <b><?= e($lastSync['entity_type']) ?></b> — <?= e($lastSync['message'] ?? '') ?></div>
                    <div class="muted"><?= jdate($lastSync['created_at'], 'Y/m/d H:i') ?></div>
                </div>
            </div>
        <?php else: ?>
            <div class="sync-status">
                <div class="sync-dot idle"></div>
                <div>هنوز رویدادی از افزونه یا نرم‌افزار ویندوزی دریافت نشده است.</div>
            </div>
        <?php endif; ?>
        <div class="hint-box">
            <b>راهنما:</b> آدرس API داریا برای افزونه و نرم‌افزار ویندوزی:
            <code dir="ltr"><?= e(((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'your-domain') . str_replace('/index.php', '', dirname($_SERVER['SCRIPT_NAME'] ?? '')) . '/api/index.php?path=v1/…') ?></code>
        </div>
    </div>
</div>
