<?php
require_once __DIR__.'/config.php';
$user = get_user();
if (!$user) { header('Location: /bs-auth/discord.php?redirect=/mobile-profile.php'); exit; }
$uid = $user['discord_id'];
$blox_bal = 0;
try { $blox_bal = get_balance($uid); } catch(Exception $e){}
$wallets=[];
try { $wr=sb('bs_user_wallets')->eq('discord_id',$uid)->get(); foreach($wr as $w) $wallets[$w['chain']]=$w['address']; } catch(Exception $e){}
$win_count=0;
try { $win_count=count(sb('bs_wins')->eq('discord_id',$uid)->select('id')->get()); } catch(Exception $e){}
$twitter_handle='';
try { $bs=sb('bs_users')->eq('discord_id',$uid)->select('twitter_handle')->first(); $twitter_handle=$bs['twitter_handle']??''; } catch(Exception $e){}

// Save wallets
$msg='';$msg_type='';
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='save_wallets'){
    $eth=trim($_POST['eth_wallet']??'');$sol=trim($_POST['sol_wallet']??'');
    if($eth) sb('bs_user_wallets')->upsert(['discord_id'=>$uid,'chain'=>'Ethereum','address'=>$eth,'added_via'=>'website'],'discord_id,chain');
    if($sol) sb('bs_user_wallets')->upsert(['discord_id'=>$uid,'chain'=>'Solana','address'=>$sol,'added_via'=>'website'],'discord_id,chain');
    $msg='✓ Wallets saved!';$msg_type='green';
    $wallets=[];
    try{$wr=sb('bs_user_wallets')->eq('discord_id',$uid)->get();foreach($wr as $w)$wallets[$w['chain']]=$w['address'];}catch(Exception $e){}
}

$avatar_url = get_avatar_url($uid, $user['avatar']??'');
$m_active = 'profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Profile · Blockstards</title>
<link rel="stylesheet" href="/bs_fonts.css?v=1783108465">
<link rel="stylesheet" href="/bs_mobile.css?v=1783108465">
</head>
<body>
<?php require_once __DIR__.'/includes/bs_mobile_nav.php'; ?>
<div class="m-body">

  <?php if($msg): ?><div class="m-notice m-notice-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <!-- Avatar card -->
  <div style="position:relative;padding:28px 16px 20px;border:1px solid #161a28;border-radius:18px;background:rgba(255,255,255,.02);text-align:center;margin-bottom:14px;overflow:hidden">
    <div style="position:absolute;inset:0;background:radial-gradient(circle at 50% 0%,rgba(111,227,255,.14),transparent 60%);pointer-events:none"></div>
    <div style="position:relative;width:76px;height:76px;margin:0 auto 12px">
      <div style="position:absolute;inset:-3px;border-radius:50%;background:linear-gradient(140deg,#6fe3ff,#b69cff);filter:blur(2px)"></div>
      <?php if($avatar_url): ?>
        <img src="<?= htmlspecialchars($avatar_url) ?>" style="position:absolute;inset:0;width:100%;height:100%;border-radius:50%;object-fit:cover;border:2px solid #0a0d18">
      <?php else: ?>
        <div style="position:absolute;inset:0;border-radius:50%;background:linear-gradient(140deg,#1a2030,#0c0e18);border:2px solid #0a0d18;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:30px;color:#bfe9f5"><?= strtoupper(substr($user['username']??'U',0,1)) ?></div>
      <?php endif; ?>
    </div>
    <div style="position:relative;font-weight:700;font-size:18px;margin-bottom:4px"><?= htmlspecialchars($user['username']??'User') ?></div>
    <div style="position:relative;font-family:'GT America Mono',monospace;font-size:11px;color:#6fe3ff"><?= number_format($blox_bal,2) ?> $BLOX</div>
    <div style="position:relative;display:grid;grid-template-columns:1fr 1fr;gap:1px;background:#12151f;border:1px solid #12151f;border-radius:12px;margin-top:16px;overflow:hidden">
      <div style="background:#06070d;padding:12px;text-align:center">
        <div style="font-weight:700;font-size:20px;color:#6fe3ff"><?= number_format($blox_bal,0) ?></div>
        <div style="font-family:'GT America Mono',monospace;font-size:8.5px;color:#7a8398;margin-top:3px">$BLOX</div>
      </div>
      <div style="background:#06070d;padding:12px;text-align:center">
        <div style="font-weight:700;font-size:20px"><?= $win_count ?></div>
        <div style="font-family:'GT America Mono',monospace;font-size:8.5px;color:#7a8398;margin-top:3px">WINS</div>
      </div>
    </div>
  </div>

  <!-- Quick links -->
  <div class="m-panel" style="margin-bottom:14px">
    <a href="/mobile-wins.php" style="display:flex;align-items:center;gap:12px;padding:14px 16px;border-bottom:1px solid #12151f;text-decoration:none">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#6fe3ff" stroke-width="1.6"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2z"/></svg>
      <span style="flex:1;font-size:14px">My Wins</span>
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#5a6478" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
    </a>
    <a href="/mobile-calendar.php?tab=wl" style="display:flex;align-items:center;gap:12px;padding:14px 16px;border-bottom:1px solid #12151f;text-decoration:none">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#b69cff" stroke-width="1.6"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
      <span style="flex:1;font-size:14px">My Calendar</span>
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#5a6478" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
    </a>
    <?php if(is_staff()): ?>
    <a href="/mobile-admin.php" style="display:flex;align-items:center;gap:12px;padding:14px 16px;border-bottom:1px solid #12151f;text-decoration:none">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#b69cff" stroke-width="1.6"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
      <span style="flex:1;font-size:14px">Admin Panel <span style="font-family:'GT America Mono',monospace;font-size:8px;padding:2px 7px;border-radius:10px;background:rgba(182,156,255,.12);color:#c9b8ff;border:1px solid rgba(182,156,255,.25);vertical-align:middle;margin-left:4px">STAFF</span></span>
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#5a6478" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
    </a>
    <?php endif; ?>
    <a href="/bs-auth/logout.php" style="display:flex;align-items:center;gap:12px;padding:14px 16px;text-decoration:none;color:#f87171">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      <span style="flex:1;font-size:14px">Sign Out</span>
    </a>
  </div>

  <!-- Wallet form -->
  <div class="m-panel" style="margin-bottom:14px">
    <div class="m-panel-hdr">WALLET ADDRESSES</div>
    <div style="padding:16px">
      <p style="font-size:12px;color:#7a8398;margin-bottom:14px;line-height:1.6">Synced with the Discord bot — add once, works everywhere.</p>
      <form method="post">
        <input type="hidden" name="action" value="save_wallets">
        <div class="m-field">
          <label>ETHEREUM</label>
          <input class="m-input" type="text" name="eth_wallet" value="<?= htmlspecialchars($wallets['Ethereum']??'') ?>" placeholder="0x…" style="font-family:'GT America Mono',monospace;font-size:13px">
        </div>
        <div class="m-field">
          <label>SOLANA</label>
          <input class="m-input" type="text" name="sol_wallet" value="<?= htmlspecialchars($wallets['Solana']??'') ?>" placeholder="Solana address…" style="font-family:'GT America Mono',monospace;font-size:13px">
        </div>
        <button type="submit" class="m-foil-btn" style="border:none;cursor:pointer"><span class="m-foil-btn-inner">Save Wallets</span></button>
      </form>
    </div>
  </div>

  <!-- Connected accounts -->
  <div class="m-panel">
    <div class="m-panel-hdr">CONNECTED ACCOUNTS</div>
    <?php
    $conns=[
      ['Discord', htmlspecialchars($user['username']??''), true],
      ['X / Twitter', $twitter_handle?'@'.ltrim($twitter_handle,'@'):null, (bool)$twitter_handle],
      ['ETH Wallet', $wallets['Ethereum']??null, isset($wallets['Ethereum']), true],
      ['SOL Wallet', $wallets['Solana']??null, isset($wallets['Solana']), true],
    ];
    foreach($conns as $c):
      $val=$c[1]??'—';
      $mono=$c[3]??false;
      if($mono&&strlen($val)>20) $val=substr($val,0,9).'…'.substr($val,-6);
    ?>
    <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;border-bottom:1px solid #12151f">
      <span style="width:80px;flex-shrink:0;font-size:12.5px;color:#aab2c5"><?= $c[0] ?></span>
      <span style="flex:1;min-width:0;font-family:<?= $mono?'\'GT America Mono\',monospace':'\'GT America\',sans-serif' ?>;font-size:12px;color:<?= $c[2]?'#eef1f8':'#5a6478' ?>;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= $val ?></span>
      <span style="flex-shrink:0;font-family:'GT America Mono',monospace;font-size:8.5px;padding:3px 9px;border-radius:20px;<?= $c[2]?'background:rgba(74,222,128,.1);color:#4ade80;border:1px solid rgba(74,222,128,.3)':'background:rgba(255,255,255,.04);color:#7a8398;border:1px solid #232838' ?>"><?= $c[2]?($c[0]==='Discord'?'CONNECTED':'LINKED'):'NOT SET' ?></span>
    </div>
    <?php endforeach; ?>
  </div>

</div>
</body>
</html>