<?php
// login.php – вход для клиентов
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/db_functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/csrf.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (getJWTFromCookie() !== null) {
        header('Location: edit-order.php');
        exit;
    }
    renderLogin('', getOrCreateCSRFToken());
    exit;
}

if ($method === 'POST') {
    if (!validateCSRFToken()) {
        http_response_code(403);
        echo 'Request denied';
        exit;
    }

    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($login === '' || $password === '') {
        renderLogin('Логин и пароль не могут быть пустыми', getOrCreateCSRFToken(true));
        exit;
    }

    $creds = findCredentialsByLogin($pdo, $login);
    if (!$creds || !checkPassword($password, $creds['password_hash'])) {
        renderLogin('Неверный логин или пароль', getOrCreateCSRFToken(true));
        exit;
    }

    $token = generateJWT($creds['order_id'], $login);
    setJWTCookie($token);
    header('Location: edit-order.php');
    exit;
}

function renderLogin($error, $csrfToken) {
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход · Цветочная Элегантность</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: linear-gradient(135deg, #FFD6E7 0%, #E6E6FA 100%);
            font-family: 'Montserrat', Georgia, serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .login-card {
            max-width: 400px;
            width: 100%;
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
        }
        h1 {
            font-family: 'Playfair Display', serif;
            color: #7A5C7A;
            margin-bottom: 1.5rem;
        }
        .field { margin-bottom: 1.2rem; text-align: left; }
        label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #8b694c;
            margin-bottom: 0.3rem;
        }
        input {
            width: 100%;
            padding: 0.7rem;
            border: 1px solid #e2d5c4;
            border-radius: 10px;
            font-family: inherit;
        }
        input:focus {
            outline: none;
            border-color: #FF8FAB;
        }
        .error-banner {
            background: #f8d7da;
            color: #721c24;
            padding: 0.8rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        .btn {
            width: 100%;
            background: #FF8FAB;
            color: white;
            border: none;
            padding: 0.8rem;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #FF7A9B;
            transform: translateY(-2px);
        }
        .links {
            margin-top: 1.5rem;
        }
        .links a {
            color: #98D7C2;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="login-card">
    <h1>🌸 Вход</h1>

    <?php if ($error): ?>
        <div class="error-banner"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form action="login.php" method="POST">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
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
        <a href="order.php">← Оформить новый заказ</a>
    </div>
</div>
</body>
</html>
<?php
}
?>