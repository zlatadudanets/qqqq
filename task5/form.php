<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/cookies.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/db_functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/jwt.php';

if (!function_exists('mb_strlen')) {
    function mb_strlen($str, $encoding = 'UTF-8') {
        return preg_match_all('/./us', $str, $matches);
    }
}


$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $pageData = loadFromCookies();
    $newCreds = null;
    $rawCreds = getCookieValue('new_credentials');
    if ($rawCreds !== null) {
        decodeFromCookie($rawCreds, $newCreds);
        deleteCookie('new_credentials');
    }
    renderForm($pageData, $newCreds);
    exit;
}

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
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Анкета · восточный стиль</title>
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
        .scroll-form {
            max-width: 720px;
            width: 100%;
            background: rgba(250, 245, 235, 0.92);
            border-left: 1px solid #dacbb8;
            border-right: 1px solid #dacbb8;
            padding: 2rem 2rem 2.2rem;
            box-shadow: 0 20px 30px -15px rgba(0,0,0,0.05), inset 0 0 0 1px rgba(255,250,240,0.5);
        }
        h1 {
            font-size: 1.9rem;
            font-weight: 400;
            letter-spacing: 4px;
            color: #6a4e2e;
            text-align: center;
            margin-bottom: 1.8rem;
            padding-bottom: 0.6rem;
            border-bottom: 0.5px solid #e2d5c4;
            display: inline-block;
            width: auto;
            margin-left: auto;
            margin-right: auto;
        }
        h1::before { content: "・"; margin-right: 10px; color: #b87c4f; }
        h1::after { content: "・"; margin-left: 10px; color: #b87c4f; }
        .field { margin-bottom: 1.4rem; }
        .field > label {
            display: block;
            font-size: 0.8rem;
            letter-spacing: 2px;
            color: #8b694c;
            margin-bottom: 0.4rem;
            font-weight: 400;
            text-transform: uppercase;
        }
        input[type="text"], input[type="tel"], input[type="email"], input[type="date"], select, textarea {
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
        input:focus, select:focus, textarea:focus {
            border-color: #b28b6f;
            background: #ffffff;
        }
        .field-error input, .field-error select, .field-error textarea {
            border-color: #c9826b;
            background: #fffaf5;
        }
        .error-msg {
            font-size: 0.7rem;
            color: #b16245;
            margin-top: 0.3rem;
            margin-left: 0.3rem;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .error-msg::before {
            content: "・";
            font-size: 0.8rem;
            color: #b87c4f;
        }
        textarea { min-height: 90px; resize: vertical; }
        select[multiple] {
            height: 150px;
            background: #fefaf5;
        }
        select[multiple] option { padding: 0.3rem 0.5rem; margin: 2px 0; }
        select[multiple] option:checked {
            background: #e8ddd0 linear-gradient(0deg, #dccbb8 0%, #dccbb8 100%);
            color: #4a2e1a;
        }
        .radio-group {
            display: flex;
            gap: 1.8rem;
            flex-wrap: wrap;
            align-items: center;
            margin-top: 0.3rem;
        }
        .radio-group label, .checkbox-label {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 400;
            color: #6a4e2e;
            font-size: 0.9rem;
            cursor: pointer;
        }
        input[type="radio"], input[type="checkbox"] {
            accent-color: #b87c4f;
            width: auto;
            margin: 0;
        }
        .btn {
            width: 100%;
            background: transparent;
            border: 1px solid #b28b6f;
            padding: 0.8rem;
            font-size: 0.9rem;
            letter-spacing: 3px;
            color: #6a4e2e;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 0.6rem;
            background: rgba(210, 180, 140, 0.05);
            text-transform: uppercase;
        }
        .btn:hover {
            background: #e8ddd0;
            border-color: #8b694c;
            color: #3e2a1a;
            letter-spacing: 4px;
        }
        .success-banner {
            background: #efe4d8;
            border: 1px solid #dacbb8;
            padding: 0.8rem 1rem;
            color: #6a4e2e;
            font-size: 0.85rem;
            margin-bottom: 1.8rem;
            text-align: center;
            letter-spacing: 1px;
        }
        .credentials-banner {
            background: #fefaf5;
            border: 1px solid #dacbb8;
            padding: 1.2rem;
            margin-top: 1.5rem;
            text-align: left;
        }
        .credentials-banner h3 {
            font-size: 0.9rem;
            color: #6a4e2e;
            margin-bottom: 0.5rem;
            letter-spacing: 1px;
        }
        .credentials-banner p {
            font-size: 0.75rem;
            color: #9e7b5e;
            margin-bottom: 0.8rem;
        }
        .cred-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        .cred-label {
            color: #8b694c;
            font-size: 0.75rem;
            width: 60px;
            letter-spacing: 1px;
        }
        .cred-row strong {
            font-family: monospace;
            font-size: 0.85rem;
            background: #efe4d8;
            padding: 0.2rem 0.6rem;
            letter-spacing: 0.5px;
            color: #6a4e2e;
            font-weight: normal;
        }
        .btn-login {
            display: inline-block;
            margin-top: 0.8rem;
            padding: 0.4rem 1.2rem;
            background: transparent;
            border: 1px solid #b28b6f;
            color: #6a4e2e;
            text-decoration: none;
            font-size: 0.75rem;
            letter-spacing: 2px;
            transition: all 0.2s;
            text-transform: uppercase;
        }
        .btn-login:hover {
            background: #e8ddd0;
            border-color: #8b694c;
            letter-spacing: 3px;
        }
        .hanko {
            text-align: center;
            margin-top: 1.8rem;
            font-size: 0.65rem;
            color: #b28b6f;
            letter-spacing: 2px;
            font-family: monospace;
            border-top: 0.5px solid #e2d5c4;
            padding-top: 1.2rem;
            width: 80%;
            margin-left: auto;
            margin-right: auto;
        }
        @media (max-width: 600px) {
            body { padding: 1rem; }
            .scroll-form { padding: 1.2rem; }
            h1 { font-size: 1.5rem; letter-spacing: 2px; }
            .radio-group { flex-direction: column; gap: 0.5rem; }
        }
    </style>
</head>
<body>
<div class="scroll-form">
    <h1>Анкета</h1>

    <?php if ($success): ?>
        <div class="success-banner">✓ Анкета успешно сохранена</div>
    <?php endif; ?>

    <form action="form.php" method="POST">
        <div class="field <?= isset($errors['name']) ? 'field-error' : '' ?>">
            <label>ФИО</label>
            <input type="text" name="name" value="<?= htmlspecialchars($values['name'] ?? '') ?>">
            <?php if (isset($errors['name'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['name']) ?></div>
            <?php endif; ?>
        </div>

        <div class="field <?= isset($errors['phone']) ? 'field-error' : '' ?>">
            <label>Телефон</label>
            <input type="tel" name="phone" value="<?= htmlspecialchars($values['phone'] ?? '') ?>">
            <?php if (isset($errors['phone'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['phone']) ?></div>
            <?php endif; ?>
        </div>

        <div class="field <?= isset($errors['email']) ? 'field-error' : '' ?>">
            <label>Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($values['email'] ?? '') ?>">
            <?php if (isset($errors['email'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['email']) ?></div>
            <?php endif; ?>
        </div>

        <div class="field <?= isset($errors['birthdate']) ? 'field-error' : '' ?>">
            <label>Дата рождения</label>
            <input type="date" name="birthdate" value="<?= htmlspecialchars($values['birthdate'] ?? '') ?>">
            <?php if (isset($errors['birthdate'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['birthdate']) ?></div>
            <?php endif; ?>
        </div>

        <div class="field <?= isset($errors['gender']) ? 'field-error' : '' ?>">
            <label>Пол</label>
            <div class="radio-group">
                <label><input type="radio" name="gender" value="male" <?= (($values['gender'] ?? '') === 'male') ? 'checked' : '' ?>> Мужской</label>
                <label><input type="radio" name="gender" value="female" <?= (($values['gender'] ?? '') === 'female') ? 'checked' : '' ?>> Женский</label>
            </div>
            <?php if (isset($errors['gender'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['gender']) ?></div>
            <?php endif; ?>
        </div>

        <div class="field <?= isset($errors['languages']) ? 'field-error' : '' ?>">
            <label>Любимый язык программирования</label>
            <select name="languages[]" multiple>
                <?php foreach ($ALL_LANGUAGES as $lang): ?>
                    <?php $selected = in_array($lang['id'], $values['languages'] ?? []) ? 'selected' : ''; ?>
                    <option value="<?= htmlspecialchars($lang['id']) ?>" <?= $selected ?>><?= htmlspecialchars($lang['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['languages'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['languages']) ?></div>
            <?php endif; ?>
        </div>

        <div class="field <?= isset($errors['bio']) ? 'field-error' : '' ?>">
            <label>Биография</label>
            <textarea name="bio"><?= htmlspecialchars($values['bio'] ?? '') ?></textarea>
            <?php if (isset($errors['bio'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['bio']) ?></div>
            <?php endif; ?>
        </div>

        <div class="field <?= isset($errors['contract']) ? 'field-error' : '' ?>">
            <label class="checkbox-label">
                <input type="checkbox" name="contract" <?= (($values['contract'] ?? false) === true) ? 'checked' : '' ?>> С контрактом ознакомлен(а)
            </label>
            <?php if (isset($errors['contract'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['contract']) ?></div>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn">Сохранить</button>

        <?php if ($newCreds): ?>
            <div class="credentials-banner">
                <h3>○ Анкета отправлена</h3>
                <p>Сохраните данные для входа — они показываются только один раз:</p>
                <div class="cred-row"><span class="cred-label">Логин</span><strong><?= htmlspecialchars($newCreds['login']) ?></strong></div>
                <div class="cred-row"><span class="cred-label">Пароль</span><strong><?= htmlspecialchars($newCreds['password']) ?></strong></div>
                <a href="login.php" class="btn-login">Войти →</a>
            </div>
        <?php endif; ?>
    </form>
    <div class="hanko">⦿ 礼</div>
</div>
</body>
</html>
<?php
}
?>