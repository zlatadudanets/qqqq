<?php
// logout.php – выход из системы
session_start();
require_once __DIR__ . '/jwt.php';

deleteJWTCookie();
header('Location: index.php');
exit;
?>