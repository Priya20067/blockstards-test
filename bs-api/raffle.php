<?php
require_once __DIR__.'/../config.php';
header('Content-Type: application/json');
$id = (int)($_GET['id'] ?? 0);
if (!$id) { echo json_encode(['error'=>'No ID']); exit; }

$r = db()->prepare("SELECT * FROM bs_raffles WHERE id=?");
$r->execute([$id]);
$raffle = $r->fetch();
if (!$raffle) { echo json_encode(['error'=>'Not found']); exit; }

// Entry count — separate simple query (the shim can't parse correlated subqueries)
$ec = db()->prepare("SELECT COUNT(*) FROM bs_raffle_entries WHERE raffle_id=?");
$ec->execute([$id]);
$raffle['entry_count'] = (int)$ec->fetchColumn();

// Ensure end_date is properly formatted
if ($raffle['end_date'] && strtotime($raffle['end_date']) === false) {
    $raffle['end_date'] = null;
}

$user    = get_user();
$entered = false;
if ($user) {
    $s = db()->prepare("SELECT 1 FROM bs_raffle_entries WHERE raffle_id=? AND discord_id=?");
    $s->execute([$id, $user['discord_id']]);
    $entered = (bool)$s->fetch();
}

// Winners + their wallets (only shown when ended)
$winners = [];
if ($raffle['status'] === 'ended') {
    $ws = db()->prepare("SELECT discord_id FROM bs_raffle_winners WHERE raffle_id=?");
    $ws->execute([$id]);
    $winnerRows = $ws->fetchAll();
    foreach ($winnerRows as $w) {
        $did = $w['discord_id'];
        $u = sb('bs_users')->eq('discord_id', $did)->select('username')->first();
        $wEth = sb('bs_user_wallets')->eq('discord_id', $did)->eq('chain', 'Ethereum')->select('address')->first();
        $wSol = sb('bs_user_wallets')->eq('discord_id', $did)->eq('chain', 'Solana')->select('address')->first();
        $winners[] = [
            'discord_id' => $did,
            'username'   => $u['username'] ?? null,
            'eth_wallet' => $wEth['address'] ?? null,
            'sol_wallet' => $wSol['address'] ?? null,
        ];
    }
}

// Never expose entrant list — only count
unset($raffle['entries_json']); // safety

echo json_encode([
    'raffle'  => $raffle,
    'entered' => $entered,
    'winners' => $winners,
    // No entrant names/IDs ever returned
]);