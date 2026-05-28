<?php
// admin.php – панель администратора
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/db_functions.php';
require_once __DIR__ . '/auth.php';
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
    $order = getOrderByID($pdo, $id);
    if (!$order) { http_response_code(404); echo 'Order not found'; exit; }
    renderAdminEdit($order, $id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit') {
    if (!validateCSRFToken()) { http_response_code(403); echo 'Request denied'; exit; }
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) { http_response_code(400); echo 'Invalid ID'; exit; }
    list($formData, $errors) = validateOrderData($_POST);
    if (!empty($errors)) {
        renderAdminEdit($formData, $id, $errors);
        exit;
    }
    if (updateOrder($pdo, $id, $formData)) {
        header('Location: admin.php');
        exit;
    } else {
        http_response_code(500);
        echo 'Update error';
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'status') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $status = $_GET['status'] ?? '';
    if ($id > 0 && in_array($status, ['new', 'processing', 'completed', 'cancelled'])) {
        updateOrderStatus($pdo, $id, $status);
    }
    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    if (!validateCSRFToken()) { http_response_code(403); echo 'Request denied'; exit; }
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) { http_response_code(400); echo 'Invalid ID'; exit; }
    if (deleteOrder($pdo, $id)) {
        header('Location: admin.php');
        exit;
    } else {
        http_response_code(500);
        echo 'Delete error';
        exit;
    }
}

$orders = getAllOrders($pdo);
$stats = getOrderStats($pdo);
$csrfToken = getOrCreateCSRFToken();

renderAdminList($orders, $stats, $csrfToken);

function renderAdminList($orders, $stats, $csrfToken) {
    $statusNames = ['new' => 'Новые', 'processing' => 'В обработке', 'completed' => 'Выполненные', 'cancelled' => 'Отмененные'];
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ панель · Заказы цветов</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #f3efe7;
            font-family: 'Montserrat', sans-serif;
            padding: 2rem;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        h1 {
            color: #7A5C7A;
            margin-bottom: 1rem;
        }
        .stats {
            display: flex;
            gap: 20px;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .stat-card {
            background: linear-gradient(135deg, #FFD6E7 0%, #E6E6FA 100%);
            border-radius: 15px;
            padding: 1rem 2rem;
            text-align: center;
            min-width: 100px;
        }
        .stat-card .number {
            font-size: 2rem;
            font-weight: bold;
            color: #7A5C7A;
        }
        .stat-card .label {
            font-size: 0.7rem;
            text-transform: uppercase;
            color: #8b694c;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        th {
            background: #FFD6E7;
            color: #7A5C7A;
            padding: 12px;
            text-align: left;
        }
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2d5c4;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-new { background: #FFE5B4; color: #856404; }
        .status-processing { background: #B0E0E6; color: #0c5460; }
        .status-completed { background: #C1F0C1; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .btn {
            padding: 4px 10px;
            font-size: 0.7rem;
            text-decoration: none;
            border-radius: 15px;
            border: none;
            cursor: pointer;
            background: #f0f0f0;
            color: #666;
            display: inline-block;
            margin: 2px;
        }
        .btn-primary { background: #FF8FAB; color: white; }
        .btn-danger { background: #f8d7da; color: #721c24; }
        .btn:hover { opacity: 0.8; }
        @media (max-width: 800px) {
            table, thead, tbody, th, td, tr { display: block; }
            th { display: none; }
            td { display: block; padding: 8px; }
            td:before {
                content: attr(data-label);
                font-weight: bold;
                display: inline-block;
                width: 100px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <h1>🌷 Управление заказами</h1>
    
    <div class="stats">
        <?php foreach ($statusNames as $key => $name): ?>
        <div class="stat-card">
            <div class="number"><?= $stats[$key] ?? 0 ?></div>
            <div class="label"><?= $name ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>№ заказа</th><th>Дата</th><th>Клиент</th><th>Телефон</th><th>Букет</th><th>Сумма</th><th>Статус</th><th>Действия</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $order): ?>
            <tr>
                <td data-label="№"><?= htmlspecialchars($order['order_number']) ?></td>
                <td data-label="Дата"><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
                <td data-label="Клиент"><?= htmlspecialchars($order['customer_name']) ?></td>
                <td data-label="Телефон"><?= htmlspecialchars($order['customer_phone']) ?></td>
                <td data-label="Букет"><?= htmlspecialchars(mb_substr($order['bouquet_name'], 0, 30)) ?></td>
                <td data-label="Сумма"><?= $order['bouquet_price'] ? number_format($order['bouquet_price'], 0, '', ' ') . ' ₽' : '-' ?></td>
                <td data-label="Статус">
                    <span class="status-badge status-<?= $order['status'] ?>">
                        <?= $statusNames[$order['status']] ?? $order['status'] ?>
                    </span>
                </td>
                <td data-label="Действия">
                    <a href="admin.php?action=edit&id=<?= $order['id'] ?>" class="btn btn-primary">✎</a>
                    <a href="admin.php?action=status&id=<?= $order['id'] ?>&status=processing" class="btn">В работу</a>
                    <a href="admin.php?action=status&id=<?= $order['id'] ?>&status=completed" class="btn btn-primary">✓</a>
                    <form style="display:inline" action="admin.php?action=delete&id=<?= $order['id'] ?>" method="POST" onsubmit="return confirm('Удалить заказ?')">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                        <button type="submit" class="btn btn-danger">🗑</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($orders)): ?>
            <tr><td colspan="8" style="text-align: center;">Заказов пока нет</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
<?php
}

function renderAdminEdit($orderData, $id, $errors = []) {
    global $BOUQUETS;
    $csrfToken = getOrCreateCSRFToken();
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактирование заказа #<?= $id ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #f3efe7; font-family: 'Montserrat', sans-serif; padding: 2rem; }
        .card { max-width: 600px; margin: 0 auto; background: white; border-radius: 20px; padding: 2rem; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        h2 { color: #7A5C7A; margin-bottom: 1.5rem; }
        .field { margin-bottom: 1rem; }
        label { display: block; font-size: 0.8rem; font-weight: 600; color: #8b694c; margin-bottom: 0.3rem; }
        input, select, textarea { width: 100%; padding: 0.7rem; border: 1px solid #e2d5c4; border-radius: 10px; font-family: inherit; }
        .btn { background: #FF8FAB; color: white; border: none; padding: 0.7rem 1.5rem; border-radius: 50px; cursor: pointer; margin-right: 0.5rem; }
        .btn-cancel { background: #ccc; color: #666; }
        .error-msg { color: #e74c3c; font-size: 0.7rem; margin-top: 0.3rem; }
    </style>
</head>
<body>
<div class="card">
    <h2>✎ Редактирование заказа #<?= $id ?></h2>
    <form action="admin.php?action=edit&id=<?= $id ?>" method="POST">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
        
        <div class="field"><label>Имя</label><input type="text" name="name" value="<?= htmlspecialchars($orderData['name'] ?? '') ?>"></div>
        <div class="field"><label>Телефон</label><input type="text" name="phone" value="<?= htmlspecialchars($orderData['phone'] ?? '') ?>"></div>
        <div class="field"><label>Email</label><input type="email" name="email" value="<?= htmlspecialchars($orderData['email'] ?? '') ?>"></div>
        <div class="field"><label>Букет</label><select name="bouquet"><?php foreach ($BOUQUETS as $b): ?><option value="<?= $b['name'] ?>" <?= ($orderData['bouquet'] ?? '') == $b['name'] ? 'selected' : '' ?>><?= $b['name'] ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Адрес</label><input type="text" name="address" value="<?= htmlspecialchars($orderData['address'] ?? '') ?>"></div>
        <div class="field"><label>Дата доставки</label><input type="date" name="delivery_date" value="<?= htmlspecialchars($orderData['delivery_date'] ?? '') ?>"></div>
        <div class="field"><label>Комментарий</label><textarea name="comment" rows="3"><?= htmlspecialchars($orderData['comment'] ?? '') ?></textarea></div>
        
        <button type="submit" class="btn">Сохранить</button>
        <a href="admin.php" class="btn btn-cancel" style="text-decoration: none;">Отмена</a>
    </form>
</div>
</body>
</html>
<?php
}
?>