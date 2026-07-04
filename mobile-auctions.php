<?php
require_once __DIR__.'/config.php';
$user = get_user();
$tab = $_GET['tab'] ?? 'live';

$blox_bal = 0;
if ($user) { try { $blox_bal = get_balance($user['discord_id']); } catch(Exception $e){} }

$auctions = [];
try {
    $status = $tab === 'ended' ? 'ended' : 'active';
    $rows = sb('bs_auctions')->eq('status',$status)->order('ends_at',true)->get();
    foreach($rows as &$a){
        $a['bids']=json_decode($a['bids_json']??'{}',true)?:[];
        $a['usernames']=json_decode($a['usernames_json']??'{}',true)?:[];
    } unset($a);
    $auctions=$rows;
} catch(Exception $e){}

$my_bids = [];
if ($user && $tab==='mybids') {
    try {
        $all=sb('bs_auctions')->eq('status','active')->get();
        foreach($all as $a){
            $bids=json_decode($a['bids_json']??'{}',true)?:[];
            if(isset($bids[$user['discord_id']])&&(float)$bids[$user['discord_id']]>0){
                $a['bids']=$bids;$a['my_bid']=(float)$bids[$user['discord_id']];
                $a['usernames']=json_decode($a['usernames_json']??'{}',true)?:[];
                $my_bids[]=$a;
            }
        }
        $auctions=$my_bids;
    } catch(Exception $e){}
}

$m_active = 'auctions';
$hues=['linear-gradient(135deg,#1e2230,#b6bccb 55%,#3a4050)','linear-gradient(135deg,#1a1a2e,#9c9cf0 55%,#3a3a6b)','linear-gradient(135deg,#13243a,#5aa9d8 60%,#1f3f6b)','linear-gradient(135deg,#2a123a,#b878d8 55%,#4f1f6b)','linear-gradient(135deg,#3a2412,#e4c590 55%,#6b4a1f)'];

function tla(?string $ts): string{if(!$ts)return '';$s=max(0,strtotime($ts)-time());if($s<=0)return 'Ended';$h=floor($s/3600);$m=floor(($s%3600)/60);if($h>=24)return round($h/24).'d';if($h>0)return $h.'h '.$m.'m';return $m.'m';}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Auctions · Blockstards</title>
<link rel="stylesheet" href="/bs_fonts.css?v=1783164697">
<link rel="stylesheet" href="/bs_mobile.css?v=1783164697">
</head>
<body>
<?php require_once __DIR__.'/includes/bs_mobile_nav.php'; ?>
<div class="m-body">

  <h1 class="m-page-title">Auctions</h1>
  <p class="m-page-sub">Bid $BLOX on WL spots. Losers fully refunded.</p>

  <div class="m-tabs">
    <a href="?tab=live" class="m-tab <?= $tab==='live'?'on':'' ?>">
      <span class="m-live-dot" style="margin-right:4px"></span>Live
    </a>
    <a href="?tab=ended" class="m-tab <?= $tab==='ended'?'on':'' ?>">Ended</a>
    <?php if($user): ?><a href="?tab=mybids" class="m-tab <?= $tab==='mybids'?'on':'' ?>">My Bids</a><?php endif; ?>
  </div>

  <?php if(empty($auctions)): ?>
  <div class="m-empty"><span class="m-empty-icon">🔨</span>No <?= $tab==='mybids'?'active bids':$tab ?> auctions.</div>
  <?php else: foreach($auctions as $i => $a):
    $bids=$a['bids']??[];
    $usernames=$a['usernames']??[];
    arsort($bids);
    $top=$bids?reset($bids):0;
    $top_uid=$bids?array_key_first($bids):'';
    $my_bid=($user&&isset($bids[$user['discord_id']]))?(float)$bids[$user['discord_id']]:0;
    $winning=$user&&$my_bid>0&&$top_uid===$user['discord_id'];
    $tl=tla($a['ends_at']??null);
    $urgent=$a['ends_at']&&(strtotime($a['ends_at'])-time())<3600;
    $is_ended=$a['status']==='ended';
    $hue=$hues[$i%count($hues)];
    $wc=(int)($a['winners']??1);
  ?>
  <div class="m-card" style="margin-bottom:12px">
    <!-- Image -->
    <div style="position:relative;height:140px;overflow:hidden">
      <?php if($a['image_url']??''): ?>
        <img src="<?= htmlspecialchars($a['image_url']) ?>" style="width:100%;height:100%;object-fit:cover;display:block;<?= $is_ended?'filter:saturate(.6) brightness(.7)':'' ?>">
      <?php else: ?>
        <div style="position:absolute;inset:0;background:<?= $hue ?>;<?= $is_ended?'filter:saturate(.6) brightness(.7)':'' ?>"></div>
      <?php endif; ?>
      <div style="position:absolute;inset:0;background:linear-gradient(to bottom,transparent 50%,rgba(8,10,18,.9))"></div>
      <!-- Reward badge -->
      <div style="position:absolute;top:10px;left:10px;display:flex;align-items:center;gap:5px;padding:4px 9px;border-radius:20px;background:rgba(6,7,13,.6);backdrop-filter:blur(6px);font-family:'GT America Mono',monospace;font-size:9px;color:#aab2c5">
        <?= htmlspecialchars($a['reward_type']??'WL') ?>
      </div>
      <!-- Timer -->
      <?php if(!$is_ended): ?>
      <div style="position:absolute;top:10px;right:10px;display:flex;align-items:center;gap:5px;padding:4px 9px;border-radius:20px;background:<?= $urgent?'rgba(248,113,113,.18)':'rgba(6,7,13,.6)' ?>;backdrop-filter:blur(6px);border:1px solid <?= $urgent?'rgba(248,113,113,.4)':'transparent' ?>;font-family:'GT America Mono',monospace;font-size:10px;color:<?= $urgent?'#fca5a5':'#dfe6f2' ?>">
        <span style="width:5px;height:5px;border-radius:50%;background:<?= $urgent?'#f87171':'#6fe3ff' ?>;animation:livePulse 1.6s infinite"></span>
        <span class="auction-timer" data-ends="<?= htmlspecialchars($a['ends_at']??'') ?>"><?= $tl ?></span>
      </div>
      <?php else: ?>
      <div style="position:absolute;top:10px;right:10px;padding:4px 9px;border-radius:20px;background:rgba(6,7,13,.6);font-family:'GT America Mono',monospace;font-size:9px;color:#7a8398">ENDED</div>
      <?php endif; ?>
      <!-- Title at bottom -->
      <div style="position:absolute;bottom:12px;left:14px;right:14px">
        <div style="font-weight:600;font-size:16px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($a['title']) ?></div>
      </div>
    </div>
    <!-- Body -->
    <div style="padding:14px">
      <!-- Mini stats -->
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:12px">
        <div style="background:rgba(255,255,255,.03);border:1px solid #161a28;border-radius:10px;padding:9px;text-align:center">
          <div style="font-weight:700;font-size:15px;color:#e4c590"><?= $top?number_format($top,0):'—' ?></div>
          <div style="font-family:'GT America Mono',monospace;font-size:8px;color:#5a6478;margin-top:2px">TOP BID</div>
        </div>
        <div style="background:rgba(255,255,255,.03);border:1px solid #161a28;border-radius:10px;padding:9px;text-align:center">
          <div style="font-weight:700;font-size:15px"><?= count(array_filter($bids,fn($v)=>floatval($v)>0)) ?></div>
          <div style="font-family:'GT America Mono',monospace;font-size:8px;color:#5a6478;margin-top:2px">BIDDERS</div>
        </div>
        <div style="background:rgba(255,255,255,.03);border:1px solid #161a28;border-radius:10px;padding:9px;text-align:center">
          <div style="font-weight:700;font-size:15px"><?= $wc ?></div>
          <div style="font-family:'GT America Mono',monospace;font-size:8px;color:#5a6478;margin-top:2px">WINNERS</div>
        </div>
      </div>

      <!-- Top bids -->
      <?php $rank=0; foreach($bids as $uid=>$amt): if($rank>=3) break; $rank++;
        $uname=$usernames[$uid]??('…'.substr($uid,-4));
        $is_me=$user&&$uid===$user['discord_id'];
      ?>
      <div style="display:flex;align-items:center;gap:9px;padding:7px 10px;border-radius:9px;background:<?= $is_me?'rgba(111,227,255,.08)':'rgba(255,255,255,.02)' ?>;border:1px solid <?= $is_me?'rgba(111,227,255,.22)':'#12151f' ?>;margin-bottom:5px">
        <span style="font-family:'GT America Mono',monospace;font-size:10px;color:<?= $rank<=1?'#e4c590':'#5a6478' ?>;width:16px">#<?= $rank ?></span>
        <span style="flex:1;font-size:12.5px;color:<?= $is_me?'#eef1f8':'#aab2c5' ?>;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($uname) ?></span>
        <?php if($is_me): ?><span style="font-family:'GT America Mono',monospace;font-size:8px;color:#6fe3ff;padding:2px 6px;border:1px solid rgba(111,227,255,.3);border-radius:8px">YOU</span><?php endif; ?>
        <span style="font-family:'GT America Mono',monospace;font-size:11.5px;color:#dfe6f2"><?= number_format((float)$amt,2) ?></span>
      </div>
      <?php endforeach; ?>
      <?php if(empty(array_filter($bids,fn($v)=>floatval($v)>0))): ?>
      <div style="text-align:center;padding:12px;font-family:'GT America Mono',monospace;font-size:11px;color:#5a6478">No bids yet — be first!</div>
      <?php endif; ?>

      <!-- My bid status -->
      <?php if($my_bid > 0): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;border-radius:9px;background:rgba(74,222,128,.06);border:1px solid rgba(74,222,128,.2);margin:10px 0">
        <span style="font-family:'GT America Mono',monospace;font-size:10.5px;color:#86efac">Your bid · <?= number_format($my_bid,2) ?></span>
        <span style="font-family:'GT America Mono',monospace;font-size:10px;color:<?= $winning?'#4ade80':'#f87171' ?>"><?= $winning?'✓ WINNING':'✗ OUTBID' ?></span>
      </div>
      <?php endif; ?>

      <!-- Bid button -->
      <?php if($is_ended): ?>
      <div style="text-align:center;font-family:'GT America Mono',monospace;font-size:11px;color:#5a6478;padding:10px">Auction ended</div>
      <?php elseif(!$user): ?>
      <a href="/bs-auth/discord.php" class="m-discord-btn" style="padding:11px;margin-top:10px">Sign in to Bid</a>
      <?php else: ?>
      <div class="m-foil-btn" style="margin-top:10px" onclick="openBid(<?= (int)$a['id'] ?>,<?= htmlspecialchars(json_encode($a['title'])) ?>,<?= $my_bid ?>,<?= $top ?>)">
        <span class="m-foil-btn-inner">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6fe3ff" stroke-width="2"><?= $my_bid>0?'<path d="M12 19V5M5 12l7-7 7 7"/>':'<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>' ?></svg>
          <?= $my_bid>0?'Increase Bid':'Place Bid' ?>
        </span>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; endif; ?>
</div>

<!-- Bid Modal -->
<div class="m-modal-overlay" id="bid-modal">
  <div class="m-modal">
    <div class="m-modal-hdr">
      <div class="m-modal-title" id="bid-modal-title">Place Bid</div>
      <div class="m-modal-close" onclick="document.getElementById('bid-modal').classList.remove('open')">×</div>
    </div>
    <div class="m-modal-body">
      <div style="display:flex;justify-content:space-between;padding:12px 14px;border-radius:11px;background:rgba(255,255,255,.03);border:1px solid #161a28;margin-bottom:16px">
        <div><div style="font-family:'GT America Mono',monospace;font-size:9px;color:#5a6478;margin-bottom:3px">TOP BID</div><div id="bid-top" style="font-family:'GT America Mono',monospace;font-size:13px">—</div></div>
        <div style="text-align:right"><div style="font-family:'GT America Mono',monospace;font-size:9px;color:#5a6478;margin-bottom:3px">YOUR BALANCE</div><div style="font-family:'GT America Mono',monospace;font-size:13px;color:#6fe3ff"><?= number_format($blox_bal,2) ?> $BLOX</div></div>
      </div>
      <div class="m-field">
        <label>YOUR BID ($BLOX)</label>
        <div style="display:flex;align-items:center;background:#0a0d18;border:1px solid #232838;border-radius:11px;padding:0 14px">
          <input id="bid-input" type="number" min="1" step="1" placeholder="0" style="flex:1;background:transparent;border:none;outline:none;color:#eef1f8;font-family:'GT America Mono',monospace;font-size:18px;padding:14px 0">
          <span style="font-family:'GT America Mono',monospace;font-size:11px;color:#7a8398">$BLOX</span>
        </div>
      </div>
      <div style="display:flex;gap:10px">
        <div onclick="document.getElementById('bid-modal').classList.remove('open')" style="flex:0 0 auto;padding:13px 20px;border-radius:11px;border:1px solid #232838;background:rgba(255,255,255,.03);color:#aab2c5;font-family:'GT America Mono',monospace;font-size:12px;cursor:pointer">Cancel</div>
        <div class="m-foil-btn" id="bid-confirm" onclick="submitBid()" style="flex:1"><span class="m-foil-btn-inner">Confirm Bid</span></div>
      </div>
    </div>
  </div>
</div>

<script>
var _bid_id=null,_bid_cur=0;
document.getElementById('bid-modal').addEventListener('click',function(e){if(e.target===this)this.classList.remove('open');});
function openBid(id,title,myBid,topBid){
  _bid_id=id;_bid_cur=myBid;
  document.getElementById('bid-modal-title').textContent=(myBid>0?'Increase Bid: ':'Bid: ')+title;
  document.getElementById('bid-top').textContent=topBid?parseFloat(topBid).toFixed(2)+' $BLOX':'No bids yet';
  document.getElementById('bid-input').value='';
  document.getElementById('bid-input').placeholder=(myBid>0?(parseFloat(myBid)+0.01).toFixed(2):'1.00');
  document.getElementById('bid-modal').classList.add('open');
}
async function submitBid(){
  const val=parseFloat(document.getElementById('bid-input').value);
  if(!val||val<=_bid_cur){alert('Bid must be higher than your current bid.');return;}
  const btn=document.getElementById('bid-confirm');
  btn.querySelector('.m-foil-btn-inner').textContent='Placing…';btn.style.pointerEvents='none';
  try{
    const res=await fetch('/bs-api/auction_bid.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({auction_id:_bid_id,amount:val})});
    const d=await res.json();
    if(d.success){document.getElementById('bid-modal').classList.remove('open');setTimeout(()=>location.reload(),500);}
    else{alert(d.message||'Failed');btn.querySelector('.m-foil-btn-inner').textContent='Confirm Bid';btn.style.pointerEvents='';}
  }catch(e){btn.querySelector('.m-foil-btn-inner').textContent='Confirm Bid';btn.style.pointerEvents='';}
}
// Live timers
function updateTimers(){document.querySelectorAll('.auction-timer[data-ends]').forEach(el=>{const e=new Date(el.dataset.ends).getTime(),ms=e-Date.now();if(ms<=0){el.textContent='Ended';return;}const h=Math.floor(ms/3600000),m=Math.floor((ms%3600000)/60000),s=Math.floor((ms%60000)/1000);el.textContent=h>=24?Math.floor(h/24)+'d '+(h%24)+'h':h>0?h+'h '+m+'m':m+'m '+String(s).padStart(2,'0')+'s';if(ms<3600000)el.style.color='#fca5a5';});}
updateTimers();setInterval(updateTimers,1000);
</script>
</body>
</html>