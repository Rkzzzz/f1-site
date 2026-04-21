<?php
// ═══════════════════════════════════════════════════════════════
//  auth.php — единый файл: база данных + вход + выход + регистрация
//  Автор: Абросимов Николай, ННГАСУ
//
//  Использование:
//    Открыть страницу:       http://localhost/f1site/auth.php
//    Войти:                  http://localhost/f1site/auth.php?page=login
//    Зарегистрироваться:     http://localhost/f1site/auth.php?page=register
//    Выйти:                  http://localhost/f1site/auth.php?page=logout
//
//  Подключить к любой странице сайта:
//    <?php require_once 'auth.php'; ?>
//    <?php f1_userbar(); ?>        — плашка с именем пользователя
//    <?php if(f1_isEditor()): ?>   — проверка роли редактора
// ═══════════════════════════════════════════════════════════════

session_start();

// ──────────────────────────────────────────────────────────────
//  1. НАСТРОЙКИ БАЗЫ ДАННЫХ
//     Измени DB_USER и DB_PASS под свой XAMPP
// ──────────────────────────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'f1_site');
define('DB_USER',    'root');   // пользователь MySQL
define('DB_PASS',    '');       // пароль MySQL (в XAMPP обычно пустой)
define('DB_CHARSET', 'utf8mb4');

// ──────────────────────────────────────────────────────────────
//  2. ПОДКЛЮЧЕНИЕ К БАЗЕ ДАННЫХ
// ──────────────────────────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die('
            <div style="font-family:Arial;padding:40px;background:#1a1a1a;color:#f0f0f0">
                <h2 style="color:#e10600">Ошибка подключения к базе данных</h2>
                <p style="opacity:0.6">Убедись что MySQL запущен в XAMPP и настройки в auth.php верны.</p>
                <code style="color:#888;font-size:0.85rem">' . htmlspecialchars($e->getMessage()) . '</code>
            </div>');
        }
    }
    return $pdo;
}

// ──────────────────────────────────────────────────────────────
//  3. РЕГИСТРАЦИЯ
// ──────────────────────────────────────────────────────────────
function f1_register(array $data): array {
    $db = getDB();

    // Проверка дубликатов
    $stmt = $db->prepare('SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1');
    $stmt->execute([':u' => $data['username'], ':e' => $data['email']]);
    if ($stmt->fetch()) {
        return ['ok' => false, 'error' => 'Такой логин или email уже занят'];
    }

    // Валидация
    if (strlen($data['password']) < 8) {
        return ['ok' => false, 'error' => 'Пароль должен быть минимум 8 символов'];
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Некорректный email'];
    }

    // Хешируем пароль
    $hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);

    $db->prepare('
        INSERT INTO users (username, email, password_hash, first_name, last_name, country, role_id)
        VALUES (:username, :email, :hash, :first, :last, :country, 2)
    ')->execute([
        ':username' => trim($data['username']),
        ':email'    => strtolower(trim($data['email'])),
        ':hash'     => $hash,
        ':first'    => trim($data['first_name']),
        ':last'     => trim($data['last_name']),
        ':country'  => $data['country'] ?? null,
    ]);
    // role_id = 2 → читатель (все новые пользователи)

    $userId = (int) $db->lastInsertId();
    f1_log($userId, 'register', 'Регистрация: ' . $data['username']);

    return ['ok' => true, 'user_id' => $userId];
}

// ──────────────────────────────────────────────────────────────
//  4. ВХОД
// ──────────────────────────────────────────────────────────────
function f1_login(string $login, string $password, bool $remember = false): array {
    $db = getDB();

    $stmt = $db->prepare('
        SELECT u.*, r.name AS role_name, r.label AS role_label
        FROM users u
        JOIN roles r ON r.id = u.role_id
        WHERE (u.username = :l OR u.email = :l) AND u.is_active = 1
        LIMIT 1
    ');
    $stmt->execute([':l' => trim($login)]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['ok' => false, 'error' => 'Неверный логин или пароль'];
    }

    // Сохраняем в сессию
    $_SESSION['f1_user'] = [
        'id'         => $user['id'],
        'username'   => $user['username'],
        'first_name' => $user['first_name'],
        'role'       => $user['role_name'],
        'role_label' => $user['role_label'],
    ];

    // Обновляем время последнего входа
    $db->prepare('UPDATE users SET last_login = NOW() WHERE id = :id')
       ->execute([':id' => $user['id']]);

    // "Запомнить меня" — токен в куках на 30 дней
    if ($remember) {
        $token = bin2hex(random_bytes(32));
        $db->prepare('
            INSERT INTO sessions (user_id, token, ip_address, user_agent, expires_at)
            VALUES (:uid, :token, :ip, :ua, DATE_ADD(NOW(), INTERVAL 30 DAY))
        ')->execute([
            ':uid'   => $user['id'],
            ':token' => $token,
            ':ip'    => $_SERVER['REMOTE_ADDR'] ?? null,
            ':ua'    => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
        setcookie('f1_remember', $token, time() + 86400 * 30, '/', '', false, true);
    }

    f1_log($user['id'], 'login', 'Вход в систему');
    return ['ok' => true, 'role' => $user['role_name']];
}

// ──────────────────────────────────────────────────────────────
//  5. ВЫХОД
// ──────────────────────────────────────────────────────────────
function f1_logout(): void {
    $userId = $_SESSION['f1_user']['id'] ?? null;

    if (!empty($_COOKIE['f1_remember'])) {
        getDB()->prepare('DELETE FROM sessions WHERE token = :t')
               ->execute([':t' => $_COOKIE['f1_remember']]);
        setcookie('f1_remember', '', time() - 3600, '/');
    }

    if ($userId) f1_log($userId, 'logout', 'Выход из системы');

    $_SESSION = [];
    session_destroy();
}

// ──────────────────────────────────────────────────────────────
//  6. ТЕКУЩИЙ ПОЛЬЗОВАТЕЛЬ
// ──────────────────────────────────────────────────────────────
function f1_user(): ?array {
    if (!empty($_SESSION['f1_user'])) {
        return $_SESSION['f1_user'];
    }

    // Проверяем кук "запомнить меня"
    if (!empty($_COOKIE['f1_remember'])) {
        $stmt = getDB()->prepare('
            SELECT u.id, u.username, u.first_name, r.name AS role_name, r.label AS role_label
            FROM sessions s
            JOIN users u ON u.id = s.user_id
            JOIN roles  r ON r.id = u.role_id
            WHERE s.token = :t AND s.expires_at > NOW() AND u.is_active = 1
            LIMIT 1
        ');
        $stmt->execute([':t' => $_COOKIE['f1_remember']]);
        $row = $stmt->fetch();
        if ($row) {
            $_SESSION['f1_user'] = [
                'id'         => $row['id'],
                'username'   => $row['username'],
                'first_name' => $row['first_name'],
                'role'       => $row['role_name'],
                'role_label' => $row['role_label'],
            ];
            return $_SESSION['f1_user'];
        }
    }

    return null;
}

// ──────────────────────────────────────────────────────────────
//  7. ПРОВЕРКИ РОЛЕЙ
// ──────────────────────────────────────────────────────────────
function f1_isEditor(): bool {
    $u = f1_user();
    return $u !== null && $u['role'] === 'editor';
}

function f1_isLoggedIn(): bool {
    return f1_user() !== null;
}

// ──────────────────────────────────────────────────────────────
//  8. ЛОГ ДЕЙСТВИЙ
// ──────────────────────────────────────────────────────────────
function f1_log(?int $userId, string $action, string $desc = ''): void {
    try {
        getDB()->prepare('
            INSERT INTO activity_log (user_id, action, description, ip_address)
            VALUES (:uid, :action, :desc, :ip)
        ')->execute([
            ':uid'    => $userId,
            ':action' => $action,
            ':desc'   => $desc,
            ':ip'     => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Exception $e) {}
}

// ──────────────────────────────────────────────────────────────
//  9. ПЛАШКА ПОЛЬЗОВАТЕЛЯ
//     Вставь сразу после <body>: <?php f1_userbar(); ?>
// ──────────────────────────────────────────────────────────────
function f1_userbar(): void {
    $user = f1_user();
    echo '<div style="background:#222;padding:6px 24px;font-size:0.82rem;
                      display:flex;justify-content:flex-end;align-items:center;
                      gap:16px;border-bottom:1px solid #3a3a3a;">';
    if ($user) {
        echo '<span style="opacity:0.7">👤 ' . htmlspecialchars($user['first_name']) . '</span>';
        echo '<span style="background:rgba(225,6,0,0.15);color:#e10600;
                           border-radius:4px;padding:2px 10px;font-weight:600">'
             . htmlspecialchars($user['role_label']) . '</span>';
        echo '<a href="auth.php?page=logout"
                 style="opacity:0.5;text-decoration:none;color:inherit;
                        border:1px solid rgba(255,255,255,0.2);border-radius:4px;padding:3px 10px;">
                 Выйти</a>';
    } else {
        echo '<a href="auth.php?page=login"
                 style="text-decoration:none;color:inherit;opacity:0.7">Войти</a>';
        echo '<a href="auth.php?page=register"
                 style="text-decoration:none;color:#fff;background:#e10600;
                        border-radius:4px;padding:4px 14px;">Регистрация</a>';
    }
    echo '</div>';
}

// ──────────────────────────────────────────────────────────────
//  10. ОБЩИЙ ХЕДЕР С НАВИГАЦИЕЙ
//      Вызов: <?php f1_header(); ?>
// ──────────────────────────────────────────────────────────────
function f1_header(string $activePage = ''): void {
    $links = [
        'index.html'          => 'Главная',
        'auth.php?page=login' => 'Войти',
        'calendar.html'       => 'Календарь',
        'pilots.html'         => 'Пилоты',
        'teams.html'          => 'Команды',
        'result.html'         => 'Результаты',
        'circitc.html'        => 'Трассы',
        'about.html'          => 'О нас',
        'contacts.html'       => 'Контакты',
    ];
    echo '<header class="header">
        <div class="logo">
            <img src="https://resources.formulaf1.com/assets/images/logo.png" alt="Формула‑1" width="120">
        </div>
        <nav class="nav">
            <button class="nav-menu-btn" aria-haspopup="true" aria-expanded="false" aria-controls="nav-dropdown">
                Меню <span class="arrow">▾</span>
            </button>
            <div class="nav-dropdown" id="nav-dropdown" role="menu">';
    foreach ($links as $href => $label) {
        echo '<a href="' . $href . '">' . $label . '</a>';
    }
    echo '    </div>
        </nav>
    </header>';
}

// ──────────────────────────────────────────────────────────────
//  11. ОБЩИЙ JS ДЛЯ МЕНЮ
//      Вставь перед </body>: <?php f1_navjs(); ?>
// ──────────────────────────────────────────────────────────────
function f1_navjs(): void {
    echo '<script>
        (function(){
            var btn  = document.querySelector(".nav-menu-btn");
            var drop = document.getElementById("nav-dropdown");
            if(!btn||!drop) return;
            btn.addEventListener("click", function(){ var o=drop.classList.toggle("open"); btn.setAttribute("aria-expanded",o); });
            document.addEventListener("click", function(e){ if(!e.target.closest(".nav")){ drop.classList.remove("open"); btn.setAttribute("aria-expanded","false"); }});
            document.addEventListener("keydown", function(e){ if(e.key==="Escape"){ drop.classList.remove("open"); btn.setAttribute("aria-expanded","false"); }});
        })();
    </script>';
}

// ══════════════════════════════════════════════════════════════
//  НИЖЕ — ОТРИСОВКА СТРАНИЦ (login / register / logout)
//  Срабатывает только когда файл открывают напрямую через браузер
//  При подключении через require_once — страницы не показываются
// ══════════════════════════════════════════════════════════════

// Определяем какую страницу показывать
$page   = $_GET['page'] ?? $_POST['page'] ?? '';
$errors = [];
$done   = false;

// ── Выход ────────────────────────────────────────────────────
if ($page === 'logout') {
    f1_logout();
    header('Location: index.html');
    exit;
}

// ── Обработка POST: вход ─────────────────────────────────────
if ($page === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = f1_login(
        $_POST['username'] ?? '',
        $_POST['password'] ?? '',
        isset($_POST['remember'])
    );
    if ($result['ok']) {
        header('Location: index.html');
        exit;
    }
    $errors[] = $result['error'];
}

// ── Обработка POST: регистрация ──────────────────────────────
if ($page === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pwd  = $_POST['password'] ?? '';
    $conf = $_POST['confirm']  ?? '';

    if ($pwd !== $conf) {
        $errors[] = 'Пароли не совпадают';
    } elseif (!isset($_POST['agree'])) {
        $errors[] = 'Необходимо принять условия использования';
    } else {
        $result = f1_register([
            'username'   => $_POST['username']  ?? '',
            'email'      => $_POST['email']     ?? '',
            'password'   => $pwd,
            'first_name' => $_POST['firstname'] ?? '',
            'last_name'  => $_POST['lastname']  ?? '',
            'country'    => $_POST['country']   ?? '',
        ]);
        if ($result['ok']) {
            f1_login($_POST['username'], $pwd);
            $done = true;
        } else {
            $errors[] = $result['error'];
        }
    }
}

// ── Если страницу открывают напрямую — рисуем HTML ───────────
if ($page === 'login' || $page === 'register' || $page === '') 

    // Если уже вошёл — перенаправляем
    if (f1_user() && !$done) {
        header('Location: index.html');
        exit;
    }

    $isLogin    = ($page !== 'register');
    $pageTitle  = $isLogin ? 'Вход' : 'Регистрация';

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> — Формула‑1</title>
    <link rel="stylesheet" href="coloring.css">
    <style>
        .auth-wrap { max-width: 460px; margin: 52px auto; padding: 0 16px; }
        .auth-wrap h1 { margin-bottom: 6px; }
        .auth-sub { opacity: 0.5; font-size: 0.88rem; margin-bottom: 26px; }
        .auth-sub a { color: inherit; }
        .field { margin-bottom: 15px; }
        .field label { display:block; margin-bottom:6px; font-size:0.87rem; opacity:0.7; }
        .field input, .field select {
            width: 100%; padding: 10px 12px; border-radius: 5px; box-sizing: border-box;
            border: 1px solid rgba(255,255,255,0.15); background: rgba(255,255,255,0.05);
            color: inherit; font-size: 0.93rem; font-family: inherit; outline: none;
            transition: border-color 0.2s; appearance: none;
        }
        .field input:focus, .field select:focus { border-color: rgba(255,255,255,0.4); }
        .field select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23aaa' stroke-width='1.5' fill='none'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center;
            padding-right: 34px; cursor: pointer;
        }
        .field select option { background: #1a1a1a; }
        .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .pwd-wrap { position: relative; }
        .pwd-wrap input { padding-right: 42px; }
        .pwd-toggle { position:absolute; right:10px; top:50%; transform:translateY(-50%);
                      background:none; border:none; color:inherit; opacity:0.5;
                      cursor:pointer; font-size:1rem; padding:2px; }
        .pwd-toggle:hover { opacity: 1; }
        .strength-bar { display:flex; gap:4px; margin-top:6px; }
        .strength-bar span { flex:1; height:3px; border-radius:2px;
                             background:rgba(255,255,255,0.1); transition:background 0.3s; }
        .strength-label { font-size:0.74rem; opacity:0.55; margin-top:3px; }
        .check-row { display:flex; align-items:flex-start; gap:9px;
                     font-size:0.84rem; opacity:0.7; margin-bottom:18px; cursor:pointer; }
        .check-row input { width:15px; height:15px; flex-shrink:0; margin-top:2px;
                           accent-color:#e10600; cursor:pointer; }
        .remember-row { display:flex; align-items:center; gap:8px;
                        font-size:0.84rem; opacity:0.65; margin-bottom:18px; }
        .remember-row input { width:auto; accent-color:#e10600; }
        .btn-submit { width:100%; padding:12px; border:none; border-radius:5px;
                      background:#e10600; color:#fff; font-size:1rem;
                      font-family:inherit; font-weight:600; cursor:pointer;
                      transition:background 0.2s; }
        .btn-submit:hover { background: #b80500; }
        .error-msg { color:#e10600; font-size:0.85rem; margin-bottom:14px;
                     padding:10px 14px; background:rgba(225,6,0,0.08);
                     border-radius:5px; border-left:3px solid #e10600; }
        .success-box { text-align:center; padding:48px 0; }
        .success-box .icon { font-size:3rem; margin-bottom:14px; }
        .success-box p { opacity:0.65; margin-bottom:20px; }
        .back-btn { display:inline-block; padding:10px 28px;
                    border:1px solid rgba(255,255,255,0.25); border-radius:5px;
                    color:inherit; text-decoration:none; transition:background 0.2s; }
        .back-btn:hover { background: rgba(255,255,255,0.07); }
        .tabs { display:flex; gap:0; margin-bottom:28px; border-bottom:1px solid rgba(255,255,255,0.1); }
        .tab { padding:10px 24px; text-decoration:none; color:inherit; opacity:0.5;
               font-size:0.95rem; border-bottom:2px solid transparent; transition:all 0.2s; }
        .tab.active { opacity:1; border-bottom-color:#e10600; font-weight:600; }
        @media(max-width:480px){ .field-row { grid-template-columns:1fr; } }
    </style>
</head>
<body>

<?php f1_userbar(); ?>
<?php f1_header(); ?>

<main class="main">
<div class="auth-wrap">

    <!-- Вкладки: Вход / Регистрация -->
    <div class="tabs">
        <a href="auth.php?page=login"    class="tab <?= $isLogin ? 'active' : '' ?>">Вход</a>
        <a href="auth.php?page=register" class="tab <?= !$isLogin ? 'active' : '' ?>">Регистрация</a>
    </div>

    <!-- Ошибки -->
    <?php foreach ($errors as $err): ?>
        <div class="error-msg">⚠ <?= htmlspecialchars($err) ?></div>
    <?php endforeach; ?>

    <!-- ── ЭКРАН УСПЕШНОЙ РЕГИСТРАЦИИ ── -->
    <?php if ($done): ?>
    <div class="success-box">
        <div class="icon">✅</div>
        <h2>Аккаунт создан!</h2>
        <p>Добро пожаловать в мир Формулы‑1.</p>
        <a href="index.html" class="back-btn">На главную</a>
    </div>

    <!-- ── ФОРМА ВХОДА ── -->
    <?php elseif ($isLogin): ?>
    <form method="POST" action="auth.php?page=login">
        <input type="hidden" name="page" value="login">

        <div class="field">
            <label>Логин или Email</label>
            <input type="text" name="username"
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                   placeholder="admin" autocomplete="username" required>
        </div>
        <div class="field">
            <label>Пароль</label>
            <div class="pwd-wrap">
                <input type="password" id="pwd-login" name="password"
                       placeholder="••••••••" autocomplete="current-password" required>
                <button type="button" class="pwd-toggle" data-target="pwd-login">👁</button>
            </div>
        </div>
        <div class="remember-row">
            <input type="checkbox" id="remember" name="remember">
            <label for="remember">Запомнить меня на 30 дней</label>
        </div>
        <button type="submit" class="btn-submit">Войти</button>
    </form>

    <!-- ── ФОРМА РЕГИСТРАЦИИ ── -->
    <?php else: ?>
    <form method="POST" action="auth.php?page=register" id="reg-form" novalidate>
        <input type="hidden" name="page" value="register">

        <div class="field-row">
            <div class="field">
                <label>Имя</label>
                <input type="text" name="firstname"
                       value="<?= htmlspecialchars($_POST['firstname'] ?? '') ?>"
                       placeholder="Николай" autocomplete="given-name" required>
            </div>
            <div class="field">
                <label>Фамилия</label>
                <input type="text" name="lastname"
                       value="<?= htmlspecialchars($_POST['lastname'] ?? '') ?>"
                       placeholder="Абросимов" autocomplete="family-name" required>
            </div>
        </div>

        <div class="field">
            <label>Имя пользователя</label>
            <input type="text" name="username"
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                   placeholder="f1_fan_2026" autocomplete="username" required>
        </div>

        <div class="field">
            <label>Email</label>
            <input type="email" name="email"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   placeholder="you@example.com" autocomplete="email" required>
        </div>

        <div class="field">
            <label>Страна</label>
            <select name="country">
                <option value="">— Выберите страну —</option>
                <?php
                $countries = ['Россия'=>'🇷🇺','Германия'=>'🇩🇪','Великобритания'=>'🇬🇧',
                              'Франция'=>'🇫🇷','Италия'=>'🇮🇹','Испания'=>'🇪🇸',
                              'США'=>'🇺🇸','Япония'=>'🇯🇵','Бразилия'=>'🇧🇷',
                              'Австралия'=>'🇦🇺','Другая'=>'🌍'];
                foreach ($countries as $name => $flag):
                    $sel = (($_POST['country'] ?? '') === $name) ? 'selected' : '';
                ?>
                <option value="<?= $name ?>" <?= $sel ?>><?= $flag ?> <?= $name ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label>Пароль</label>
            <div class="pwd-wrap">
                <input type="password" id="pwd-reg" name="password"
                       placeholder="Минимум 8 символов" autocomplete="new-password" required>
                <button type="button" class="pwd-toggle" data-target="pwd-reg">👁</button>
            </div>
            <div class="strength-bar">
                <span id="s1"></span><span id="s2"></span>
                <span id="s3"></span><span id="s4"></span>
            </div>
            <div class="strength-label" id="slabel"></div>
        </div>

        <div class="field">
            <label>Подтверждение пароля</label>
            <div class="pwd-wrap">
                <input type="password" id="pwd-conf" name="confirm"
                       placeholder="Повторите пароль" autocomplete="new-password" required>
                <button type="button" class="pwd-toggle" data-target="pwd-conf">👁</button>
            </div>
        </div>

        <label class="check-row">
            <input type="checkbox" name="agree" id="agree" required>
            <span>Я согласен с <a href="#">условиями использования</a></span>
        </label>

        <button type="submit" class="btn-submit">Создать аккаунт</button>
    </form>
    <?php endif; ?>

</div>
</main>

<footer class="footer">
    <p>&copy; 2026 Формула‑1. Все права защищены.</p>
    <p>Данные из официального источника: <a href="https://www.formula1.com">formula1.com</a></p>
</footer>

<script>
// Показать/скрыть пароль
document.querySelectorAll('.pwd-toggle').forEach(function(btn){
    btn.addEventListener('click', function(){
        var inp = document.getElementById(btn.dataset.target);
        var show = inp.type === 'password';
        inp.type = show ? 'text' : 'password';
        btn.textContent = show ? '🙈' : '👁';
    });
});

// Индикатор силы пароля (только на форме регистрации)
var pwdReg = document.getElementById('pwd-reg');
if (pwdReg) {
    var bars   = [1,2,3,4].map(function(i){ return document.getElementById('s'+i); });
    var slabel = document.getElementById('slabel');
    var colors = ['#e10600','#f97316','#eab308','#22c55e'];
    var names  = ['Очень слабый','Слабый','Средний','Сильный'];
    pwdReg.addEventListener('input', function(){
        var v = this.value, score = 0;
        if(v.length>=8)  score++;
        if(v.length>=12) score++;
        if(/[A-Z]/.test(v)&&/[a-z]/.test(v)) score++;
        if(/\d/.test(v)) score++;
        if(/[^A-Za-z0-9]/.test(v)) score++;
        score = Math.min(4, score);
        bars.forEach(function(b,i){ b.style.background = i<score ? colors[score-1] : 'rgba(255,255,255,0.1)'; });
        slabel.textContent = v ? names[score-1]||'' : '';
        slabel.style.color = v ? colors[score-1] : '';
    });

    // Клиентская проверка формы регистрации
    document.getElementById('reg-form').addEventListener('submit', function(e){
        var pwd  = document.getElementById('pwd-reg').value;
        var conf = document.getElementById('pwd-conf').value;
        if(pwd.length < 8){ e.preventDefault(); alert('Пароль минимум 8 символов'); return; }
        if(pwd !== conf)  { e.preventDefault(); alert('Пароли не совпадают'); return; }
        if(!document.getElementById('agree').checked){ e.preventDefault(); alert('Примите условия использования'); }
    });
}
</script>

<?php f1_navjs(); ?>
</body>
</html>

<?php  // конец блока отрисовки страницы ?>