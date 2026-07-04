<?php
require_once __DIR__.'/../config.php';
header('Content-Type: application/json');

$user = get_user();
if (!$user) { echo json_encode(['success'=>false,'message'=>'Not logged in']); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$chain   = trim($data['chain']   ?? '');
$address = trim($data['address'] ?? '');

if (!$chain || !$address) {
    echo json_encode(['success'=>false,'message'=>'Missing chain or address']);
    exit;
}

// Basic sanity validation
if ($chain === 'Ethereum' && !preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
    echo json_encode(['success'=>false,'message'=>'Invalid Ethereum address']);
    exit;
}
if ($chain === 'Solana' && (strlen($address) < 32 || strlen($address) > 44)) {
    echo json_encode(['success'=>false,'message'=>'Invalid Solana address']);
    exit;
}

try {
    // Check if wallet for this chain already exists
    $existing = sb('bs_user_wallets')
        ->eq('discord_id', $user['discord_id'])
        ->eq('chain', $chain)
        ->select('address')
        ->first();

    if ($existing) {
        // Update existing
        sb('bs_user_wallets')
            ->eq('discord_id', $user['discord_id'])
            ->eq('chain', $chain)
            ->update(['address' => $address, 'added_via' => 'wallet_connect']);
    } else {
        // Insert new
        sb('bs_user_wallets')->insert([
            'discord_id' => $user['discord_id'],
            'chain'      => $chain,
            'address'    => $address,
            'added_via'  => 'wallet_connect',
        ]);
    }

    echo json_encode([
        'success'  => true,
        'address'  => $address,
        'chain'    => $chain,
        'updated'  => !empty($existing),
        'message'  => ($chain === 'Solana' ? 'SOL' : 'ETH') . ' wallet saved! Discord bot is now synced.'
    ]);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}