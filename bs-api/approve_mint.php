<?php
session_start();
require_once __DIR__.'/../config.php';

$user = get_user();
if (!$user || !is_staff()) { http_response_code(403); exit; }

$id     = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';
$db     = get_db();

if ($action === 'approve') {
    $mint = $db->prepare("SELECT * FROM bs_mints WHERE id=?")->execute([$id]);
    $stmt = $db->prepare("SELECT * FROM bs_mints WHERE id=?");
    $stmt->execute([$id]);
    $mint = $stmt->fetch();
    $status = $mint && $mint['mint_date'] ? 'approved' : 'tba';
    $db->prepare("UPDATE bs_mints SET status=?, approved_by=? WHERE id=?")
       ->execute([$status, $user['discord_id'], $id]);
} elseif ($action === 'reject') {
    $db->prepare("DELETE FROM bs_mints WHERE id=?")->execute([$id]);
}

header('Location: /admin?success=Done');
exit;
