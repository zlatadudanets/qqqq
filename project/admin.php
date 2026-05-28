<?php
// admin.php – панель администратора
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/db_functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/config.php';

// Логаут
if (isset($_GET['logout'])) {
    session_destroy();
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    header('HTTP/1.0 401 Unauthorized');
    header('Location: admin.php');
    exit;
}

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

// Редактирование заказа
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'edit') {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    if (!$order) { die('Заказ не найден'); }
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Редактирование заказа #<?= $id ?></title>
        <style>
            body { font-family: Arial; padding: 20px; background: #f5f5f5; }
            .form { max-width: 500px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; }
            input, select, textarea { width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 4px; }
            button { background: #FF8FAB; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        </style>
    </head>
    <body>
        <div class="form">
            <h2>Редактирование заказа #<?= $id ?></h2>
            <form method="POST" action="admin.php?action=edit&id=<?= $id ?>">
                <input type="hidden" name="_csrf" value="<?= getOrCreateCSRFToken() ?>">
                <label>Имя:</label>
                <input type="text" name="name" value="<?= htmlspecialchars($order['customer_name']) ?>" required>
                <label>Телефон:</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($order['customer_phone']) ?>" required>
                <label>Email:</label>
                <input type="email" name="email" value="<?= htmlspecialchars($order['customer_email']) ?>">
                <label>Букет:</label>
                <input type="text" name="bouquet" value="<?= htmlspecialchars($order['bouquet_name']) ?>" required>
                <label>Статус:</label>
                <select name="status">
                    <option value="new" <?= $order['status'] == 'new' ? 'selected' : ?>>Новый</option>
                    <option value="processing" <?= $order['status'] == 'processing' ? 'selected' : ?>>В обработке</option>
                    <option value="completed" <?= $order['status'] == 'completed' ? 'selected' : ?>>Выполнен</option>
                    <option value="cancelled" <?= $order['status'] == 'cancelled' ? 'selected' : ?>>Отменен</option>
                </select>
                <label>Комментарий:</label>
                <textarea name="comment" rows="3"><?= htmlspecialchars($order['comment']) ?></textarea>
                <button type="submit">Сохранить</button>
                <a href="admin.php">Отмена</a>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Сохранение редактирования
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit') {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("UPDATE orders SET customer_name=?, customer_phone=?, customer_email=?, bouquet_name=?, comment=?, status=? WHERE id=?");
    $stmt->execute([
        $_POST['name'], $_POST['phone'], $_POST['email'],
        $_POST['bouquet'], $_POST['comment'], $_POST['status'], $id
    ]);
    header('Location: admin.php');
    exit;
}

// Смена статуса
if ($action === 'status') {
    $id = (int)$_GET['id'];
    $status = $_GET['status'];
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
    header('Location: admin.php');
    exit;
}

// Удаление
if ($action === 'delete') {
    $id = (int)$_GET['id'];
    $pdo->prepare("DELETE FROM credentials_2 WHERE order_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$id]);
    header('Location: admin.php');
    exit;
}

// Получаем все заказы
$orders = $pdo->query("SELECT * FROM orders ORDER BY id DESC")->fetchAll();

// Статистика
$stats = [];
$statuses = ['new', 'processing', 'completed', 'cancelled'];
foreach ($statuses as $s) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status = ?");
    $stmt->execute([$s]);
    $stats[$s] = $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админ панель · Заказы цветов</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #f3efe7;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        .container {
            max-width: 1300px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        h1 { color: #7A5C7A; margin-bottom: 20px; border-bottom: 2px solid #FFD6E7; padding-bottom: 10px; }
        .stats {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        .stat-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 12px 25px;
            text-align: center;
            border: 1px solid #e2d5c4;
            min-width: 100px;
        }
        .stat-card .num { font-size: 28px; font-weight: bold; color: #FF8FAB; }
        .stat-card .label { font-size: 12px; color: #8b694c; }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: #FFD6E7;
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2d5c4;
        }
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-new { background: #FFE5B4; color: #856404; }
        .status-processing { background: #B0E0E6; color: #0c5460; }
        .status-completed { background: #C1F0C1; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .btn {
            display: inline-block;
            padding: 5px 12px;
            font-size: 12px;
            text-decoration: none;
            border-radius: 15px;
            margin: 2px;
            border: none;
            cursor: pointer;
        }
        .btn-edit { background: #FF8FAB; color: white; }
        .btn-status { background: #98D7C2; color: white; }
        .btn-delete { background: #f8d7da; color: #721c24; }
        .logout {
            float: right;
            background: #ccc;
            color: #666;
            padding: 5px 15px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 14px;
        }
        .logout:hover { background: #bbb; }
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
    <h1>🌷 Управление заказами <a href="?logout=1" class="logout" onclick="return confirm('Выйти?')">Выйти</a></h1>
    
    <div class="stats">
        <div class="stat-card"><div class="num"><?= $stats['new'] ?? 0 ?></div><div class="label">Новые</div></div>
        <div class="stat-card"><div class="num"><?= $stats['processing'] ?? 0 ?></div><div class="label">В обработке</div></div>
        <div class="stat-card"><div class="num"><?= $stats['completed'] ?? 0 ?></div><div class="label">Выполненные</div></div>
        <div class="stat-card"><div class="num"><?= $stats['cancelled'] ?? 0 ?></div><div class="label">Отмененные</div></div>
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
                <td data-label="Букет"><?= htmlspecialchars($order['bouquet_name']) ?></td>
                <td data-label="Сумма"><?= number_format($order['bouquet_price'], 0, '', ' ') ?> ₽</td>
                <td data-label="Статус"><span class="status status-<?= $order['status'] ?>"><?= $order['status'] ?></span></td>
                <td data-label="Действия">
                    <a href="admin.php?action=edit&id=<?= $order['id'] ?>" class="btn btn-edit">✎</a>
                    <a href="admin.php?action=status&id=<?= $order['id'] ?>&status=processing" class="btn btn-status">В работу</a>
                    <a href="admin.php?action=status&id=<?= $order['id'] ?>&status=completed" class="btn btn-status">✓</a>
                    <a href="admin.php?action=delete&id=<?= $order['id'] ?>" class="btn btn-delete" onclick="return confirm('Удалить?')">🗑</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($orders)): ?>
            <tr><td colspan="8" style="text-align:center;">Заказов пока нет</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>