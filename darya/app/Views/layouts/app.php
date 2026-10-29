<?php /* لایه اصلی مدیریت — RTL فارسی */ ?>
<!doctype html>
<html dir="rtl" lang="fa">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'داریا') ?> | داریا</title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body>
<div class="layout">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <span class="brand-logo">د</span>
            <span class="brand-text">داریا <small>حسابداری و مدیریت</small></span>
        </div>
        <nav class="sidebar-nav">
            <?php
            $menu = require BASE_PATH . '/config/menu.php';
            $current = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '', '/');
            foreach ($menu as $item):
                if (isset($item['children'])) {
                    $children = array_filter($item['children'], fn($c) => can($c['perm']));
                    if (!$children) continue;
                    ?>
                    <div class="nav-group">
                        <div class="nav-group-title"><span class="icon"><?= icon_svg($item['icon']) ?></span><?= e($item['label']) ?></div>
                        <?php foreach ($children as $c): ?>
                            <a href="<?= url($c['route']) ?>" class="nav-link sub"><?= e($c['label']) ?></a>
                        <?php endforeach; ?>
                    </div>
                    <?php
                } else {
                    if (!can($item['perm'])) continue;
                    ?>
                    <a href="<?= url($item['route']) ?>" class="nav-link">
                        <span class="icon"><?= icon_svg($item['icon']) ?></span><?= e($item['label']) ?>
                    </a>
                    <?php
                }
            endforeach;
            ?>
        </nav>
        <div class="sidebar-footer">نسخه ۱.۰.۰</div>
    </aside>

    <div class="main">
        <header class="topbar">
            <button class="btn-icon menu-toggle" id="menuToggle" aria-label="منو">☰</button>
            <div class="topbar-title"><?= e($title ?? '') ?></div>
            <div class="topbar-spacer"></div>
            <div class="topbar-date"><?= jdate(today()) ?></div>
            <div class="topbar-user">
                <span class="avatar"><?= e(mb_substr(\App\Core\Auth::user()['full_name'] ?? '؟', 0, 1)) ?></span>
                <span class="user-name"><?= e(\App\Core\Auth::user()['full_name'] ?? '') ?></span>
                <a href="<?= url('login/logout') ?>" class="btn btn-sm btn-outline-danger">خروج</a>
            </div>
        </header>

        <main class="content">
            <?php foreach (flash_get() as $f): ?>
                <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
            <?php endforeach; ?>

            <?= $content /* محتوای نمای ماژول */ ?>
        </main>

        <footer class="app-footer">
            <span>داریا — سامانه حسابداری و مدیریت کسب‌وکار</span>
            <span>© <?= fa_num(jdate(today(), 'Y')) ?></span>
        </footer>
    </div>
</div>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
