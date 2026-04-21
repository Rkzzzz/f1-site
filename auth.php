<?php
// ═══════════════════════════════════════════════════════
//  auth.php — авторизация + БД + роли + страницы входа
//  Подключить: <?php require_once 'auth.php'; ?>
//  Плашка:     <?php f1_userbar(); ?>  — сразу после <body>
//  Проверка:   <?php if(f1_isEditor()): ?> ... <?php endif; ?>
// ═══════════════════════════════════════════════════════
if (session_status() === PHP_SESSION_NONE) session_start();

// ── 1. Настройки БД ─────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'f1_site');
define('DB_USER',    'root');   // ← твой пользователь MySQL
define('DB_PASS',    '');       // ← твой пароль MySQL (XAMPP: пустой)
define('DB_CHARSET', 'utf8mb4');

// ── 2. Подключение к БД ─────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET,
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                 PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
                 PDO::ATTR_EMULATE_PREPARES=>false]
            );
        } catch (PDOException $e) {
            die('<div style="font-family:Arial;padding:30px;background:#1a1a1a;color:#f0f0f0">
                <h2 style="color:#e10600">Ошибка подключения к БД</h2>
                <p style="opacity:.6">Проверь что MySQL запущен и настройки в auth.php верны.</p>
                <code style="color:#888">'.htmlspecialchars($e->getMessage()).'</code></div>');
        }
    }
    return $pdo;
}

// ── 3. Регистрация ──────────────────────────────────────
function f1_register(array $d): array {
    $db = getDB();
    $stmt = $db->prepare('SELECT id FROM users WHERE username=:u OR email=:e LIMIT 1');
    $stmt->execute([':u'=>$d['username'],':e'=>$d['email']]);
    if ($stmt->fetch()) return ['ok'=>false,'error'=>'Такой логин или email уже занят'];
    if (strlen($d['password'])<8) return ['ok'=>false,'error'=>'Пароль минимум 8 символов'];
    if (!filter_var($d['email'],FILTER_VALIDATE_EMAIL)) return ['ok'=>false,'error'=>'Некорректный email'];
    $hash = password_hash($d['password'],PASSWORD_BCRYPT,['cost'=>12]);
    $db->prepare('INSERT INTO users(username,email,password_hash,first_name,last_name,country,role_id)
                  VALUES(:u,:e,:h,:f,:l,:c,2)')
       ->execute([':u'=>trim($d['username']),':e'=>strtolower(trim($d['email'])),
                  ':h'=>$hash,':f'=>trim($d['first_name']),':l'=>trim($d['last_name']),':c'=>$d['country']??null]);
    $id = (int)$db->lastInsertId();
    f1_log($id,'register','Регистрация: '.$d['username']);
    return ['ok'=>true,'user_id'=>$id];
}

// ── 4. Вход ─────────────────────────────────────────────
function f1_login(string $login, string $password, bool $remember=false): array {
    $db = getDB();
    $stmt = $db->prepare('SELECT u.*,r.name AS role_name,r.label AS role_label
        FROM users u JOIN roles r ON r.id=u.role_id
        WHERE (u.username=:l OR u.email=:l) AND u.is_active=1 LIMIT 1');
    $stmt->execute([':l'=>trim($login)]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password,$user['password_hash']))
        return ['ok'=>false,'error'=>'Неверный логин или пароль'];
    $_SESSION['f1_user'] = ['id'=>$user['id'],'username'=>$user['username'],
        'first_name'=>$user['first_name'],'role'=>$user['role_name'],'role_label'=>$user['role_label']];
    $db->prepare('UPDATE users SET last_login=NOW() WHERE id=:id')->execute([':id'=>$user['id']]);
    if ($remember) {
        $token = bin2hex(random_bytes(32));
        $db->prepare('INSERT INTO sessions(user_id,token,ip_address,expires_at)
                      VALUES(:uid,:t,:ip,DATE_ADD(NOW(),INTERVAL 30 DAY))')
           ->execute([':uid'=>$user['id'],':t'=>$token,':ip'=>$_SERVER['REMOTE_ADDR']??null]);
        setcookie('f1_remember',$token,time()+86400*30,'/','',$false=false,true);
    }
    f1_log($user['id'],'login','Вход в систему');
    return ['ok'=>true,'role'=>$user['role_name']];
}

// ── 5. Выход ────────────────────────────────────────────
function f1_logout(): void {
    $uid = $_SESSION['f1_user']['id']??null;
    if (!empty($_COOKIE['f1_remember'])) {
        getDB()->prepare('DELETE FROM sessions WHERE token=:t')->execute([':t'=>$_COOKIE['f1_remember']]);
        setcookie('f1_remember','',time()-3600,'/');
    }
    if ($uid) f1_log($uid,'logout','Выход из системы');
    $_SESSION=[];
    session_destroy();
}

// ── 6. Текущий пользователь ─────────────────────────────
function f1_user(): ?array {
    if (!empty($_SESSION['f1_user'])) return $_SESSION['f1_user'];
    if (!empty($_COOKIE['f1_remember'])) {
        $stmt = getDB()->prepare('SELECT u.id,u.username,u.first_name,r.name AS role_name,r.label AS role_label
            FROM sessions s JOIN users u ON u.id=s.user_id JOIN roles r ON r.id=u.role_id
            WHERE s.token=:t AND s.expires_at>NOW() AND u.is_active=1 LIMIT 1');
        $stmt->execute([':t'=>$_COOKIE['f1_remember']]);
        $row = $stmt->fetch();
        if ($row) {
            $_SESSION['f1_user'] = ['id'=>$row['id'],'username'=>$row['username'],
                'first_name'=>$row['first_name'],'role'=>$row['role_name'],'role_label'=>$row['role_label']];
            return $_SESSION['f1_user'];
        }
    }
    return null;
}

// ── 7. Проверки ролей ───────────────────────────────────
function f1_isEditor(): bool { $u=f1_user(); return $u!==null && $u['role']==='editor'; }
function f1_isLoggedIn(): bool { return f1_user()!==null; }

// ── 8. Лог ─────────────────────────────────────────────
function f1_log(?int $uid, string $action, string $desc=''): void {
    try { getDB()->prepare('INSERT INTO activity_log(user_id,action,description,ip_address)VALUES(:u,:a,:d,:i)')
          ->execute([':u'=>$uid,':a'=>$action,':d'=>$desc,':i'=>$_SERVER['REMOTE_ADDR']??null]); }
    catch(Exception $e){}
}

// ── 9. Плашка пользователя ──────────────────────────────
function f1_userbar(): void {
    $u = f1_user();
    echo '<div id="f1-userbar" style="background:#111;padding:5px 24px;font-size:0.8rem;
          display:flex;justify-content:flex-end;align-items:center;gap:14px;
          border-bottom:1px solid rgba(255,255,255,0.06);">';
    if ($u) {
        echo '<span style="opacity:.5">👤 '.htmlspecialchars($u['first_name']).'</span>';
        echo '<span style="background:rgba(225,6,0,.15);color:#e10600;border-radius:4px;
              padding:2px 9px;font-weight:600">'.htmlspecialchars($u['role_label']).'</span>';
        if ($u['role']==='editor') {
            echo '<button onclick="F1Editor.toggleEditMode()" id="edit-mode-btn"
                  style="background:none;border:1px solid rgba(255,255,255,.2);color:#f0f0f0;
                  border-radius:4px;padding:3px 12px;cursor:pointer;font-size:0.78rem;">
                  ✏ Режим редактирования</button>';
        }
        echo '<a href="auth.php?page=logout" style="opacity:.45;color:inherit;text-decoration:none;
              border:1px solid rgba(255,255,255,.15);border-radius:4px;padding:3px 10px;">Выйти</a>';
    } else {
        echo '<a href="auth.php?page=login" style="color:inherit;text-decoration:none;opacity:.6">Войти</a>';
        echo '<a href="auth.php?page=register" style="color:#fff;background:#e10600;text-decoration:none;
              border-radius:4px;padding:4px 14px;">Регистрация</a>';
    }
    echo '</div>';
    // Передаём роль в JS
    $role = $u ? $u['role'] : 'guest';
    echo '<script>window.F1_USER_ROLE="'.$role.'";</script>';
}

// ══════════════════════════════════════════════════════
//  СТРАНИЦЫ ВХОДА И РЕГИСТРАЦИИ
//  Открывать: auth.php?page=login  /  auth.php?page=register
// ══════════════════════════════════════════════════════
$page   = $_GET['page'] ?? $_POST['page'] ?? '';
$errors = [];
$done   = false;

if ($page==='logout') { f1_logout(); header('Location: index.html'); exit; }

if ($page==='login' && $_SERVER['REQUEST_METHOD']==='POST') {
    $r = f1_login($_POST['username']??'',$_POST['password']??'',isset($_POST['remember']));
    if ($r['ok']) { header('Location: index.html'); exit; }
    $errors[] = $r['error'];
}

if ($page==='register' && $_SERVER['REQUEST_METHOD']==='POST') {
    $pwd=$_POST['password']??''; $conf=$_POST['confirm']??'';
    if ($pwd!==$conf)         $errors[]='Пароли не совпадают';
    elseif(!isset($_POST['agree'])) $errors[]='Необходимо принять условия использования';
    else {
        $r = f1_register(['username'=>$_POST['username']??'','email'=>$_POST['email']??'',
            'password'=>$pwd,'first_name'=>$_POST['firstname']??'',
            'last_name'=>$_POST['lastname']??'','country'=>$_POST['country']??'']);
        if ($r['ok']) { f1_login($_POST['username'],$pwd); $done=true; }
        else $errors[]=$r['error'];
    }
}

if (in_array($page,['login','register','']) && !($page==='' && headers_sent())) {
    if (f1_user()&&!$done) { header('Location: index.html'); exit; }
    $isLogin = ($page!=='register');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= $isLogin?'Вход':'Регистрация' ?> — Формула‑1</title>
<link rel="stylesheet" href="coloring.css">
<style>
.auth-wrap{max-width:460px;margin:50px auto;padding:0 16px}
.auth-wrap h1{margin-bottom:6px}
.auth-sub{opacity:.5;font-size:.88rem;margin-bottom:24px}
.auth-sub a{color:inherit}
.tabs{display:flex;border-bottom:1px solid rgba(255,255,255,.1);margin-bottom:24px}
.tab{padding:10px 22px;text-decoration:none;color:inherit;opacity:.5;
     border-bottom:2px solid transparent;font-size:.92rem;transition:all .2s}
.tab.active{opacity:1;border-bottom-color:#e10600;font-weight:600}
.field{margin-bottom:15px}
.field label{display:block;margin-bottom:5px;font-size:.87rem;opacity:.7}
.field input,.field select{width:100%;padding:10px 12px;border-radius:5px;box-sizing:border-box;
  border:1px solid rgba(255,255,255,.15);background:rgba(255,255,255,.05);
  color:inherit;font-size:.93rem;font-family:inherit;outline:none;
  transition:border-color .2s;appearance:none}
.field input:focus,.field select:focus{border-color:rgba(255,255,255,.4)}
.field select{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23aaa' stroke-width='1.5' fill='none'/%3E%3C/svg%3E");
  background-repeat:no-repeat;background-position:right 12px center;padding-right:34px;cursor:pointer}
.field select option{background:#1a1a1a}
.field-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.pwd-wrap{position:relative}.pwd-wrap input{padding-right:42px}
.pwd-toggle{position:absolute;right:10px;top:50%;transform:translateY(-50%);
  background:none;border:none;color:inherit;opacity:.5;cursor:pointer;font-size:1rem}
.strength-bar{display:flex;gap:4px;margin-top:6px}
.strength-bar span{flex:1;height:3px;border-radius:2px;background:rgba(255,255,255,.1);transition:background .3s}
.strength-label{font-size:.74rem;opacity:.55;margin-top:3px}
.check-row{display:flex;align-items:flex-start;gap:9px;font-size:.84rem;opacity:.7;margin-bottom:18px;cursor:pointer}
.check-row input{width:15px;height:15px;flex-shrink:0;margin-top:2px;accent-color:#e10600;cursor:pointer}
.remember-row{display:flex;align-items:center;gap:8px;font-size:.84rem;opacity:.65;margin-bottom:18px}
.remember-row input{width:auto;accent-color:#e10600}
.btn-submit{width:100%;padding:12px;border:none;border-radius:5px;background:#e10600;color:#fff;
  font-size:1rem;font-family:inherit;font-weight:600;cursor:pointer;transition:background .2s}
.btn-submit:hover{background:#b80500}
.form-options{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;font-size:.85rem}
.forgot-link{color:inherit;opacity:.7;text-decoration:none}
.forgot-link:hover{opacity:1;text-decoration:underline}
.error-msg{color:#e10600;font-size:.85rem;margin-bottom:12px;padding:10px 14px;
  background:rgba(225,6,0,.08);border-radius:5px;border-left:3px solid #e10600}
.success-box{text-align:center;padding:48px 0}
.success-box .icon{font-size:3rem;margin-bottom:14px}
.success-box p{opacity:.65;margin-bottom:20px}
.back-btn{display:inline-block;padding:10px 28px;border:1px solid rgba(255,255,255,.25);
  border-radius:5px;color:inherit;text-decoration:none;transition:background .2s}
.back-btn:hover{background:rgba(255,255,255,.07)}
@media(max-width:480px){.field-row{grid-template-columns:1fr}}
</style>
</head>
<body>
<?php f1_userbar(); ?>
<header class="header">
  <div class="logo"><a href="index.html"><img src="https://resources.formulaf1.com/assets/images/logo.png" alt="Формула‑1" width="120"></a></div>
  <nav class="nav">
    <button class="nav-menu-btn" aria-haspopup="true" aria-expanded="false" aria-controls="nav-dropdown">Меню <span class="arrow">▾</span></button>
    <div class="nav-dropdown" id="nav-dropdown" role="menu">
      <a href="index.html">Главная</a>
      <a href="auth.php?page=login">Войти</a>
      <a href="auth.php?page=register">Регистрация</a>
      <a href="calendar.html">Календарь</a>
      <a href="pilots.html">Пилоты</a>
      <a href="teams.html">Команды</a>
      <a href="result.html">Результаты</a>
      <a href="circitc.html">Трассы</a>
      <a href="about.html">О нас</a>
      <a href="contacts.html">Контакты</a>
    </div>
  </nav>
</header>

<main class="main">
<div class="auth-wrap">
  <div class="tabs">
    <a href="auth.php?page=login"    class="tab <?= $isLogin?'active':'' ?>">Вход</a>
    <a href="auth.php?page=register" class="tab <?= !$isLogin?'active':'' ?>">Регистрация</a>
  </div>
  <?php foreach($errors as $e): ?><div class="error-msg">⚠ <?=htmlspecialchars($e)?></div><?php endforeach; ?>

  <?php if($done): ?>
  <div class="success-box"><div class="icon">✅</div><h2>Аккаунт создан!</h2>
    <p>Добро пожаловать в мир Формулы‑1.</p><a href="index.html" class="back-btn">На главную</a></div>

  <?php elseif($isLogin): ?>
  <form method="POST" action="auth.php?page=login">
    <input type="hidden" name="page" value="login">
    <div class="field"><label>Логин или Email</label>
      <input type="text" name="username" value="<?=htmlspecialchars($_POST['username']??'')?>" placeholder="admin" required></div>
    <div class="field"><label>Пароль</label>
      <div class="pwd-wrap"><input type="password" id="pl" name="password" placeholder="••••••••" required>
      <button type="button" class="pwd-toggle" data-t="pl">👁</button></div></div>
    <div class="form-options">
      <label class="remember-row"><input type="checkbox" name="remember"> <span>Запомнить меня</span></label>
      <a href="#" class="forgot-link">Забыли пароль?</a></div>
    <button type="submit" class="btn-submit">Войти</button>
  </form>

  <?php else: ?>
  <form method="POST" action="auth.php?page=register" id="reg-form" novalidate>
    <input type="hidden" name="page" value="register">
    <div class="field-row">
      <div class="field"><label>Имя</label><input type="text" name="firstname" value="<?=htmlspecialchars($_POST['firstname']??'')?>" placeholder="Николай" required></div>
      <div class="field"><label>Фамилия</label><input type="text" name="lastname" value="<?=htmlspecialchars($_POST['lastname']??'')?>" placeholder="Абросимов" required></div>
    </div>
    <div class="field"><label>Имя пользователя</label><input type="text" name="username" value="<?=htmlspecialchars($_POST['username']??'')?>" placeholder="f1_fan_2026" required></div>
    <div class="field"><label>Email</label><input type="email" name="email" value="<?=htmlspecialchars($_POST['email']??'')?>" placeholder="you@example.com" required></div>
    <div class="field"><label>Страна</label><select name="country">
      <option value="">— Выберите страну —</option>
      <?php foreach(['Россия'=>'🇷🇺','Германия'=>'🇩🇪','Великобритания'=>'🇬🇧','Франция'=>'🇫🇷','Италия'=>'🇮🇹','Испания'=>'🇪🇸','США'=>'🇺🇸','Япония'=>'🇯🇵','Бразилия'=>'🇧🇷','Австралия'=>'🇦🇺','Другая'=>'🌍'] as $n=>$f):
        $s=(($_POST['country']??'')===$n)?'selected':''; ?>
      <option value="<?=$n?>" <?=$s?>><?=$f?> <?=$n?></option>
      <?php endforeach; ?>
    </select></div>
    <div class="field"><label>Пароль</label>
      <div class="pwd-wrap"><input type="password" id="pr" name="password" placeholder="Минимум 8 символов" required>
      <button type="button" class="pwd-toggle" data-t="pr">👁</button></div>
      <div class="strength-bar"><span id="s1"></span><span id="s2"></span><span id="s3"></span><span id="s4"></span></div>
      <div class="strength-label" id="slbl"></div></div>
    <div class="field"><label>Подтверждение пароля</label>
      <div class="pwd-wrap"><input type="password" id="pc" name="confirm" placeholder="Повторите пароль" required>
      <button type="button" class="pwd-toggle" data-t="pc">👁</button></div></div>
    <label class="check-row"><input type="checkbox" name="agree" required>
      <span>Я согласен с <a href="#">условиями использования</a></span></label>
    <button type="submit" class="btn-submit">Создать аккаунт</button>
  </form>
  <?php endif; ?>
</div>
</main>
<footer class="footer">
  <p>&copy; 2026 Формула‑1. Все права защищены.</p>
  <p>Данные из официального источника: <a href="https://www.formula1.com">formula1.com</a></p>
</footer>
<script src="scripts.js"></script>
<script>
document.querySelectorAll('.pwd-toggle').forEach(b=>{
  b.addEventListener('click',()=>{
    const i=document.getElementById(b.dataset.t);
    i.type=i.type==='password'?'text':'password'; b.textContent=i.type==='text'?'🙈':'👁';
  });
});
const pr=document.getElementById('pr');
if(pr){
  const bars=[1,2,3,4].map(i=>document.getElementById('s'+i));
  const lbl=document.getElementById('slbl');
  const clr=['#e10600','#f97316','#eab308','#22c55e'];
  const nm=['Очень слабый','Слабый','Средний','Сильный'];
  pr.addEventListener('input',function(){
    let v=this.value,s=0;
    if(v.length>=8)s++;if(v.length>=12)s++;
    if(/[A-Z]/.test(v)&&/[a-z]/.test(v))s++;
    if(/\d/.test(v))s++;if(/[^A-Za-z0-9]/.test(v))s++;
    s=Math.min(4,s);
    bars.forEach((b,i)=>{b.style.background=i<s?clr[s-1]:'rgba(255,255,255,.1)';});
    lbl.textContent=v?nm[s-1]||'':''; lbl.style.color=v?clr[s-1]:'';
  });
  document.getElementById('reg-form').addEventListener('submit',e=>{
    const p=document.getElementById('pr').value,c=document.getElementById('pc').value;
    if(p.length<8){e.preventDefault();alert('Пароль минимум 8 символов');return;}
    if(p!==c){e.preventDefault();alert('Пароли не совпадают');}
  });
}
</script>
</body></html>
<?php } ?>
