<?php /** ورود به سامانه */ ?>
<form method="post" action="<?= url('login') ?>" class="auth-form">
    <?= csrf_field() ?>
    <label class="form-label">نام کاربری</label>
    <input type="text" name="username" class="form-input" required autofocus autocomplete="username">
    <label class="form-label">رمز عبور</label>
    <input type="password" name="password" class="form-input" required autocomplete="current-password">
    <button type="submit" class="btn btn-primary btn-block">ورود به داریا</button>
</form>
