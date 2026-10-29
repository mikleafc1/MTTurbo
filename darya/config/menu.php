<?php
/** منوی مدیریت — نمایش بر اساس دسترسی کاربر */
return [
    ['label' => 'داشبورد', 'icon' => 'home', 'route' => 'dashboard', 'perm' => 'dashboard.view'],
    ['label' => 'فروش', 'icon' => 'cart', 'perm' => 'invoices.view', 'children' => [
        ['label' => 'فاکتورهای فروش', 'route' => 'invoices', 'perm' => 'invoices.view'],
        ['label' => 'فاکتورهای خرید', 'route' => 'purchases', 'perm' => 'invoices.view'],
        ['label' => 'فاکتورهای برگشتی', 'route' => 'invoices/returns', 'perm' => 'invoices.view'],
    ]],
    ['label' => 'انبار و کالا', 'icon' => 'box', 'perm' => 'products.view', 'children' => [
        ['label' => 'کالاها', 'route' => 'products', 'perm' => 'products.view'],
        ['label' => 'دسته‌بندی‌ها', 'route' => 'categories', 'perm' => 'products.view'],
        ['label' => 'کاردکس کالا', 'route' => 'stock/kardex', 'perm' => 'stock.view'],
        ['label' => 'اصلاح موجودی', 'route' => 'stock/adjust', 'perm' => 'stock.edit'],
        ['label' => 'انتقال بین انبار', 'route' => 'stock/transfer', 'perm' => 'stock.edit'],
        ['label' => 'انبارها', 'route' => 'warehouses', 'perm' => 'products.view'],
    ]],
    ['label' => 'حسابداری', 'icon' => 'book', 'perm' => 'accounting.view', 'children' => [
        ['label' => 'کدینگ حساب‌ها', 'route' => 'accounting/accounts', 'perm' => 'accounting.view'],
        ['label' => 'سند حسابداری', 'route' => 'accounting/vouchers', 'perm' => 'accounting.view'],
        ['label' => 'دفتر کل و معین', 'route' => 'accounting/ledger', 'perm' => 'accounting.view'],
    ]],
    ['label' => 'خزانه', 'icon' => 'wallet', 'perm' => 'treasury.view', 'children' => [
        ['label' => 'صندوق و بانک', 'route' => 'treasury/cashboxes', 'perm' => 'treasury.view'],
        ['label' => 'دریافت و پرداخت', 'route' => 'treasury/transactions', 'perm' => 'treasury.view'],
        ['label' => 'چک‌ها', 'route' => 'treasury/cheques', 'perm' => 'treasury.view'],
    ]],
    ['label' => 'افراد', 'icon' => 'users', 'perm' => 'customers.view', 'children' => [
        ['label' => 'مشتریان', 'route' => 'customers', 'perm' => 'customers.view'],
        ['label' => 'کارمندان', 'route' => 'employees', 'perm' => 'employees.view'],
        ['label' => 'مبلغان (موبوآردر)', 'route' => 'promoters', 'perm' => 'employees.view'],
    ]],
    ['label' => 'حقوق و دستمزد', 'icon' => 'badge', 'route' => 'payroll', 'perm' => 'payroll.view'],
    ['label' => 'گزارش‌ها', 'icon' => 'chart', 'route' => 'reports', 'perm' => 'reports.view'],
    ['label' => 'ورود/خروج اکسل', 'icon' => 'sheet', 'route' => 'excel', 'perm' => 'excel.view'],
    ['label' => 'همگام‌سازی', 'icon' => 'sync', 'route' => 'sync', 'perm' => 'sync.view'],
    ['label' => 'کاربران و نقش‌ها', 'icon' => 'shield', 'perm' => 'users.view', 'children' => [
        ['label' => 'کاربران', 'route' => 'users', 'perm' => 'users.view'],
        ['label' => 'نقش‌ها و دسترسی‌ها', 'route' => 'roles', 'perm' => 'users.view'],
    ]],
    ['label' => 'تنظیمات', 'icon' => 'gear', 'route' => 'settings', 'perm' => 'settings.view'],
];
