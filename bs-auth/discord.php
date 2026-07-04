<?php
require_once __DIR__.'/../config.php';

$redirect = $_GET['redirect'] ?? '/raffles/';

if (!isset($_GET['code'])) {
    $_SESSION['oauth_state']    = bin2hex(random_bytes(16));
    $_SESSION['oauth_redirect'] = $redirect;
    $params = http_build_query([
        'client_id'     => DISCORD_CLIENT_ID,
        'redirect_uri'  => DISCORD_REDIRECT_URI,
        'response_type' => 'code',
        'scope'         => 'identify',
        'state'         => $_SESSION['oauth_state'],
    ]);
    header('Location: https://discord.com/oauth2/authorize?' . $params);
    exit;
}

if ($_GET['state'] !== ($_SESSION['oauth_state'] ?? '')) {
    die('Invalid state — please try again.');
}

// Exchange code for token
$ch = curl_init('https://discord.com/api/oauth2/token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'client_id'     => DISCORD_CLIENT_ID,
        'client_secret' => DISCORD_CLIENT_SECRET,
        'grant_type'    => 'authorization_code',
        'code'          => $_GET['code'],
        'redirect_uri'  => DISCORD_REDIRECT_URI,
    ]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
]);
$token = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!$token || !isset($token['access_token'])) {
    die('Auth failed: ' . json_encode($token));
}

// Get Discord user
$ch = curl_init('https://discord.com/api/users/@me');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token['access_token']],
]);
$duser = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!$duser || !isset($duser['id'])) {
    die('Could not fetch Discord user.');
}

global $STAFF_IDS;
$is_staff = in_array($duser['id'], $STAFF_IDS) ? 1 : 0;

// Upsert to Supabase bs_users
sb('bs_users')->upsert([
    'discord_id'   => $duser['id'],
    'username'     => $duser['username'],
    'avatar'       => $duser['avatar'] ?? '',
    'access_token' => $token['access_token'],
    'refresh_token'=> $token['refresh_token'] ?? '',
    'token_expires'=> time() + ($token['expires_in'] ?? 604800),
    'joined_at'    => time(),
    'is_staff'     => $is_staff,
], 'discord_id');

$_SESSION['bs_user'] = [
    'discord_id' => $duser['id'],
    'username'   => $duser['username'],
    'avatar'     => $duser['avatar'] ?? '',
    'is_staff'   => $is_staff,
];

$go = $_SESSION['oauth_redirect'] ?? '/raffles/';
unset($_SESSION['oauth_state'], $_SESSION['oauth_redirect']);
header('Location: ' . SITE_URL . $go);
exit;
