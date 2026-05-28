<?php
// edit-order.php – редактирование заказа
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/cookies.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/db_functions.php';
require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/csrf.php';

$payload = getJWTFromCookie();
if (!$payload) {
    header('Location: login.php');
    exit;
}
$orderId = $payload['order_id'];
$login = $payload['login'];

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $pageData = loadFromCookies();
    if (empty($pageData['errors'])) {
        $orderData = getOrderByID($pdo, $orderId);
        if ($orderData) {
            $pageData['values'] = $orderData;
        } else {
            http_response_code(404);
            echo 'Заказ не найден';
            exit;
        }
    }
    $pageData['csrf_token'] = getOrCreateCSRFToken();
    renderEditOrder($pageData, $login, $orderData['status'] ?? 'new');
    exit;
}

if ($method === 'POST') {
    if (!validateCSRFToken()) {
        http_response_code(403);
        echo 'Request denied';
        exit;
    }

    // Обновляем только нужные поля (без address и delivery_date)
    $updateData = [
        'name' => trim($_POST['name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'bouquet' => trim($_POST['bouquet'] ?? ''),
        'comment' => trim($_POST['comment'] ?? ''),
        'address' => '',  // не используем
        'delivery_date' => null,  // не используем
        'agree' => true
    ];
    
    // Валидация
    $errors = [];
    if (empty($updateData['name'])) $errors['name'] = 'Имя обязательно';
    if (empty($updateData['phone'])) $errors['phone'] = 'Телефон обязателен';
    if (empty($updateData['bouquet'])) $errors['bouquet'] = 'Выберите букет';
    
    if (!empty($errors)) {
        saveErrorsToCookie($errors, $updateData);
        header('Location: edit-order.php');
        exit;
    }
    
    // Обновляем заказ
    $stmt = $pdo->prepare("
        UPDATE orders SET 
            customer_name = :name,
            customer_phone = :phone,
            customer_email = :email,
            bouquet_name = :bouquet,
            comment = :comment
        WHERE id = :id
    ");
    
    $result = $stmt->execute([
        ':name' => $updateData['name'],
        ':phone' => $updateData['phone'],
        ':email' => $updateData['email'] ?: null,
        ':bouquet' => $updateData['bouquet'],
        ':comment' => $updateData['comment'],
        ':id' => $orderId
    ]);
    
    if ($result) {
        // Очищаем старые ошибки и сохраняем успех
        deleteCookie('form_errors');
        setSessionCookie('form_success', '1');
        header('Location: edit-order.php');
        exit;
    } else {
        saveErrorsToCookie(['general' => 'Ошибка сохранения'], $updateData);
        header('Location: edit-order.php');
        exit;
    }
}

function renderEditOrder($pageData, $login, $status) {
    global $BOUQUETS;
    $values = $pageData['values'];
    $errors = $pageData['errors'];
    $success = getCookieValue('form_success') !== null;
    if ($success) deleteCookie('form_success');
    
    $csrfToken = htmlspecialchars($pageData['csrf_token']);
    
    $statusText = [
        'new' => '🟡 Новый',
        'processing' => '🟠 В обработке',
        'completed' => '✅ Выполнен',
        'cancelled' => '❌ Отменен'
    ][$status] ?? $status;
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мой заказ · Цветочная Элегантность</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #f3efe7;
            font-family: 'Montserrat', Georgia, serif;
            padding: 2rem;
        }
        .form-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e2d5c4;
            flex-wrap: wrap;
            gap: 10px;
        }
        h1 {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            color: #7A5C7A;
        }
        .status-badge {
            padding: 0.3rem 0.8rem;
            background: #f0f0f0;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        .user-info {
            background: #f8f9fa;
            padding: 0.8rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        .field { margin-bottom: 1.2rem; }
        label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #8b694c;
            margin-bottom: 0.3rem;
        }
        input, select, textarea {
            width: 100%;
            padding: 0.7rem;
            border: 1px solid #e2d5c4;
            border-radius: 10px;
            font-family: inherit;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #FF8FAB;
        }
        .field-error input, .field-error select { border-color: #e74c3c; }
        .error-msg { color: #e74c3c; font-size: 0.7rem; margin-top: 0.3rem; }
        .btn {
            width: 100%;
            background: #FF8FAB;
            color: white;
            border: none;
            padding: 0.8rem;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn:hover { background: #FF7A9B; transform: translateY(-2px); }
        .btn-secondary {
            background: transparent;
            border: 1px solid #e2d5c4;
            color: #8b694c;
            margin-top: 0.5rem;
            text-align: center;
            display: inline-block;
            width: auto;
            padding: 0.7rem 1.5rem;
            text-decoration: none;
        }
        .btn-secondary:hover { background: #f8f9fa; transform: none; }
        .success-banner {
            background: #d4edda;
            color: #155724;
            padding: 0.8rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            text-align: center;
        }
        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        .button-group .btn { flex: 1; }
        .button-group .btn-secondary { flex: 1; text-align: center; }
        @media (max-width: 600px) {
            body { padding: 1rem; }
            .form-container { padding: 1.2rem; }
            .topbar { flex-direction: column; text-align: center; }
            .button-group { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="form-container">
    <div class="topbar">
        <h1>🌸 Мой заказ</h1>
        <span class="status-badge"><?= htmlspecialchars($statusText) ?></span>
    </div>
    
    <div class="user-info">
        Вы вошли как <strong><?= htmlspecialchars($login) ?></strong>
    </div>

    <?php if ($success): ?>
        <div class="success-banner">✓ Данные заказа обновлены</div>
    <?php endif; ?>
    
    <?php if (isset($errors['general'])): ?>
        <div class="success-banner" style="background:#f8d7da; color:#721c24;">⚠️ <?= htmlspecialchars($errors['general']) ?></div>
    <?php endif; ?>

    <form action="edit-order.php" method="POST">
        <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">

        <div class="field <?= isset($errors['name']) ? 'field-error' : '' ?>">
            <label>Ваше имя *</label>
            <input type="text" name="name" value="<?= htmlspecialchars($values['name'] ?? '') ?>">
            <?php if (isset($errors['name'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['name']) ?></div>
            <?php endif; ?>
        </div>

        <div class="field <?= isset($errors['phone']) ? 'field-error' : '' ?>">
            <label>Телефон *</label>
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

        <div class="field <?= isset($errors['bouquet']) ? 'field-error' : '' ?>">
            <label>Букет *</label>
            <select name="bouquet">
                <option value="">-- Выберите букет --</option>
                <?php foreach ($BOUQUETS as $bouquet): ?>
                <option value="<?= htmlspecialchars($bouquet['name']) ?>" <?= ($values['bouquet'] ?? '') == $bouquet['name'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($bouquet['name']) ?> - <?= number_format($bouquet['price'], 0, '', ' ') ?> ₽
                </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['bouquet'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['bouquet']) ?></div>
            <?php endif; ?>
        </div>

        <div class="field">
            <label>Комментарий к заказу</label>
            <textarea name="comment" rows="3" placeholder="Ваши пожелания..."><?= htmlspecialchars($values['comment'] ?? '') ?></textarea>
        </div>

        <div class="button-group">
            <button type="submit" class="btn">Сохранить изменения</button>
            <a href="logout.php" class="btn-secondary">← На главную</a>
        </div>
    </form>
</div>
</body>
</html>
<?php
}
?>