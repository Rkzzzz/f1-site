<?php
// ═══════════════════════════════════════════════════════
//  content_api.php — API для редактирования контента
//  GET  ?action=load&page=result  → возвращает JSON с контентом
//  POST ?action=save              → сохраняет изменение
// ═══════════════════════════════════════════════════════
require_once 'auth.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── Загрузить контент страницы ──────────────────────────
if ($action === 'load') {
    $page = preg_replace('/[^a-z0-9_]/', '', strtolower($_GET['page'] ?? ''));
    if (!$page) { echo json_encode(['ok'=>false,'error'=>'Не указана страница']); exit; }

    $stmt = getDB()->prepare('SELECT `key`, value FROM page_content WHERE page=:p');
    $stmt->execute([':p' => $page]);
    $rows = $stmt->fetchAll();

    $data = [];
    foreach ($rows as $row) $data[$row['key']] = $row['value'];
    echo json_encode(['ok'=>true,'data'=>$data]);
    exit;
}

// ── Сохранить блок контента ─────────────────────────────
if ($action === 'save') {
    // Только редактор может сохранять
    if (!f1_isEditor()) {
        http_response_code(403);
        echo json_encode(['ok'=>false,'error'=>'Недостаточно прав']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $page  = preg_replace('/[^a-z0-9_]/', '', strtolower($input['page'] ?? ''));
    $key   = preg_replace('/[^a-zA-Z0-9_]/', '', $input['key'] ?? '');
    $value = trim($input['value'] ?? '');

    if (!$page || !$key) {
        echo json_encode(['ok'=>false,'error'=>'Не указан page или key']); exit;
    }

    $db = getDB();
    $uid = f1_user()['id'];

    // INSERT или UPDATE (upsert)
    $db->prepare('INSERT INTO page_content (page,`key`,value,updated_by)
                  VALUES(:p,:k,:v,:u)
                  ON DUPLICATE KEY UPDATE value=:v2, updated_by=:u2, updated_at=NOW()')
       ->execute([':p'=>$page,':k'=>$key,':v'=>$value,':u'=>$uid,':v2'=>$value,':u2'=>$uid]);

    f1_log($uid, 'edit_content', "Изменён блок {$page}.{$key}");
    echo json_encode(['ok'=>true,'message'=>'Сохранено']);
    exit;
}

echo json_encode(['ok'=>false,'error'=>'Неизвестное действие']);
