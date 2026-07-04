<?php
require_once __DIR__.'/../config.php';
header('Content-Type: application/json');

$user = get_user();
if (!$user) { echo json_encode(['success'=>false,'message'=>'Login with Discord first']); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$rid  = (int)($data['raffle_id'] ?? 0);
if (!$rid) { echo json_encode(['success'=>false,'message'=>'Invalid raffle']); exit; }

$uid = $user['discord_id'];
$gid = DISCORD_GUILD_ID;

$raffle = sb('bs_raffles')->eq('id', $rid)->eq('status', 'active')->first();
if (!$raffle) { echo json_encode(['success'=>false,'message'=>'Raffle not found or ended']); exit; }
if ($raffle['end_date'] && strtotime($raffle['end_date']) < time()) {
    echo json_encode(['success'=>false,'message'=>'Raffle has ended']); exit;
}

$existing = sb('bs_raffle_entries')->eq('raffle_id', $rid)->eq('discord_id', $uid)->first();
if ($existing) { echo json_encode(['success'=>false,'message'=>'Already entered!']); exit; }

$new_bal = null;
if (in_array($raffle['entry_type'], ['blox', 'both'])) {
    $cost    = (float)$raffle['blox_cost'];
    $bal_row = sb('bs_user_blox')->eq('discord_id', $uid)->eq('guild_id', $gid)->select('balance')->first();
    $balance = $bal_row ? (float)$bal_row['balance'] : 0;
    if ($balance < $cost) {
        echo json_encode(['success'=>false,'message'=>"Not enough \$BLOX. Have {$balance}, need {$cost}"]); exit;
    }
    $new_bal = round($balance - $cost, 4);
    sb('bs_user_blox')->eq('discord_id', $uid)->eq('guild_id', $gid)->update(['balance' => $new_bal]);
}

sb('bs_raffle_entries')->insert([
    'raffle_id'    => $rid,
    'discord_id'   => $uid,
    'entry_method' => 'website',
]);

echo json_encode(['success'=>true,'message'=>'Entered! Good luck 🎉','new_balance'=>$new_bal]);
