<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/db.php';

// Если это GET – показываем форму
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head><meta charset="UTF-8"><title>Анкета</title></head>
    <body>
    <form action="form.php" method="POST">
        <label>ФИО: <input type="text" name="name" required></label><br>
        <label>Телефон: <input type="tel" name="phone" required></label><br>
        <label>Email: <input type="email" name="email" required></label><br>
        <label>Дата рождения: <input type="date" name="birthdate" required></label><br>
        <label>Пол: 
            <input type="radio" name="gender" value="male"> Мужской
            <input type="radio" name="gender" value="female"> Женский
        </label><br>
        <label>Языки: 
            <select name="languages[]" multiple required>
                <option value="1">Pascal</option><option value="2">C</option><option value="3">C++</option>
                <option value="4">JavaScript</option><option value="5">PHP</option><option value="6">Python</option>
                <option value="7">Java</option><option value="8">Haskell</option><option value="9">Clojure</option>
                <option value="10">Prolog</option><option value="11">Scala</option><option value="12">Go</option>
            </select>
        </label><br>
        <label>Биография: <textarea name="bio" required></textarea></label><br>
        <label><input type="checkbox" name="contract" required> Согласен с контрактом</label><br>
        <button type="submit">Отправить</button>
    </form>
    </body>
    </html>
    <?php
    exit;
}

// POST – обработка
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<h2>Method not allowed</h2>';
    exit;
}

// Эмуляция mb_strlen, если расширение не загружено
if (!function_exists('mb_strlen')) {
    function mb_strlen($str, $encoding = null) {
        return preg_match_all('/./us', $str, $matches);
    }
}

$errors = [];
$data = [
    'name' => '', 'phone' => '', 'email' => '', 'birthdate' => '',
    'gender' => '', 'bio' => '', 'languages' => []
];

function getPostString($key, $default = '') {
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

// Name
$name = getPostString('name');
if ($name === '') {
    $errors[] = 'Name is required';
} else {
    $length = mb_strlen($name, 'UTF-8');
    if ($length > 150) {
        $errors[] = 'Name must be at most 150 characters';
    } elseif (!preg_match('/^[\p{L} ]+$/u', $name)) {
        $errors[] = 'Name contains invalid characters';
    } else {
        $data['name'] = $name;
    }
}

// Phone
$phone = getPostString('phone');
if ($phone === '') {
    $errors[] = 'Phone is required';
} elseif (!preg_match('/^\+?[0-9()\- ]{7,32}$/', $phone)) {
    $errors[] = 'Phone format is invalid';
} else {
    $data['phone'] = $phone;
}

// Email
$email = getPostString('email');
if ($email === '') {
    $errors[] = 'Email is required';
} elseif (!preg_match('/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/', $email)) {
    $errors[] = 'Email format is invalid';
} else {
    $data['email'] = $email;
}

// Birthdate
$birthdate = getPostString('birthdate');
if ($birthdate === '') {
    $errors[] = 'Birthdate is required';
} else {
    $date = DateTime::createFromFormat('Y-m-d', $birthdate);
    if (!$date || $date->format('Y-m-d') !== $birthdate) {
        $errors[] = 'Birthdate format is invalid (expected YYYY-MM-DD)';
    } else {
        $data['birthdate'] = $birthdate;
    }
}

// Gender
$gender = getPostString('gender');
$validGenders = ['male', 'female'];
if (!in_array($gender, $validGenders, true)) {
    $errors[] = "Gender must be 'male' or 'female'";
} else {
    $data['gender'] = $gender;
}

// Languages
$languages = isset($_POST['languages']) && is_array($_POST['languages']) ? $_POST['languages'] : [];
$validLanguageIDs = array_map('strval', range(1, 12));
if (empty($languages)) {
    $errors[] = 'At least one language must be selected';
} else {
    $allValid = true;
    foreach ($languages as $lang) {
        if (!in_array((string)$lang, $validLanguageIDs, true)) {
            $errors[] = 'Invalid language selection' . htmlspecialchars($lang);
            $allValid = false;
            break;
        }
    }
    if ($allValid) {
        $data['languages'] = $languages;
    }
}

// Bio
$bio = getPostString('bio');
if ($bio === '') {
    $errors[] = 'Bio is required';
} else {
    $data['bio'] = $bio;
}

// Contract
if (!isset($_POST['contract']) || $_POST['contract'] === '') {
    $errors[] = 'You must agree to the contract';
}

if (!empty($errors)) {
    echo '<h2>Errors:</h2><ul>';
    foreach ($errors as $err) {
        echo '<li>' . htmlspecialchars($err) . '</li>';
    }
    echo '</ul>';
    exit;
}

// Сохранение в БД
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
        ':biography' => $data['bio'],
    ]);
    $appId = $pdo->lastInsertId();

    $langStmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (:app_id, :lang_id)");
    foreach ($data['languages'] as $langId) {
        $langStmt->execute([':app_id' => $appId, ':lang_id' => $langId]);
    }
    $pdo->commit();
    echo '<h2>Application submitted successfully!</h2>';
} catch (PDOException $e) {
    $pdo->rollBack();
    echo '<h2>Database error:</h2><p>' . htmlspecialchars($e->getMessage()) . '</p>';
}
?>