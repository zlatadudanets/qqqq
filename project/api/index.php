<?php
// api/index.php – REST API для заказов
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db_functions.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../jwt.php';
require_once __DIR__ . '/../validation.php';

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function sendError($message, $statusCode = 400, $errors = null) {
    $response = ['success' => false, 'message' => $message];
    if ($errors) $response['errors'] = $errors;
    sendResponse($response, $statusCode);
}

$requestMethod = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($path, '/'));
$resourceId = $segments[2] ?? null;

// POST /api/orders – создание заказа
if ($requestMethod === 'POST' && !$resourceId) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) $data = $_POST;
    
    list($formData, $errors) = validateOrderData($data);
    if (!empty($errors)) {
        sendError('Ошибка валидации', 400, $errors);
    }
    
    $result = saveOrderToDatabase($pdo, $formData);
    if (!$result) {
        sendError('Ошибка сохранения заказа', 500);
    }
    
    $login = generateLogin();
    $password = generatePassword();
    $passwordHash = hashPassword($password);
    saveCredentials($pdo, $result['id'], $login, $passwordHash);
    
    sendResponse([
        'success' => true,
        'message' => 'Заказ создан',
        'order' => ['id' => $result['id'], 'number' => $result['number']],
        'user' => ['login' => $login, 'password' => $password, 'profile_url' => "/edit-order.php"]
    ], 201);
}

// PUT /api/orders/:id – обновление заказа
if ($requestMethod === 'PUT' && $resourceId) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = str_replace('Bearer ', '', $authHeader);
    $payload = validateJWT($token);
    
    if (!$payload || $payload['order_id'] != $resourceId) {
        sendError('Unauthorized', 401);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    list($formData, $errors) = validateOrderData($data);
    if (!empty($errors)) {
        sendError('Ошибка валидации', 400, $errors);
    }
    
    if (updateOrder($pdo, $resourceId, $formData)) {
        sendResponse(['success' => true, 'message' => 'Заказ обновлен']);
    } else {
        sendError('Ошибка обновления', 500);
    }
}

sendError('Method not allowed', 405);
?>