<?php
// form.php - полная замена CGI: отображение формы (GET) и обработка POST с cookies, валидацией, БД
session_start();
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/db.php';

// ---- Вспомогательные функции для cookies (аналог cookie.go) ----
function setSessionCookie($name, $value) {
    setcookie($name, $value, [
        'expires' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function setPersistentCookie($name, $value) {
    setcookie($name, $value, [
        'expires' => time() + 365 * 24 * 3600,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function deleteCookie($name) {
    setcookie($name, '', [
        'expires' => 1,
        'path' => '/',
        'httponly' => true
    ]);
}

function getCookieValue($name) {
    return isset($_COOKIE[$name]) ? $_COOKIE[$name] : null;
}

function encodeToCookie($value) {
    $json = json_encode($value);
    return urlencode($json);
}

function decodeFromCookie($encoded, &$target) {
    $decoded = urldecode($encoded);
    $data = json_decode($decoded, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $target = $data;
        return true;
    }
    return false;
}

function saveErrorsToCookie($errors, $formValues) {
    if (!empty($errors)) {
        $encoded = encodeToCookie($errors);
        setSessionCookie('form_errors', $encoded);
    }
    if (!empty($formValues)) {
        $encoded = encodeToCookie($formValues);
        setSessionCookie('form_values', $encoded);
    }
}

function saveSuccessToCookie($formValues) {
    $encoded = encodeToCookie($formValues);
    setPersistentCookie('form_values', $encoded);
    setSessionCookie('form_success', '1');
}

function loadFromCookies() {
    $data = [
        'values' => [],
        'errors' => [],
        'success' => false
    ];
    // Читаем form_values
    $rawValues = getCookieValue('form_values');
    if ($rawValues !== null) {
        decodeFromCookie($rawValues, $data['values']);
    }
    // Читаем form_errors
    $rawErrors = getCookieValue('form_errors');
    if ($rawErrors !== null) {
        decodeFromCookie($rawErrors, $data['errors']);
        deleteCookie('form_errors');
    }
    // Читаем form_success
    if (getCookieValue('form_success') !== null) {
        $data['success'] = true;
        deleteCookie('form_success');
    }
    return $data;
}

// ---- Список языков (аналог allLanguages из template.go) ----
$allLanguages = [
    ['id' => '1', 'name' => 'Pascal'],
    ['id' => '2', 'name' => 'C'],
    ['id' => '3', 'name' => 'C++'],
    ['id' => '4', 'name' => 'JavaScript'],
    ['id' => '5', 'name' => 'PHP'],
    ['id' => '6', 'name' => 'Python'],
    ['id' => '7', 'name' => 'Java'],
    ['id' => '8', 'name' => 'Haskell'],
    ['id' => '9', 'name' => 'Clojure'],
    ['id' => '10', 'name' => 'Prolog'],
    ['id' => '11', 'name' => 'Scala'],
    ['id' => '12', 'name' => 'Go'],
];

// ---- Функция валидации (аналог validate.go) ----
function validateFormData($post) {
    $data = [
        'name' => '',
        'phone' => '',
        'email' => '',
        'birthdate' => '',
        'gender' => '',
        'bio' => '',
        'languages' => [],
        'contract' => false
    ];
    $errors = [];

    // Name
    $name = trim($post['name'] ?? '');
    if ($name === '') {
        $errors['name'] = 'Name is required';
    } else {
        $len = mb_strlen($name, 'UTF-8');
        if ($len > 150) {
            $errors['name'] = 'Name must be at most 150 characters';
        } elseif (!preg_match('/^[\p{L} ]+$/u', $name)) {
            $errors['name'] = 'Name contains invalid characters';
        } else {
            $data['name'] = $name;
        }
    }

    // Phone
    $phone = trim($post['phone'] ?? '');
    if ($phone === '') {
        $errors['phone'] = 'Phone is required';
    } elseif (!preg_match('/^\+?[0-9()\- ]{7,32}$/', $phone)) {
        $errors['phone'] = 'Phone contains invalid characters';
    } else {
        $data['phone'] = $phone;
    }

    // Email
    $email = trim($post['email'] ?? '');
    if ($email === '') {
        $errors['email'] = 'Email is required';
    } elseif (strlen($email) > 255) {
        $errors['email'] = 'Email must be at most 255 characters';
    } elseif (!preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $email)) {
        $errors['email'] = 'Email format is invalid, try name@domain.com';
    } else {
        $data['email'] = $email;
    }

    // Birthdate
    $birthdate = trim($post['birthdate'] ?? '');
    if ($birthdate === '') {
        $errors['birthdate'] = 'Birthdate is required';
    } else {
        $date = DateTime::createFromFormat('Y-m-d', $birthdate);
        if (!$date || $date->format('Y-m-d') !== $birthdate) {
            $errors['birthdate'] = 'Birthdate format is invalid (expected YYYY-MM-DD)';
        } elseif ($date > new DateTime()) {
            $errors['birthdate'] = 'Birthdate cannot be in the future';
        } else {
            $data['birthdate'] = $birthdate;
        }
    }

    // Gender
    $gender = $post['gender'] ?? '';
    if (!in_array($gender, ['male', 'female'], true)) {
        $errors['gender'] = 'Gender must be \'male\' or \'female\'';
    } else {
        $data['gender'] = $gender;
    }

    // Languages
    $languages = $post['languages'] ?? [];
    if (!is_array($languages)) {
        $languages = [];
    }
    if (count($languages) === 0) {
        $errors['languages'] = 'At least one language must be selected';
    } else {
        $validIds = array_map('strval', range(1, 12));
        $allValid = true;
        foreach ($languages as $lang) {
            if (!in_array((string)$lang, $validIds, true)) {
                $errors['languages'] = 'Invalid language selection';
                $allValid = false;
                break;
            }
        }
        if ($allValid) {
            $data['languages'] = $languages;
        }
    }

    // Bio
    $bio = trim($post['bio'] ?? '');
    if ($bio === '') {
        $errors['bio'] = 'Bio is required';
    } else {
        $data['bio'] = $bio;
    }

    // Contract
    $contract = $post['contract'] ?? '';
    if ($contract === '') {
        $errors['contract'] = 'You must accept the contract';
    } elseif ($contract !== 'on') {
        $errors['contract'] = 'Invalid contract value';
    } else {
        $data['contract'] = true;
    }

    return [$data, $errors];
}

// ---- Сохранение в БД (аналог db.go) ----
function saveToDatabase($pdo, $data) {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO applications (full_name, phone, email, birth_date, gender, biography, contract_accepted)
            VALUES (:full_name, :phone, :email, :birth_date, :gender, :biography, 1)
        ");
        $stmt->execute([
            ':full_name' => $data['name'],
            ':phone' => $data['phone'],
            ':email' => $data['email'],
            ':birth_date' => $data['birthdate'],
            ':gender' => $data['gender'],
            ':biography' => $data['bio']
        ]);
        $appId = $pdo->lastInsertId();

        $langStmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (:app_id, :lang_id)");
        foreach ($data['languages'] as $langId) {
            $langStmt->execute([':app_id' => $appId, ':lang_id' => $langId]);
        }

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Database error: ' . $e->getMessage());
        return false;
    }
}

// ---- Рендеринг формы (аналог template.go) ----
function renderForm($pageData, $allLanguages) {
    $values = $pageData['values'];
    $errors = $pageData['errors'];
    $success = $pageData['success'];
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
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

        h1::before {
            content: "・";
            margin-right: 10px;
            color: #b87c4f;
        }
        h1::after {
            content: "・";
            margin-left: 10px;
            color: #b87c4f;
        }

        .field {
            margin-bottom: 1.4rem;
        }

        .field > label {
            display: block;
            font-size: 0.8rem;
            letter-spacing: 2px;
            color: #8b694c;
            margin-bottom: 0.4rem;
            font-weight: 400;
            text-transform: uppercase;
        }

        input[type="text"],
        input[type="tel"],
        input[type="email"],
        input[type="date"],
        select,
        textarea {
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

        input:focus,
        select:focus,
        textarea:focus {
            border-color: #b28b6f;
            background: #ffffff;
        }

        .field-error input,
        .field-error select,
        .field-error textarea {
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

        textarea {
            min-height: 90px;
            resize: vertical;
        }

        select[multiple] {
            height: 150px;
            background: #fefaf5;
        }

        select[multiple] option {
            padding: 0.3rem 0.5rem;
            margin: 2px 0;
        }

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

        .radio-group label,
        .checkbox-label {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 400;
            color: #6a4e2e;
            font-size: 0.9rem;
            cursor: pointer;
        }

        input[type="radio"],
        input[type="checkbox"] {
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
            body {
                padding: 1rem;
            }
            .scroll-form {
                padding: 1.2rem;
            }
            h1 {
                font-size: 1.5rem;
                letter-spacing: 2px;
            }
            .radio-group {
                flex-direction: column;
                gap: 0.5rem;
            }
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
                <label>
                    <input type="radio" name="gender" value="male" <?= (($values['gender'] ?? '') === 'male') ? 'checked' : '' ?>> Мужской
                </label>
                <label>
                    <input type="radio" name="gender" value="female" <?= (($values['gender'] ?? '') === 'female') ? 'checked' : '' ?>> Женский
                </label>
            </div>
            <?php if (isset($errors['gender'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['gender']) ?></div>
            <?php endif; ?>
        </div>

        <div class="field <?= isset($errors['languages']) ? 'field-error' : '' ?>">
            <label>Любимый язык программирования</label>
            <select name="languages[]" multiple>
                <?php foreach ($allLanguages as $lang): ?>
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
    </form>

    <div class="hanko">⦿ 礼</div>
</div>
</body>
</html>
<?php
}

// ---- Основная логика (handler) ----
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // GET: загружаем данные из cookies и показываем форму
    $pageData = loadFromCookies();
    renderForm($pageData, $allLanguages);
    exit;
}

if ($method === 'POST') {
    // POST: валидация, сохранение, установка cookies, редирект
    list($formData, $errors) = validateFormData($_POST);

    if (!empty($errors)) {
        // Ошибки: сохраняем ошибки и отправленные значения в cookies, редирект на GET
        saveErrorsToCookie($errors, $formData);
        header('Location: form.php');
        exit;
    }

    // Нет ошибок: сохраняем в БД
    $saved = saveToDatabase($pdo, $formData);
    if (!$saved) {
        // Ошибка БД – показываем 500 (упрощённо: можно записать ошибку и редирект)
        http_response_code(500);
        echo '<h2>Internal server error. Try again later</h2>';
        exit;
    }

    // Успех: сохраняем данные в persistent cookie, success cookie и редирект
    saveSuccessToCookie($formData);
    header('Location: form.php');
    exit;
}

// Любой другой метод – 405
http_response_code(405);
echo 'Method is not allowed';