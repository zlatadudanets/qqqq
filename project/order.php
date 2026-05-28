<?php
// order.php – оформление заказа
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/cookies.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/db_functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/csrf.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $pageData = loadFromCookies();
    $pageData['csrf_token'] = getOrCreateCSRFToken();
    $newCreds = null;
    $rawCreds = getCookieValue('new_credentials');
    if ($rawCreds !== null) {
        decodeFromCookie($rawCreds, $newCreds);
        deleteCookie('new_credentials');
    }
    renderOrderForm($pageData, $newCreds);
    exit;
}

if ($method === 'POST') {
    if (!validateCSRFToken()) {
        http_response_code(403);
        echo 'Request denied';
        exit;
    }

    list($formData, $errors) = validateOrderData($_POST);
    if (!empty($errors)) {
        saveErrorsToCookie($errors, $formData);
        header('Location: order.php');
        exit;
    }

    $result = saveOrderToDatabase($pdo, $formData);
    if ($result === false) {
        http_response_code(500);
        echo 'Ошибка сервера. Попробуйте позже.';
        exit;
    }

    $login = generateLogin();
    $password = generatePassword();
    $passwordHash = hashPassword($password);
    if (!saveCredentials($pdo, $result['id'], $login, $passwordHash)) {
        http_response_code(500);
        echo 'Ошибка сервера';
        exit;
    }

    saveSuccessToCookie($formData);
    setSessionCookie('new_credentials', encodeToCookie([
        'login' => $login, 
        'password' => $password,
        'order_number' => $result['number']
    ]));
    header('Location: order.php');
    exit;
}

function renderOrderForm($pageData, $newCreds) {
    global $BOUQUETS;
    $values = $pageData['values'];
    $errors = $pageData['errors'];
    $success = $pageData['success'];
    $csrfToken = htmlspecialchars($pageData['csrf_token']);
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Оформление заказа · Цветочная Элегантность</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #f3efe7;
            font-family: 'Montserrat', Georgia, serif;
            min-height: 100vh;
            padding: 2rem;
        }
        .form-container {
            max-width: 600px;
            width: 100%;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        h1 {
            font-family: 'Playfair Display', serif;
            text-align: center;
            color: #7A5C7A;
            margin-bottom: 1.5rem;
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
            font-size: 0.9rem;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #FF8FAB;
        }
        .field-error input, .field-error select {
            border-color: #e74c3c;
        }
        .error-msg {
            color: #e74c3c;
            font-size: 0.7rem;
            margin-top: 0.3rem;
        }
        .btn {
            width: 100%;
            background: #FF8FAB;
            color: white;
            border: none;
            padding: 0.8rem;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #FF7A9B;
            transform: translateY(-2px);
        }
        .success-banner {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        .credentials-banner {
            background: #f8f9fa;
            border: 1px solid #e2d5c4;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1.5rem;
        }
        .cred-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .cred-label {
            font-weight: 600;
            color: #8b694c;
            width: 60px;
        }
        .cred-row strong {
            font-family: monospace;
            background: #e8ddd0;
            padding: 0.2rem 0.6rem;
            border-radius: 5px;
        }
        .btn-login {
            display: inline-block;
            margin-top: 0.8rem;
            padding: 0.4rem 1rem;
            background: #98D7C2;
            color: white;
            text-decoration: none;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 1rem;
            color: #8b694c;
            text-decoration: none;
        }
        .radio-group {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        .radio-group label {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-weight: normal;
        }
        .radio-group input {
            width: auto;
        }
        @media (max-width: 600px) {
            body { padding: 1rem; }
            .form-container { padding: 1.2rem; }
        }
    </style>
</head>
<body>
<div class="form-container">
    <h1>🌷 Оформление заказа</h1>

    <?php if ($success): ?>
        <div class="success-banner">✓ Заказ успешно оформлен!</div>
    <?php endif; ?>

    <form action="order.php" method="POST">
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
            <label>Выберите букет *</label>
            <select name="bouquet">
                <option value="">-- Выберите --</option>
                <?php foreach ($BOUQUETS as $bouquet): ?>
                <option value="<?= htmlspecialchars($bouquet['name']) ?>" <?= ($values['bouquet'] ?? '') == $bouquet['name'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($bouquet['name']) ?> - <?= number_format($bouquet['price'], 0, '', ' ') ?> ₽
                </option>
                <?php endforeach; ?>
                <option value="Другой (в комментарии)">Другой (укажите в комментарии)</option>
            </select>
            <?php if (isset($errors['bouquet'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['bouquet']) ?></div>
            <?php endif; ?>
        </div>

        <div class="field">
            <label>Адрес доставки</label>
            <input type="text" name="address" value="<?= htmlspecialchars($values['address'] ?? '') ?>" placeholder="Улица, дом, квартира">
        </div>

        <div class="field">
            <label>Желаемая дата доставки</label>
            <input type="date" name="delivery_date" value="<?= htmlspecialchars($values['delivery_date'] ?? '') ?>">
        </div>

        <div class="field">
            <label>Комментарий к заказу</label>
            <textarea name="comment" rows="3" placeholder="Ваши пожелания..."><?= htmlspecialchars($values['comment'] ?? '') ?></textarea>
        </div>

        <div class="field <?= isset($errors['agree']) ? 'field-error' : '' ?>">
            <label class="checkbox-label" style="display: flex; align-items: center; gap: 0.5rem;">
                <input type="checkbox" name="agree" <?= ($values['agree'] ?? false) ? 'checked' : '' ?>> 
                Я согласен с политикой обработки персональных данных *
            </label>
            <?php if (isset($errors['agree'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['agree']) ?></div>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn">🌷 Оформить заказ</button>

        <?php if ($newCreds): ?>
        <div class="credentials-banner">
            <h3>✅ Заказ №<?= htmlspecialchars($newCreds['order_number']) ?> принят!</h3>
            <p>Сохраните данные для отслеживания заказа:</p>
            <div class="cred-row">
                <span class="cred-label">Логин</span>
                <strong><?= htmlspecialchars($newCreds['login']) ?></strong>
            </div>
            <div class="cred-row">
                <span class="cred-label">Пароль</span>
                <strong><?= htmlspecialchars($newCreds['password']) ?></strong>
            </div>
            <a href="login.php" class="btn-login">Войти и отследить заказ →</a>
        </div>
        <?php endif; ?>
    </form>
    <a href="index.php" class="back-link">← На главную</a>
</div>
</body>
</html>
<?php
}
?>