<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/cookies.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/db_functions.php';
require_once __DIR__ . '/jwt.php';

$payload = getJWTFromCookie();
if (!$payload) {
    header('Location: login.php');
    exit;
}
$applicationId = $payload['application_id'];
$login = $payload['login'];

$method = $_SERVER['REQUEST_METHOD'];

// GET – показать форму редактирования
if ($method === 'GET') {
    $pageData = loadFromCookies();
    if (empty($pageData['errors'])) {
        $appData = getApplicationByID($pdo, $applicationId);
        if ($appData) {
            $pageData['values'] = $appData;
        } else {
            http_response_code(500);
            echo 'Failed to load application data';
            exit;
        }
    }
    renderEdit($pageData, $login);
    exit;
}

// POST – обновление данных
if ($method === 'POST') {
    list($formData, $errors) = validateFormData($_POST);
    if (!empty($errors)) {
        saveErrorsToCookie($errors, $formData);
        header('Location: edit.php');
        exit;
    }
    if (!updateApplication($pdo, $applicationId, $formData)) {
        http_response_code(500);
        echo 'Internal server error';
        exit;
    }
    saveSuccessToCookie($formData);
    header('Location: edit.php');
    exit;
}

http_response_code(405);
echo 'Method not allowed';

function renderEdit($pageData, $login) {
    global $ALL_LANGUAGES;
    $values = $pageData['values'];
    $errors = $pageData['errors'];
    $success = $pageData['success'];
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head><meta charset="UTF-8"><title>Редактирование анкеты</title><style>/* стили из Go */</style></head>
    <body>
    <div class="topbar"><span class="topbar-user">Вы вошли как <strong><?= htmlspecialchars($login) ?></strong></span><a href="logout.php">Выйти</a></div>
    <div class="scroll-form">
        <h1>✎ Редактирование анкеты</h1>
        <?php if ($success): ?>
            <div class="success-banner">✓ Данные успешно обновлены</div>
        <?php endif; ?>
        <form action="edit.php" method="POST">
            <!-- поля формы с подстановкой значений и ошибок -->
        </form>
        <div class="hanko">⦿ 礼</div>
    </div>
    </body>
    </html>
    <?php
}
?>