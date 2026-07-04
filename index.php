<?php
require_once __DIR__.'/config.php';

// Auto-detect mobile and redirect
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (preg_match('/Mobile|Android|iPhone|iPad|webOS|BlackBerry/i', $ua)) {
    header('Location: /mobile.php');
    exit;
}

$user = get_user();

// ── Stats ──────────────────────────────────────────────────────────────────
try { $active_raffles  = count(sb('bs_raffles')->eq('status','active')->select('id')->get()); }
catch(Exception $e) { $active_raffles = 0; }

try { $active_auctions = count(sb('bs_auctions')->eq('status','active')->select('id')->get()); }
catch(Exception $e) { $active_auctions = 0; }

try { $total_users = count(sb('bs_users')->select('discord_id')->get()); }
catch(Exception $e) { $total_users = 0; }

try { $total_wins = count(sb('bs_wins')->select('id')->get()); }
catch(Exception $e) { $total_wins = 0; }

// ── User balance ───────────────────────────────────────────────────────────
$user_balance = 0.0;
if ($user) {
    $user_balance = get_balance($user['discord_id']);
}

// ── Live raffles (up to 6) ─────────────────────────────────────────────────
try {
    $live_raffles = sb('bs_raffles')->eq('status','active')->order('end_date', true)->limit(6)->get();
} catch(Exception $e) { $live_raffles = []; }

// Entry counts for raffles
$raffle_entries = [];
foreach ($live_raffles as $r) {
    try {
        $ec = db()->prepare("SELECT COUNT(*) FROM bs_raffle_entries WHERE raffle_id=?");
        $ec->execute([$r['id']]);
        $raffle_entries[$r['id']] = (int)$ec->fetchColumn();
    } catch(Exception $e) { $raffle_entries[$r['id']] = 0; }
}

// ── Live auctions (up to 6) ───────────────────────────────────────────────
try {
    $live_auctions = sb('bs_auctions')->eq('status','active')->order('ends_at', true)->limit(6)->get();
    foreach($live_auctions as &$a) {
        $a['bids'] = json_decode($a['bids_json']??'{}', true) ?: [];
    }
    unset($a);
} catch(Exception $e) { $live_auctions = []; }

// ── Helpers ────────────────────────────────────────────────────────────────
function time_left_hms(?string $ts): string {
    if (!$ts) return '';
    $secs = max(0, strtotime($ts) - time());
    if ($secs <= 0) return 'Ended';
    $h = floor($secs / 3600); $m = floor(($secs % 3600) / 60); $s = $secs % 60;
    if ($h > 24) return round($h/24).'d '.($h%24).'h';
    return str_pad($h,2,'0',STR_PAD_LEFT).':'.str_pad($m,2,'0',STR_PAD_LEFT).':'.str_pad($s,2,'0',STR_PAD_LEFT);
}

// ── User avatar/initials ──────────────────────────────────────────────────
$user_avatar_url = '';
$user_initial    = '?';
$user_name       = 'Guest';
if ($user) {
    $user_name       = htmlspecialchars($user['username'] ?? 'User');
    $user_initial    = strtoupper(substr($user['username'] ?? 'U', 0, 1));
    $user_avatar_url = get_avatar_url($user['discord_id'], $user['avatar'] ?? '');
}

// Banner image: site banner, else best live artwork
$site_banner = file_exists(__DIR__.'/assets/banner.jpg') ? '/assets/banner.jpg' : '';
if (!$site_banner) {
    foreach (array_merge($live_raffles, $live_auctions) as $x) {
        if (!empty($x['image_url'])) { $site_banner = $x['image_url']; break; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Home · Blockstards</title>
<link rel="stylesheet" href="/bs_fonts.css?v=1783108465">
<link rel="stylesheet" href="/bs_flat.css?v=1783108465">
<style>
  .login-cta{border:1px solid var(--line);border-radius:12px;background:var(--card);padding:26px 28px;margin-top:30px;display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap}
  .login-cta h2{font-family:var(--font);font-weight:700;font-size:18px;margin-bottom:4px}
  .login-cta p{font-size:13px;color:var(--sub)}
  @media (max-width:640px){ .login-cta{padding:20px 16px} }
</style>
</head>
<body>
<div class="bs-layout">

  <?php $active_page = 'home'; require_once __DIR__.'/includes/bs_sidebar.php'; ?>

  <main class="bs-main">

    <!-- ═══ Page header ═══ -->
    <div class="page-head">
      <div class="page-head-left">
        <div class="page-logo"><img src="/assets/blockstards.gif?v=2" alt="Blockstards"></div>
        <div class="page-title">Blockstards</div>
      </div>
      <div class="page-head-right">
        <?php if ($user): ?>
        <div class="blox-pill">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="1.8"><path d="M3 9V7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 6v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-6z"/><path d="M13 5v2M13 11v2M13 17v2"/></svg>
          <div>
            <div class="blox-pill-val"><?= number_format($user_balance, 0) ?> BLOX</div>
            <div class="blox-pill-sub"><?= $user_name ?></div>
          </div>
        </div>
        <a href="/profile/" class="head-avatar" aria-label="My profile">
          <?php if ($user_avatar_url): ?><img src="<?= htmlspecialchars($user_avatar_url) ?>" alt=""><?php else: ?><?= $user_initial ?><?php endif; ?>
        </a>
        <?php else: ?>
        <a href="/bs-auth/discord.php" class="bs-discord-btn">Sign in with Discord</a>
        <?php endif; ?>
      </div>
    </div>

    <!-- ═══ Banner ═══ -->
    <div class="banner">
      <div class="banner-media"><?php if ($site_banner): ?><img src="<?= htmlspecialchars($site_banner) ?>" alt=""><?php endif; ?></div>
      <div class="banner-scrim"></div>
      <div class="banner-content">
        <div class="banner-title">
          <span>Blockstards</span>
          <span class="vbadge"><svg width="20" height="20" viewBox="0 0 24 24" fill="#8b5cf6"><path d="M12 2 14.4 4.2 17.6 3.8 18.4 7 21.4 8.4 20.2 11.4 22 14.1 19.4 16 19.4 19.3 16.2 19.7 14.4 22.4 11.5 20.9 8.6 22.4 6.8 19.7 3.6 19.3 3.6 16 1 14.1 2.8 11.4 1.6 8.4 4.6 7 5.4 3.8 8.6 4.2z"/><path d="m9.2 12.2 2 2 3.8-4" stroke="#fff" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
        </div>
        <div class="banner-sub">Win whitelist spots in raffles and bid $BLOX on exclusive auctions.</div>
      </div>
    </div>

    <!-- ═══ Club numbers ═══ -->
    <div class="stat-strip">
      <div class="stat-cell"><b><?= $active_raffles ?></b><span>Live raffles</span></div>
      <div class="stat-cell"><b><?= $active_auctions ?></b><span>Live auctions</span></div>
      <div class="stat-cell"><b><?= number_format($total_users) ?></b><span>Members</span></div>
      <div class="stat-cell"><b><?= number_format($total_wins) ?></b><span>Wins paid out</span></div>
    </div>

    <!-- ═══ Live Raffles ═══ -->
    <div class="sec">
      <div class="sec-head">
        <div>
          <div class="sec-title">Live Raffles</div>
          <div class="sec-sub">Curated list of raffles happening now and coming soon.</div>
        </div>
        <a href="/raffles/" class="sec-link">View all <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
      </div>

      <?php if (!empty($live_raffles)): ?>
      <div class="card-row">
        <?php foreach ($live_raffles as $r):
          $entries = $raffle_entries[$r['id']] ?? 0;
          $sub = $entries > 0 ? number_format($entries).' Entries' : date('M j \a\t g:i A', strtotime($r['end_date'] ?? 'now'));
        ?>
        <a href="/raffles/" class="mcard">
          <div class="mcard-img">
            <?php if (!empty($r['image_url'])): ?><div class="mcard-img-bg" style="background-image:url('<?= htmlspecialchars($r['image_url']) ?>')"></div><img src="<?= htmlspecialchars($r['image_url']) ?>" alt="" loading="lazy" class="mcard-img-fg"><?php endif; ?>
            <div class="mcard-live"><span class="dot"></span><span>LIVE</span></div>
          </div>
          <div class="mcard-body">
            <div class="mcard-name"><span><?= htmlspecialchars($r['title']) ?></span></div>
            <div class="mcard-sub"><?= htmlspecialchars($sub) ?></div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="bs-empty">
        <h3>No live raffles</h3>
        <p>New raffles drop regularly — announced in Discord first.</p>
        <a href="/raffles/?filter=ended" class="btn btn-ghost btn-sm">See past raffles</a>
      </div>
      <?php endif; ?>
    </div>

    <!-- ═══ Live Auctions ═══ -->
    <div class="sec">
      <div class="sec-head">
        <div>
          <div class="sec-title">Live Auctions</div>
          <div class="sec-sub">Live whitelist auctions. Place your bids before time runs out.</div>
        </div>
        <a href="/auctions/" class="sec-link">View all <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
      </div>

      <?php if (!empty($live_auctions)): ?>
      <div class="card-row">
        <?php foreach ($live_auctions as $a):
          $reward = htmlspecialchars($a['reward_type'] ?? 'Whitelist Spots');
        ?>
        <a href="/auctions/" class="mcard acard">
          <div class="mcard-img">
            <?php if (!empty($a['image_url'])): ?><div class="mcard-img-bg" style="background-image:url('<?= htmlspecialchars($a['image_url']) ?>')"></div><img src="<?= htmlspecialchars($a['image_url']) ?>" alt="" loading="lazy" class="mcard-img-fg"><?php endif; ?>
            <div class="acard-scrim"></div>
            <div class="acard-meta">
              <div class="mcard-name">
                <span><?= htmlspecialchars($a['title']) ?></span>
                <span class="vbadge"><svg width="14" height="14" viewBox="0 0 24 24" fill="#793afb"><path d="M12 2 14.4 4.2 17.6 3.8 18.4 7 21.4 8.4 20.2 11.4 22 14.1 19.4 16 19.4 19.3 16.2 19.7 14.4 22.4 11.5 20.9 8.6 22.4 6.8 19.7 3.6 19.3 3.6 16 1 14.1 2.8 11.4 1.6 8.4 4.6 7 5.4 3.8 8.6 4.2z"/><path d="m9.2 12.2 2 2 3.8-4" stroke="#fff" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
              </div>
              <div class="mcard-sub" style="color:#c8cbd0"><?= $reward ?></div>
            </div>
          </div>
          <div class="mcard-body">
            <div class="mcard-foot">
              <span class="mcard-foot-live"><span class="dot"></span>LIVE</span>
              <span class="mcard-timer">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 2.5"/><path d="M10 2h4"/></svg>
                <span class="auction-timer" data-ends="<?= htmlspecialchars($a['ends_at'] ?? '') ?>"><?= time_left_hms($a['ends_at'] ?? null) ?></span>
              </span>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="bs-empty">
        <h3>No live auctions</h3>
        <p>Auctions open here as soon as bidding starts.</p>
        <a href="/auction-form.php" class="btn btn-ghost btn-sm">Request an auction</a>
      </div>
      <?php endif; ?>
    </div>

    <!-- Login CTA (only when not logged in) -->
    <?php if (!$user): ?>
    <div class="login-cta">
      <div>
        <h2>Join the club</h2>
        <p>Sign in with Discord to enter raffles, bid on auctions, and earn $BLOX.</p>
      </div>
      <a href="/bs-auth/discord.php" class="bs-discord-btn">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057.101 18.08.114 18.102.132 18.115a19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03z"/></svg>
        Sign in with Discord
      </a>
    </div>
    <?php endif; ?>

    <script>
    function updateTimers() {
      document.querySelectorAll('.auction-timer[data-ends]').forEach(el => {
        const ends = new Date(el.dataset.ends).getTime();
        const ms   = ends - Date.now();
        if (ms <= 0) { el.textContent = 'Ended'; return; }
        const h = Math.floor(ms/3600000), m = Math.floor((ms%3600000)/60000), s = Math.floor((ms%60000)/1000);
        el.textContent = String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
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

  </main>
</div>
</body>
</html>
