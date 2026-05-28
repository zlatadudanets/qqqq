<?php
// config.php – глобальные константы
const JWT_SECRET = 'flower-shop-secret-key-2025';
const LOGIN_ALPHABET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
const PASSWORD_ALPHABET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!-_';
const LOGIN_LENGTH = 8;
const PASSWORD_LENGTH = 12;

// Список букетов
$BOUQUETS = [
    ['id' => '1', 'name' => 'Розовая нежность', 'price' => 3900],
    ['id' => '2', 'name' => 'Сиреневый рассвет', 'price' => 4500],
    ['id' => '3', 'name' => 'Солнечное настроение', 'price' => 2900],
    ['id' => '4', 'name' => 'Голубая мечта', 'price' => 3700],
];
?>