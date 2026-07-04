<?php
require_once __DIR__.'/config.php';
$user = get_user();
if (!$user) { header('Location: /bs-auth/discord.php?redirect=/mobile-auction-form.php'); exit; }
$uid = $user['discord_id'];
$blox_bal = 0;
try { $blox_bal = get_balance($uid); } catch(Exception $e){}
$success=false;$error='';
$m_active = 'profile';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $title=trim($_POST['title']??'');$chain=$_POST['chain']??'Ethereum';$reward_type=$_POST['reward_type']??'GTD WL';
    $winners=max(1,(int)($_POST['winners']??1));$starting_bid=1.0;
    $duration_h=(int)($_POST['duration_h']??24);$twitter=trim($_POST['twitter']??'');$supply=trim($_POST['supply']??'');
    $mint_price=trim($_POST['mint_price']??'');$description=trim($_POST['description']??'');
    $image_url=trim($_POST['image_url']??'');
    if(!$title||!$chain||!$reward_type){ $error='Title is required.'; }
    else {
        $r=sb('bs_auction_requests')->insert(['discord_id'=>$uid,'guild_id'=>DISCORD_GUILD_ID,'requester_id'=>$uid,'title'=>$title,'description'=>$description,'winners'=>$winners,'chain'=>$chain,'reward_type'=>$reward_type,'starting_bid'=>$starting_bid,'duration_h'=>$duration_h,'twitter'=>$twitter,'supply'=>$supply?:(null),'mint_price'=>$mint_price?:(null),'image_url'=>$image_url,'status'=>'pending','expires_at'=>date('c',time()+$duration_h*3600)]);
        if(($r['code']??200)>=400){$error='Submit failed: '.($r['data']['message']??'Unknown error');}
        else{$success=true;}
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Request Auction · Blockstards</title>
<link rel="stylesheet" href="/bs_fonts.css?v=1783164697">
<link rel="stylesheet" href="/bs_mobile.css?v=1783164697">
</head>
<body>
<?php require_once __DIR__.'/includes/bs_mobile_nav.php'; ?>
<div class="m-body">

  <h1 class="m-page-title">Request Auction</h1>
  <p class="m-page-sub">Submit a project — staff review before it goes live.</p>

  <?php if($success): ?>
  <div class="m-notice m-notice-green">✓ Request submitted! You'll be pinged in Discord when it goes live.</div>
  <?php endif; if($error): ?>
  <div class="m-notice m-notice-red"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="post">
    <div class="m-panel" style="margin-bottom:14px">
      <div class="m-panel-hdr">PROJECT DETAILS</div>
      <div style="padding:16px">
        <div class="m-field">
          <label>PROJECT TITLE *</label>
          <input class="m-input" type="text" name="title" value="<?= htmlspecialchars($_POST['title']??'') ?>" placeholder="e.g. Azuki GTD WL" required>
        </div>
        <div class="m-field">
          <label>X HANDLE — banner auto-loads</label>
          <div style="display:flex;align-items:center;background:#0a0d18;border:1px solid #232838;border-radius:11px;padding:0 14px">
            <span style="font-family:'GT America Mono',monospace;font-size:14px;color:#5a6478">@</span>
            <input class="m-input" type="text" name="twitter" value="<?= htmlspecialchars($_POST['twitter']??'') ?>" placeholder="ProjectHandle" style="border:none;background:transparent;padding-left:6px" id="m_tw" oninput="doMBanner()">
          </div>
          <input type="hidden" name="image_url" id="m_img">
          <img id="m_prev" src="" style="display:none;width:100%;height:50px;object-fit:cover;border-radius:8px;margin-top:8px;border:1px solid #232838">
        </div>
        <div class="m-field">
          <label>DESCRIPTION</label>
          <textarea class="m-input" name="description" placeholder="Any details…" style="min-height:80px;resize:vertical"><?= htmlspecialchars($_POST['description']??'') ?></textarea>
        </div>
      </div>
    </div>

    <div class="m-panel" style="margin-bottom:14px">
      <div class="m-panel-hdr">AUCTION TERMS</div>
      <div style="padding:16px">
        <div class="m-field">
          <label>CHAIN</label>
          <div class="m-select-wrap"><select class="m-input" name="chain">
            <?php foreach(['Ethereum','Solana','Bitcoin','Base','Polygon','Other'] as $c): ?>
            <option <?= (($_POST['chain']??'Ethereum')===$c)?'selected':'' ?>><?= $c ?></option>
            <?php endforeach; ?>
          </select></div>
        </div>
        <div class="m-field">
          <label>REWARD TYPE</label>
          <div class="m-select-wrap"><select class="m-input" name="reward_type">
            <?php foreach(['GTD WL','FCFS WL','Raffle WL','Other'] as $rt): ?>
            <option <?= (($_POST['reward_type']??'GTD WL')===$rt)?'selected':'' ?>><?= $rt ?></option>
            <?php endforeach; ?>
          </select></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="m-field">
            <label>WINNERS</label>
            <input class="m-input" type="number" name="winners" min="1" value="<?= (int)($_POST['winners']??3) ?>" style="font-family:'GT America Mono',monospace">
          </div>
          <div class="m-field">
            <label>STARTING BID ($BLOX)</label>
            <input class="m-input" type="number" name="starting_bid" min="1" step="0.01" value="1" readonly style="font-family:'GT America Mono',monospace;opacity:0.5;cursor:not-allowed">
          </div>
          <div class="m-field">
            <label>DURATION</label>
            <div class="m-select-wrap"><select class="m-input" name="duration_h">
              <?php foreach([12=>12,24=>24,48=>48,72=>72] as $h=>$l): ?>
              <option value="<?= $h ?>" <?= (($_POST['duration_h']??24)==$h)?'selected':'' ?>><?= $l ?>h</option>
              <?php endforeach; ?>
            </select></div>
          </div>
          <div class="m-field">
            <label>SUPPLY</label>
            <input class="m-input" type="text" name="supply" value="<?= htmlspecialchars($_POST['supply']??'') ?>" placeholder="3,333" style="font-family:'GT America Mono',monospace">
          </div>
        </div>
        <div class="m-field">
          <label>MINT PRICE</label>
          <input class="m-input" type="text" name="mint_price" value="<?= htmlspecialchars($_POST['mint_price']??'') ?>" placeholder="0.05 ETH" style="font-family:'GT America Mono',monospace">
        </div>
      </div>
    </div>

    <button type="submit" class="m-foil-btn" style="border:none;cursor:pointer">
      <span class="m-foil-btn-inner">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6fe3ff" stroke-width="2"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
        Submit for Review
      </span>
    </button>
  </form>

</div>
<script>
var _mbt;
function doMBanner(){
  clearTimeout(_mbt);
  var h=document.getElementById('m_tw').value.trim().replace('@','');
  if(h.length<2){document.getElementById('m_prev').style.display='none';document.getElementById('m_img').value='';return;}
  _mbt=setTimeout(()=>{
    fetch('/auction-form.php?fetch_banner=1&handle='+encodeURIComponent(h)).then(r=>r.json()).then(d=>{
      if(d.banner){document.getElementById('m_img').value=d.banner;var p=document.getElementById('m_prev');p.src=d.banner;p.onload=()=>p.style.display='block';}
    }).catch(()=>{});
  },800);
}
</script>
</body>
</html>