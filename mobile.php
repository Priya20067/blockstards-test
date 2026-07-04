<?php
require_once __DIR__.'/config.php';
$user = get_user();

$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
// No redirect needed — this IS the mobile page

$blox_bal = 0;
if ($user) { try { $blox_bal = get_balance($user['discord_id']); } catch(Exception $e){} }
try { $active_raffles = count(sb('bs_raffles')->eq('status','active')->select('id')->get()); } catch(Exception $e){ $active_raffles=0; }
try { $active_auctions = count(sb('bs_auctions')->eq('status','active')->select('id')->get()); } catch(Exception $e){ $active_auctions=0; }
try { $live_raffles = sb('bs_raffles')->eq('status','active')->order('end_date', true)->limit(4)->get(); } catch(Exception $e){ $live_raffles=[]; }
try {
    $live_auctions = sb('bs_auctions')->eq('status','active')->order('ends_at', true)->limit(4)->get();
    foreach($live_auctions as &$a){ $a['bids']=json_decode($a['bids_json']??'{}',true)?:[]; } unset($a);
} catch(Exception $e){ $live_auctions=[]; }

$m_active = 'home';

function tl_str(?string $ts): string {
    if(!$ts) return '';
    $s=max(0,strtotime($ts)-time());
    if($s<=0) return 'Ended';
    if($s<3600) return round($s/60).'m';
    if($s<86400) return round($s/3600).'h';
    return round($s/86400).'d';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Blockstards</title>
<link rel="stylesheet" href="/bs_fonts.css?v=1783164697">
<link rel="stylesheet" href="/bs_mobile.css?v=1783164697">
</head>
<body>

<?php require_once __DIR__.'/includes/bs_mobile_nav.php'; ?>

<div class="m-body">

  <!-- Hero -->
  <div style="position:relative;border-radius:18px;overflow:hidden;border:1px solid #1a1d2b;background:#080a12;padding:26px 18px;margin-bottom:14px">
    <div style="position:absolute;width:240px;height:240px;right:-80px;top:-120px;border-radius:50%;background:radial-gradient(circle,rgba(111,227,255,.22),transparent 65%);animation:meshDrift 14s ease-in-out infinite;pointer-events:none"></div>
    <div style="position:relative;z-index:1">
      <div style="font-family:'GT America Mono',monospace;font-size:9px;letter-spacing:.2em;color:#6fe3ff;margin-bottom:10px;display:flex;align-items:center;gap:6px">
        MEMBERS ONLY
      </div>
      <h1 style="font-weight:700;font-size:28px;line-height:1.08;letter-spacing:-.02em;margin-bottom:10px">
        The Blockstards<br>
        <span style="background:linear-gradient(110deg,#6fe3ff,#b69cff 50%,#e4c590);-webkit-background-clip:text;background-clip:text;color:transparent">NFT Hub</span>
      </h1>
      <p style="font-size:13px;line-height:1.6;color:#aab2c5;margin-bottom:18px">Win whitelist spots, bid on exclusive auctions, earn $BLOX.</p>
      <?php if ($user): ?>
      <div style="display:flex;gap:9px">
        <a href="/mobile-raffles.php" class="m-foil-btn" style="flex:1;animation-duration:4.5s"><span class="m-foil-btn-inner" style="padding:11px 10px;font-size:12px">🎟 Raffles</span></a>
        <a href="/mobile-auctions.php" style="flex:1;display:flex;align-items:center;justify-content:center;padding:11px 10px;border-radius:11px;background:rgba(255,255,255,.04);border:1px solid #1e2230;font-family:'GT America Mono',monospace;font-size:12px;color:#aab2c5">⭐ Auctions</a>
      </div>
      <?php else: ?>
      <a href="/bs-auth/discord.php" class="m-discord-btn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057.101 18.08.114 18.102.132 18.115a19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03z"/></svg>
        Sign in with Discord
      </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Stats -->
  <div class="m-stats">
    <div class="m-stat">
      <div class="m-stat-val" style="color:#6fe3ff"><?= $active_raffles ?></div>
      <div class="m-stat-label">LIVE RAFFLES</div>
    </div>
    <div class="m-stat">
      <div class="m-stat-val" style="color:#b69cff"><?= $active_auctions ?></div>
      <div class="m-stat-label">LIVE AUCTIONS</div>
    </div>
  </div>

  <!-- Live Raffles -->
  <?php if (!empty($live_raffles)): ?>
  <div class="m-sec-head">
    <span class="m-sec-title">LIVE RAFFLES</span>
    <a href="/mobile-raffles.php" class="m-sec-link">View all →</a>
  </div>
  <?php
  $hues = ['linear-gradient(135deg,#13243a,#5aa9d8 60%,#1f3f6b)','linear-gradient(135deg,#2a123a,#b878d8 55%,#4f1f6b)','linear-gradient(135deg,#123a2a,#78d8a0 55%,#1f6b4a)','linear-gradient(135deg,#1a1a2e,#9c9cf0 55%,#3a3a6b)'];
  foreach($live_raffles as $i => $r):
    try{$ec=db()->prepare("SELECT COUNT(*) FROM bs_raffle_entries WHERE raffle_id=?");$ec->execute([$r['id']]);$entries=(int)$ec->fetchColumn();}catch(Exception $e){$entries=0;}
    $cost=$r['entry_type']==='blox'?$r['blox_cost'].' $BLOX':'Free';
    $hue=$hues[$i % count($hues)];
    $tl=tl_str($r['end_date']??null);
  ?>
  <a href="/mobile-raffles.php" class="m-irow">
    <div class="m-irow-thumb" style="min-height:72px">
      <?php if($r['image_url']??''): ?><img src="<?= htmlspecialchars($r['image_url']) ?>" alt=""><?php else: ?><div class="m-irow-thumb-placeholder" style="background:<?= $hue ?>"></div><?php endif; ?>
    </div>
    <div class="m-irow-body">
      <div class="m-irow-title"><?= htmlspecialchars($r['title']) ?></div>
      <div class="m-chips">
        <span class="m-badge m-badge-cyan"><?= $r['spots'] ?> spots</span>
        <span class="m-badge <?= $r['entry_type']==='blox'?'m-badge-gold':'m-badge-green' ?>"><?= $cost ?></span>
        <span class="m-badge m-badge-gray"><?= htmlspecialchars($r['chain']??'ETH') ?></span>
      </div>
      <div class="m-irow-foot">
        <span style="font-family:'GT America Mono',monospace;font-size:10px;color:#7a8398;display:flex;align-items:center;gap:4px"><span class="m-live-dot"></span><?= $entries ?> entries</span>
        <?php if($tl): ?><span style="font-family:'GT America Mono',monospace;font-size:10px;color:#6fe3ff"><?= $tl ?></span><?php endif; ?>
      </div>
    </div>
  </a>
  <?php endforeach; ?>
  <?php endif; ?>

  <!-- Live Auctions -->
  <?php if (!empty($live_auctions)): ?>
  <div class="m-sec-head" style="margin-top:6px">
    <span class="m-sec-title">LIVE AUCTIONS</span>
    <a href="/mobile-auctions.php" class="m-sec-link">View all →</a>
  </div>
  <?php foreach($live_auctions as $i => $a):
    $bids=$a['bids'];$top=$bids?max(array_values($bids)):0;
    $tl=tl_str($a['ends_at']??null);
    $hue=$hues[$i % count($hues)];
  ?>
  <a href="/mobile-auctions.php" class="m-irow">
    <div class="m-irow-thumb" style="min-height:72px">
      <?php if($a['image_url']??''): ?><img src="<?= htmlspecialchars($a['image_url']) ?>" alt=""><?php else: ?><div class="m-irow-thumb-placeholder" style="background:<?= $hue ?>"></div><?php endif; ?>
    </div>
    <div class="m-irow-body">
      <div class="m-irow-title"><?= htmlspecialchars($a['title']) ?></div>
      <div class="m-chips">
        <span class="m-badge m-badge-gold"><?= $a['winners']??1 ?> winners</span>
        <span class="m-badge m-badge-violet"><?= htmlspecialchars($a['reward_type']??'WL') ?></span>
      </div>
      <div class="m-irow-foot">
        <span style="font-family:'GT America Mono',monospace;font-size:10px;color:#7a8398"><?= count($bids) ?> bids<?= $top?' · '.number_format($top,2).' top':'' ?></span>
        <?php if($tl): ?><span style="font-family:'GT America Mono',monospace;font-size:10px;color:#e4c590"><?= $tl ?></span><?php endif; ?>
      </div>
    </div>
  </a>
  <?php endforeach; ?>
  <?php endif; ?>

  <?php if (empty($live_raffles) && empty($live_auctions)): ?>
  <div class="m-empty"><span class="m-empty-icon">🎯</span>No live events right now — check back soon!</div>
  <?php endif; ?>

  <!-- Features -->
  <div style="margin-top:6px;display:flex;flex-direction:column;gap:10px">
    <?php
    $feats = [
      ['🎟', 'Raffles', 'Win WL spots. Free or $BLOX entry.', '#6fe3ff', '111,227,255'],
      ['⭐', 'Auctions', 'Bid $BLOX on exclusive WLs. Losers fully refunded.', '#b69cff', '182,156,255'],
      ['📅', 'Mint Calendar', 'Track upcoming mints and your WL portfolio.', '#e4c590', '228,197,144'],
    ];
    foreach ($feats as $f): ?>
    <div style="display:flex;align-items:center;gap:13px;padding:14px;background:rgba(255,255,255,.02);border:1px solid #161a28;border-radius:14px">
      <div style="width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;background:rgba(<?= $f[4] ?>,.1);border:1px solid rgba(<?= $f[4] ?>,.22);flex-shrink:0"><?= $f[0] ?></div>
      <div>
        <div style="font-weight:600;font-size:14px;margin-bottom:3px"><?= $f[1] ?></div>
        <div style="font-size:12px;color:#7a8398"><?= $f[2] ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if (!$user): ?>
  <div style="margin-top:16px;padding:22px 18px;background:rgba(255,255,255,.02);border:1px solid #161a28;border-radius:16px;text-align:center">
    <div style="font-weight:700;font-size:18px;margin-bottom:7px">Ready to start?</div>
    <div style="font-size:12.5px;color:#7a8398;margin-bottom:16px">Sign in with Discord to enter raffles, bid on auctions, and earn $BLOX.</div>
    <a href="/bs-auth/discord.php" class="m-discord-btn">Sign in with Discord</a>
  </div>
  <?php endif; ?>

</div>
</body>
</html>