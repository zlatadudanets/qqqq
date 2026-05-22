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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Анкета</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f3efe7;
            font-family: 'Noto Serif SC', 'Noto Serif JP', 'Times New Roman', '游明朝', 'Yu Mincho', Georgia, serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background-image: radial-gradient(circle at 10% 30%, rgba(140, 100, 70, 0.04) 2%, transparent 2.5%);
            background-size: 28px 28px;
        }

        .scroll-card {
            max-width: 480px;
            width: 100%;
            background: rgba(250, 245, 235, 0.92);
            border-left: 1px solid #dacbb8;
            border-right: 1px solid #dacbb8;
            padding: 2rem 1.8rem 2.2rem;
            box-shadow: 0 20px 30px -15px rgba(0,0,0,0.05), inset 0 0 0 1px rgba(255,250,240,0.5);
            text-align: center;
        }

        .emblem {
            font-size: 3rem;
            margin-bottom: 0.8rem;
            display: inline-block;
            color: #8b694c;
        }

        h1 {
            font-size: 1.8rem;
            font-weight: 400;
            letter-spacing: 3px;
            color: #6a4e2e;
            margin-bottom: 0.6rem;
            padding-bottom: 0.4rem;
            border-bottom: 0.5px solid #e2d5c4;
            display: inline-block;
        }

        h1::before {
            content: "・";
            margin-right: 8px;
            color: #b87c4f;
        }
        h1::after {
            content: "・";
            margin-left: 8px;
            color: #b87c4f;
        }

        .subtitle {
            font-size: 0.75rem;
            letter-spacing: 2px;
            color: #9e7b5e;
            margin-top: 0.5rem;
            margin-bottom: 1.8rem;
        }

        .user-greeting {
            background: #fefaf5;
            border: 1px solid #e2d5c4;
            padding: 0.6rem 1rem;
            color: #6a4e2e;
            font-size: 0.8rem;
            letter-spacing: 1px;
            margin-bottom: 1.5rem;
        }

        .user-greeting strong {
            color: #8b694c;
            font-weight: 500;
        }

        .btn {
            display: block;
            width: 100%;
            padding: 0.7rem;
            font-size: 0.85rem;
            letter-spacing: 2px;
            text-decoration: none;
            transition: all 0.2s;
            margin-bottom: 0.8rem;
            font-family: inherit;
            background: transparent;
            border: 1px solid #b28b6f;
            color: #6a4e2e;
            text-transform: uppercase;
        }

        .btn-primary {
            background: rgba(210, 180, 140, 0.08);
        }

        .btn-primary:hover {
            background: #e8ddd0;
            border-color: #8b694c;
            letter-spacing: 3px;
        }

        .btn-secondary {
            border-color: #dacbb8;
        }

        .btn-secondary:hover {
            background: #f0e8de;
            border-color: #b28b6f;
            letter-spacing: 3px;
        }

        .btn-danger {
            border-color: #dbb8a8;
        }

        .btn-danger:hover {
            background: #f0e0d8;
            border-color: #b87c5a;
            letter-spacing: 3px;
        }

        .divider {
            height: 0.5px;
            background: #e2d5c4;
            margin: 0.8rem 0 1.2rem;
        }

        .hanko {
            margin-top: 1.6rem;
            font-size: 0.65rem;
            color: #b28b6f;
            letter-spacing: 2px;
            font-family: monospace;
            border-top: 0.5px solid #e2d5c4;
            padding-top: 1rem;
            width: 70%;
            margin-left: auto;
            margin-right: auto;
        }

        @media (max-width: 500px) {
            body { padding: 1rem; }
            .scroll-card { padding: 1.2rem; }
            h1 { font-size: 1.4rem; }
        }
    </style>
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