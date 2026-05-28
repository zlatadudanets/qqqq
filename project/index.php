<?php
// index.php – главная страница с каталогом цветов
session_start();
require_once __DIR__ . '/jwt.php';

$payload = getJWTFromCookie();
$isLoggedIn = ($payload !== null);
$login = $isLoggedIn ? htmlspecialchars($payload['login']) : '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Цветочная Элегантность</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Montserrat', sans-serif;
            background: #FFFCF9;
            color: #5A5A5A;
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        
        header {
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(10px);
        }
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            flex-wrap: wrap;
            gap: 15px;
        }
        .logo {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            font-weight: 700;
            color: #FF8FAB;
        }
        .logo span { color: #98D7C2; }
        .nav-links { display: flex; gap: 30px; list-style: none; }
        .nav-links a {
            color: #5A5A5A;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.4s ease;
        }
        .nav-links a:hover { color: #FF8FAB; }
        
        .user-panel {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        .user-greeting { font-size: 14px; color: #5A5A5A; }
        
        .btn {
            background: #FF8FAB;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.4s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 14px;
        }
        .btn-outline {
            background: transparent;
            border: 1px solid #FF8FAB;
            color: #FF8FAB;
        }
        .btn-outline:hover {
            background: #FF8FAB;
            color: white;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            margin-top: 70px;
            background: linear-gradient(135deg, #FFD6E7 0%, #E6E6FA 100%);
        }
        .hero-content {
            text-align: center;
            padding: 60px 20px;
        }
        .hero h1 {
            font-size: clamp(32px, 5vw, 56px);
            font-family: 'Playfair Display', serif;
            color: #7A5C7A;
            margin-bottom: 20px;
        }
        .hero p {
            font-size: clamp(16px, 2.5vw, 20px);
            max-width: 600px;
            margin: 0 auto 30px;
        }
        
        .features {
            padding: 80px 0;
            background: white;
        }
        .features h2, .catalog h2 {
            text-align: center;
            font-size: 36px;
            color: #7A5C7A;
            margin-bottom: 50px;
        }
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
        }
        .feature-card {
            text-align: center;
            padding: 25px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            transition: all 0.4s ease;
        }
        .feature-card:hover { transform: translateY(-10px); }
        .feature-icon {
            width: 70px;
            height: 70px;
            background: #FF8FAB;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 24px;
        }
        
        .catalog {
            padding: 80px 0;
            background: #FFFCF9;
        }
        .flower-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
        }
        .flower-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            transition: all 0.4s ease;
            text-align: center;
        }
        .flower-card:hover { transform: translateY(-10px); }
        .flower-img {
            height: 250px;
            background: #FFD6E7;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 80px;
        }
        .flower-info { padding: 20px; }
        .flower-info h3 { color: #7A5C7A; margin-bottom: 10px; }
        .price { font-size: 22px; font-weight: 700; color: #FF8FAB; margin: 15px 0; }
        
        footer {
            background: #7A5C7A;
            color: white;
            padding: 60px 0 30px;
        }
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
        }
        .footer-logo {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            font-weight: 700;
        }
        .social-icons { display: flex; gap: 15px; margin-top: 20px; }
        .social-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }
        .social-icon:hover { background: #FF8FAB; }
        .copyright {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid rgba(255,255,255,0.1);
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .nav-links { display: none; }
            .features-grid, .flower-grid { grid-template-columns: 1fr; }
            nav { flex-direction: column; }
            .user-panel { justify-content: center; }
        }
    </style>
</head>
<body>
<header>
    <div class="container">
        <nav>
            <div class="logo">Цветочная<span>Элегантность</span></div>
            <ul class="nav-links">
                <li><a href="#home">Главная</a></li>
                <li><a href="#features">Преимущества</a></li>
                <li><a href="#catalog">Каталог</a></li>
                <li><a href="#contacts">Контакты</a></li>
            </ul>
            <div class="user-panel">
                <?php if ($isLoggedIn): ?>
                    <span class="user-greeting">🌸 <?= htmlspecialchars($login) ?></span>
                    <a href="edit-order.php" class="btn">Мой заказ</a>
                    <a href="logout.php" class="btn btn-outline">Выйти</a>
                <?php else: ?>
                    <a href="order.php" class="btn">🌷 Заказать</a>
                    <a href="login.php" class="btn btn-outline">Войти</a>
                <?php endif; ?>
            </div>
        </nav>
    </div>
</header>

<section class="hero" id="home">
    <div class="container">
        <div class="hero-content">
            <h1>Доставка цветов с элегантностью</h1>
            <p>Премиальные букеты от лучших флористов. Бесплатная доставка за 2 часа.</p>
            <a href="order.php" class="btn" style="padding: 15px 40px; font-size: 18px;">
                <i class="fas fa-gift"></i> Заказать букет
            </a>
        </div>
    </div>
</section>

<section class="features" id="features">
    <div class="container">
        <h2>Почему выбирают нас</h2>
        <div class="features-grid">
            <div class="feature-card"><div class="feature-icon"><i class="fas fa-leaf"></i></div><h3>Свежие цветы</h3><p>Только свежие цветы от проверенных поставщиков</p></div>
            <div class="feature-card"><div class="feature-icon"><i class="fas fa-truck"></i></div><h3>Быстрая доставка</h3><p>Доставляем букеты за 2 часа</p></div>
            <div class="feature-card"><div class="feature-icon"><i class="fas fa-gift"></i></div><h3>Подарочная упаковка</h3><p>Бесплатная элегантная упаковка</p></div>
            <div class="feature-card"><div class="feature-icon"><i class="fas fa-heart"></i></div><h3>С любовью</h3><p>Каждый букет создается с душой</p></div>
        </div>
    </div>
</section>

<section class="catalog" id="catalog">
    <div class="container">
        <h2>Популярные букеты</h2>
        <div class="flower-grid">
            <div class="flower-card"><div class="flower-img">🌹🌷</div><div class="flower-info"><h3>Розовая нежность</h3><p>Розы, пионы, эустома</p><div class="price">3 900 ₽</div><a href="order.php" class="btn" style="width:100%;">Заказать</a></div></div>
            <div class="flower-card"><div class="flower-img">💜🌸</div><div class="flower-info"><h3>Сиреневый рассвет</h3><p>Орхидеи, лаванда, ирисы</p><div class="price">4 500 ₽</div><a href="order.php" class="btn" style="width:100%;">Заказать</a></div></div>
            <div class="flower-card"><div class="flower-img">🌻🌼</div><div class="flower-info"><h3>Солнечное настроение</h3><p>Герберы, тюльпаны</p><div class="price">2 900 ₽</div><a href="order.php" class="btn" style="width:100%;">Заказать</a></div></div>
            <div class="flower-card"><div class="flower-img">💙🌿</div><div class="flower-info"><h3>Голубая мечта</h3><p>Гортензии, дельфиниумы</p><div class="price">3 700 ₽</div><a href="order.php" class="btn" style="width:100%;">Заказать</a></div></div>
        </div>
    </div>
</section>

<footer id="contacts">
    <div class="container">
        <div class="footer-content">
            <div>
                <div class="footer-logo">Цветочная<span style="color:#FFD6E7;">Элегантность</span></div>
                <p style="margin-top: 15px;">Доставка цветов по всей России</p>
                <div class="social-icons">
                    <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-vk"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-telegram"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-whatsapp"></i></a>
                </div>
            </div>
            <div>
                <h3 style="color:#FFD6E7; margin-bottom: 20px;">Контакты</h3>
                <p><i class="fas fa-phone"></i> +7 (929) 837-40-17</p>
                <p><i class="fas fa-envelope"></i> info@flowers.ru</p>
                <p><i class="fas fa-map-marker-alt"></i> Краснодар, ул. Красная, 184</p>
            </div>
            <div>
                <h3 style="color:#FFD6E7; margin-bottom: 20px;">Время работы</h3>
                <p>Пн-Вс: 9:00 - 21:00</p>
                <p style="margin-top: 10px;">Доставка круглосуточно</p>
            </div>
        </div>
        <div class="copyright">© 2025 Цветочная Элегантность. Все права защищены.</div>
    </div>
</footer>
</body>
</html>