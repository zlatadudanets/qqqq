<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/db.php';

// Эмуляция mb_strlen, если расширение не установлено
if (!function_exists('mb_strlen')) {
    function mb_strlen($str, $encoding = 'UTF-8') {
        return preg_match_all('/./us', $str, $matches);
    }
}

$formData = [
    'name' => '', 'phone' => '', 'email' => '', 'birthdate' => '',
    'gender' => '', 'bio' => '', 'languages' => [], 'contract' => false
];
$errors = [];
$success = false;

// Обработка POST-запроса
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Валидация (аналогично Go)
    $formData = [
        'name' => trim($_POST['name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'birthdate' => trim($_POST['birthdate'] ?? ''),
        'gender' => trim($_POST['gender'] ?? ''),
        'bio' => trim($_POST['bio'] ?? ''),
        'languages' => $_POST['languages'] ?? [],
        'contract' => isset($_POST['contract'])
    ];

    // Name
    $name = $formData['name'];
    if ($name === '') {
        $errors['name'] = 'Name is required';
    } else {
        $len = mb_strlen($name, 'UTF-8');
        if ($len > 150) {
            $errors['name'] = 'Name must be at most 150 characters';
        } elseif (!preg_match('/^[\p{L} ]+$/u', $name)) {
            $errors['name'] = 'Name contains invalid characters';
        }
    }

    // Phone
    $phone = $formData['phone'];
    if ($phone === '') {
        $errors['phone'] = 'Phone is required';
    } elseif (!preg_match('/^\+?[0-9()\- ]{7,32}$/', $phone)) {
        $errors['phone'] = 'Phone format is invalid';
    }

    // Email
    $email = $formData['email'];
    if ($email === '') {
        $errors['email'] = 'Email is required';
    } elseif (!preg_match('/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/', $email)) {
        $errors['email'] = 'Email format is invalid';
    }

    // Birthdate
    $birthdate = $formData['birthdate'];
    if ($birthdate === '') {
        $errors['birthdate'] = 'Birthdate is required';
    } else {
        $date = DateTime::createFromFormat('Y-m-d', $birthdate);
        if (!$date || $date->format('Y-m-d') !== $birthdate) {
            $errors['birthdate'] = 'Birthdate format is invalid (expected YYYY-MM-DD)';
        } elseif ($date > new DateTime()) {
            $errors['birthdate'] = 'Birthdate cannot be in the future';
        }
    }

    // Gender
    if (!in_array($formData['gender'], ['male', 'female'], true)) {
        $errors['gender'] = "Gender must be 'male' or 'female'";
    }

    // Languages
    $languages = $formData['languages'];
    $validLangIds = array_map('strval', range(1, 12));
    if (empty($languages)) {
        $errors['languages'] = 'At least one language must be selected';
    } else {
        foreach ($languages as $lang) {
            if (!in_array((string)$lang, $validLangIds, true)) {
                $errors['languages'] = 'Invalid language selection';
                break;
            }
        }
    }

    // Bio
    if ($formData['bio'] === '') {
        $errors['bio'] = 'Bio is required';
    }

    // Contract
    if (!$formData['contract']) {
        $errors['contract'] = 'You must accept the contract';
    }

    // Если ошибок нет – сохраняем в БД
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                INSERT INTO applications (full_name, phone, email, birth_date, gender, biography, contract_accepted)
                VALUES (:full_name, :phone, :email, :birth_date, :gender, :biography, 1)
            ");
            $stmt->execute([
                ':full_name' => $formData['name'],
                ':phone' => $formData['phone'],
                ':email' => $formData['email'],
                ':birth_date' => $formData['birthdate'],
                ':gender' => $formData['gender'],
                ':biography' => $formData['bio']
            ]);
            $appId = $pdo->lastInsertId();

            $langStmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (:app_id, :lang_id)");
            foreach ($formData['languages'] as $langId) {
                $langStmt->execute([':app_id' => $appId, ':lang_id' => $langId]);
            }
            $pdo->commit();
            $success = true;
            // Очищаем данные формы после успеха
            $formData = [
                'name' => '', 'phone' => '', 'email' => '', 'birthdate' => '',
                'gender' => '', 'bio' => '', 'languages' => [], 'contract' => false
            ];
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors['general'] = 'Database error: ' . $e->getMessage();
        }
    }
}
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
            background-image: radial-gradient(circle at 10% 30%, rgba(140, 100, 70, 0.05) 2%, transparent 2.5%);
            background-size: 28px 28px;
        }
        .scroll-form {
            max-width: 680px;
            width: 100%;
            background: rgba(250, 245, 235, 0.85);
            border-left: 1px solid #dacbb8;
            border-right: 1px solid #dacbb8;
            padding: 2rem 2rem 2.2rem;
            box-shadow: 0 20px 30px -15px rgba(0,0,0,0.05), inset 0 0 0 1px rgba(255,250,240,0.6);
        }
        h1 {
            font-size: 1.8rem;
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
        .field-group { margin-bottom: 1.4rem; }
        .field-group label:first-child {
            display: block;
            font-size: 0.8rem;
            letter-spacing: 2px;
            color: #8b694c;
            margin-bottom: 0.4rem;
            font-weight: 400;
            text-transform: uppercase;
        }
        input, select, textarea {
            width: 100%;
            padding: 0.7rem 0.8rem;
            background: #fefaf5;
            border: 1px solid #e2d5c4;
            font-family: inherit;
            font-size: 0.9rem;
            color: #4a3924;
            transition: all 0.2s;
            outline: none;
            border-radius: 0px;
        }
        input:focus, select:focus, textarea:focus {
            border-color: #b28b6f;
            background: #ffffff;
        }
        .error-border input, .error-border select, .error-border textarea {
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
        .radio-group, .checkbox-group {
            display: flex;
            gap: 1.8rem;
            flex-wrap: wrap;
            align-items: center;
            margin-top: 0.3rem;
        }
        .radio-group label, .checkbox-group label {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 400;
            color: #6a4e2e;
            font-size: 0.9rem;
            cursor: pointer;
        }
        input[type="radio"], input[type="checkbox"] {
            width: auto;
            accent-color: #b87c4f;
            margin: 0;
        }
        select[multiple] {
            height: 150px;
            background: #fefaf5;
        }
        select[multiple] option {
            padding: 0.3rem 0.5rem;
            margin: 2px 0;
            font-size: 0.85rem;
        }
        select[multiple] option:checked {
            background: #e8ddd0 linear-gradient(0deg, #dccbb8 0%, #dccbb8 100%);
            color: #4a2e1a;
        }
        textarea { resize: vertical; min-height: 90px; }
        .btn-submit {
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
            margin-top: 0.8rem;
            background: rgba(210, 180, 140, 0.05);
            text-transform: uppercase;
        }
        .btn-submit:hover {
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
        .error-banner {
            background: #fef0e8;
            border: 1px solid #e2c8b8;
            padding: 0.6rem 1rem;
            color: #b16245;
            font-size: 0.8rem;
            margin-bottom: 1.5rem;
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
            h1 { font-size: 1.4rem; letter-spacing: 2px; }
            .radio-group { flex-direction: column; gap: 0.5rem; }
        }
    </style>
</head>
<body>
<div class="scroll-form">
    <h1>Анкета</h1>

    <?php if ($success): ?>
        <div class="success-banner">✓ Анкета успешно сохранена</div>
    <?php elseif (!empty($errors) && isset($errors['general'])): ?>
        <div class="error-banner"><?= htmlspecialchars($errors['general']) ?></div>
    <?php endif; ?>

    <form action="form.php" method="POST">
        <!-- ФИО -->
        <div class="field-group <?= isset($errors['name']) ? 'error-border' : '' ?>">
            <label>ФИО</label>
            <input type="text" name="name" value="<?= htmlspecialchars($formData['name']) ?>">
            <?php if (isset($errors['name'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['name']) ?></div>
            <?php endif; ?>
        </div>

        <!-- Телефон -->
        <div class="field-group <?= isset($errors['phone']) ? 'error-border' : '' ?>">
            <label>Телефон</label>
            <input type="tel" name="phone" value="<?= htmlspecialchars($formData['phone']) ?>">
            <?php if (isset($errors['phone'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['phone']) ?></div>
            <?php endif; ?>
        </div>

        <!-- Email -->
        <div class="field-group <?= isset($errors['email']) ? 'error-border' : '' ?>">
            <label>Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($formData['email']) ?>">
            <?php if (isset($errors['email'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['email']) ?></div>
            <?php endif; ?>
        </div>

        <!-- Дата рождения -->
        <div class="field-group <?= isset($errors['birthdate']) ? 'error-border' : '' ?>">
            <label>Дата рождения</label>
            <input type="date" name="birthdate" value="<?= htmlspecialchars($formData['birthdate']) ?>">
            <?php if (isset($errors['birthdate'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['birthdate']) ?></div>
            <?php endif; ?>
        </div>

        <!-- Пол -->
        <div class="field-group <?= isset($errors['gender']) ? 'error-border' : '' ?>">
            <label>Пол</label>
            <div class="radio-group">
                <label><input type="radio" name="gender" value="male" <?= $formData['gender'] === 'male' ? 'checked' : '' ?>> Мужской</label>
                <label><input type="radio" name="gender" value="female" <?= $formData['gender'] === 'female' ? 'checked' : '' ?>> Женский</label>
            </div>
            <?php if (isset($errors['gender'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['gender']) ?></div>
            <?php endif; ?>
        </div>

        <!-- Языки -->
        <div class="field-group <?= isset($errors['languages']) ? 'error-border' : '' ?>">
            <label>Любимый язык программирования</label>
            <select name="languages[]" multiple size="5">
                <option value="1" <?= in_array('1', $formData['languages']) ? 'selected' : '' ?>>Pascal</option>
                <option value="2" <?= in_array('2', $formData['languages']) ? 'selected' : '' ?>>C</option>
                <option value="3" <?= in_array('3', $formData['languages']) ? 'selected' : '' ?>>C++</option>
                <option value="4" <?= in_array('4', $formData['languages']) ? 'selected' : '' ?>>JavaScript</option>
                <option value="5" <?= in_array('5', $formData['languages']) ? 'selected' : '' ?>>PHP</option>
                <option value="6" <?= in_array('6', $formData['languages']) ? 'selected' : '' ?>>Python</option>
                <option value="7" <?= in_array('7', $formData['languages']) ? 'selected' : '' ?>>Java</option>
                <option value="8" <?= in_array('8', $formData['languages']) ? 'selected' : '' ?>>Haskell</option>
                <option value="9" <?= in_array('9', $formData['languages']) ? 'selected' : '' ?>>Clojure</option>
                <option value="10" <?= in_array('10', $formData['languages']) ? 'selected' : '' ?>>Prolog</option>
                <option value="11" <?= in_array('11', $formData['languages']) ? 'selected' : '' ?>>Scala</option>
                <option value="12" <?= in_array('12', $formData['languages']) ? 'selected' : '' ?>>Go</option>
            </select>
            <?php if (isset($errors['languages'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['languages']) ?></div>
            <?php endif; ?>
        </div>

        <!-- Биография -->
        <div class="field-group <?= isset($errors['bio']) ? 'error-border' : '' ?>">
            <label>Биография</label>
            <textarea name="bio"><?= htmlspecialchars($formData['bio']) ?></textarea>
            <?php if (isset($errors['bio'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['bio']) ?></div>
            <?php endif; ?>
        </div>

        <!-- Чекбокс контракта -->
        <div class="field-group <?= isset($errors['contract']) ? 'error-border' : '' ?>">
            <div class="checkbox-group">
                <label>
                    <input type="checkbox" name="contract" <?= $formData['contract'] ? 'checked' : '' ?>>
                    С контрактом ознакомлен
                </label>
            </div>
            <?php if (isset($errors['contract'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['contract']) ?></div>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn-submit">Сохранить</button>
    </form>
    <div class="hanko">⦿ 礼</div>
</div>
</body>
</html>