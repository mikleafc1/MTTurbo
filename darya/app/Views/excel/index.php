<?php /** ورود/خروج اکسل — هاب */ ?>
<div class="page-head">
    <h2>ورود/خروج اکسل (CSV)</h2>
</div>

<div class="card">
    <div class="card-head"><h3>خروجی CSV (سازگار با Excel و LibreOffice)</h3></div>
    <p class="muted">فایل‌ها با کدگذاری UTF-8 و علامت BOM ساخته می‌شوند و مستقیماً در Excel/LibreOffice قابل باز کردن هستند.</p>
    <div class="page-actions">
        <a class="btn btn-primary" href="<?= url('excel/export/products') ?>"><?= icon_svg('box', 15) ?> خروجی کالاها</a>
        <a class="btn btn-primary" href="<?= url('excel/export/customers') ?>"><?= icon_svg('users', 15) ?> خروجی مشتریان</a>
        <a class="btn btn-primary" href="<?= url('excel/export/sales') ?>"><?= icon_svg('cart', 15) ?> خروجی فروش</a>
        <a class="btn btn-outline" href="<?= url('excel/export/inventory') ?>"><?= icon_svg('sheet', 15) ?> خروجی موجودی انبار</a>
    </div>
</div>

<?php if (can('excel.edit')): ?>
<div class="grid-2">
    <div class="card">
        <div class="card-head"><h3>ورود کالاها</h3><a class="btn btn-sm btn-outline" href="<?= url('excel/sample/products') ?>">دانلود فایل نمونه</a></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>ستون</th><th>توضیح</th></tr></thead>
                <tbody>
                    <tr><td>۱. کد کالا (SKU)</td><td class="muted">کلید بروزرسانی — کالای موجود با همین SKU بروز می‌شود</td></tr>
                    <tr><td>۲. نام کالا</td><td class="muted">الزامی</td></tr>
                    <tr><td>۳. دسته‌بندی</td><td class="muted">در نبود دسته، ساخته می‌شود</td></tr>
                    <tr><td>۴. واحد</td><td class="muted">پیش‌فرض: عدد</td></tr>
                    <tr><td>۵. قیمت خرید</td><td class="muted">عدد بدون جداکننده</td></tr>
                    <tr><td>۶. قیمت فروش</td><td class="muted">عدد بدون جداکننده</td></tr>
                    <tr><td>۷. حداقل موجودی</td><td class="muted">عدد</td></tr>
                    <tr><td>۸. موجودی</td><td class="muted">اختلاف با موجودی فعلی در کاردکس ثبت می‌شود</td></tr>
                </tbody>
            </table>
        </div>
        <a class="btn btn-outline btn-block" href="<?= url('excel/import-products') ?>">بارگذاری فایل کالاها</a>
    </div>

    <div class="card">
        <div class="card-head"><h3>ورود مشتریان</h3><a class="btn btn-sm btn-outline" href="<?= url('excel/sample/customers') ?>">دانلود فایل نمونه</a></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>ستون</th><th>توضیح</th></tr></thead>
                <tbody>
                    <tr><td>۱. نام کامل</td><td class="muted">الزامی</td></tr>
                    <tr><td>۲. موبایل</td><td class="muted">کلید بروزرسانی (اولویت اول)</td></tr>
                    <tr><td>۳. تلفن</td><td class="muted">اختیاری</td></tr>
                    <tr><td>۴. ایمیل</td><td class="muted">کلید بروزرسانی (اولویت دوم)</td></tr>
                    <tr><td>۵. شهر</td><td class="muted">اختیاری</td></tr>
                    <tr><td>۶. آدرس</td><td class="muted">اختیاری</td></tr>
                </tbody>
            </table>
        </div>
        <a class="btn btn-outline btn-block" href="<?= url('excel/import-customers') ?>">بارگذاری فایل مشتریان</a>
    </div>
</div>
<div class="hint-box">
    <b>نکته:</b> حداکثر حجم فایل ۲ مگابایت — پسوند CSV یا TXT — جداکننده , یا ; (خودکار تشخیص داده می‌شود) — کدگذاری UTF-8.
    اگر فایل را از Excel ذخیره می‌کنید گزینه «CSV UTF-8» را انتخاب کنید.
</div>
<?php else: ?>
<div class="alert alert-error">برای ورود اطلاعات از فایل، دسترسی «بارگذاری اکسل» (excel.edit) لازم است.</div>
<?php endif; ?>
