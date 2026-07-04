<?php
session_start();
require_once __DIR__.'/../config.php';
header('Content-Type: application/json');

$user = get_user();
if (!$user) { echo json_encode(['error'=>'Login required']); exit; }

$d = json_decode(file_get_contents('php://input'), true);
$name = trim($d['name'] ?? '');
if (!$name) { echo json_encode(['error'=>'Name required']); exit; }

$db = get_db();
$db->prepare("INSERT INTO bs_mints (name, description, image_url, mint_url, chain, mint_date, price, supply, status, submitted_by) VALUES (?,?,?,?,?,?,?,?,?,?)")
   ->execute([
       $name,
       trim($d['description'] ?? ''),
       trim($d['image_url'] ?? ''),
       trim($d['mint_url'] ?? ''),
       $d['chain'] ?? 'Ethereum',
       $d['mint_date'] ?: null,
       $d['price'] ?: 'TBA',
       $d['supply'] ?: 'TBA',
       'pending',
       $user['discord_id']
   ]);

echo json_encode(['success'=>true]);
