<?php
require_once __DIR__.'/config.php';
$user = get_user();
$filter = $_GET['filter'] ?? 'active';
$status = $filter === 'ended' ? 'ended' : 'active';

$blox_bal = 0;
if ($user) { try { $blox_bal = get_balance($user['discord_id']); } catch(Exception $e){} }

try { $raffle_rows = sb('bs_raffles')->eq('status',$status)->order('id',false)->get(); } catch(Exception $e){ $raffle_rows=[]; }

// Entry counts + user entries
$raffles = [];
foreach ($raffle_rows as $r) {
    try{$ec=db()->prepare("SELECT COUNT(*) FROM bs_raffle_entries WHERE raffle_id=?");$ec->execute([$r['id']]);$r['entries']=(int)$ec->fetchColumn();}catch(Exception $e){$r['entries']=0;}
    $raffles[] = $r;
}

$entered = [];
if ($user) {
    try { $my = sb('bs_raffle_entries')->eq('discord_id',$user['discord_id'])->select('raffle_id')->get(); $entered=array_column($my,'raffle_id'); } catch(Exception $e){}
}

$m_active = 'raffles';

$hues=['linear-gradient(135deg,#13243a,#5aa9d8 60%,#1f3f6b)','linear-gradient(135deg,#2a123a,#b878d8 55%,#4f1f6b)','linear-gradient(135deg,#123a2a,#78d8a0 55%,#1f6b4a)','linear-gradient(135deg,#1a1a2e,#9c9cf0 55%,#3a3a6b)','linear-gradient(135deg,#3a2412,#e4c590 55%,#6b4a1f)'];

function tl_m(?string $ts): string {
    if(!$ts) return '';$s=max(0,strtotime($ts)-time());
    if($s<=0) return 'Ended';if($s<3600) return round($s/60).'m';if($s<86400) return round($s/3600).'h';return round($s/86400).'d';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Raffles · Blockstards</title>
<link rel="stylesheet" href="/bs_fonts.css?v=1783108465">
<link rel="stylesheet" href="/bs_mobile.css?v=1783108465">
</head>
<body>
<?php require_once __DIR__.'/includes/bs_mobile_nav.php'; ?>
<div class="m-body">

  <h1 class="m-page-title">Raffles</h1>
  <p class="m-page-sub">Win whitelist spots for upcoming NFT projects.</p>

  <div class="m-tabs">
    <a href="?filter=active" class="m-tab <?= $filter==='active'?'on':'' ?>">
      <?php if($filter==='active'): ?><span class="m-live-dot" style="margin-right:4px"></span><?php endif; ?>Active
    </a>
    <a href="?filter=ended" class="m-tab <?= $filter==='ended'?'on':'' ?>">Ended</a>
  </div>

  <?php if (empty($raffles)): ?>
  <div class="m-empty"><span class="m-empty-icon">🎟️</span>No <?= $filter ?> raffles right now.</div>
  <?php else: foreach($raffles as $i => $r):
    $hue=$hues[$i%count($hues)];
    $is_entered=in_array($r['id'],$entered);
    $cost=$r['entry_type']==='blox'?$r['blox_cost'].' $BLOX':'Free';
    $tl=tl_m($r['end_date']??null);
    $is_ended=$r['status']==='ended';
    $spots=(int)($r['spots']??0);
    $chance=$r['entries']>0?min(100,round($spots/$r['entries']*100)):100;
  ?>

  <div class="m-card" style="margin-bottom:12px" onclick="openRaffle(<?= (int)$r['id'] ?>)">
    <!-- Image -->
    <div style="position:relative;height:130px;overflow:hidden">
      <?php if($r['image_url']??''): ?>
        <img src="<?= htmlspecialchars($r['image_url']) ?>" style="width:100%;height:100%;object-fit:cover;display:block;<?= $is_ended?'filter:saturate(.6) brightness(.7)':'' ?>">
      <?php else: ?>
        <div style="position:absolute;inset:0;background:<?= $hue ?>;<?= $is_ended?'filter:saturate(.6) brightness(.7)':'' ?>"></div>
      <?php endif; ?>
      <div style="position:absolute;inset:0;background:radial-gradient(circle at 30% 20%,rgba(255,255,255,.22),transparent 55%);mix-blend-mode:screen;pointer-events:none"></div>
      <!-- Status pill -->
      <div style="position:absolute;top:10px;left:10px;display:flex;align-items:center;gap:5px;padding:4px 9px;border-radius:20px;background:rgba(6,7,13,.6);backdrop-filter:blur(6px);font-family:'GT America Mono',monospace;font-size:9px">
        <?php if(!$is_ended): ?><span class="m-live-dot"></span><span>LIVE</span><?php else: ?><span style="color:#7a8398">ENDED</span><?php endif; ?>
      </div>
      <?php if($is_entered && !$is_ended): ?>
      <div style="position:absolute;top:10px;right:10px;display:flex;align-items:center;gap:4px;padding:4px 9px;border-radius:20px;background:rgba(74,222,128,.14);border:1px solid rgba(74,222,128,.35);font-family:'GT America Mono',monospace;font-size:9px;color:#4ade80">
        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6 9 17l-5-5"/></svg>ENTERED
      </div>
      <?php endif; ?>
    </div>
    <!-- Body -->
    <div style="padding:14px">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:10px">
        <div style="font-weight:600;font-size:15px;flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($r['title']) ?></div>
        <span class="m-badge <?= $r['entry_type']==='blox'?'m-badge-gold':'m-badge-green' ?>" style="flex-shrink:0"><?= $cost ?></span>
      </div>
      <!-- Win chance bar -->
      <?php if(!$is_ended): ?>
      <div class="m-progress-wrap">
        <div class="m-progress-label"><span>WIN CHANCE</span><span style="color:#6fe3ff"><?= $chance ?>%</span></div>
        <div class="m-progress-track"><div class="m-progress-fill" style="width:<?= max(4,$chance) ?>%;background:linear-gradient(90deg,#6fe3ff,#6fe3ff88);box-shadow:0 0 6px #6fe3ff"></div></div>
      </div>
      <?php endif; ?>
      <!-- Stats row -->
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:12px">
        <div style="background:rgba(255,255,255,.03);border:1px solid #161a28;border-radius:10px;padding:9px;text-align:center">
          <div style="font-weight:700;font-size:16px"><?= $r['entries'] ?></div>
          <div style="font-family:'GT America Mono',monospace;font-size:8.5px;color:#5a6478;margin-top:2px">ENTRIES</div>
        </div>
        <div style="background:rgba(255,255,255,.03);border:1px solid #161a28;border-radius:10px;padding:9px;text-align:center">
          <div style="font-weight:700;font-size:16px"><?= $spots ?></div>
          <div style="font-family:'GT America Mono',monospace;font-size:8.5px;color:#5a6478;margin-top:2px">SPOTS</div>
        </div>
        <div style="background:rgba(255,255,255,.03);border:1px solid #161a28;border-radius:10px;padding:9px;text-align:center">
          <div style="font-family:'GT America Mono',monospace;font-size:13px;font-weight:700;color:#6fe3ff;line-height:1.3"><?= $tl?:($is_ended?'Done':'—') ?></div>
          <div style="font-family:'GT America Mono',monospace;font-size:8.5px;color:#5a6478;margin-top:2px">TIME</div>
        </div>
      </div>
      <!-- CTA -->
      <?php if($is_ended): ?>
      <div style="text-align:center;font-family:'GT America Mono',monospace;font-size:11px;color:#5a6478;padding:10px">Raffle ended</div>
      <?php elseif($is_entered): ?>
      <div style="display:flex;align-items:center;justify-content:center;gap:7px;padding:12px;border-radius:11px;background:rgba(74,222,128,.08);border:1px solid rgba(74,222,128,.25);color:#4ade80;font-family:'GT America Mono',monospace;font-size:12px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg>Entered — Good luck!
      </div>
      <?php elseif(!$user): ?>
      <a href="/bs-auth/discord.php" class="m-discord-btn" style="padding:11px">Sign in to Enter</a>
      <?php else: ?>
      <div class="m-foil-btn" onclick="event.stopPropagation();enterRaffle(<?= (int)$r['id'] ?>,this)">
        <span class="m-foil-btn-inner">Enter Raffle · <?= $cost ?></span>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <?php endforeach; endif; ?>
</div>

<script>
async function enterRaffle(id, btn) {
  btn.style.pointerEvents='none';
  btn.querySelector('.m-foil-btn-inner').textContent='Entering…';
  try {
    const res=await fetch('/bs-api/enter_raffle.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({raffle_id:id})});
    const d=await res.json();
    if(d.success){btn.parentNode.innerHTML='<div style="display:flex;align-items:center;justify-content:center;gap:7px;padding:12px;border-radius:11px;background:rgba(74,222,128,.08);border:1px solid rgba(74,222,128,.25);color:#4ade80;font-family:\'GT America Mono\',monospace;font-size:12px">✓ Entered — Good luck!</div>';}
    else{alert(d.message||'Failed');btn.style.pointerEvents='';btn.querySelector('.m-foil-btn-inner').textContent='Enter Raffle';}
  } catch(e){btn.style.pointerEvents='';btn.querySelector('.m-foil-btn-inner').textContent='Enter Raffle';}
}
function openRaffle(id){/* tap on card just goes to raffles page on mobile */}
</script>
</body>
</html>