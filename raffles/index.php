<?php
require_once __DIR__.'/../config.php';

$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (preg_match('/Mobile|Android|iPhone|iPad|webOS|BlackBerry/i', $ua)) {
    header('Location: /mobile-raffles.php'); exit;
}

$user        = get_user();
$active_page = 'raffles';
$filter      = $_GET['filter'] ?? 'active';
$status      = $filter === 'ended' ? 'ended' : 'active';
$search      = strtolower(trim($_GET['q'] ?? ''));

// ── Data fetch ─────────────────────────────────────────────────────────────
$raffle_rows = sb('bs_raffles')->eq('status', $status)->order('id', false)->get();
$total_active = count(sb('bs_raffles')->eq('status','active')->select('id')->get());
$total_ended  = count(sb('bs_raffles')->eq('status','ended')->select('id')->get());

// Entry counts + optional search filter
$raffles = [];
foreach ($raffle_rows as $r) {
    if ($search && stripos($r['title'], $search) === false) continue;
    $entries = sb('bs_raffle_entries')->eq('raffle_id', $r['id'])->select('discord_id')->get();
    $r['entry_count'] = count($entries);
    $raffles[] = $r;
}

// Which raffles has current user entered?
$entered = [];
if ($user) {
    $my_entries = sb('bs_raffle_entries')->eq('discord_id', $user['discord_id'])->select('raffle_id')->get();
    $entered    = array_column($my_entries, 'raffle_id');
}
$user_entry_count = count($entered);

// Raffles the user has entered (for the Allowlist section, active view)
$allowlist = [];
if ($user && $status === 'active') {
    foreach ($raffles as $r) {
        if (in_array($r['id'], $entered)) $allowlist[] = $r;
    }
}

// ── User meta ──────────────────────────────────────────────────────────────
$user_balance    = $user ? get_balance($user['discord_id']) : 0;
$user_name       = $user ? htmlspecialchars($user['username'] ?? 'User') : 'Guest';
$user_initial    = $user ? strtoupper(substr($user['username'] ?? 'U', 0, 1)) : '?';
$user_avatar_url = $user ? get_avatar_url($user['discord_id'], $user['avatar'] ?? '') : '';

function raffle_date_str(?string $ts): string {
    if (!$ts) return '';
    return date('F j \a\t g:i A T', strtotime($ts));
}

// ── Banner slides: brand + live raffles with artwork ───────────────────────
$site_banner = file_exists(__DIR__.'/../assets/banner.jpg') ? '/assets/banner.jpg' : '';
$banner_slides = [];
$banner_slides[] = ['type'=>'brand','title'=>'Blockstards Raffles','sub'=>'By Blockstards','image'=>$site_banner,'id'=>0];
if ($status === 'active') {
    foreach (array_slice($raffles, 0, 4) as $r) {
        if (empty($r['image_url'])) continue;
        $banner_slides[] = ['type'=>'raffle','title'=>$r['title'],'sub'=>$r['entry_count'].' Entries · '.htmlspecialchars($r['chain'] ?? 'ETH'),'image'=>$r['image_url'],'id'=>(int)$r['id']];
    }
}
// if brand slide has no image, borrow the first raffle artwork
if (!$banner_slides[0]['image']) {
    foreach ($raffles as $r) { if (!empty($r['image_url'])) { $banner_slides[0]['image'] = $r['image_url']; break; } }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Raffles · Blockstards</title>
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
      <div class="page-title">Raffles</div>
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

  <!-- ═══ Pill tabs ═══ -->
  <div class="pill-tabs">
    <?php if ($filter !== 'ended'): ?>
    <a href="/" class="pill-tab icon-only" aria-label="Home">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 10.2 12 2.8l8.5 7.4V19.5a1.5 1.5 0 0 1-1.5 1.5h-4.2v-6.3a2.8 2.8 0 0 0-5.6 0V21H5a1.5 1.5 0 0 1-1.5-1.5z"/></svg>
    </a>
    <?php endif; ?>
    <a href="?filter=active" class="pill-tab <?= $filter==='active'?'active':'' ?>">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="4" y="5" width="16" height="16" rx="2"/><path d="M9 3v2M15 3v2"/></svg>
      <?= $filter==='ended' ? 'Live &amp; Upcoming' : 'Live' ?>
    </a>
    <a href="?filter=ended" class="pill-tab <?= $filter==='ended'?'active':'' ?>">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v4h4"/><path d="M12 7v5l3 2"/></svg>
      Past
    </a>
  </div>

  <?php if ($filter !== 'ended'): ?>

  <!-- ═══ Banner ═══ -->
  <div class="banner" id="raffle-banner">
    <?php foreach ($banner_slides as $si => $sl): ?>
    <div class="banner-media banner-slide<?= $si===0?' is-active':'' ?>" data-idx="<?= $si ?>">
      <?php if ($sl['image']): ?><img src="<?= htmlspecialchars($sl['image']) ?>" alt=""><?php endif; ?>
    </div>
    <?php endforeach; ?>
    <div class="banner-scrim"></div>
    <div class="banner-content" id="banner-click" style="cursor:pointer">
      <div class="banner-title">
        <span id="banner-title-txt"><?= htmlspecialchars($banner_slides[0]['title']) ?></span>
        <span class="vbadge"><svg width="20" height="20" viewBox="0 0 24 24" fill="#8b5cf6"><path d="M12 2 14.4 4.2 17.6 3.8 18.4 7 21.4 8.4 20.2 11.4 22 14.1 19.4 16 19.4 19.3 16.2 19.7 14.4 22.4 11.5 20.9 8.6 22.4 6.8 19.7 3.6 19.3 3.6 16 1 14.1 2.8 11.4 1.6 8.4 4.6 7 5.4 3.8 8.6 4.2z"/><path d="m9.2 12.2 2 2 3.8-4" stroke="#fff" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
      </div>
      <div class="banner-sub" id="banner-sub-txt"><?= htmlspecialchars($banner_slides[0]['sub']) ?></div>
    </div>
    <?php if (count($banner_slides) > 1): ?>
    <button class="banner-nav prev" onclick="event.stopPropagation();bannerPrev()" aria-label="Previous slide"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg></button>
    <button class="banner-nav next" onclick="event.stopPropagation();bannerNext()" aria-label="Next slide"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg></button>
    <div class="banner-dots">
      <?php foreach ($banner_slides as $si => $sl): ?>
      <button class="banner-dot <?= $si===0?'active':'' ?>" data-idx="<?= $si ?>" onclick="event.stopPropagation();bannerGo(<?= $si ?>)" aria-label="Slide <?= $si+1 ?>"></button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- ═══ Live Raffles ═══ -->
  <div class="sec">
    <div class="sec-head">
      <div>
        <div class="sec-title">Live Raffles</div>
        <div class="sec-sub">Curated list of raffles happening now and coming soon.</div>
      </div>
    </div>

    <?php if (!empty($raffles)): ?>
    <div class="card-row">
      <?php foreach ($raffles as $r):
        $sub = $r['entry_count'] > 0
             ? number_format($r['entry_count']).' Entries'
             : raffle_date_str($r['end_date'] ?? null);
      ?>
      <button class="mcard" onclick="openRaffle(<?= (int)$r['id'] ?>)">
        <div class="mcard-img">
          <?php if (!empty($r['image_url'])): ?><div class="mcard-img-bg" style="background-image:url('<?= htmlspecialchars($r['image_url']) ?>')"></div><img src="<?= htmlspecialchars($r['image_url']) ?>" alt="" loading="lazy" class="mcard-img-fg"><?php endif; ?>
          <div class="mcard-live"><span class="dot"></span><span>LIVE</span></div>
        </div>
        <div class="mcard-body">
          <div class="mcard-name"><span><?= htmlspecialchars($r['title']) ?></span></div>
          <div class="mcard-sub"><?= htmlspecialchars($sub) ?></div>
        </div>
      </button>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="bs-empty">
      <h3>No live raffles right now</h3>
      <p>New raffles appear here the moment they go live.</p>
      <?php if ($total_ended > 0): ?><a href="?filter=ended" class="btn btn-ghost btn-sm">View past raffles</a><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- ═══ Raffles Won ═══ -->
  <div class="sec">
    <div class="sec-head">
      <div>
        <div class="sec-title">Raffles Won</div>
        <div class="sec-sub">Whitelist spots you've won from raffle entries.</div>
      </div>
      <a href="/wins/" class="sec-link">View wins <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
    </div>

    <div class="card-row">
      <?php if (!empty($allowlist)): ?>
        <?php foreach ($allowlist as $r): ?>
        <button class="mcard" onclick="openRaffle(<?= (int)$r['id'] ?>)">
          <div class="mcard-img">
            <?php if (!empty($r['image_url'])): ?><div class="mcard-img-bg" style="background-image:url('<?= htmlspecialchars($r['image_url']) ?>')"></div><img src="<?= htmlspecialchars($r['image_url']) ?>" alt="" loading="lazy" class="mcard-img-fg"><?php endif; ?>
            <div class="mcard-live"><span class="dot"></span><span>LIVE</span></div>
          </div>
          <div class="mcard-body">
            <div class="mcard-name"><span><?= htmlspecialchars($r['title']) ?></span></div>
            <div class="mcard-sub"><?= number_format($r['entry_count']) ?> Entries</div>
          </div>
        </button>
        <?php endforeach; ?>
        <?php for ($i = count($allowlist); $i < 4; $i++): ?>
        <div class="skel-card" aria-hidden="true"><div style="position:relative;flex:1"><div class="skel-dot"></div></div><div class="skel-line w60"></div><div class="skel-line w40"></div></div>
        <?php endfor; ?>
      <?php else: ?>
        <div class="empty-card">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="1.8" style="transform:rotate(-14deg)"><path d="M3 9V7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 6v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-6z"/><path d="M13 5v2M13 11v2M13 17v2"/></svg>
          <b>No raffles won yet</b>
          <p><?= $user ? 'Win a raffle and your whitelist spot will show up here.' : 'Sign in with Discord — raffles you win will appear here.' ?></p>
        </div>
        <?php for ($i = 1; $i < 4; $i++): ?>
        <div class="skel-card" aria-hidden="true"><div style="position:relative;flex:1"><div class="skel-dot"></div></div><div class="skel-line w60"></div><div class="skel-line w40"></div></div>
        <?php endfor; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php else: ?>

  <!-- ═══ Past raffles grid ═══ -->
  <?php if (empty($raffles)): ?>
  <div class="bs-empty" style="margin-top:8px">
    <h3>No past raffles yet</h3>
    <p>Ended raffles and their winners will appear here.</p>
    <a href="?filter=active" class="btn btn-ghost btn-sm">View live raffles</a>
  </div>
  <?php else: ?>
  <div class="card-grid" style="margin-top:6px">
    <?php foreach ($raffles as $r): ?>
    <button class="mcard" onclick="openRaffle(<?= (int)$r['id'] ?>)">
      <div class="mcard-img">
        <?php if (!empty($r['image_url'])): ?><div class="mcard-img-bg" style="background-image:url('<?= htmlspecialchars($r['image_url']) ?>')"></div><img src="<?= htmlspecialchars($r['image_url']) ?>" alt="" loading="lazy" class="mcard-img-fg"><?php endif; ?>
      </div>
      <div class="mcard-body">
        <div class="mcard-name"><span><?= htmlspecialchars($r['title']) ?></span></div>
        <div class="mcard-badge-row">
          <span class="ended-badge">RAFFLE ENDED</span>
          <span>· <?= number_format($r['entry_count']) ?> Entries</span>
        </div>
      </div>
    </button>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php endif; ?>

</main>
</div>

<!-- ═══ RAFFLE MODAL ═══ -->
<div class="bs-modal-overlay" id="raffle-modal">
  <div class="bs-modal" style="width:500px">
    <div class="bs-modal-img" id="modal-img-wrap">
      <div class="bs-modal-overlay-dark"></div>
      <div class="bs-modal-close" onclick="closeRaffleModal()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#cdd4e2" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </div>
      <div class="bs-modal-meta">
        <div id="modal-subtitle" class="microlabel" style="margin-bottom:4px"></div>
        <div id="modal-title" style="font-family:var(--font);font-weight:700;font-size:20px">Loading…</div>
      </div>
    </div>
    <div class="bs-modal-body">
      <div id="modal-body"></div>
      <div id="modal-footer" style="margin-top:16px"></div>
    </div>
  </div>
</div>

<script>
const _userId   = <?= $user ? "'".addslashes($user['discord_id'])."'" : 'null' ?>;
const _isStaff  = <?= (is_staff() ? 'true' : 'false') ?>;

/* ── Banner carousel ── */
const _slides = <?= json_encode(array_map(fn($s)=>['title'=>$s['title'],'sub'=>$s['sub'],'id'=>$s['id']], $banner_slides)) ?>;
let _bIdx = 0, _bTimer = null;
function bannerGo(i){
  _bIdx = i;
  document.querySelectorAll('.banner-slide').forEach((s,si)=>s.classList.toggle('is-active', si===i));
  document.querySelectorAll('.banner-dot').forEach((d,di)=>d.classList.toggle('active', di===i));
  document.getElementById('banner-title-txt').textContent = _slides[i].title;
  document.getElementById('banner-sub-txt').textContent   = _slides[i].sub;
  restartBanner();

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

}
function bannerNext(){ bannerGo((_bIdx+1)%_slides.length); }
function bannerPrev(){ bannerGo((_bIdx-1+_slides.length)%_slides.length); }
function restartBanner(){
  if (_slides.length < 2) return;
  clearInterval(_bTimer);
  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches)
    _bTimer = setInterval(()=>bannerGo((_bIdx+1)%_slides.length), 6000);
}
restartBanner();
const _bc = document.getElementById('banner-click');
if (_bc) _bc.addEventListener('click', ()=>{ const s=_slides[_bIdx]; if (s.id) openRaffle(s.id); });

/* ── Raffle modal (unchanged backend logic) ── */
function openRaffleModal(){ document.getElementById('raffle-modal').classList.add('open'); }
function closeRaffleModal(){ document.getElementById('raffle-modal').classList.remove('open'); }
document.getElementById('raffle-modal').addEventListener('click', function(e){ if(e.target===this) closeRaffleModal(); });

async function openRaffle(id) {
  openRaffleModal();
  document.getElementById('modal-title').textContent = 'Loading…';
  document.getElementById('modal-body').innerHTML = '<div style="text-align:center;padding:30px;color:var(--dim)">Loading…</div>';
  document.getElementById('modal-footer').innerHTML = '';
  try {
    const res  = await fetch('/bs-api/raffle.php?id='+id);
    const data = await res.json();
    const r    = data.raffle, ent = data.entered, wins = data.winners || [];

    document.getElementById('modal-title').textContent = r.title;
    document.getElementById('modal-subtitle').textContent = (r.chain||'ETH') + ' · ' + r.spots + ' SPOTS';
    const imgWrap = document.getElementById('modal-img-wrap');
    if (r.image_url) {
      imgWrap.style.backgroundImage = 'url('+JSON.stringify(r.image_url)+')';
      imgWrap.style.backgroundSize  = 'cover';
      imgWrap.style.backgroundPosition = 'center';
    }

    let winnersHtml = '';
    if (wins.length) {
      winnersHtml = `<div style="margin-top:14px;padding:14px;background:var(--card-2);border:1px solid var(--line);border-radius:12px">
        <div class="microlabel" style="margin-bottom:10px">Winners</div>
        ${wins.map(w => `<div style="padding:8px 0;border-bottom:1px solid var(--line);display:flex;gap:12px;align-items:center">
          <div style="flex:1">
            <div style="font-size:13px;font-weight:600">${w.username||'…'+w.discord_id.slice(-4)}</div>
            ${w.eth_wallet?`<div style="font-family:var(--mono);font-size:9.5px;color:var(--dim)">ETH: ${w.eth_wallet.slice(0,8)}…${w.eth_wallet.slice(-6)}</div>`:''}
            ${w.sol_wallet?`<div style="font-family:var(--mono);font-size:9.5px;color:var(--dim)">SOL: ${w.sol_wallet.slice(0,8)}…${w.sol_wallet.slice(-6)}</div>`:''}
          </div>
        </div>`).join('')}
      </div>`;
    }

    const costStr = r.entry_type==='blox' ? r.blox_cost+' $BLOX' : 'Free';
    const endDate = r.end_date ? new Date(r.end_date).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : '';

    document.getElementById('modal-body').innerHTML = `
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
        <span class="bs-badge ${r.entry_type==='blox'?'bs-badge-gold':'bs-badge-green'}">${costStr}</span>
        <span class="bs-badge">${r.chain||'ETH'}</span>
        <span class="bs-badge ${r.status==='active'?'bs-badge-green':''}">${r.status==='active'?'LIVE':'ENDED'}</span>
      </div>
      ${r.description?`<p style="font-size:13px;color:var(--sub);line-height:1.6;margin-bottom:14px">${r.description}</p>`:''}
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:14px">
        <div style="background:var(--card-2);border:1px solid var(--line);border-radius:11px;padding:12px;text-align:center">
          <div class="microlabel" style="margin-bottom:4px">Spots</div>
          <div style="font-family:var(--font);font-weight:700;font-size:20px">${r.spots}</div>
        </div>
        <div style="background:var(--card-2);border:1px solid var(--line);border-radius:11px;padding:12px;text-align:center">
          <div class="microlabel" style="margin-bottom:4px">Entries</div>
          <div style="font-family:var(--font);font-weight:700;font-size:20px">${r.entry_count||0}</div>
        </div>
        <div style="background:var(--card-2);border:1px solid var(--line);border-radius:11px;padding:12px;text-align:center">
          <div class="microlabel" style="margin-bottom:4px">Ends</div>
          <div style="font-size:12px;margin-top:4px">${endDate||'—'}</div>
        </div>
      </div>
      ${r.mint_url?`<a href="${r.mint_url}" target="_blank" style="display:inline-block;margin-bottom:10px;font-size:12px;color:var(--sub)">View mint page →</a>`:''}
      ${winnersHtml}
    `;

    const footer = document.getElementById('modal-footer');
    if (!_userId) {
      footer.innerHTML = `<a href="/bs-auth/discord.php" class="bs-foil-btn"><span class="bs-foil-btn-inner">Sign in with Discord to enter</span></a>`;
    } else if (r.status==='ended') {
      footer.innerHTML = '<span style="color:var(--dim);font-size:12px;font-family:var(--mono)">Raffle ended</span>';
      if (_isStaff) footer.innerHTML += ` <button onclick="endRaffle(${r.id},this)" class="btn btn-ghost btn-sm" style="margin-left:12px">Re-announce winners</button>`;
    } else if (ent) {
      footer.innerHTML = '<div style="display:flex;align-items:center;gap:8px;color:var(--green);font-family:var(--mono);font-size:12px"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg>Entered — good luck</div>';
      if (_isStaff) footer.innerHTML += ` <button onclick="endRaffle(${r.id},this)" class="btn btn-ghost btn-sm" style="margin-left:12px;color:var(--red);border-color:rgba(240,98,93,.4)">End raffle</button>`;
    } else {
      footer.innerHTML = `<div class="bs-foil-btn" onclick="enterRaffle(${r.id},this)"><span class="bs-foil-btn-inner">Enter raffle <span>· ${costStr}</span></span></div>`;
      if (_isStaff) footer.innerHTML += ` <button onclick="endRaffle(${r.id},this)" class="btn btn-ghost btn-sm" style="margin-top:10px;color:var(--red);border-color:rgba(240,98,93,.4)">End raffle</button>`;
    }
  } catch(e) {
    document.getElementById('modal-body').innerHTML = '<div style="color:var(--red);padding:20px;font-size:13px">Failed to load raffle. Close this window and try again.</div>';
  }
}

async function enterRaffle(id, btn) {
  btn.style.pointerEvents = 'none';
  btn.querySelector('.bs-foil-btn-inner').textContent = 'Entering…';
  try {
    const res  = await fetch('/bs-api/enter_raffle.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({raffle_id:id})});
    const data = await res.json();
    if (data.success) {
      document.getElementById('modal-footer').innerHTML = '<div style="display:flex;align-items:center;gap:8px;color:var(--green);font-family:var(--mono);font-size:12px"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg>Entered — good luck</div>';
      if (data.new_balance != null) {
        document.querySelectorAll('#blox-balance,#blox-balance-sidebar').forEach(el => el.textContent = parseFloat(data.new_balance).toFixed(2)+' $BLOX');
      }
    } else { alert(data.message||'Failed'); btn.style.pointerEvents=''; btn.querySelector('.bs-foil-btn-inner').textContent='Enter raffle'; }
  } catch(e) { btn.style.pointerEvents=''; btn.querySelector('.bs-foil-btn-inner').textContent='Enter raffle'; }
}

async function endRaffle(id, btn) {
  if (!confirm('End this raffle and pick winners now?')) return;
  btn.textContent = 'Ending…'; btn.disabled = true;
  try {
    const res  = await fetch('/bs-api/end_raffle.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({raffle_id:id})});
    const data = await res.json();
    if (data.success) { document.getElementById('modal-footer').innerHTML='<span style="color:var(--green);font-size:12px">Raffle ended — winners announced.</span>'; setTimeout(()=>location.reload(),2000); }
    else { alert(data.message||'Failed'); btn.textContent='End raffle'; btn.disabled=false; }
  } catch(e) { btn.textContent='End raffle'; btn.disabled=false; }
}
</script>
</body>
</html>
