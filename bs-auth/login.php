<?php
session_start();
require_once __DIR__.'/../../config.php';

$return = $_GET['return'] ?? '/raffles';
$_SESSION['oauth_return'] = $return;

$state = bin2hex(random_bytes(16));
$_SESSION['discord_state'] = $state;

$params = http_build_query([
    'client_id'     => DISCORD_CLIENT_ID,
    'redirect_uri'  => DISCORD_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'identify',
    'state'         => $state,
]);

header('Location: https://discord.com/api/oauth2/authorize?'.$params);
exit;
