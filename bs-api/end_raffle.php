<?php
require_once __DIR__.'/../config.php';
header('Content-Type: application/json');

$user = get_user();
if (!$user || !is_staff()) { echo json_encode(['success'=>false,'message'=>'Staff only']); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$rid  = (int)($data['raffle_id'] ?? 0);
if (!$rid) { echo json_encode(['success'=>false,'message'=>'Missing raffle_id']); exit; }

$raffle = sb('bs_raffles')->eq('id', $rid)->first();
if (!$raffle) { echo json_encode(['success'=>false,'message'=>'Raffle not found']); exit; }

// Pick random winners
$spots   = (int)$raffle['spots'];
$entries = sb('bs_raffle_entries')->eq('raffle_id', $rid)->get();
if (empty($entries)) { echo json_encode(['success'=>false,'message'=>'No entries']); exit; }

shuffle($entries);
$winners = array_slice(array_column($entries, 'discord_id'), 0, $spots);

foreach ($winners as $wid) {
    sb('bs_raffle_winners')->insert(['raffle_id'=>$rid,'discord_id'=>$wid]);
    sb('bs_wins')->insert([
        'discord_id'  => $wid,
        'win_type'    => 'raffle',
        'ref_id'      => (string)$rid,
        'title'       => $raffle['title'],
        'reward_type' => $raffle['reward_type'] ?? 'GTD WL',
        'chain'       => $raffle['chain'] ?? 'Ethereum',
        'image_url'   => $raffle['image_url'] ?? '',
        'amount_paid' => 0,
    ]);
}

sb('bs_raffles')->eq('id', $rid)->update(['status'=>'ended']);

// Queue bot Discord announcement
sb('bs_raffle_announce_queue')->insert(['raffle_id'=>$rid,'guild_id'=>$raffle['guild_id'] ?? DISCORD_GUILD_ID]);

echo json_encode(['success'=>true,'message'=>'Raffle ended','winners'=>$winners]);