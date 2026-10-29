<?php /* لایه چاپ (فاکتور/گزارش) — A4 */ ?>
<!doctype html>
<html dir="rtl" lang="fa">
<head>
<meta charset="utf-8">
<title><?= e($title ?? 'چاپ') ?></title>
<link rel="stylesheet" href="<?= asset('css/print.css') ?>">
</head>
<body onload="window.print()">
<?= $content ?>
</body>
</html>
