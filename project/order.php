<?php
// order.php – обрабатывает заказы из вашего index.html
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/cookies.php';

// Получаем данные из формы
$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$bouquet = trim($_POST['bouquet'] ?? '');
$comment = trim($_POST['message'] ?? ''); // в форме поле называется message
$agree = isset($_POST['agree']);

// Валидация
$errors = [];
if (empty($name)) $errors['name'] = 'Имя обязательно';
if (empty($phone)) $errors['phone'] = 'Телефон обязателен';
if (empty($bouquet)) $errors['bouquet'] = 'Выберите букет';
if (!$agree) $errors['agree'] = 'Необходимо согласие';

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => 'Ошибка валидации', 'errors' => $errors]);
    exit;
}

// Сохраняем заказ
$orderData = [
    'name' => $name,
    'phone' => $phone,
    'email' => $email ?: null,
    'bouquet' => $bouquet,
    'comment' => $comment,
    'address' => '',
    'delivery_date' => null,
    'agree' => true
];

$result = saveOrderToDatabase($pdo, $orderData);
if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Ошибка сохранения заказа']);
    exit;
}

// Генерируем логин и пароль
$login = generateLogin();
$password = generatePassword();
$passwordHash = hashPassword($password);
saveCredentials($pdo, $result['id'], $login, $passwordHash);

// Возвращаем успешный ответ с данными для входа
echo json_encode([
    'success' => true,
    'code' => 200,
    'message' => '✅ Заказ успешно оформлен!',
    'order_number' => $result['number'],
    'credentials' => [
        'login' => $login,
        'password' => $password
    ]
]);
?>