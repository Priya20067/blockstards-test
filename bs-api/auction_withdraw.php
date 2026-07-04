<?php
require_once __DIR__.'/../config.php';
header('Content-Type: application/json');

$user = get_user();
if (!$user) { echo json_encode(['success'=>false,'message'=>'Login with Discord first']); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$aid  = trim($data['auction_id'] ?? '');
if (!$aid) { echo json_encode(['success'=>false,'message'=>'Invalid auction ID']); exit; }

$uid = $user['discord_id'];

// Get auction
$auction = sb('bs_auctions')->eq('id', $aid)->eq('status', 'active')->first();
if (!$auction) { echo json_encode(['success'=>false,'message'=>'Auction not found or ended']); exit; }
if ($auction['ends_at'] && strtotime($auction['ends_at']) < time()) {
    echo json_encode(['success'=>false,'message'=>'Auction has ended']); exit;
}

// Use auction's actual guild_id
$gid = $auction['guild_id'] ?? DISCORD_GUILD_ID;

$bids        = is_array($auction['bids_json']) ? $auction['bids_json'] : (json_decode($auction['bids_json']??'{}', true) ?: []);
$current_bid = (float)($bids[$uid] ?? 0);
if ($current_bid <= 0) {
    echo json_encode(['success'=>false,'message'=>'No active bid to withdraw']); exit;
}

// Refund BLOX to user
$bal_row = sb('bs_user_blox')->eq('discord_id', $uid)->eq('guild_id', $gid)->select('balance')->first();
$balance = $bal_row ? (float)$bal_row['balance'] : 0;
$new_bal = round($balance + $current_bid, 4);
if ($bal_row) {
    // Row exists — update it
    sb('bs_user_blox')->eq('discord_id', $uid)->eq('guild_id', $gid)->update(['balance' => $new_bal]);
} else {
    // Row missing — upsert it (shouldn't happen but safety net)
    sb('bs_user_blox')->upsert([
        'discord_id' => $uid,
        'guild_id'   => $gid,
        'balance'    => $new_bal,
    ]);
}

// Remove bid from auction
unset($bids[$uid]);
$usernames = is_array($auction['usernames_json']) ? $auction['usernames_json'] : (json_decode($auction['usernames_json']??'{}', true) ?: []);
unset($usernames[$uid]);
sb('bs_auctions')->eq('id', $aid)->update([
    'bids_json'      => $bids,
    'usernames_json' => $usernames,
]);

// Queue for bot Discord embed sync (amount=0 signals withdrawal)
$queue_result = sb('bs_auction_web_bids')->insert([
    'auction_id' => $aid,
    'discord_id' => $uid,
    'guild_id'   => $gid,
    'amount'     => 0,
    'processed'  => false,
]);

$queue_ok    = isset($queue_result['code']) && $queue_result['code'] < 400;
$queue_error = $queue_ok ? null : ($queue_result['error'] ?? 'Queue insert failed');

echo json_encode([
    'success'     => true,
    'message'     => "Bid of {$current_bid} \$BLOX refunded!" . ($queue_error ? " (sync warning: {$queue_error})" : ''),
    'new_balance' => $new_bal,
    'refunded'    => $current_bid,
    'queue_ok'    => $queue_ok,
    'queue_error' => $queue_error,
]);