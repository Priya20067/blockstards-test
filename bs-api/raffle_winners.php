<?php
require_once '../config.php';
header('Content-Type: application/json');
$id = (int)($_GET['id'] ?? 0);
$winners = db()->prepare("SELECT w.discord_id, u.username, w.won_at FROM bs_raffle_winners w LEFT JOIN bs_users u ON u.discord_id=w.discord_id WHERE w.raffle_id=?");
$winners->execute([$id]);
echo json_encode($winners->fetchAll());
