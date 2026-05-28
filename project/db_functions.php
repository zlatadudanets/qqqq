<?php
// db_functions.php – работа с заказами
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

function generateOrderNumber() {
    return 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

function saveOrderToDatabase($pdo, $data) {
    try {
        $pdo->beginTransaction();
        
        $orderNumber = generateOrderNumber();
        
        $stmt = $pdo->prepare("
            INSERT INTO orders (
                order_number, customer_name, customer_phone, customer_email,
                bouquet_name, bouquet_price, comment, status
            ) VALUES (
                :order_number, :name, :phone, :email,
                :bouquet, :price, :comment, 'new'
            )
        ");
        
        $bouquetName = $data['bouquet'];
        $price = null;
        
        global $BOUQUETS;
        foreach ($BOUQUETS as $b) {
            if ($b['name'] === $bouquetName || strpos($bouquetName, $b['name']) !== false) {
                $price = $b['price'];
                break;
            }
        }
        
        if (!$price && preg_match('/(\d+(?:\s?\d+)?)\s?₽/', $bouquetName, $matches)) {
            $price = (float) str_replace(' ', '', $matches[1]);
        }
        
        $stmt->execute([
            ':order_number' => $orderNumber,
            ':name' => $data['name'],
            ':phone' => $data['phone'],
            ':email' => $data['email'] ?? null,
            ':bouquet' => $bouquetName,
            ':price' => $price,
            ':comment' => $data['comment'] ?? ''
        ]);
        
        $orderId = $pdo->lastInsertId();
        $pdo->commit();
        
        return ['id' => $orderId, 'number' => $orderNumber];
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('saveOrderToDatabase error: ' . $e->getMessage());
        return false;
    }
}

function saveCredentials($pdo, $orderId, $login, $passwordHash) {
    $stmt = $pdo->prepare("INSERT INTO credentials_2 (order_id, login, password_hash) VALUES (:order_id, :login, :hash)");
    return $stmt->execute([
        ':order_id' => $orderId,
        ':login' => $login,
        ':hash' => $passwordHash
    ]);
}

function findCredentialsByLogin($pdo, $login) {
    $stmt = $pdo->prepare("SELECT order_id, password_hash FROM credentials_2 WHERE login = :login");
    $stmt->execute([':login' => $login]);
    $row = $stmt->fetch();
    if (!$row) return null;
    return [
        'order_id' => (int)$row['order_id'],
        'password_hash' => $row['password_hash']
    ];
}

function getOrderByID($pdo, $id) {
    $stmt = $pdo->prepare("
        SELECT order_number, customer_name, customer_phone, customer_email,
               bouquet_name, bouquet_price, comment, status
        FROM orders WHERE id = :id
    ");
    $stmt->execute([':id' => $id]);
    $order = $stmt->fetch();
    if (!$order) return null;

    return [
        'number' => $order['order_number'],
        'name' => $order['customer_name'],
        'phone' => $order['customer_phone'],
        'email' => $order['customer_email'],
        'bouquet' => $order['bouquet_name'],
        'price' => $order['bouquet_price'],
        'comment' => $order['comment'],
        'status' => $order['status']
    ];
}

function updateOrder($pdo, $id, $data) {
    try {
        $stmt = $pdo->prepare("
            UPDATE orders SET
                customer_name = :name,
                customer_phone = :phone,
                customer_email = :email,
                bouquet_name = :bouquet,
                comment = :comment
            WHERE id = :id
        ");
        
        return $stmt->execute([
            ':name' => $data['name'],
            ':phone' => $data['phone'],
            ':email' => $data['email'],
            ':bouquet' => $data['bouquet'],
            ':comment' => $data['comment'],
            ':id' => $id
        ]);
    } catch (PDOException $e) {
        error_log('updateOrder error: ' . $e->getMessage());
        return false;
    }
}

function getAllOrders($pdo) {
    $stmt = $pdo->query("
        SELECT id, order_number, customer_name, customer_phone, customer_email,
               bouquet_name, bouquet_price, comment, status, created_at
        FROM orders ORDER BY id DESC
    ");
    return $stmt->fetchAll();
}

function deleteOrder($pdo, $id) {
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM credentials_2 WHERE order_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$id]);
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('deleteOrder error: ' . $e->getMessage());
        return false;
    }
}

function updateOrderStatus($pdo, $id, $status) {
    $stmt = $pdo->prepare("UPDATE orders SET status = :status WHERE id = :id");
    return $stmt->execute([':status' => $status, ':id' => $id]);
}

function getOrderStats($pdo) {
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
    $stats = [];
    while ($row = $stmt->fetch()) {
        $stats[$row['status']] = $row['count'];
    }
    return $stats;
}

function getAdminByLogin($pdo, $login) {
    $stmt = $pdo->prepare("SELECT password_hash FROM admin_credentials_2 WHERE login = ?");
    $stmt->execute([$login]);
    $row = $stmt->fetch();
    if (!$row) return null;
    return $row['password_hash'];
}
?>