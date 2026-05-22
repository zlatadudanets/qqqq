<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/db_functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/jwt.php';

$method = $_SERVER['REQUEST_METHOD'];

// GET – показать форму входа
if ($method === 'GET') {
    // Если уже есть JWT – редирект на edit.php
    if (getJWTFromCookie() !== null) {
        header('Location: edit.php');
        exit;
    }
    renderLogin('');
    exit;
}

// POST – обработка входа
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
    <head><meta charset="UTF-8"><title>Вход</title><style>/* стили из Go */</style></head>
    <body>
    <div class="scroll-card">
        <div class="emblem">⛩️</div>
        <h1>Вход</h1>
        <?php if ($error): ?>
            <div class="error-banner"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form action="login.php" method="POST">
            <div class="field"><label>Логин</label><input type="text" name="login" autocomplete="username"></div>
            <div class="field"><label>Пароль</label><input type="password" name="password" autocomplete="current-password"></div>
            <button type="submit" class="btn">Войти</button>
        </form>
        <div class="links"><a href="form.php">← заполнить новую анкету</a></div>
        <div class="hanko">⦿ 礼</div>
    </div>
    </body>
    </html>
    <?php
}
?>