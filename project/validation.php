<?php
// validation.php – валидация данных заказа

function validateOrderData($post) {
    $data = [
        'name' => '', 'phone' => '', 'email' => '', 'bouquet' => '',
        'comment' => '', 'address' => '', 'delivery_date' => '', 'agree' => false
    ];
    $errors = [];

    // Имя
    $name = trim($post['name'] ?? '');
    if ($name === '') {
        $errors['name'] = 'Имя обязательно для заполнения';
    } elseif (mb_strlen($name, 'UTF-8') > 100) {
        $errors['name'] = 'Имя не должно превышать 100 символов';
    } elseif (!preg_match('/^[\p{L} ]+$/u', $name)) {
        $errors['name'] = 'Имя может содержать только буквы и пробелы';
    } else {
        $data['name'] = $name;
    }

    // Телефон
    $phone = trim($post['phone'] ?? '');
    if ($phone === '') {
        $errors['phone'] = 'Телефон обязателен для заполнения';
    } elseif (!preg_match('/^\+?[0-9()\- ]{10,20}$/', $phone)) {
        $errors['phone'] = 'Введите корректный номер телефона';
    } else {
        $data['phone'] = $phone;
    }

    // Email
    $email = trim($post['email'] ?? '');
    if ($email !== '') {
        if (strlen($email) > 255) {
            $errors['email'] = 'Email не должен превышать 255 символов';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Введите корректный email';
        } else {
            $data['email'] = $email;
        }
    }

    // Букет
    $bouquet = trim($post['bouquet'] ?? '');
    if ($bouquet === '') {
        $errors['bouquet'] = 'Выберите букет';
    } else {
        $data['bouquet'] = $bouquet;
    }

    // Комментарий
    $data['comment'] = trim($post['comment'] ?? '');

    // Адрес доставки
    $data['address'] = trim($post['address'] ?? '');

    // Дата доставки
    $deliveryDate = trim($post['delivery_date'] ?? '');
    if ($deliveryDate !== '') {
        $date = DateTime::createFromFormat('Y-m-d', $deliveryDate);
        if ($date && $date->format('Y-m-d') === $deliveryDate) {
            $data['delivery_date'] = $deliveryDate;
        }
    }

    // Согласие
    $agree = $post['agree'] ?? '';
    if ($agree !== 'on') {
        $errors['agree'] = 'Необходимо согласие с политикой обработки данных';
    } else {
        $data['agree'] = true;
    }

    return [$data, $errors];
}
?>