<?php
// admin.php – панель администратора (восточный стиль)
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/db_functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/config.php';

function authenticateBasic() {
    if (!isset($_SERVER['PHP_AUTH_USER'])) {
        header('WWW-Authenticate: Basic realm="Admin Panel"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Need authentication';
        exit;
    }
    $login = $_SERVER['PHP_AUTH_USER'];
    $password = $_SERVER['PHP_AUTH_PW'];
    global $pdo;
    $passwordHash = getAdminByLogin($pdo, $login);
    if (!$passwordHash || !checkPassword($password, $passwordHash)) {
        header('WWW-Authenticate: Basic realm="Admin Panel"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Invalid credentials';
        exit;
    }
}

authenticateBasic();

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'edit') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) { http_response_code(400); echo 'Invalid ID'; exit; }
    $appData = getApplicationByID($pdo, $id);
    if (!$appData) { http_response_code(404); echo 'Form not found'; exit; }
    renderAdminEdit($appData, $id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit') {
    if (!validateCSRFToken()) { http_response_code(403); echo 'Request denied'; exit; }
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) { http_response_code(400); echo 'Invalid ID'; exit; }
    list($formData, $errors) = validateFormData($_POST);
    if (!empty($errors)) {
        renderAdminEdit($formData, $id, $errors);
        exit;
    }
    if (updateApplication($pdo, $id, $formData)) {
        header('Location: admin.php');
        exit;
    } else {
        http_response_code(500);
        echo 'Update error';
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    if (!validateCSRFToken()) { http_response_code(403); echo 'Request denied'; exit; }
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) { http_response_code(400); echo 'Invalid ID'; exit; }
    if (deleteApplication($pdo, $id)) {
        header('Location: admin.php');
        exit;
    } else {
        http_response_code(500);
        echo 'Delete error';
        exit;
    }
}

$applications = getAllApplications($pdo);
$stats = getLanguageStats($pdo);
$csrfToken = getOrCreateCSRFToken();

renderAdminList($applications, $stats, $csrfToken);

function renderAdminList($apps, $stats, $csrfToken) {
    $maxCount = 0;
    foreach ($stats as $s) if ($s['count'] > $maxCount) $maxCount = $s['count'];
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ панель · восточный стиль</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #f3efe7;
            font-family: 'Noto Serif SC', 'Noto Serif JP', 'Times New Roman', '游明朝', 'Yu Mincho', Georgia, serif;
            padding: 2rem;
            background-image: radial-gradient(circle at 10% 30%, rgba(140, 100, 70, 0.04) 2%, transparent 2.5%);
            background-size: 28px 28px;
            color: #3e2e20;
        }
        .scroll-container {
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
            background: rgba(250, 245, 235, 0.92);
            border-left: 1px solid #dacbb8;
            border-right: 1px solid #dacbb8;
            padding: 2rem 2rem 2.5rem;
            box-shadow: 0 20px 30px -15px rgba(0,0,0,0.05), inset 0 0 0 1px rgba(255,250,240,0.5);
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(250, 245, 235, 0.8);
            border-left: 1px solid #dacbb8;
            border-right: 1px solid #dacbb8;
            padding: 0.6rem 1.5rem;
            margin-bottom: 1.5rem;
        }
        .topbar h1 { font-size: 1.4rem; color: #6a4e2e; letter-spacing: 2px; margin: 0; border: none; }
        .topbar h1::before, .topbar h1::after { content: none; }
        .topbar a { font-size: 0.75rem; letter-spacing: 1px; color: #8b694c; text-decoration: none; border: 0.5px solid #dacbb8; padding: 0.2rem 0.8rem; transition: all 0.2s; }
        .topbar a:hover { background: #e8ddd0; color: #4a3924; }
        .card {
            background: rgba(250, 245, 235, 0.6);
            border-left: 1px solid #dacbb8;
            border-right: 1px solid #dacbb8;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        h2 {
            font-size: 1.3rem;
            font-weight: 400;
            letter-spacing: 2px;
            color: #6a4e2e;
            margin-bottom: 1rem;
            border-bottom: 0.5px solid #e2d5c4;
            display: inline-block;
        }
        h2::before { content: "・"; margin-right: 6px; color: #b87c4f; }
        h2::after { content: "・"; margin-left: 6px; color: #b87c4f; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }
        th {
            background: #e8ddd0;
            color: #6a4e2e;
            padding: 8px 10px;
            text-align: left;
            font-weight: 500;
            letter-spacing: 1px;
        }
        td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2d5c4;
            vertical-align: top;
        }
        .lang-badge {
            display: inline-block;
            background: #efe4d8;
            color: #6a4e2e;
            padding: 2px 8px;
            font-size: 0.7rem;
            margin: 2px;
            border: 0.5px solid #dacbb8;
        }
        .btn {
            display: inline-block;
            padding: 4px 12px;
            font-size: 0.7rem;
            letter-spacing: 1px;
            text-decoration: none;
            background: transparent;
            border: 1px solid #b28b6f;
            color: #6a4e2e;
            transition: all 0.2s;
            font-family: monospace;
        }
        .btn:hover { background: #e8ddd0; border-color: #8b694c; }
        .btn-delete { border-color: #dbb8a8; }
        .btn-delete:hover { background: #f0e0d8; border-color: #b87c5a; }
        .stat-row {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            gap: 12px;
        }
        .stat-name { width: 120px; font-weight: 500; color: #8b694c; }
        .stat-bar-wrap {
            flex: 1;
            background: #e8ddd0;
            height: 16px;
            overflow: hidden;
        }
        .stat-bar {
            height: 100%;
            background: #b87c4f;
            width: 0%;
        }
        .stat-count { width: 35px; text-align: right; color: #6a4e2e; }
        .edit-form .field { margin-bottom: 1rem; }
        .edit-form label {
            display: block;
            font-size: 0.75rem;
            letter-spacing: 1px;
            color: #8b694c;
            margin-bottom: 0.3rem;
            text-transform: uppercase;
        }
        .edit-form input, .edit-form select, .edit-form textarea {
            width: 100%;
            padding: 0.5rem 0.7rem;
            background: #fefaf5;
            border: 1px solid #e2d5c4;
            font-family: inherit;
        }
        .edit-form .field-error input { border-color: #c9826b; background: #fffaf5; }
        .edit-form .error-msg { font-size: 0.7rem; color: #b16245; margin-top: 0.2rem; }
        .edit-form .radio-group { display: flex; gap: 1rem; margin-top: 0.2rem; }
        .hanko { text-align: center; margin-top: 2rem; font-size: 0.65rem; color: #b28b6f; letter-spacing: 2px; border-top: 0.5px solid #e2d5c4; padding-top: 1rem; width: 40%; margin-left: auto; margin-right: auto; }
        @media (max-width: 800px) {
            table, thead, tbody, th, td, tr { display: block; }
            th { display: none; }
            td { display: block; padding: 6px 8px; border-bottom: none; }
            td:before { content: attr(data-label); font-weight: bold; display: inline-block; width: 100px; color: #8b694c; }
            .topbar { flex-direction: column; gap: 0.5rem; text-align: center; }
        }
    </style>
</head>
<body>
<div class="scroll-container">
    <div class="topbar">
        <h1>⚙️ Панель администратора</h1>
        <a href="../task6/index.php">← На главную</a>
    </div>

    <div class="card">
        <h2>📋 Все анкеты (<?= count($apps) ?>)</h2>
        <?php if ($apps): ?>
        <table>
            <thead><tr><th>ID</th><th>ФИО</th><th>Телефон</th><th>Email</th><th>Дата рождения</th><th>Пол</th><th>Языки</th><th>Действия</th></tr></thead>
            <tbody>
            <?php foreach ($apps as $app): ?>
            <tr>
                <td data-label="ID"><?= htmlspecialchars($app['id']) ?></td>
                <td data-label="ФИО"><?= htmlspecialchars($app['name']) ?></td>
                <td data-label="Телефон"><?= htmlspecialchars($app['phone']) ?></td>
                <td data-label="Email"><?= htmlspecialchars($app['email']) ?></td>
                <td data-label="Дата рождения"><?= htmlspecialchars($app['birthdate']) ?></td>
                <td data-label="Пол"><?= $app['gender'] === 'male' ? 'Мужской' : 'Женский' ?></td>
                <td data-label="Языки"><?php foreach ($app['languages'] as $lang): ?><span class="lang-badge"><?= htmlspecialchars($lang) ?></span><?php endforeach; ?></td>
                <td data-label="Действия">
                    <a href="admin.php?action=edit&id=<?= $app['id'] ?>" class="btn">✎ Изменить</a>
                    <form style="display:inline" action="admin.php?action=delete&id=<?= $app['id'] ?>" method="POST" onsubmit="return confirm('Удалить анкету #<?= $app['id'] ?>?')">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                        <button type="submit" class="btn btn-delete">🗑 Удалить</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p style="color:#9e7b5e; margin-top:1rem;">Анкет пока нет</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>📊 Статистика по языкам</h2>
        <?php foreach ($stats as $s): ?>
        <div class="stat-row">
            <span class="stat-name"><?= htmlspecialchars($s['name']) ?></span>
            <div class="stat-bar-wrap"><div class="stat-bar" style="width: <?= $maxCount > 0 ? round($s['count'] / $maxCount * 100) : 0 ?>%"></div></div>
            <span class="stat-count"><?= $s['count'] ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="hanko">⦿ 管</div>
</div>
</body>
</html>
<?php
}

function renderAdminEdit($appData, $id, $errors = []) {
    global $ALL_LANGUAGES;
    $csrfToken = getOrCreateCSRFToken();
    $isSelected = function($langId) use ($appData) {
        return in_array($langId, $appData['languages'] ?? []);
    };
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактирование анкеты #<?= $id ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #f3efe7;
            font-family: 'Noto Serif SC', 'Noto Serif JP', 'Times New Roman', '游明朝', 'Yu Mincho', Georgia, serif;
            padding: 2rem;
            background-image: radial-gradient(circle at 10% 30%, rgba(140, 100, 70, 0.04) 2%, transparent 2.5%);
        }
        .scroll-card {
            max-width: 720px;
            margin: 0 auto;
            background: rgba(250, 245, 235, 0.92);
            border-left: 1px solid #dacbb8;
            border-right: 1px solid #dacbb8;
            padding: 2rem;
        }
        h1 {
            font-size: 1.6rem;
            font-weight: 400;
            letter-spacing: 2px;
            color: #6a4e2e;
            text-align: center;
            margin-bottom: 1.5rem;
            border-bottom: 0.5px solid #e2d5c4;
            display: inline-block;
            width: auto;
            margin-left: auto;
            margin-right: auto;
        }
        h1::before { content: "・"; margin-right: 8px; color: #b87c4f; }
        h1::after { content: "・"; margin-left: 8px; color: #b87c4f; }
        .field { margin-bottom: 1.2rem; }
        label {
            display: block;
            font-size: 0.75rem;
            letter-spacing: 1px;
            color: #8b694c;
            margin-bottom: 0.3rem;
            text-transform: uppercase;
        }
        input, select, textarea {
            width: 100%;
            padding: 0.6rem 0.8rem;
            background: #fefaf5;
            border: 1px solid #e2d5c4;
            font-family: inherit;
            font-size: 0.9rem;
            color: #4a3924;
        }
        input:focus, select:focus, textarea:focus { border-color: #b28b6f; background: #ffffff; }
        .field-error input { border-color: #c9826b; background: #fffaf5; }
        .error-msg { font-size: 0.7rem; color: #b16245; margin-top: 0.2rem; }
        .radio-group { display: flex; gap: 1.5rem; margin-top: 0.2rem; }
        .btn {
            display: inline-block;
            padding: 8px 20px;
            font-size: 0.8rem;
            letter-spacing: 2px;
            text-decoration: none;
            background: transparent;
            border: 1px solid #b28b6f;
            color: #6a4e2e;
            cursor: pointer;
            transition: all 0.2s;
            font-family: monospace;
        }
        .btn:hover { background: #e8ddd0; border-color: #8b694c; }
        .btn-save { margin-right: 10px; }
        .btn-cancel { border-color: #dacbb8; }
    </style>
</head>
<body>
<div class="scroll-card">
    <h1>✎ Редактирование #<?= $id ?></h1>
    <form action="admin.php?action=edit&id=<?= $id ?>" method="POST">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">

        <div class="field <?= isset($errors['name']) ? 'field-error' : '' ?>">
            <label>ФИО</label>
            <input type="text" name="name" value="<?= htmlspecialchars($appData['name'] ?? '') ?>">
            <?php if (isset($errors['name'])): ?><div class="error-msg"><?= htmlspecialchars($errors['name']) ?></div><?php endif; ?>
        </div>

        <div class="field <?= isset($errors['phone']) ? 'field-error' : '' ?>">
            <label>Телефон</label>
            <input type="tel" name="phone" value="<?= htmlspecialchars($appData['phone'] ?? '') ?>">
            <?php if (isset($errors['phone'])): ?><div class="error-msg"><?= htmlspecialchars($errors['phone']) ?></div><?php endif; ?>
        </div>

        <div class="field <?= isset($errors['email']) ? 'field-error' : '' ?>">
            <label>Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($appData['email'] ?? '') ?>">
            <?php if (isset($errors['email'])): ?><div class="error-msg"><?= htmlspecialchars($errors['email']) ?></div><?php endif; ?>
        </div>

        <div class="field <?= isset($errors['birthdate']) ? 'field-error' : '' ?>">
            <label>Дата рождения</label>
            <input type="date" name="birthdate" value="<?= htmlspecialchars($appData['birthdate'] ?? '') ?>">
            <?php if (isset($errors['birthdate'])): ?><div class="error-msg"><?= htmlspecialchars($errors['birthdate']) ?></div><?php endif; ?>
        </div>

        <div class="field <?= isset($errors['gender']) ? 'field-error' : '' ?>">
            <label>Пол</label>
            <div class="radio-group">
                <label><input type="radio" name="gender" value="male" <?= ($appData['gender'] ?? '') === 'male' ? 'checked' : '' ?>> Мужской</label>
                <label><input type="radio" name="gender" value="female" <?= ($appData['gender'] ?? '') === 'female' ? 'checked' : '' ?>> Женский</label>
            </div>
            <?php if (isset($errors['gender'])): ?><div class="error-msg"><?= htmlspecialchars($errors['gender']) ?></div><?php endif; ?>
        </div>

        <div class="field <?= isset($errors['languages']) ? 'field-error' : '' ?>">
            <label>Языки</label>
            <select name="languages[]" multiple>
                <?php foreach ($ALL_LANGUAGES as $lang): ?>
                <option value="<?= htmlspecialchars($lang['id']) ?>" <?= $isSelected($lang['id']) ? 'selected' : '' ?>><?= htmlspecialchars($lang['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['languages'])): ?><div class="error-msg"><?= htmlspecialchars($errors['languages']) ?></div><?php endif; ?>
        </div>

        <div class="field <?= isset($errors['bio']) ? 'field-error' : '' ?>">
            <label>Биография</label>
            <textarea name="bio"><?= htmlspecialchars($appData['bio'] ?? '') ?></textarea>
            <?php if (isset($errors['bio'])): ?><div class="error-msg"><?= htmlspecialchars($errors['bio']) ?></div><?php endif; ?>
        </div>

        <div class="field <?= isset($errors['contract']) ? 'field-error' : '' ?>">
            <label class="checkbox-label"><input type="checkbox" name="contract" <?= ($appData['contract'] ?? false) ? 'checked' : '' ?>> С контрактом ознакомлен(а)</label>
            <?php if (isset($errors['contract'])): ?><div class="error-msg"><?= htmlspecialchars($errors['contract']) ?></div><?php endif; ?>
        </div>

        <button type="submit" class="btn btn-save">Сохранить</button>
        <a href="admin.php" class="btn btn-cancel">Отмена</a>
    </form>
</div>
</body>
</html>
<?php
}
?>