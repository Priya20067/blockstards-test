<?php
require_once __DIR__.'/config.php';
$user = get_user();
if (!$user) { header('Location: /bs-auth/discord.php'); exit; }
if (!is_staff()) { header('Location: /mobile.php'); exit; }
$uid = $user['discord_id'];
$blox_bal = 0;
try { $blox_bal = get_balance($uid); } catch(Exception $e){}
$m_active = 'admin';

$stats=['raffles'=>0,'auctions'=>0,'users'=>0,'pending_a'=>0,'pending_m'=>0];
try{$stats['raffles'] =count(sb('bs_raffles')->eq('status','active')->select('id')->get());}catch(Exception $e){}
try{$stats['auctions']=count(sb('bs_auctions')->eq('status','active')->select('id')->get());}catch(Exception $e){}
try{$stats['users']   =count(sb('bs_users')->select('discord_id')->get());}catch(Exception $e){}
try{$stats['pending_a']=count(sb('bs_auction_requests')->eq('status','pending')->select('id')->get());}catch(Exception $e){}
try{$stats['pending_m']=count(sb('bs_mint_submissions')->eq('status','pending')->select('id')->get());}catch(Exception $e){}

$raffles=[];
try{$rows=sb('bs_raffles')->order('id',false)->limit(8)->get();foreach($rows as $r){$r['entries']=count(sb('bs_raffle_entries')->eq('raffle_id',$r['id'])->select('discord_id')->get());$raffles[]=$r;}}catch(Exception $e){}
$auctions=[];
try{$auctions=sb('bs_auctions')->order('id',false)->limit(8)->get();foreach($auctions as &$a){$a['bids']=json_decode($a['bids_json']??'{}',true)?:[];}unset($a);}catch(Exception $e){}
$subs=[];
try{$sr=sb('bs_mint_submissions')->eq('status','pending')->limit(6)->get();foreach($sr as $s){$u=sb('bs_users')->eq('discord_id',$s['discord_id']??'')->select('username')->first();$s['username']=$u['username']??'?';$subs[]=$s;}}catch(Exception $e){}

$tp=$stats['pending_a']+$stats['pending_m'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Admin · Blockstards</title>
<link rel="stylesheet" href="/bs_fonts.css?v=1783108465">
<link rel="stylesheet" href="/bs_mobile.css?v=1783108465">
</head>
<body>
<?php require_once __DIR__.'/includes/bs_mobile_nav.php'; ?>
<div class="m-body">

  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px">
    <div>
      <div style="display:inline-flex;align-items:center;gap:5px;padding:2px 9px;border-radius:20px;background:rgba(182,156,255,.12);border:1px solid rgba(182,156,255,.3);font-family:'GT America Mono',monospace;font-size:8px;letter-spacing:.18em;color:#c9b8ff;margin-bottom:6px">ADMIN PANEL</div>
      <h1 class="m-page-title" style="font-size:24px;margin-bottom:0">Dashboard</h1>
    </div>
    <div class="m-topbar-avatar" style="width:38px;height:38px"><?php if($admin_avatar_url??''): ?><img src="<?= htmlspecialchars($admin_avatar_url??'') ?>" alt=""><?php else: ?><?= strtoupper(substr($user['username']??'A',0,1)) ?><?php endif; ?></div>
  </div>

  <!-- Stats -->
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:16px">
    <div class="m-stat" style="padding:12px"><div class="m-stat-val" style="font-size:22px;color:#6fe3ff"><?= $stats['raffles'] ?></div><div class="m-stat-label" style="font-size:8.5px">RAFFLES</div></div>
    <div class="m-stat" style="padding:12px"><div class="m-stat-val" style="font-size:22px;color:#b69cff"><?= $stats['auctions'] ?></div><div class="m-stat-label" style="font-size:8.5px">AUCTIONS</div></div>
    <div class="m-stat" style="padding:12px"><div class="m-stat-val" style="font-size:22px"><?= number_format($stats['users']) ?></div><div class="m-stat-label" style="font-size:8.5px">USERS</div></div>
  </div>

  <!-- Pending alert -->
  <?php if($tp>0): ?>
  <a href="#pending" style="display:flex;align-items:center;gap:10px;padding:11px 14px;border-radius:12px;background:rgba(251,146,60,.08);border:1px solid rgba(251,146,60,.25);color:#fb923c;text-decoration:none;margin-bottom:16px">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
    <span style="font-family:'GT America Mono',monospace;font-size:11px"><?= $tp ?> item<?= $tp>1?'s':'' ?> pending review ↓</span>
  </a>
  <?php endif; ?>

  <!-- Quick actions grid -->
  <div style="font-family:'GT America Mono',monospace;font-size:10px;letter-spacing:.14em;color:#7a8398;margin-bottom:10px">MANAGE</div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:18px">
    <?php
    $qlinks=[
      ['/mobile-admin-raffles.php','🎟','Raffles','Create & draw winners','111,227,255'],
      ['/mobile-admin-auctions.php','⭐','Auctions','Approve & end','182,156,255'],
      ['/mobile-admin-mints.php','📅','Mints','Review submissions','228,197,144'],
      ['/mobile-admin-staff.php','👥','Staff','Manage team','74,222,128'],
      ['/mobile-admin-permissions.php','🔒','Permissions','Grant access','74,222,128'],
      ['/mobile-admin-logs.php','📋','Logs','Activity log','111,227,255'],
    ];
    foreach($qlinks as $q): ?>
    <a href="<?= $q[0] ?>" style="display:flex;align-items:center;gap:11px;padding:14px;background:rgba(255,255,255,.02);border:1px solid rgba(<?= $q[4] ?>,.2);border-radius:13px;text-decoration:none">
      <div style="width:36px;height:36px;border-radius:10px;background:rgba(<?= $q[4] ?>,.1);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0"><?= $q[1] ?></div>
      <div><div style="font-weight:600;font-size:13px"><?= $q[2] ?></div><div style="font-size:10.5px;color:#7a8398;margin-top:1px"><?= $q[3] ?></div></div>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Live Raffles -->
  <?php if(!empty($raffles)): ?>
  <div class="m-sec-head">
    <span class="m-sec-title">LIVE RAFFLES</span>
    <a href="/mobile-admin-raffles.php" class="m-sec-link">All →</a>
  </div>
  <?php foreach($raffles as $r): ?>
  <div style="display:flex;align-items:center;gap:10px;padding:11px 13px;background:rgba(255,255,255,.02);border:1px solid #161a28;border-radius:12px;margin-bottom:8px">
    <div style="width:36px;height:36px;border-radius:8px;flex-shrink:0;overflow:hidden;background:#1a1d2b"><?php if($r['image_url']??''): ?><img src="<?= htmlspecialchars($r['image_url']) ?>" style="width:100%;height:100%;object-fit:cover"><?php else: ?>🎟<?php endif; ?></div>
    <div style="flex:1;min-width:0">
      <div style="font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($r['title']) ?></div>
      <div style="font-size:11px;color:#7a8398"><?= $r['entries'] ?> entries · <?= $r['spots'] ?> spots</div>
    </div>
    <span class="m-badge <?= $r['status']==='active'?'m-badge-green':($r['status']==='ended'?'m-badge-gray':'m-badge-gold') ?>"><?= $r['status'] ?></span>
    <?php if($r['status']==='active'): ?>
    <button onclick="endRaffle(<?= (int)$r['id'] ?>,this)" style="background:rgba(74,222,128,.1);border:1px solid rgba(74,222,128,.3);color:#4ade80;padding:5px 10px;border-radius:8px;font-family:'GT America Mono',monospace;font-size:10px;cursor:pointer;flex-shrink:0">End</button>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <!-- Live Auctions -->
  <?php if(!empty($auctions)): ?>
  <div class="m-sec-head" style="margin-top:6px">
    <span class="m-sec-title">LIVE AUCTIONS</span>
    <a href="/mobile-admin-auctions.php" class="m-sec-link">All →</a>
  </div>
  <?php foreach($auctions as $a):
    $bids=$a['bids']??[];$top=$bids?max(array_values($bids)):0;
  ?>
  <div style="display:flex;align-items:center;gap:10px;padding:11px 13px;background:rgba(255,255,255,.02);border:1px solid #161a28;border-radius:12px;margin-bottom:8px">
    <div style="width:36px;height:36px;border-radius:8px;flex-shrink:0;overflow:hidden;background:#1a1d2b"><?php if($a['image_url']??''): ?><img src="<?= htmlspecialchars($a['image_url']) ?>" style="width:100%;height:100%;object-fit:cover"><?php else: ?>⭐<?php endif; ?></div>
    <div style="flex:1;min-width:0">
      <div style="font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($a['title']) ?></div>
      <div style="font-size:11px;color:#7a8398"><?= count(array_filter($bids,fn($v)=>floatval($v)>0)) ?> bids<?= $top?' · '.number_format($top,2).' top':'' ?></div>
    </div>
    <?php if($a['status']==='active'): ?>
    <button onclick="endAuction(<?= (int)$a['id'] ?>,this)" style="background:rgba(228,197,144,.1);border:1px solid rgba(228,197,144,.3);color:#e4c590;padding:5px 10px;border-radius:8px;font-family:'GT America Mono',monospace;font-size:10px;cursor:pointer;flex-shrink:0">End</button>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <!-- Pending submissions -->
  <?php if(!empty($subs)): ?>
  <div class="m-sec-head" style="margin-top:6px" id="pending">
    <span class="m-sec-title" style="color:#fb923c">PENDING SUBMISSIONS</span>
    <span class="m-badge m-badge-gold"><?= count($subs) ?></span>
  </div>
  <?php foreach($subs as $s): ?>
  <div style="padding:12px 14px;background:rgba(251,146,60,.04);border:1px solid rgba(251,146,60,.18);border-radius:12px;margin-bottom:8px">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
      <div style="width:36px;height:36px;border-radius:8px;flex-shrink:0;overflow:hidden;background:#1a1d2b"><?php if($s['image_url']??''): ?><img src="<?= htmlspecialchars($s['image_url']) ?>" style="width:100%;height:100%;object-fit:cover"><?php else: ?>📅<?php endif; ?></div>
      <div style="flex:1;min-width:0">
        <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($s['name']) ?></div>
        <div style="font-size:11px;color:#7a8398"><?= htmlspecialchars($s['chain']??'ETH') ?> · by @<?= htmlspecialchars($s['username']) ?></div>
      </div>
    </div>
    <div style="display:flex;gap:8px">
      <form method="POST" action="/bs-admin/mints.php" style="flex:1"><input type="hidden" name="action" value="approve_sub"><input type="hidden" name="sub_id" value="<?= $s['id'] ?>"><button class="m-foil-btn" style="border:none;cursor:pointer;width:100%"><span class="m-foil-btn-inner" style="padding:10px">✓ Approve</span></button></form>
      <form method="POST" action="/bs-admin/mints.php" style="flex:1"><input type="hidden" name="action" value="reject_sub"><input type="hidden" name="sub_id" value="<?= $s['id'] ?>"><button style="width:100%;padding:10px;border-radius:11px;background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.3);color:#f87171;font-family:'GT America Mono',monospace;font-size:12px;cursor:pointer" onclick="return confirm('Reject?')">✕ Reject</button></form>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

</div>
<script>
async function endRaffle(id,btn){if(!confirm('End raffle and pick winners?'))return;btn.textContent='…';btn.disabled=true;try{var r=await fetch('/bs-api/end_raffle.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({raffle_id:id})});var d=await r.json();if(d.success){alert('Done!');location.reload();}else{alert(d.message||'Failed');btn.textContent='End';btn.disabled=false;}}catch(e){btn.textContent='End';btn.disabled=false;}}
async function endAuction(id,btn){if(!confirm('End auction and announce winners?'))return;btn.textContent='…';btn.disabled=true;try{var r=await fetch('/bs-api/end-auction.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({auction_id:id})});var d=await r.json();if(d.success){alert('Done!');location.reload();}else{alert(d.message||'Failed');btn.textContent='End';btn.disabled=false;}}catch(e){btn.textContent='End';btn.disabled=false;}}
</script>
<?php
// Set admin_avatar_url for sidebar
$admin_avatar_url = get_avatar_url($uid, $user['avatar']??'');
?>
</body>
</html>