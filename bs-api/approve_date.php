<?php
session_start();
require_once __DIR__.'/../config.php';

$user = get_user();
if (!$user || !is_staff()) { http_response_code(403); exit; }

$id     = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';
$db     = get_db();

if ($action === 'approve') {
    $stmt = $db->prepare("SELECT * FROM bs_date_submissions WHERE id=?");
    $stmt->execute([$id]);
    $sub = $stmt->fetch();
    if ($sub) {
        $db->prepare("UPDATE bs_mints SET mint_date=?, status='approved' WHERE id=?")
           ->execute([$sub['proposed_date'], $sub['mint_id']]);
        $db->prepare("UPDATE bs_date_submissions SET status='approved' WHERE id=?")
           ->execute([$id]);
    }
} elseif ($action === 'reject') {
    $db->prepare("UPDATE bs_date_submissions SET status='rejected' WHERE id=?")->execute([$id]);
}

header('Location: /admin?success=Done');
exit;
