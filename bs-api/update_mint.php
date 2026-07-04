<?php
require_once __DIR__.'/../config.php';

header('Content-Type: application/json');

if (!is_staff()) {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) $data = $_POST;

$id = (int)($data['mint_id'] ?? 0);
if (!$id) { echo json_encode(['success'=>false,'message'=>'No mint ID']); exit; }

$name      = trim($data['name']     ?? '');
$twitter   = ltrim(trim($data['twitter']  ?? ''), '@');
$chain     = trim($data['chain']    ?? 'Ethereum');
$mint_url  = trim($data['mint_url'] ?? '');
$price     = trim($data['price']    ?? '');
$supply    = trim($data['supply']   ?? '');
$mint_date = trim($data['mint_date']?? '');

if (!$name) { echo json_encode(['success'=>false,'message'=>'Name required']); exit; }

// Auto-fetch banner if twitter handle provided
$image_url = '';
if ($twitter) {
    $existing = sb('bs_mints')->eq('id',(string)$id)->select('twitter,image_url')->first();
    if (($existing['twitter']??'') !== $twitter || !($existing['image_url']??'')) {
        $ctx = stream_context_create(['http'=>['timeout'=>4,'header'=>"X-API-Key: 2d725456-9686-4ecf-9cff-a9e0c8f74041\r\n"]]);
        $raw = @file_get_contents("https://api.sorsa.io/v3/info?username=$twitter", false, $ctx);
        if ($raw) {
            $info = json_decode($raw, true);
            foreach (['banner_url','profile_banner_url','profile_background_image_url'] as $k) {
                if (!empty($info[$k])) { $image_url = $info[$k]; break; }
            }
        }
        if (!$image_url) $image_url = "https://unavatar.io/twitter/$twitter";
    }
}

$update = [
    'name'      => $name,
    'twitter'   => $twitter,
    'chain'     => $chain,
    'mint_url'  => $mint_url,
    'price'     => $price,
    'supply'    => $supply,
    'mint_date' => $mint_date ?: null,
    'status'    => $mint_date ? 'approved' : 'tba',
];
if ($image_url) $update['image_url'] = $image_url;

try {
    sb('bs_mints')->eq('id',(string)$id)->update($update);
    echo json_encode(['success'=>true,'message'=>'Saved!']);
} catch(Exception $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}