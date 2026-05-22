<?php
// form.php - обработчик POST запроса (полная замена form.go)
session_start();
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<h2>Method not allowed</h2>';
    exit;
}

$errors = [];
$data = [
    'name' => '',
    'phone' => '',
    'email' => '',
    'birthdate' => '',
    'gender' => '',
    'bio' => '',
    'languages' => [],
];

function getPostString($key, $default = '') {
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

// 1. Name
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

// 2. Phone
$phone = getPostString('phone');
if ($phone === '') {
    $errors[] = 'Phone is required';
} elseif (!preg_match('/^\+?[0-9()\- ]{7,32}$/', $phone)) {
    $errors[] = 'Phone format is invalid';
} else {
    $data['phone'] = $phone;
}

// 3. Email
$email = getPostString('email');
if ($email === '') {
    $errors[] = 'Email is required';
} elseif (!preg_match('/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/', $email)) {
    $errors[] = 'Email format is invalid';
} else {
    $data['email'] = $email;
}

// 4. Birthdate
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

// 5. Gender
$gender = getPostString('gender');
$validGenders = ['male', 'female'];
if (!in_array($gender, $validGenders, true)) {
    $errors[] = "Gender must be 'male' or 'female'";
} else {
    $data['gender'] = $gender;
}

// 6. Languages
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

// 7. Bio
$bio = getPostString('bio');
if ($bio === '') {
    $errors[] = 'Bio is required';
} else {
    $data['bio'] = $bio;
}

// 8. Contract
if (!isset($_POST['contract']) || $_POST['contract'] === '') {
    $errors[] = 'You must agree to the contract';
}

// Вывод ошибок (точно как в Go: "Erorrs:")
if (!empty($errors)) {
    echo '<h2>Erorrs:</h2><ul>';
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