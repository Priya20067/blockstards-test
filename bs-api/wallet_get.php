<?php
require_once __DIR__.'/../config.php';
header('Content-Type: application/json');

$user = get_user();
if (!$user) { echo json_encode(['success'=>false,'wallets'=>[]]); exit; }

$rows    = sb('bs_user_wallets')->eq('discord_id', $user['discord_id'])->get();
$wallets = [];
foreach ($rows as $w) {
    $wallets[$w['chain']] = ['address'=>$w['address'],'via'=>$w['added_via']??'website'];
}
echo json_encode(['success'=>true,'wallets'=>$wallets]);
