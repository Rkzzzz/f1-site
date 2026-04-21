<?php
// ═══════════════════════════════════════════════════════
//  make_hash.php — генератор хешей паролей
//
//  КАК ИСПОЛЬЗОВАТЬ:
//  1. Положи этот файл в C:\xampp\htdocs\f1site\
//  2. Открой http://localhost/f1site/make_hash.php
//  3. Скопируй хеши и вставь в phpMyAdmin (таблица users)
//  4. УДАЛИ этот файл после использования!
// ═══════════════════════════════════════════════════════
$passwords = [
    'admin'  => 'Admin123!',
    'reader' => 'Reader123!',
];

echo '<pre style="font-family:monospace;background:#1a1a1a;color:#f0f0f0;padding:30px">';
echo "<h2 style='color:#e10600'>Хеши паролей для database.sql</h2>\n\n";

foreach ($passwords as $user => $pwd) {
    $hash = password_hash($pwd, PASSWORD_BCRYPT, ['cost' => 12]);
    echo "Пользователь: <b style='color:#22c55e'>{$user}</b>\n";
    echo "Пароль:       {$pwd}\n";
    echo "Хеш:          <span style='color:#f59e0b'>{$hash}</span>\n\n";
    echo "SQL:\n";
    echo "<span style='color:#888'>UPDATE users SET password_hash='{$hash}' WHERE username='{$user}';</span>\n\n";
    echo str_repeat('─', 70) . "\n\n";
}

echo "<span style='color:#e10600'>⚠ УДАЛИ ЭТОТ ФАЙЛ ПОСЛЕ ИСПОЛЬЗОВАНИЯ!</span>";
echo '</pre>';
