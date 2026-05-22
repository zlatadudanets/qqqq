<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/db_functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/jwt.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (getJWTFromCookie() !== null) {
        header('Location: edit.php');
        exit;
    }
    renderLogin('');
    exit;
}

if ($method === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($login === '' || $password === '') {
        renderLogin('Login and password cannot be empty');
        exit;
    }

    $creds = findCredentialsByLogin($pdo, $login);
    if (!$creds || !checkPassword($password, $creds['password_hash'])) {
        renderLogin('Invalid login or password');
        exit;
    }

    $token = generateJWT($creds['application_id'], $login);
    setJWTCookie($token);
    header('Location: edit.php');
    exit;
}

http_response_code(405);
echo 'Method not allowed';

function renderLogin($error) {
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход · восточный стиль</title>
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
            max-width: 440px;
            width: 100%;
            background: rgba(250, 245, 235, 0.92);
            border-left: 1px solid #dacbb8;
            border-right: 1px solid #dacbb8;
            padding: 2rem 1.8rem 2.2rem;
            box-shadow: 0 20px 30px -15px rgba(0,0,0,0.05), inset 0 0 0 1px rgba(255,250,240,0.5);
            text-align: center;
        }
        .emblem {
            font-size: 2.8rem;
            margin-bottom: 0.8rem;
            display: inline-block;
            color: #8b694c;
        }
        h1 {
            font-size: 1.7rem;
            font-weight: 400;
            letter-spacing: 3px;
            color: #6a4e2e;
            margin-bottom: 1.5rem;
            padding-bottom: 0.4rem;
            border-bottom: 0.5px solid #e2d5c4;
            display: inline-block;
        }
        h1::before { content: "・"; margin-right: 8px; color: #b87c4f; }
        h1::after { content: "・"; margin-left: 8px; color: #b87c4f; }
        .field { margin-bottom: 1.2rem; text-align: left; }
        label {
            display: block;
            font-size: 0.75rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #8b694c;
            margin-bottom: 0.3rem;
        }
        input {
            width: 100%;
            padding: 0.7rem 0.8rem;
            background: #fefaf5;
            border: 1px solid #e2d5c4;
            font-family: inherit;
            font-size: 0.9rem;
            color: #4a3924;
            transition: all 0.2s;
            outline: none;
            border-radius: 0;
        }
        input:focus {
            border-color: #b28b6f;
            background: #ffffff;
        }
        .error-banner {
            background: #fef0e8;
            border: 1px solid #e2c8b8;
            padding: 0.6rem 1rem;
            color: #b16245;
            font-size: 0.8rem;
            margin-bottom: 1.5rem;
            letter-spacing: 1px;
        }
        .btn {
            width: 100%;
            background: transparent;
            border: 1px solid #b28b6f;
            padding: 0.7rem;
            font-size: 0.85rem;
            letter-spacing: 3px;
            color: #6a4e2e;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 0.5rem;
            text-transform: uppercase;
        }
        .btn:hover {
            background: #e8ddd0;
            border-color: #8b694c;
            letter-spacing: 4px;
        }
        .links {
            margin-top: 1.5rem;
            font-size: 0.75rem;
            letter-spacing: 1px;
        }
        .links a {
            color: #9e7b5e;
            text-decoration: none;
            border-bottom: 0.5px dotted #dacbb8;
            transition: color 0.2s;
        }
        .links a:hover {
            color: #6a4e2e;
            border-bottom-color: #8b694c;
        }
        .hanko {
            margin-top: 1.8rem;
            font-size: 0.65rem;
            color: #b28b6f;
            letter-spacing: 2px;
            font-family: monospace;
            border-top: 0.5px solid #e2d5c4;
            padding-top: 1rem;
            width: 60%;
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
    <div class="emblem">⛩️</div>
    <h1>Вход</h1>

    <?php if ($error): ?>
        <div class="error-banner"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form action="login.php" method="POST">
        <div class="field">
            <label>Логин</label>
            <input type="text" name="login" autocomplete="username">
        </div>
        <div class="field">
            <label>Пароль</label>
            <input type="password" name="password" autocomplete="current-password">
        </div>
        <button type="submit" class="btn">Войти</button>
    </form>

    <div class="links">
        <a href="form.php">← заполнить новую анкету</a>
    </div>
    <div class="hanko">⦿ 礼</div>
</div>
</body>
</html>
<?php
}
?>