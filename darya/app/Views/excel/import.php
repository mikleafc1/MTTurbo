<?php /** فرم بارگذاری CSV — متغیرها: type (products|customers), columns */ ?>
<div class="page-head">
    <h2><?= $type === 'products' ? 'بارگذاری کالاها از CSV' : 'بارگذاری مشتریان از CSV' ?></h2>
    <a class="btn btn-outline" href="<?= url('excel') ?>">← ورود/خروج اکسل</a>
</div>

<div class="grid-2">
    <div class="card">
        <form method="post"
              action="<?= url($type === 'products' ? 'excel/store-import-products' : 'excel/store-import-customers') ?>"
              enctype="multipart/form-data">
            <?= csrf_field() ?>
            <label class="form-label">فایل CSV (حداکثر ۲ مگابایت — پسوند csv یا txt)</label>
            <input type="file" name="csv_file" class="form-input" accept=".csv,.txt" required>
            <div class="muted" style="font-size:12.5px;margin-top:8px">ردیف‌های خالی به‌صورت خودکار رد می‌شوند.</div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit">شروع بارگذاری</button>
                <a class="btn btn-outline" href="<?= url('excel') ?>">انصراف</a>
            </div>
        </form>
        <div class="hint-box">
            ردیف اول اگر سرستون (شامل «نام» یا «کد») باشد به‌صورت خودکار نادیده گرفته می‌شود.
            کالاها بر اساس <b>SKU</b> و مشتریان بر اساس <b>موبایل</b> یا <b>ایمیل</b> بروزرسانی/ایجاد می‌شوند.
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h3>ترتیب ستون‌های فایل</h3>
            <a class="btn btn-sm btn-outline" href="<?= url('excel/sample/' . ($type === 'products' ? 'products' : 'customers')) ?>">فایل نمونه</a>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>ستون</th><th>توضیح</th></tr></thead>
                <tbody>
                <?php foreach ($columns as $i => [$name, $desc]): ?>
                    <tr><td><?= fa_num($i + 1) ?>. <?= e($name) ?></td><td class="muted"><?= e($desc) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
