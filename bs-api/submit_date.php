<?php
session_start();
require_once __DIR__.'/../config.php';
header('Content-Type: application/json');

$user = get_user();
if (!$user) { echo json_encode(['error'=>'Login required']); exit; }

$d    = json_decode(file_get_contents('php://input'), true);
$mid  = (int)($d['mint_id'] ?? 0);
$date = trim($d['proposed_date'] ?? '');
$src  = trim($d['source_url'] ?? '');
if (!$mid || !$date) { echo json_encode(['error'=>'Missing fields']); exit; }

$db = get_db();
$db->prepare("INSERT INTO bs_date_submissions (mint_id, discord_id, proposed_date, source_url) VALUES (?,?,?,?)")
   ->execute([$mid, $user['discord_id'], $date, $src]);

echo json_encode(['success'=>true]);
