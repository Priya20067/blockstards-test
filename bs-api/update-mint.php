<?php
require_once __DIR__.'/../config.php';
header('Content-Type: application/json');

$user = get_user();
if (!$user || !is_staff()) {
    echo json_encode(['success'=>false,'message'=>'Staff only']); exit;
}

$data     = json_decode(file_get_contents('php://input'), true);
$mint_id  = (int)($data['mint_id'] ?? 0);
if (!$mint_id) { echo json_encode(['success'=>false,'message'=>'Invalid mint ID']); exit; }

$update = [];
if (isset($data['name'])     && trim($data['name']))     $update['name']     = trim($data['name']);
if (isset($data['chain'])    && trim($data['chain']))    $update['chain']    = trim($data['chain']);
if (isset($data['price']))                               $update['price']    = trim($data['price']);
if (isset($data['supply']))                              $update['supply']   = trim($data['supply']);
if (isset($data['mint_url']))                            $update['mint_url'] = trim($data['mint_url']);
if (isset($data['twitter']))                             $update['twitter']  = ltrim(trim($data['twitter']), '@');
if (isset($data['mint_date']) && $data['mint_date'])     $update['mint_date'] = $data['mint_date'];

if (empty($update)) { echo json_encode(['success'=>false,'message'=>'Nothing to update']); exit; }

// Auto-fetch image from twitter if twitter changed and no custom image
if (!empty($update['twitter'])) {
    $existing = sb('bs_mints')->eq('id', (string)$mint_id)->select('image_url,twitter')->first();
    $old_tw = $existing['twitter'] ?? '';
    if ($update['twitter'] !== $old_tw) {
        $tw = $update['twitter'];
        $ctx = stream_context_create(['http'=>['timeout'=>3,'header'=>'X-API-Key: 2d725456-9686-4ecf-9cff-a9e0c8f74041']]);
        $raw = @file_get_contents("https://api.sorsa.io/v3/info?username=$tw", false, $ctx);
        if ($raw) {
            $info = json_decode($raw, true);
            $banner = $info['banner_url'] ?? '';
            if ($banner) $update['image_url'] = $banner;
        }
        if (empty($update['image_url'])) $update['image_url'] = "https://unavatar.io/twitter/$tw";
    }
}

$result = sb('bs_mints')->eq('id', (string)$mint_id)->update($update);

if (isset($result['code']) && $result['code'] >= 400) {
    echo json_encode(['success'=>false,'message'=>$result['error']??'Update failed']); exit;
}

echo json_encode(['success'=>true,'message'=>'Mint updated']);