<?php
require_once __DIR__.'/../config.php';

$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (preg_match('/Mobile|Android|iPhone|iPad|webOS|BlackBerry/i', $ua)) {
    header('Location: /mobile-wins.php'); exit;
}

$user = get_user();
if (!$user) { header('Location: /bs-auth/discord.php?redirect=/wins/'); exit; }

$active_page = 'wins';
$uid         = $user['discord_id'];

// ── Handle delete ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_win_id'])) {
    $del_id = (int)$_POST['delete_win_id'];
    try {
        sb('bs_wins')->eq('id', $del_id)->eq('discord_id', $uid)->delete();
    } catch(Exception $e) {}
    header('Location: /wins/'); exit;
}

// ── Fetch wins ──────────────────────────────────────────────────────────────
try {
    $wins = sb('bs_wins')->eq('discord_id', $uid)->order('won_at', false)->get();
} catch(Exception $e) { $wins = []; }

$auction_wins = array_values(array_filter($wins, fn($w) => ($w['win_type'] ?? '') === 'auction'));
$raffle_wins  = array_values(array_filter($wins, fn($w) => ($w['win_type'] ?? '') === 'raffle'));

// ── User meta ───────────────────────────────────────────────────────────────
$user_balance    = get_balance($uid);
$user_name       = htmlspecialchars($user['username'] ?? 'User');
$user_initial    = strtoupper(substr($user['username'] ?? 'U', 0, 1));
$user_avatar_url = get_avatar_url($uid, $user['avatar'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Wins · Blockstards</title>
<link rel="stylesheet" href="/bs_fonts.css?v=1783164697">
<link rel="stylesheet" href="/bs_design.css?v=1783164697">
<style>
  /* ── Win row card ── */
  .win-row{display:flex;align-items:center;gap:14px;padding:12px 16px;border-radius:13px;border:1px solid #161a28;background:rgba(255,255,255,.02);cursor:pointer;transition:.18s;position:relative;overflow:hidden}
  .win-row::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;border-radius:3px 0 0 3px}
  .win-row.auction-row::before{background:linear-gradient(#6fe3ff,#4fa8cc);box-shadow:0 0 10px #6fe3ff66}
  .win-row.raffle-row::before{background:linear-gradient(#b69cff,#8a6ccc);box-shadow:0 0 10px #b69cff66}
  .win-row:hover{border-color:#2a3048;background:rgba(255,255,255,.04);transform:translateX(3px)}
  .win-thumb{width:48px;height:48px;border-radius:9px;object-fit:cover;flex-shrink:0;background:#0d1020}
  .win-thumb-placeholder{width:48px;height:48px;border-radius:9px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:18px}
  .win-info{flex:1;min-width:0}
  .win-title{font-weight:600;font-size:13.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:4px}
  .win-meta{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
  .win-badge{font-family:'GT America Mono',monospace;font-size:9.5px;padding:2px 7px;border-radius:6px;letter-spacing:.04em}
  .win-date{font-family:'GT America Mono',monospace;font-size:10px;color:#5a6478;white-space:nowrap;flex-shrink:0}
  .win-arrow{color:#3a4258;transition:.18s;flex-shrink:0}
  .win-row:hover .win-arrow{color:#6fe3ff}

  /* ── Section head ── */
  .ws-head{display:flex;align-items:center;gap:10px;margin:28px 0 12px}
  .ws-head-line{flex:1;height:1px;background:linear-gradient(90deg,#1e2338,transparent)}

  /* ── Modal ── */
  .wm-overlay{position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(6px);z-index:1000;display:none;align-items:center;justify-content:center;padding:24px}
  .wm-overlay.open{display:flex}
  .wm-box{background:linear-gradient(160deg,#0d1020,#0a0c18);border:1px solid #222840;border-radius:20px;width:100%;max-width:500px;overflow:hidden;animation:wmIn .2s ease}
  @keyframes wmIn{from{transform:scale(.95);opacity:0}to{transform:scale(1);opacity:1}}
  .wm-banner{position:relative;height:160px;overflow:hidden}
  .wm-banner img{width:100%;height:100%;object-fit:cover}
  .wm-banner-bg{position:absolute;inset:0}
  .wm-banner-shine{position:absolute;inset:0;background:radial-gradient(circle at 30% 20%,rgba(255,255,255,.18),transparent 55%);mix-blend-mode:screen}
  .wm-banner-overlay{position:absolute;inset:0;background:linear-gradient(to top,rgba(10,12,24,.9) 0%,transparent 50%)}
  .wm-body{padding:22px}
  .wm-title{font-size:18px;font-weight:700;margin-bottom:14px}
  .wm-row{display:flex;justify-content:space-between;align-items:center;padding:9px 0;border-bottom:1px solid #12151f;font-size:13px}
  .wm-row:last-child{border-bottom:none}
  .wm-label{color:#5a6478;font-family:'GT America Mono',monospace;font-size:10.5px;letter-spacing:.06em}
  .wm-val{color:#cdd4e2;font-weight:500}
  .wm-footer{padding:16px 22px;border-top:1px solid #12151f;display:flex;gap:10px;align-items:center}
  .wm-close-btn{position:absolute;top:12px;right:12px;width:30px;height:30px;border-radius:50%;background:rgba(0,0,0,.5);border:1px solid rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;cursor:pointer;color:#aab2c5;transition:.16s}
  .wm-close-btn:hover{background:rgba(255,255,255,.1);color:#fff}

  /* ── Delete btn ── */
  .btn-delete{display:flex;align-items:center;gap:6px;padding:9px 16px;border-radius:10px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);color:#f87171;font-family:'GT America Mono',monospace;font-size:11px;cursor:pointer;transition:.16s;letter-spacing:.04em}
  .btn-delete:hover{background:rgba(239,68,68,.16);border-color:rgba(239,68,68,.4)}
</style>
</head>
<body>
<div class="bs-layout">
<?php require_once __DIR__.'/../includes/bs_sidebar.php'; ?>
<main class="bs-main">

  <div class="bs-topbar">
    <div class="bs-breadcrumb">CLUB / <span class="bc-active">MY WINS</span></div>
  </div>

  <!-- Page header -->
  <div class="bs-page-header">
    <div>
      <h1 class="bs-page-title">My Wins</h1>
      <p class="bs-page-sub">Your personal WL portfolio — every auction and raffle win in one place.</p>
    </div>
    <div class="bs-page-stats">
      <div class="bs-page-stat"><div class="bs-page-stat-val"><?= count($wins) ?></div><div class="bs-page-stat-label">TOTAL WINS</div></div>
      <div class="bs-divider"></div>
      <div class="bs-page-stat"><div class="bs-page-stat-val" style="color:#6fe3ff"><?= count($auction_wins) ?></div><div class="bs-page-stat-label">AUCTIONS</div></div>
      <div class="bs-divider"></div>
      <div class="bs-page-stat"><div class="bs-page-stat-val" style="color:#b69cff"><?= count($raffle_wins) ?></div><div class="bs-page-stat-label">RAFFLES</div></div>
    </div>
  </div>

  <?php if (empty($wins)): ?>
  <div class="bs-empty">
    <span class="bs-empty-icon">🏆</span>
    <p>No wins yet — enter auctions and raffles to get started!</p>
    <div style="display:flex;gap:12px;justify-content:center">
      <a href="/auctions/" class="bs-foil-btn" style="display:inline-flex;width:auto;padding:2px;border-radius:12px;text-decoration:none"><span class="bs-foil-btn-inner" style="padding:10px 22px">View Auctions</span></a>
      <a href="/raffles/" style="display:inline-flex;align-items:center;padding:10px 22px;border:1px solid #232838;border-radius:12px;background:transparent;color:#aab2c5;text-decoration:none;font-family:'GT America Mono',monospace;font-size:12.5px">View Raffles</a>
    </div>
  </div>
  <?php else: ?>

  <!-- Auction wins -->
  <?php if (!empty($auction_wins)): ?>
  <div class="ws-head">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6fe3ff" stroke-width="1.7" style="filter:drop-shadow(0 0 5px #6fe3ff88)"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
    <span style="font-family:'GT America Mono',monospace;font-size:10px;letter-spacing:.2em;color:#6fe3ff">AUCTION WINS</span>
    <div class="ws-head-line"></div>
    <span style="font-family:'GT America Mono',monospace;font-size:10px;color:#4a5266"><?= count($auction_wins) ?></span>
  </div>
  <div style="display:flex;flex-direction:column;gap:7px;margin-bottom:28px">
    <?php foreach ($auction_wins as $w): ?>
    <?php
      $date_str = $w['won_at'] ? date('M j, Y', strtotime($w['won_at'])) : '';
      $amount   = (float)($w['amount_paid'] ?? 0);
    ?>
    <div class="win-row auction-row" onclick="openWin(<?= htmlspecialchars(json_encode($w), ENT_QUOTES) ?>)">
      <?php if (!empty($w['image_url'])): ?>
        <img class="win-thumb" src="<?= htmlspecialchars($w['image_url']) ?>" alt="">
      <?php else: ?>
        <div class="win-thumb-placeholder" style="background:linear-gradient(135deg,#1a2240,#2a3860)">⭐</div>
      <?php endif; ?>
      <div class="win-info">
        <div class="win-title"><?= htmlspecialchars($w['title']) ?></div>
        <div class="win-meta">
          <?php if ($w['reward_type'] ?? ''): ?><span class="win-badge" style="background:rgba(111,227,255,.1);color:#6fe3ff;border:1px solid rgba(111,227,255,.2)"><?= htmlspecialchars($w['reward_type']) ?></span><?php endif; ?>
          <?php if ($w['chain'] ?? ''): ?><span class="win-badge" style="background:rgba(255,255,255,.05);color:#8892a4;border:1px solid #1e2338"><?= htmlspecialchars($w['chain']) ?></span><?php endif; ?>
          <?php if ($amount > 0): ?><span class="win-badge" style="background:rgba(228,197,144,.1);color:#e4c590;border:1px solid rgba(228,197,144,.2)"><?= number_format($amount,2) ?> $BLOX</span><?php endif; ?>
        </div>
      </div>
      <span class="win-date"><?= $date_str ?></span>
      <svg class="win-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Raffle wins -->
  <?php if (!empty($raffle_wins)): ?>
  <div class="ws-head">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#b69cff" stroke-width="1.7" style="filter:drop-shadow(0 0 5px #b69cff88)"><path d="M20 12V22H4V12"/><path d="M22 7H2v5h20V7z"/><path d="M12 22V7"/></svg>
    <span style="font-family:'GT America Mono',monospace;font-size:10px;letter-spacing:.2em;color:#b69cff">RAFFLE WINS</span>
    <div class="ws-head-line"></div>
    <span style="font-family:'GT America Mono',monospace;font-size:10px;color:#4a5266"><?= count($raffle_wins) ?></span>
  </div>
  <div style="display:flex;flex-direction:column;gap:7px;margin-bottom:28px">
    <?php foreach ($raffle_wins as $w): ?>
    <?php
      $date_str = $w['won_at'] ? date('M j, Y', strtotime($w['won_at'])) : '';
      $amount   = (float)($w['amount_paid'] ?? 0);
    ?>
    <div class="win-row raffle-row" onclick="openWin(<?= htmlspecialchars(json_encode($w), ENT_QUOTES) ?>)">
      <?php if (!empty($w['image_url'])): ?>
        <img class="win-thumb" src="<?= htmlspecialchars($w['image_url']) ?>" alt="">
      <?php else: ?>
        <div class="win-thumb-placeholder" style="background:linear-gradient(135deg,#1e1a3a,#2e2860)">🎟</div>
      <?php endif; ?>
      <div class="win-info">
        <div class="win-title"><?= htmlspecialchars($w['title']) ?></div>
        <div class="win-meta">
          <?php if ($w['reward_type'] ?? ''): ?><span class="win-badge" style="background:rgba(182,156,255,.1);color:#b69cff;border:1px solid rgba(182,156,255,.2)"><?= htmlspecialchars($w['reward_type']) ?></span><?php endif; ?>
          <?php if ($w['chain'] ?? ''): ?><span class="win-badge" style="background:rgba(255,255,255,.05);color:#8892a4;border:1px solid #1e2338"><?= htmlspecialchars($w['chain']) ?></span><?php endif; ?>
          <?php if ($amount > 0): ?><span class="win-badge" style="background:rgba(228,197,144,.1);color:#e4c590;border:1px solid rgba(228,197,144,.2)"><?= number_format($amount,2) ?> $BLOX</span><?php endif; ?>
        </div>
      </div>
      <span class="win-date"><?= $date_str ?></span>
      <svg class="win-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Calendar link -->
  <div style="display:flex;align-items:center;gap:8px;padding:14px 18px;border:1px solid #161a28;border-radius:14px;background:rgba(255,255,255,.02)">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#b69cff" stroke-width="1.6"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
    <a href="/calendar/?tab=wl" style="font-size:12.5px;color:#bfe9f5;text-decoration:none">View your Calendar →</a>
    <span style="font-size:12px;color:#5a6478">· All wins are automatically synced.</span>
  </div>

  <?php endif; ?>

</main>
</div>

<!-- ── Win detail modal ── -->
<div class="wm-overlay" id="winModal" onclick="if(event.target===this)closeWin()">
  <div class="wm-box">
    <div class="wm-banner" id="wmBanner">
      <div class="wm-banner-bg" id="wmBannerBg"></div>
      <div class="wm-banner-shine"></div>
      <div class="wm-banner-overlay"></div>
      <button class="wm-close-btn" onclick="closeWin()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>
      <!-- won badge -->
      <div style="position:absolute;bottom:14px;left:16px;display:flex;align-items:center;gap:6px;padding:4px 10px;border-radius:8px;background:rgba(74,222,128,.15);border:1px solid rgba(74,222,128,.4)">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#4ade80" stroke-width="3"><path d="M20 6 9 17l-5-5"/></svg>
        <span style="font-family:'GT America Mono',monospace;font-size:10px;color:#4ade80;letter-spacing:.06em">WON</span>
      </div>
      <div id="wmTypeBadge" style="position:absolute;bottom:14px;right:16px;padding:4px 10px;border-radius:8px;font-family:'GT America Mono',monospace;font-size:10px;letter-spacing:.06em"></div>
    </div>
    <div class="wm-body">
      <div class="wm-title" id="wmTitle"></div>
      <div id="wmRows"></div>
    </div>
    <div class="wm-footer">
      <a id="wmMintLink" href="#" target="_blank" class="bs-foil-btn" style="display:none;padding:2px;border-radius:10px;text-decoration:none;flex:1"><span class="bs-foil-btn-inner" style="padding:9px 18px;font-size:12.5px">Mint →</span></a>
      <form method="post" id="wmDeleteForm" onsubmit="return confirm('Remove this win from your portfolio?')">
        <input type="hidden" name="delete_win_id" id="wmDeleteId">
        <button type="submit" class="btn-delete">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6l-1 14H6L5 6M10 11v6M14 11v6M9 6V4h6v2"/></svg>
          Remove Win
        </button>
      </form>
    </div>
  </div>
</div>

<script>
const hues = [
  'linear-gradient(135deg,#1e2230,#4a5070 55%,#3a4050)',
  'linear-gradient(135deg,#2a123a,#6a38a8 55%,#4f1f6b)',
  'linear-gradient(135deg,#123a2a,#38a870 55%,#1f6b4a)',
  'linear-gradient(135deg,#1a1a2e,#4c4ca0 55%,#3a3a6b)',
  'linear-gradient(135deg,#3a2412,#a08048 55%,#6b4a1f)',
  'linear-gradient(135deg,#13243a,#3a79a8 60%,#1f3f6b)',
];

function openWin(w) {
  const isAuction = w.win_type === 'auction';
  const ac = isAuction ? '#6fe3ff' : '#b69cff';

  // banner
  if (w.image_url) {
    document.getElementById('wmBanner').style.background = '';
    document.getElementById('wmBannerBg').style.background = '';
    document.getElementById('wmBannerBg').innerHTML = '<img src="'+w.image_url+'" style="width:100%;height:100%;object-fit:cover">';
  } else {
    document.getElementById('wmBannerBg').innerHTML = '';
    document.getElementById('wmBannerBg').style.background = hues[Math.floor(Math.random()*hues.length)];
  }

  // type badge
  const tb = document.getElementById('wmTypeBadge');
  tb.textContent = isAuction ? '⭐ AUCTION' : '🎟 RAFFLE';
  tb.style.cssText = `position:absolute;bottom:14px;right:16px;padding:4px 10px;border-radius:8px;font-family:'GT America Mono',monospace;font-size:10px;letter-spacing:.06em;background:rgba(0,0,0,.5);border:1px solid ${ac}66;color:${ac}`;

  document.getElementById('wmTitle').textContent = w.title || '';

  // rows
  const rows = [];
  if (w.reward_type) rows.push(['Reward Type', w.reward_type]);
  if (w.chain) rows.push(['Chain', w.chain]);
  if (w.amount_paid > 0) rows.push(['Amount Paid', parseFloat(w.amount_paid).toLocaleString(undefined,{minimumFractionDigits:2}) + ' $BLOX']);
  if (w.won_at) rows.push(['Won On', new Date(w.won_at).toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'})]);
  if (w.description) rows.push(['Notes', w.description]);

  document.getElementById('wmRows').innerHTML = rows.map(([l,v]) =>
    `<div class="wm-row"><span class="wm-label">${l}</span><span class="wm-val">${v}</span></div>`
  ).join('');

  // mint link
  const ml = document.getElementById('wmMintLink');
  if (w.mint_url) { ml.href = w.mint_url; ml.style.display = 'flex'; }
  else { ml.style.display = 'none'; }

  document.getElementById('wmDeleteId').value = w.id;
  document.getElementById('winModal').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeWin() {
  document.getElementById('winModal').classList.remove('open');
  document.body.style.overflow = '';
}

document.addEventListener('keydown', e => { if(e.key==='Escape') closeWin(); });
</script>
</body>
</html>