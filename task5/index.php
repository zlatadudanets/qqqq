<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/config.php';

$payload = getJWTFromCookie();
$isLoggedIn = ($payload !== null);
$login = $isLoggedIn ? htmlspecialchars($payload['login']) : '';

?><!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Анкета</title>
    <style>/* Стили из Go (сокращены для краткости, можно скопировать из Go шаблона) */</style>
</head>
<body>
<div class="scroll-card">
    <div class="emblem">☯</div>
    <h1>Анкетирование</h1>
    <div class="subtitle">заполните или войдите</div>

    <?php if ($isLoggedIn): ?>
        <div class="user-greeting">вы вошли как <strong><?= $login ?></strong></div>
        <a href="edit.php" class="btn btn-primary">✎ редактировать анкету</a>
        <div class="divider"></div>
        <a href="logout.php" class="btn btn-danger">выйти</a>
    <?php else: ?>
        <a href="form.php" class="btn btn-primary">📝 заполнить анкету</a>
        <a href="login.php" class="btn btn-secondary">🔐 войти</a>
    <?php endif; ?>
    <div class="hanko">⦿ 道</div>
</div>
</body>
</html>