<?php
require_once __DIR__.'/../config.php';
header('Content-Type: application/json');

$user = get_user();
if (!$user) { echo json_encode(['success'=>false,'message'=>'Login required']); exit; }

$data  = json_decode(file_get_contents('php://input'), true);
$chain = trim($data['chain'] ?? '');
$addr  = trim($data['address'] ?? '');
if (!$chain) { echo json_encode(['success'=>false,'message'=>'Chain required']); exit; }

$uid = $user['discord_id'];

if ($addr) {
    sb('bs_user_wallets')->upsert([
        'discord_id' => $uid,
        'chain'      => $chain,
        'address'    => $addr,
        'added_via'  => 'website',
    ], 'discord_id,chain');
} else {
    sb('bs_user_wallets')->eq('discord_id', $uid)->eq('chain', $chain)->delete();
}

echo json_encode(['success'=>true,'chain'=>$chain,'address'=>$addr]);
