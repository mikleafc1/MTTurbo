<?php /* لایه ورود */ ?>
<!doctype html>
<html dir="rtl" lang="fa">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ورود | داریا</title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="auth-body">
<div class="auth-box">
    <div class="auth-logo"><span class="brand-logo big">د</span></div>
    <h1>داریا</h1>
    <p class="auth-sub">سامانه حسابداری و مدیریت کسب‌وکار</p>
    <?php foreach (flash_get() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
    <p class="auth-foot">نسخه ۱.۰.۰</p>
</div>
</body>
</html>
