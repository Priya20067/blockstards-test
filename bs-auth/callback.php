<?php
session_start();
require_once __DIR__.'/../../config.php';

$code  = $_GET['code']  ?? '';
$state = $_GET['state'] ?? '';

if (empty($code) || $state !== ($_SESSION['discord_state'] ?? '')) {
    header('Location: /raffles?error=Auth+failed'); exit;
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
        'code'          => $code,
        'redirect_uri'  => DISCORD_REDIRECT_URI,
    ]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
]);
$token = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!$token || !isset($token['access_token'])) {
    header('Location: /raffles?error=Token+failed'); exit;
}

// Get user info
$ch = curl_init('https://discord.com/api/users/@me');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer '.$token['access_token']],
]);
$discord_user = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!$discord_user || !isset($discord_user['id'])) {
    header('Location: /raffles?error=User+fetch+failed'); exit;
}

// Save to DB
$db = get_db();
$db->prepare("INSERT INTO bs_users (discord_id, username, discriminator, avatar, access_token, token_expires)
              VALUES (?,?,?,?,?,?)
              ON DUPLICATE KEY UPDATE username=VALUES(username), avatar=VALUES(avatar), access_token=VALUES(access_token), last_login=NOW()")
   ->execute([
       $discord_user['id'],
       $discord_user['username'],
       $discord_user['discriminator'] ?? '0',
       $discord_user['avatar'] ?? '',
       $token['access_token'],
       time() + ($token['expires_in'] ?? 604800),
   ]);

// Set session
$_SESSION['user'] = [
    'discord_id' => $discord_user['id'],
    'username'   => $discord_user['username'],
    'avatar'     => $discord_user['avatar'] ?? '',
];

$return = $_SESSION['oauth_return'] ?? '/raffles';
unset($_SESSION['discord_state'], $_SESSION['oauth_return']);
header('Location: '.$return);
exit;
