<?php
require_once __DIR__.'/../config.php';
header('Content-Type: application/json');

$user = get_user();
if (!$user || !is_staff()) { echo json_encode(['success'=>false,'message'=>'Staff only']); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$aid  = (int)($data['auction_id'] ?? 0);
if (!$aid) { echo json_encode(['success'=>false,'message'=>'Missing auction_id']); exit; }

$auction = sb('bs_auctions')->eq('id', $aid)->first();
if (!$auction) { echo json_encode(['success'=>false,'message'=>'Auction not found']); exit; }
if ($auction['status'] === 'ended') { echo json_encode(['success'=>false,'message'=>'Already ended']); exit; }

$bids          = is_array($auction['bids_json']) ? $auction['bids_json'] : (json_decode($auction['bids_json']??'{}', true) ?: []);
$winners_count = (int)($auction['winners'] ?? 1);
arsort($bids);
$winners = array_slice(array_keys($bids), 0, $winners_count);

foreach ($winners as $wid) {
    sb('bs_wins')->insert([
        'discord_id'  => $wid,
        'win_type'    => 'auction',
        'ref_id'      => (string)$aid,
        'title'       => $auction['title'],
        'reward_type' => $auction['reward_type'] ?? 'GTD WL',
        'chain'       => $auction['chain'] ?? 'Ethereum',
        'image_url'   => $auction['image_url'] ?? '',
        'amount_paid' => $bids[$wid] ?? 0,
    ]);
}

sb('bs_auctions')->eq('id', $aid)->update(['status'=>'ended']);

// Queue bot to update Discord panel + announce winners
sb('bs_auction_end_queue')->insert(['auction_id' => $aid, 'processed' => false]);

echo json_encode(['success'=>true,'message'=>'Auction ended','winners'=>$winners]);