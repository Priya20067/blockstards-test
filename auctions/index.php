<?php
require_once __DIR__.'/../config.php';

$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (preg_match('/Mobile|Android|iPhone|iPad|webOS|BlackBerry/i', $ua)) {
    header('Location: /mobile-auctions.php'); exit;
}

$user        = get_user();
$active_page = 'auctions';
$tab         = $_GET['tab'] ?? 'live';

require_once __DIR__.'/../bs-api/bot_sync.php';

// ── Data fetch ─────────────────────────────────────────────────────────────
$auctions = [];
$my_bids  = [];

if ($tab === 'live' || $tab === 'ending') {
    $auctions = get_live_auctions();
} elseif ($tab === 'ended') {
    $rows = sb('bs_auctions')->eq('status','ended')->order('ends_at', false)->limit(20)->get();
    foreach ($rows as &$r) {
        $r['bids']      = json_decode($r['bids_json']   ?? '{}', true) ?: [];
        $r['usernames'] = json_decode($r['usernames_json'] ?? '{}', true) ?: [];
    }
    $auctions = $rows;
} elseif ($tab === 'mybids' && $user) {
    $rows = sb('bs_auctions')->eq('status','active')->order('ends_at', true)->get();
    foreach ($rows as $r) {
        $bids = json_decode($r['bids_json'] ?? '{}', true) ?: [];
        if (isset($bids[$user['discord_id']]) && $bids[$user['discord_id']] > 0) {
            $r['bids']      = $bids;
            $r['my_bid']    = (float)$bids[$user['discord_id']];
            $r['usernames'] = json_decode($r['usernames_json'] ?? '{}', true) ?: [];
            $my_bids[]      = $r;
        }
    }
    $auctions = $my_bids;
}

// Stats
$live_count  = count(sb('bs_auctions')->eq('status','active')->select('id')->get());
$ended_count = count(sb('bs_auctions')->eq('status','ended')->select('id')->get());
$my_bid_count = 0;
if ($user) {
    $all_live = sb('bs_auctions')->eq('status','active')->select('bids_json')->get();
    foreach ($all_live as $a) {
        $bids = json_decode($a['bids_json'] ?? '{}', true) ?: [];
        if (isset($bids[$user['discord_id']]) && $bids[$user['discord_id']] > 0) $my_bid_count++;
    }
}

// ── User meta ──────────────────────────────────────────────────────────────
$user_balance    = $user ? get_balance($user['discord_id']) : 0;
$user_name       = $user ? htmlspecialchars($user['username'] ?? 'User') : 'Guest';
$user_wallet     = '';
if ($user) {
    $w = $user['eth_wallet'] ?? ($user['sol_wallet'] ?? '');
    $user_wallet = $w ? substr($w,0,4).'...'.substr($w,-4) : '';
}
$user_initial    = $user ? strtoupper(substr($user['username'] ?? 'U', 0, 1)) : '?';
$user_avatar_url = $user ? get_avatar_url($user['discord_id'], $user['avatar'] ?? '') : '';

function time_left_str_a(?string $ts): string {
    if (!$ts) return '';
    $secs = max(0, strtotime($ts) - time());
    if ($secs <= 0)    return 'Ended';
    $h = floor($secs / 3600); $m = floor(($secs % 3600) / 60); $s = $secs % 60;
    if ($h > 24) return round($h/24).'d '.($h%24).'h';
    return str_pad($h,2,'0',STR_PAD_LEFT).':'.str_pad($m,2,'0',STR_PAD_LEFT).':'.str_pad($s,2,'0',STR_PAD_LEFT);
}

// Decode bids for live rows + compute helper fields
function prep_auction(array $a, ?array $user): array {
    $bids = is_array($a['bids_json'] ?? null) ? $a['bids_json'] : (json_decode($a['bids_json'] ?? '{}', true) ?: ($a['bids'] ?? []));
    if (!is_array($bids)) $bids = [];
    arsort($bids);
    $a['_bids']    = $bids;
    $a['_top']     = $bids ? (float)reset($bids) : 0;
    $a['_top_uid'] = $bids ? array_key_first($bids) : '';
    $a['_my_bid']  = ($user && isset($bids[$user['discord_id']])) ? (float)$bids[$user['discord_id']] : 0;
    $a['_winning'] = $user && $a['_my_bid'] > 0 && $a['_top_uid'] === $user['discord_id'];
    return $a;
}

// Live lists for the live tab: all live + ending soon (sorted)
$live_all = $ending_soon = [];
if ($tab === 'live' || $tab === 'ending') {
    foreach ($auctions as $a) $live_all[] = prep_auction($a, $user);
    $ending_soon = $live_all;
    usort($ending_soon, fn($x,$y) => strtotime($x['ends_at'] ?? '2099-01-01') <=> strtotime($y['ends_at'] ?? '2099-01-01'));
}

// Banner image: site banner, else first live auction artwork
$site_banner = file_exists(__DIR__.'/../assets/banner.jpg') ? '/assets/banner.jpg' : '';
if (!$site_banner) {
    foreach (($live_all ?: $auctions) as $a) { if (!empty($a['image_url'])) { $site_banner = $a['image_url']; break; } }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Whitelist Auctions · Blockstards</title>
<link rel="stylesheet" href="/bs_fonts.css?v=1783164697">
<link rel="stylesheet" href="/bs_flat.css?v=1783164697">
</head>
<body>
<div class="bs-layout">

<?php require_once __DIR__.'/../includes/bs_sidebar.php'; ?>

<main class="bs-main">

  <!-- ═══ Page header ═══ -->
  <div class="page-head">
    <div class="page-head-left">
      <div class="page-logo"><img src="/assets/blockstards.gif?v=2" alt="Blockstards"></div>
      <div class="page-title">Whitelist Auctions</div>
    </div>
    <div class="page-head-right">
      <?php if ($user): ?>
      <div class="blox-pill">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="1.8"><path d="M3 9V7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 6v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-6z"/><path d="M13 5v2M13 11v2M13 17v2"/></svg>
        <div>
          <div class="blox-pill-val" id="blox-balance"><?= number_format($user_balance, 0) ?> BLOX</div>
          <div class="blox-pill-sub"><?= $user_wallet ?: $user_name ?></div>
        </div>
      </div>
      <a href="/profile/" class="head-avatar" aria-label="My profile">
        <?php if ($user_avatar_url): ?><img src="<?= htmlspecialchars($user_avatar_url) ?>" alt=""><?php else: ?><?= $user_initial ?><?php endif; ?>
      </a>
      <a href="?tab=mybids" class="head-bell" aria-label="My bids">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
        <?php if ($my_bid_count > 0): ?><span class="bell-dot"><?= $my_bid_count ?></span><?php endif; ?>
      </a>
      <?php else: ?>
      <a href="/bs-auth/discord.php" class="bs-discord-btn">Sign in with Discord</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- ═══ Pill tabs ═══ -->
  <div class="pill-tabs">
    <a href="/" class="pill-tab icon-only" aria-label="Home">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 10.2 12 2.8l8.5 7.4V19.5a1.5 1.5 0 0 1-1.5 1.5h-4.2v-6.3a2.8 2.8 0 0 0-5.6 0V21H5a1.5 1.5 0 0 1-1.5-1.5z"/></svg>
    </a>
    <a href="?tab=live" class="pill-tab <?= $tab==='live'?'active':'' ?>">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="4" y="5" width="16" height="16" rx="2"/><path d="M9 3v2M15 3v2"/></svg>
      Live
    </a>
    <a href="?tab=ending" class="pill-tab <?= $tab==='ending'?'active':'' ?>">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 2.5"/><path d="M10 2h4"/></svg>
      Ending Soon
    </a>
    <a href="?tab=ended" class="pill-tab <?= $tab==='ended'?'active':'' ?>">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v4h4"/><path d="M12 7v5l3 2"/></svg>
      Past
    </a>
    <?php if ($user): ?>
    <a href="?tab=mybids" class="pill-tab <?= $tab==='mybids'?'active':'' ?>">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m14 13-7.5 7.5a2.1 2.1 0 0 1-3-3L11 10"/><path d="m16 16 6-6"/><path d="m8 8 6-6"/><path d="m9 7 8 8"/><path d="m21 11-8-8"/></svg>
      My Bids
    </a>
    <?php endif; ?>
  </div>

  <?php if ($tab === 'live' || $tab === 'ending'): ?>
  <!-- ═══ Banner ═══ -->
  <div class="banner">
    <div class="banner-media"><?php if ($site_banner): ?><img src="<?= htmlspecialchars($site_banner) ?>" alt=""><?php endif; ?></div>
    <div class="banner-scrim"></div>
    <div class="banner-content">
      <div class="banner-title"><span>Compete for Exclusive Access</span></div>
      <div class="banner-sub">Bid using $BLOX on whitelist spots from top NFT projects.</div>
    </div>
  </div>
  <?php endif; ?>

  <?php
  // ── Card renderer ─────────────────────────────────────────────────────────
  function auction_card(array $a, ?array $user, string $mode = 'live'): void {
      $reward = htmlspecialchars($a['reward_type'] ?? 'Whitelist Spots');
      $ends   = htmlspecialchars($a['ends_at'] ?? '');
      $tl     = time_left_str_a($a['ends_at'] ?? null);
      $click  = '';
      if ($mode === 'live' || $mode === 'mybids') {
          if ($user) {
              $click = 'openBidModal('.(int)$a['id'].','.htmlspecialchars(json_encode($a['title'])).','.($a['_my_bid'] ?? 0).','.($a['_top'] ?? 0).')';
          } else {
              $click = "location.href='/bs-auth/discord.php'";
          }
      }
      $tag   = $click ? 'button' : 'div';
      ?>
      <<?= $tag ?> class="mcard <?= $mode==='ended' ? '' : 'acard' ?>" <?= $click ? 'onclick="'.$click.'"' : '' ?>>
        <div class="mcard-img">
          <?php if (!empty($a['image_url'])): ?><div class="mcard-img-bg" style="background-image:url('<?= htmlspecialchars($a['image_url']) ?>')"></div><img src="<?= htmlspecialchars($a['image_url']) ?>" alt="" loading="lazy" class="mcard-img-fg"><?php endif; ?>
        </div>
        <div class="mcard-body">
          <?php if ($mode === 'ended'): ?>
          <div class="mcard-name">
            <span><?= htmlspecialchars($a['title']) ?></span>
            <span class="vbadge"><svg width="14" height="14" viewBox="0 0 24 24" fill="#793afb"><path d="M12 2 14.4 4.2 17.6 3.8 18.4 7 21.4 8.4 20.2 11.4 22 14.1 19.4 16 19.4 19.3 16.2 19.7 14.4 22.4 11.5 20.9 8.6 22.4 6.8 19.7 3.6 19.3 3.6 16 1 14.1 2.8 11.4 1.6 8.4 4.6 7 5.4 3.8 8.6 4.2z"/><path d="m9.2 12.2 2 2 3.8-4" stroke="#fff" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
          </div>
          <div class="mcard-badge-row" style="padding-top:6px">
            <span class="ended-badge">AUCTION ENDED</span>
            <span>· <?= ($a['_top'] ?? 0) ? number_format($a['_top'],0).' $BLOX' : 'No bids' ?></span>
          </div>
          <?php else: ?>
          <div class="mcard-name">
            <span><?= htmlspecialchars($a['title']) ?></span>
            <span class="vbadge"><svg width="14" height="14" viewBox="0 0 24 24" fill="#793afb"><path d="M12 2 14.4 4.2 17.6 3.8 18.4 7 21.4 8.4 20.2 11.4 22 14.1 19.4 16 19.4 19.3 16.2 19.7 14.4 22.4 11.5 20.9 8.6 22.4 6.8 19.7 3.6 19.3 3.6 16 1 14.1 2.8 11.4 1.6 8.4 4.6 7 5.4 3.8 8.6 4.2z"/><path d="m9.2 12.2 2 2 3.8-4" stroke="#fff" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
          </div>
          <div class="mcard-sub"><?= $mode==='mybids' ? 'Your bid · '.number_format($a['_my_bid'] ?? 0, 2).' $BLOX' : $reward ?></div>
          <div class="mcard-foot">
            <?php if ($mode === 'mybids'): ?>
            <span class="mcard-foot-live <?= ($a['_winning'] ?? false) ? '' : 'is-red' ?>"><span class="dot"></span><?= ($a['_winning'] ?? false) ? 'WINNING' : 'OUTBID' ?></span>
            <?php else: ?>
            <span class="mcard-foot-live"><span class="dot"></span>LIVE</span>
            <?php endif; ?>
            <span class="mcard-timer">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 2.5"/><path d="M10 2h4"/></svg>
              <span class="auction-timer" data-ends="<?= $ends ?>"><?= $tl ?></span>
            </span>
          </div>
          <?php endif; ?>
        </div>
      </<?= $tag ?>>
      <?php
  }
  ?>

  <?php if ($tab === 'live'): ?>

    <?php if (empty($live_all)): ?>
    <div class="bs-empty" style="margin-top:20px">
      <h3>No live auctions right now</h3>
      <p>New auctions appear here as soon as bidding opens.</p>
      <?php if ($ended_count > 0): ?><a href="?tab=ended" class="btn btn-ghost btn-sm">View past auctions</a><?php endif; ?>
    </div>
    <?php else: ?>

    <!-- ═══ Live Auctions ═══ -->
    <div class="sec">
      <div class="sec-head">
        <div>
          <div class="sec-title">Live Auctions</div>
          <div class="sec-sub">Live whitelist auctions. Place your bids before time runs out.</div>
        </div>
        <a href="?tab=live" class="sec-link">View all <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
      </div>
      <div class="card-row">
        <?php foreach ($live_all as $a) auction_card($a, $user, 'live'); ?>
      </div>
    </div>

    <!-- ═══ Ending Soon ═══ -->
    <?php if (count($ending_soon) > 1): ?>
    <div class="sec">
      <div class="sec-head">
        <div>
          <div class="sec-title">Ending Soon</div>
          <div class="sec-sub">Auctions ending soon. Don't miss your chance.</div>
        </div>
        <a href="?tab=ending" class="sec-link">View all <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
      </div>
      <div class="card-row">
        <?php foreach ($ending_soon as $a) auction_card($a, $user, 'live'); ?>
      </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>

  <?php elseif ($tab === 'ending'): ?>

    <div class="sec">
      <div class="sec-head">
        <div>
          <div class="sec-title">Ending Soon</div>
          <div class="sec-sub">Auctions ending soon. Don't miss your chance.</div>
        </div>
      </div>
      <?php if (empty($ending_soon)): ?>
      <div class="bs-empty"><h3>No live auctions</h3><p>Nothing is ending soon — check back later.</p></div>
      <?php else: ?>
      <div class="card-row">
        <?php foreach ($ending_soon as $a) auction_card($a, $user, 'live'); ?>
      </div>
      <?php endif; ?>
    </div>

  <?php elseif ($tab === 'ended'): ?>

    <div class="sec">
      <div class="sec-head">
        <div>
          <div class="sec-title">Past Auctions</div>
          <div class="sec-sub">Ended auctions and their winning bids.</div>
        </div>
      </div>
      <?php if (empty($auctions)): ?>
      <div class="bs-empty"><h3>No past auctions yet</h3><p>Ended auctions will appear here.</p></div>
      <?php else: ?>
      <div class="card-grid">
        <?php foreach ($auctions as $a) auction_card(prep_auction($a, $user), $user, 'ended'); ?>
      </div>
      <?php endif; ?>
    </div>

  <?php elseif ($tab === 'mybids'): ?>

    <div class="sec">
      <div class="sec-head">
        <div>
          <div class="sec-title">My Bids</div>
          <div class="sec-sub">Auctions you're bidding on. Tap a card to increase your bid.</div>
        </div>
      </div>
      <?php if (empty($auctions)): ?>
      <div class="bs-empty">
        <h3>No active bids yet</h3>
        <p>Place a bid on a live auction and it will show up here.</p>
        <?php if ($live_count > 0): ?><a href="?tab=live" class="btn btn-ghost btn-sm">View live auctions</a><?php endif; ?>
      </div>
      <?php else: ?>
      <div class="card-row">
        <?php foreach ($auctions as $a) auction_card(prep_auction($a, $user), $user, 'mybids'); ?>
      </div>
      <?php endif; ?>
    </div>

  <?php endif; ?>

</main>
</div>

<!-- ═══ BID MODAL ═══ -->
<div class="bs-modal-overlay" id="bid-modal">
  <div class="bs-modal" style="width:440px">
    <div class="bs-modal-img" id="bid-modal-hdr" style="height:100px">
      <div class="bs-modal-overlay-dark"></div>
      <div class="bs-modal-close" onclick="document.getElementById('bid-modal').classList.remove('open')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#cdd4e2" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </div>
      <div class="bs-modal-meta">
        <div id="bid-modal-sub" class="microlabel" style="margin-bottom:4px"></div>
        <div id="bid-modal-title" style="font-family:var(--font);font-weight:700;font-size:20px"></div>
      </div>
    </div>
    <div class="bs-modal-body">
      <div style="display:flex;justify-content:space-between;padding:12px 14px;border-radius:12px;background:var(--card-2);border:1px solid var(--line);margin-bottom:18px">
        <div>
          <div class="microlabel" style="margin-bottom:3px">Top bid</div>
          <div id="bid-modal-top" style="font-family:var(--mono);font-size:14px">—</div>
        </div>
        <div style="text-align:right">
          <div class="microlabel" style="margin-bottom:3px">Your balance</div>
          <div style="font-family:var(--mono);font-size:14px;color:var(--gold)"><?= number_format($user_balance,2) ?> $BLOX</div>
        </div>
      </div>
      <div class="microlabel" style="margin-bottom:8px">Your bid ($BLOX)</div>
      <div style="display:flex;align-items:center;gap:12px;background:var(--bg);border:1px solid var(--line-2);border-radius:10px;padding:0 16px;margin-bottom:8px">
        <input id="bid-input" type="number" min="1" step="1" placeholder="0" style="flex:1;min-width:0;background:transparent;border:none;outline:none;color:var(--text);font-family:var(--mono);font-size:17px;padding:14px 0">
        <span style="font-family:var(--mono);font-size:12px;color:var(--dim)">$BLOX</span>
      </div>
      <div id="bid-hint" style="font-family:var(--mono);font-size:10.5px;color:var(--dim);margin-bottom:20px"></div>
      <div style="display:flex;gap:10px">
        <button class="btn btn-ghost" onclick="document.getElementById('bid-modal').classList.remove('open')">Cancel</button>
        <div class="bs-foil-btn" id="bid-confirm-btn" onclick="submitBid()" style="flex:1">
          <span class="bs-foil-btn-inner">Confirm bid</span>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
let _bidAuctionId = null;
let _currentMyBid = 0;
let _currentTop   = 0;

function openBidModal(id, title, myBid, topBid) {
  _bidAuctionId = id;
  _currentMyBid = myBid;
  _currentTop   = topBid;
  document.getElementById('bid-modal-title').textContent = title;
  document.getElementById('bid-modal-sub').textContent   = myBid > 0 ? 'INCREASE BID' : 'PLACE BID';
  document.getElementById('bid-modal-top').textContent   = topBid ? parseFloat(topBid).toFixed(2)+' $BLOX' : 'No bids yet';
  const minNext = myBid > 0 ? parseFloat(myBid) + 0.01 : 1;
  document.getElementById('bid-input').placeholder = minNext.toFixed(2);
  document.getElementById('bid-input').value = '';
  document.getElementById('bid-hint').textContent = myBid > 0 ? 'Current bid '+parseFloat(myBid).toFixed(2)+' — enter a higher amount' : 'Enter your bid';
  document.getElementById('bid-modal').classList.add('open');
}
document.getElementById('bid-modal').addEventListener('click',function(e){if(e.target===this)this.classList.remove('open');});

async function submitBid() {
  const val = parseFloat(document.getElementById('bid-input').value);
  if (!val || val <= _currentMyBid) { alert('Bid must be higher than your current bid.'); return; }
  const btn = document.getElementById('bid-confirm-btn');
  btn.querySelector('.bs-foil-btn-inner').textContent = 'Placing…';
  btn.style.pointerEvents = 'none';
  try {
    const res  = await fetch('/bs-api/auction_bid.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({auction_id:_bidAuctionId,amount:val})});
    const data = await res.json();
    if (data.success) {
      document.getElementById('bid-modal').classList.remove('open');
      if (data.new_balance != null) {
        document.querySelectorAll('#blox-balance,#blox-balance-sidebar').forEach(el => el.textContent = parseFloat(data.new_balance).toFixed(2)+' $BLOX');
      }
      setTimeout(()=>location.reload(),800);
    } else { alert(data.message||'Failed'); btn.querySelector('.bs-foil-btn-inner').textContent='Confirm bid'; btn.style.pointerEvents=''; }
  } catch(e) { btn.querySelector('.bs-foil-btn-inner').textContent='Confirm bid'; btn.style.pointerEvents=''; }
}

// Live countdown timers (HH:MM:SS like the mockup)
function updateTimers() {
  document.querySelectorAll('.auction-timer[data-ends]').forEach(el => {
    const ends = new Date(el.dataset.ends).getTime();
    const ms   = ends - Date.now();
    if (ms <= 0) { el.textContent = 'Ended'; return; }
    const h = Math.floor(ms/3600000), m = Math.floor((ms%3600000)/60000), s = Math.floor((ms%60000)/1000);
    el.textContent = String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
    if (ms < 3600000) el.style.color='var(--red)';
  });
}
updateTimers();
setInterval(updateTimers,1000);

/* ── Card carousels (OpenSea-style horizontal scroll) ── */
(function(){
  function initCarousel(row){
    if(row.dataset.carousel) return;
    row.dataset.carousel = '1';
    var wrap = document.createElement('div');
    wrap.className = 'card-carousel';
    row.parentNode.insertBefore(wrap, row);
    wrap.appendChild(row);

    var prev = document.createElement('button');
    prev.className = 'carousel-nav prev';
    prev.setAttribute('aria-label','Scroll left');
    prev.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>';
    var next = document.createElement('button');
    next.className = 'carousel-nav next';
    next.setAttribute('aria-label','Scroll right');
    next.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>';
    wrap.appendChild(prev);
    wrap.appendChild(next);

    function scrollByCards(dir){
      var card = row.querySelector('.mcard, .skel-card, .empty-card');
      var step = card ? (card.getBoundingClientRect().width + 16) * 2 : row.clientWidth * 0.8;
      row.scrollBy({left: dir * step, behavior: 'smooth'});
    }
    prev.addEventListener('click', function(){ scrollByCards(-1); });
    next.addEventListener('click', function(){ scrollByCards(1); });

    function updateArrows(){
      var atStart = row.scrollLeft <= 2;
      var atEnd = row.scrollLeft + row.clientWidth >= row.scrollWidth - 2;
      prev.disabled = atStart;
      next.disabled = atEnd;
      // hide both if nothing to scroll
      var noScroll = row.scrollWidth <= row.clientWidth + 4;
      prev.style.display = noScroll ? 'none' : '';
      next.style.display = noScroll ? 'none' : '';
    }
    row.addEventListener('scroll', updateArrows, {passive:true});
    window.addEventListener('resize', updateArrows);
    // run after images/layout settle
    setTimeout(updateArrows, 60);
    setTimeout(updateArrows, 400);
  }
  function initAll(){
    document.querySelectorAll('.card-row').forEach(initCarousel);
  }
  if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initAll);
  else initAll();
})();

</script>
</body>
</html>
