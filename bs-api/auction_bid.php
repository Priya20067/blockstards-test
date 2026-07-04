<?php
require_once __DIR__.'/../config.php';
header('Content-Type: application/json');

$user = get_user();
if (!$user) { echo json_encode(['success'=>false,'message'=>'Login with Discord first']); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$aid  = trim($data['auction_id'] ?? '');
$amt  = round((float)($data['amount'] ?? 0), 4);
if (!$aid || $amt <= 0) { echo json_encode(['success'=>false,'message'=>'Invalid bid']); exit; }

$uid = $user['discord_id'];

// Get auction
$auction = sb('bs_auctions')->eq('id', $aid)->eq('status', 'active')->first();
if (!$auction) { echo json_encode(['success'=>false,'message'=>'Auction not found or ended']); exit; }
if ($auction['ends_at'] && strtotime($auction['ends_at']) < time()) {
    echo json_encode(['success'=>false,'message'=>'Auction has ended']); exit;
}

// Use auction's actual guild_id (supports both main and test server)
$gid = $auction['guild_id'] ?? DISCORD_GUILD_ID;

$bids        = is_array($auction['bids_json']) ? $auction['bids_json'] : (json_decode($auction['bids_json']??'{}', true) ?: []);
$current_bid = (float)($bids[$uid] ?? 0);
if ($amt <= $current_bid) { echo json_encode(['success'=>false,'message'=>"Must bid higher than your current {$current_bid}"]); exit; }
if ($amt < (float)($auction['starting_bid'] ?? 1)) {
    echo json_encode(['success'=>false,'message'=>"Min bid is {$auction['starting_bid']}"]); exit;
}

// Check balance
$bal_row = sb('bs_user_blox')->eq('discord_id', $uid)->eq('guild_id', $gid)->select('balance')->first();
$balance = $bal_row ? (float)$bal_row['balance'] : 0;
$extra   = round($amt - $current_bid, 4);
if ($balance < $extra) {
    echo json_encode(['success'=>false,'message'=>"Insufficient \$BLOX. Have {$balance}, need {$extra}"]); exit;
}

// Deduct balance
$new_bal = round($balance - $extra, 4);
if ($bal_row) {
    sb('bs_user_blox')->eq('discord_id', $uid)->eq('guild_id', $gid)->update(['balance' => $new_bal]);
} else {
    sb('bs_user_blox')->upsert([
        'discord_id' => $uid,
        'guild_id'   => $gid,
        'balance'    => $new_bal,
    ]);
}

// Update bids
$bids[$uid] = $amt;
$usernames  = is_array($auction['usernames_json']) ? $auction['usernames_json'] : (json_decode($auction['usernames_json']??'{}', true) ?: []);
$usernames[$uid] = $user['username'] ?? $uid;
sb('bs_auctions')->eq('id', $aid)->update([
    'bids_json'      => $bids,
    'usernames_json' => $usernames,
]);

// Queue for bot Discord embed sync
$queue_result = sb('bs_auction_web_bids')->insert([
    'auction_id' => $aid,
    'discord_id' => $uid,
    'guild_id'   => $gid,
    'amount'     => $amt,
    'processed'  => false,
]);

$queue_ok    = isset($queue_result['code']) && $queue_result['code'] < 400;
$queue_error = $queue_ok ? null : ($queue_result['error'] ?? 'Queue insert failed');

echo json_encode([
    'success'     => true,
    'message'     => "Bid of {$amt} \$BLOX placed!" . ($queue_error ? " (sync warning: {$queue_error})" : ''),
    'new_balance' => $new_bal,
    'queue_ok'    => $queue_ok,
    'queue_error' => $queue_error,
]);