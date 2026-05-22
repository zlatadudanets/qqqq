<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/cookies.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/db_functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/jwt.php';

$method = $_SERVER['REQUEST_METHOD'];

// GET: показать форму с данными из cookies
if ($method === 'GET') {
    $pageData = loadFromCookies();
    // Если есть new_credentials в cookies, показать их
    $newCreds = null;
    $rawCreds = getCookieValue('new_credentials');
    if ($rawCreds !== null) {
        decodeFromCookie($rawCreds, $newCreds);
        deleteCookie('new_credentials');
    }
    renderForm($pageData, $newCreds);
    exit;
}

// POST: обработка отправки
if ($method === 'POST') {
    list($formData, $errors) = validateFormData($_POST);
    if (!empty($errors)) {
        saveErrorsToCookie($errors, $formData);
        header('Location: form.php');
        exit;
    }

    $appId = saveToDatabase($pdo, $formData);
    if ($appId === false) {
        http_response_code(500);
        echo 'Internal server error. Try again later';
        exit;
    }

    $login = generateLogin();
    $password = generatePassword();
    $passwordHash = hashPassword($password);
    if (!saveCredentials($pdo, $appId, $login, $passwordHash)) {
        http_response_code(500);
        echo 'Internal server error';
        exit;
    }

    saveSuccessToCookie($formData);
    setSessionCookie('new_credentials', encodeToCookie(['login' => $login, 'password' => $password]));
    header('Location: form.php');
    exit;
}

http_response_code(405);
echo 'Method not allowed';

function renderForm($pageData, $newCreds) {
    global $ALL_LANGUAGES;
    $values = $pageData['values'];
    $errors = $pageData['errors'];
    $success = $pageData['success'];
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head><meta charset="UTF-8"><title>Анкета</title><style>/* стили как в Go */</style></head>
    <body>
    <div class="scroll-form">
        <h1>Анкета</h1>
        <?php if ($success): ?>
            <div class="success-banner">✓ Анкета успешно сохранена</div>
        <?php endif; ?>
        <form action="form.php" method="POST">
            <!-- поля аналогично Go шаблону, с htmlspecialchars -->
            <!-- для краткости структура полей описана в Go, здесь повторяем -->
        </form>
        <?php if ($newCreds): ?>
            <div class="credentials-banner">
                <h3>○ Анкета отправлена</h3>
                <p>Сохраните данные для входа — они показываются только один раз:</p>
                <div class="cred-row"><span class="cred-label">Логин</span><strong><?= htmlspecialchars($newCreds['login']) ?></strong></div>
                <div class="cred-row"><span class="cred-label">Пароль</span><strong><?= htmlspecialchars($newCreds['password']) ?></strong></div>
                <a href="login.php" class="btn-login">Войти →</a>
            </div>
        <?php endif; ?>
        <div class="hanko">⦿ 礼</div>
    </div>
    </body>
    </html>
    <?php
}
?>