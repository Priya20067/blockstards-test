<?php /* mobile-wins.php */
require_once __DIR__.'/config.php';
$user = get_user();
if (!$user) { header('Location: /bs-auth/discord.php?redirect=/mobile-wins.php'); exit; }
$uid = $user['discord_id'];
$blox_bal = 0;
try { $blox_bal = get_balance($uid); } catch(Exception $e){}
try { $wins=sb('bs_wins')->eq('discord_id',$uid)->order('won_at',false)->get(); } catch(Exception $e){ $wins=[]; }
$auction_wins=array_values(array_filter($wins,fn($w)=>($w['win_type']??'')==='auction'));
$raffle_wins =array_values(array_filter($wins,fn($w)=>($w['win_type']??'')==='raffle'));
$m_active = 'profile';
$hues=['linear-gradient(135deg,#1e2230,#b6bccb 55%,#3a4050)','linear-gradient(135deg,#2a123a,#b878d8 55%,#4f1f6b)','linear-gradient(135deg,#123a2a,#78d8a0 55%,#1f6b4a)','linear-gradient(135deg,#1a1a2e,#9c9cf0 55%,#3a3a6b)'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>My Wins · Blockstards</title>
<link rel="stylesheet" href="/bs_fonts.css?v=1783108465">
<link rel="stylesheet" href="/bs_mobile.css?v=1783108465">
</head>
<body>
<?php require_once __DIR__.'/includes/bs_mobile_nav.php'; ?>
<div class="m-body">
  <h1 class="m-page-title">My Wins</h1>
  <p class="m-page-sub">Your WL wins — auto-synced to your portfolio.</p>

  <!-- Stats -->
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:18px">
    <div class="m-stat" style="padding:12px">
      <div class="m-stat-val" style="font-size:22px"><?= count($wins) ?></div>
      <div class="m-stat-label" style="font-size:8.5px">TOTAL</div>
    </div>
    <div class="m-stat" style="padding:12px">
      <div class="m-stat-val" style="font-size:22px;color:#6fe3ff"><?= count($auction_wins) ?></div>
      <div class="m-stat-label" style="font-size:8.5px">AUCTIONS</div>
    </div>
    <div class="m-stat" style="padding:12px">
      <div class="m-stat-val" style="font-size:22px;color:#b69cff"><?= count($raffle_wins) ?></div>
      <div class="m-stat-label" style="font-size:8.5px">RAFFLES</div>
    </div>
  </div>

  <?php if(empty($wins)): ?>
  <div class="m-empty">
    <span class="m-empty-icon">🏆</span>
    <p>No wins yet — enter raffles and auctions to get started!</p>
    <div style="display:flex;gap:10px;justify-content:center;margin-top:14px">
      <a href="/mobile-raffles.php" class="m-foil-btn" style="display:inline-flex;width:auto"><span class="m-foil-btn-inner" style="padding:10px 20px">Raffles</span></a>
      <a href="/mobile-auctions.php" style="display:inline-flex;align-items:center;padding:10px 20px;border:1px solid #232838;border-radius:11px;font-family:'GT America Mono',monospace;font-size:12.5px;color:#aab2c5">Auctions</a>
    </div>
  </div>
  <?php else: ?>

  <?php
  function render_m_win(array $w, int $i, array $hues): void {
    $is_auc=($w['win_type']??'')==='auction';
    $ac=$is_auc?'#6fe3ff':'#b69cff';
    $rgb=$is_auc?'111,227,255':'182,156,255';
    $hue=$hues[$i%count($hues)];
    $date=$w['won_at']?date('M j, Y',strtotime($w['won_at'])):'';
    $amt=(float)($w['amount_paid']??0);
  ?>
  <div class="m-irow" style="margin-bottom:10px">
    <div class="m-irow-thumb" style="width:72px;min-height:72px;position:relative">
      <?php if($w['image_url']??''): ?><img src="<?= htmlspecialchars($w['image_url']) ?>" alt=""><?php else: ?><div style="position:absolute;inset:0;background:<?= $hue ?>"></div><?php endif; ?>
      <div style="position:absolute;top:6px;left:6px;width:18px;height:18px;background:rgba(74,222,128,.2);border:1px solid rgba(74,222,128,.4);border-radius:50%;display:flex;align-items:center;justify-content:center">
        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#4ade80" stroke-width="3"><path d="M20 6 9 17l-5-5"/></svg>
      </div>
    </div>
    <div class="m-irow-body">
      <div class="m-irow-title"><?= htmlspecialchars($w['title']) ?></div>
      <div class="m-chips">
        <span class="m-badge" style="background:rgba(<?= $rgb ?>,.1);color:<?= $ac ?>;border:1px solid rgba(<?= $rgb ?>,.22)"><?= $is_auc?'⭐ AUCTION':'🎟 RAFFLE' ?></span>
        <?php if($w['chain']??''): ?><span class="m-badge m-badge-gray"><?= htmlspecialchars($w['chain']) ?></span><?php endif; ?>
        <?php if($amt>0): ?><span class="m-badge m-badge-gold"><?= number_format($amt,2) ?> $BLOX</span><?php endif; ?>
      </div>
      <div style="display:flex;align-items:center;justify-content:space-between">
        <span style="font-family:'GT America Mono',monospace;font-size:9.5px;color:#5a6478"><?= $date ?></span>
        <?php if($w['mint_url']??''): ?><a href="<?= htmlspecialchars($w['mint_url']) ?>" target="_blank" style="font-family:'GT America Mono',monospace;font-size:10px;color:<?= $ac ?>">Mint →</a><?php endif; ?>
      </div>
    </div>
  </div>
  <?php } ?>

  <?php if(!empty($auction_wins)): ?>
  <div class="m-sec-head"><span class="m-sec-title">AUCTION WINS</span></div>
  <?php foreach($auction_wins as $i=>$w) render_m_win($w,$i,$hues); ?>
  <?php endif; ?>
  <?php if(!empty($raffle_wins)): ?>
  <div class="m-sec-head" style="margin-top:6px"><span class="m-sec-title">RAFFLE WINS</span></div>
  <?php foreach($raffle_wins as $i=>$w) render_m_win($w,$i,$hues); ?>
  <?php endif; ?>

  <a href="/mobile-calendar.php?tab=wl" style="display:flex;align-items:center;gap:10px;padding:13px 14px;border:1px solid #161a28;border-radius:13px;background:rgba(255,255,255,.02);margin-top:10px">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#b69cff" stroke-width="1.6"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
    <span style="font-size:13px;color:#bfe9f5">View WL Portfolio →</span>
  </a>
  <?php endif; ?>
</div>
</body>
</html>