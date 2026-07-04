<?php
/**
 * bs_sidebar.php — Public Sidebar (OpenSea-style collapsed/hover-expand)
 * Usage: require_once __DIR__.'/../includes/bs_sidebar.php';
 * Expects: $user, $user_name, $user_initial, $user_avatar_url, $user_balance, $active_page
 * $active_page values: home | raffles | auctions | request | calendar | wins | profile | admin
 */

$_nav_items = [
  ['k'=>'home',     't'=>'Home',            'href'=>'/', 'active_use_paths'=>true,
    'paths'=>['M3.5 10.2L12 2.8l8.5 7.4V19.5a1.5 1.5 0 0 1-1.5 1.5h-4.2v-6.3a2.8 2.8 0 0 0-5.6 0V21H5a1.5 1.5 0 0 1-1.5-1.5z'],
    'filled'=>'M3.5 10.2L12 2.8l8.5 7.4V19.5a1.5 1.5 0 0 1-1.5 1.5h-4.2v-6.3a2.8 2.8 0 0 0-5.6 0V21H5a1.5 1.5 0 0 1-1.5-1.5z'],
  ['k'=>'raffles',  't'=>'Raffles',         'href'=>'/raffles/', 'active_use_paths'=>true,
    'paths'=>['M3.5 8h17v2.4a1 1 0 0 1-1 1H4.5a1 1 0 0 1-1-1z','M5 11.4v8.8a.9.9 0 0 0 .9.9h12.2a.9.9 0 0 0 .9-.9v-8.8','M10.5 8v13','M13.5 8v13','M12 8c-1-1.9-2.2-3.1-3.7-3.1a1.75 1.75 0 0 0 0 3.5c1.5 0 2.7-.4 3.7-.4z','M12 8c1-1.9 2.2-3.1 3.7-3.1a1.75 1.75 0 0 1 0 3.5c-1.5 0-2.7-.4-3.7-.4z'],
    'filled'=>'M3.5 8h17v2.4a1 1 0 0 1-1 1H4.5a1 1 0 0 1-1-1zM5 11.4h14v8.8a.9.9 0 0 1-.9.9H5.9a.9.9 0 0 1-.9-.9zM12 8c-1-1.9-2.2-3.1-3.7-3.1a1.75 1.75 0 0 0 0 3.5c1.5 0 2.7-.4 3.7-.4zM12 8c1-1.9 2.2-3.1 3.7-3.1a1.75 1.75 0 0 1 0 3.5c-1.5 0-2.7-.4-3.7-.4zM11.3 8.2h1.4v13h-1.4z'],
  ['k'=>'auctions', 't'=>'Auctions',        'href'=>'/auctions/', 'active_use_paths'=>true,
    'paths'=>['M4 9l1.6-3.7A2 2 0 0 1 7.4 4h9.2a2 2 0 0 1 1.8 1.3L20 9','M4 9c1 0 1 1.3 2 1.3S7 9 8 9s1 1.3 2 1.3S11 9 12 9s1 1.3 2 1.3S15 9 16 9s1 1.3 2 1.3S19 9 20 9','M5 10.3V20a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9.7','M10 21v-4a2 2 0 0 1 4 0v4'],
    'filled'=>'M4 9l1.6-3.7A2 2 0 0 1 7.4 4h9.2a2 2 0 0 1 1.8 1.3L20 9zM5 10.6h14V20a1 1 0 0 1-1 1h-4v-4a2 2 0 0 0-4 0v4H6a1 1 0 0 1-1-1z'],
  ['k'=>'request',  't'=>'Request Auction', 'href'=>'/auction-form.php',
    'paths'=>['M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0z','M12 8v8','M8 12h8'],
    'filled'=>'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 11h-4v4h-2v-4H7v-2h4V7h2v4h4v2z'],
  ['k'=>'calendar', 't'=>'Mint Calendar',   'href'=>'/calendar/', 'active_use_paths'=>true,
    'paths'=>['M6.6 3.4h1.6a.8.8 0 0 1 .8.8v1.8a.8.8 0 0 1-.8.8H6.6a.8.8 0 0 1-.8-.8V4.2a.8.8 0 0 1 .8-.8z','M15.4 3.4h1.6a.8.8 0 0 1 .8.8v1.8a.8.8 0 0 1-.8.8h-1.6a.8.8 0 0 1-.8-.8V4.2a.8.8 0 0 1 .8-.8z','M4.5 5.4h15a1.3 1.3 0 0 1 1.3 1.3V19a1.3 1.3 0 0 1-1.3 1.3H4.5A1.3 1.3 0 0 1 3.2 19V6.7A1.3 1.3 0 0 1 4.5 5.4z','M3.2 9.6h17.6','M6.4 11.9h2.4v2.4H6.4z','M10.8 11.9h2.4v2.4h-2.4z','M15.2 11.9h2.4v2.4h-2.4z','M6.4 15.7h2.4v2.4H6.4z','M10.8 15.7h2.4v2.4h-2.4z','M15.2 15.7h2.4v2.4h-2.4z'],
    'filled'=>'M6.4 3.2h2.4v3.6H6.4zM15.2 3.2h2.4v3.6h-2.4zM4.5 5.4h15a1.3 1.3 0 0 1 1.3 1.3V19a1.3 1.3 0 0 1-1.3 1.3H4.5A1.3 1.3 0 0 1 3.2 19V6.7A1.3 1.3 0 0 1 4.5 5.4zM6.4 11.9h2.4v2.4H6.4zM10.8 11.9h2.4v2.4h-2.4zM15.2 11.9h2.4v2.4h-2.4zM6.4 15.7h2.4v2.4H6.4zM10.8 15.7h2.4v2.4h-2.4zM15.2 15.7h2.4v2.4h-2.4z'],
  ['k'=>'wins',     't'=>'My Wins',         'href'=>'/wins/',
    'paths'=>['M6 9H4.5a2.5 2.5 0 0 1 0-5H6','M18 9h1.5a2.5 2.5 0 0 0 0-5H18','M4 22h16','M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22','M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22','M18 2H6v7a6 6 0 0 0 12 0V2z'],
    'filled'=>'M19 5h-2V3H7v2H5c-1.1 0-2 .9-2 2v1c0 2.55 1.92 4.63 4.39 4.94.63 1.5 1.98 2.63 3.61 2.96V19H7v2h10v-2h-4v-3.1c1.63-.33 2.98-1.46 3.61-2.96C19.08 12.63 21 10.55 21 8V7c0-1.1-.9-2-2-2zM5 8V7h2v3.82C5.84 10.4 5 9.3 5 8zm14 0c0 1.3-.84 2.4-2 2.82V7h2v1z'],
  ['k'=>'profile',  't'=>'My Profile',      'href'=>'/profile/',
    'paths'=>['M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2','M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z'],
    'filled'=>'M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'],
];
if (function_exists('is_staff') && is_staff()) {
  $_nav_items[] = ['k'=>'admin', 't'=>'Admin Panel', 'href'=>'/bs-admin/',
    'paths'=>['M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z'],
    'filled'=>'M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z'];
}

function _bs_svg_grad(string $id): string {
  return '<defs><linearGradient id="'.htmlspecialchars($id).'" x1="3" y1="3" x2="21" y2="21" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#ffffff"/><stop offset="1" stop-color="#ffffff"/></linearGradient></defs>';
}

function _bs_svg_icon(array $item, bool $on): string {
  $gid  = 'ng-'.$item['k'];
  $grad = _bs_svg_grad($gid);
  if ($on && !empty($item['active_use_paths'])) {
    // Active (shape-preserving): same outline paths, soft fill + stroke on top so inner details stay visible
    $p = $grad;
    $fillOpacity = in_array($item['k'], ['raffles', 'auctions', 'calendar'], true) ? '.28' : '1';

    foreach ($item['paths'] as $d) {
      $p .= '<path d="'.htmlspecialchars($d).'"/>';
    }

    return '<svg width="34" height="34" viewBox="0 0 24 24" fill="url(#'.$gid.')" fill-opacity="'.$fillOpacity.'" stroke="url(#'.$gid.')" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0">'.$p.'</svg>';
  }
  if ($on && !empty($item['filled'])) {
    // Active: filled gradient icon
    $paths = '';
    foreach (explode('M', $item['filled']) as $seg) {
      if ($seg === '') continue;
      $paths .= '<path d="M'.htmlspecialchars($seg).'"/>';
    }
    return '<svg width="34" height="34" viewBox="0 0 24 24" fill="url(#'.$gid.')" stroke="none" fill-rule="evenodd" style="flex-shrink:0">'.$grad.'<path d="'.htmlspecialchars($item['filled']).'"/></svg>';
  }
  // Inactive: muted monochrome outline
  $p = '';
  foreach ($item['paths'] as $d) $p .= '<path d="'.htmlspecialchars($d).'"/>';
  return '<svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#9aa2ad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0">'.$p.'</svg>';
}
?>
<link rel="stylesheet" href="/bs_fonts.css?v=1783164697">
<style>
.bs-nav-link, .bs-nav-link:focus, .bs-nav-link:focus-visible, .bs-nav-link svg, .bs-nav-link svg:focus { outline: none !important; box-shadow: none !important; }
.sb-icon-img{width:26px;height:26px;object-fit:contain;flex-shrink:0;display:block;filter:drop-shadow(0 0 0px transparent);transition:filter .18s}
.bs-nav-link:hover .sb-icon-img{filter:drop-shadow(0 0 4px rgba(255,255,255,.3))}

:root{ --bs-sb-collapsed: 84px; --bs-sb-expanded: 264px; }

#bs-aside{
  width:var(--bs-sb-collapsed);
  flex-shrink:0;
  background:#06070d;
  border-right:none;
  display:flex;
  flex-direction:column;
  position:sticky;
  top:0;
  height:100vh;
  font-family:'GT America',sans-serif;
  color:#ffffff;
  overflow:hidden;
  transition:width .22s cubic-bezier(.2,.9,.3,1);
  z-index:50;
}
#bs-aside:hover{ width:var(--bs-sb-expanded); }

@media (max-width:900px){
  :root{ --bs-sb-collapsed: 60px; }
  #bs-aside:hover{ width:var(--bs-sb-collapsed); } /* no hover-expand on touch/narrow — nothing to hover with a finger */
  #bs-aside .sb-logo-icon,#bs-aside .sb-logo-gif{ margin-left:11px; }
  #bs-aside .bs-nav-link{ margin:0 6px; padding:0 10px; }
}

#bs-aside .sb-logo-row{
  padding:24px 0 20px;
  display:flex;
  align-items:center;
  gap:13px;
  white-space:nowrap;
  position:relative;
  min-height:38px;
}
#bs-aside .sb-logo-icon{
  width:38px;height:38px;border-radius:var(--radius-btn);flex-shrink:0;
  background:#ffffff;
  display:flex;align-items:center;justify-content:center;
  font-weight:700;font-size:16px;color:#06070d;
  margin-left:23px;
}
#bs-aside .sb-logo-gif{
  width:38px;height:38px;border-radius:var(--radius-btn);flex-shrink:0;
  overflow:hidden;
  margin-left:23px;
  opacity:1;
  transition:opacity .16s ease;
  position:absolute;
}
#bs-aside .sb-logo-gif img{
  width:100%;height:100%;object-fit:cover;display:block;
}
#bs-aside:hover .sb-logo-gif{ opacity:0; pointer-events:none; }

#bs-aside .sb-logo-text{
  margin-left:23px;
  opacity:0;
  transform:translateX(-6px);
  transition:opacity .18s ease .04s, transform .18s ease .04s;
}
#bs-aside:hover .sb-logo-text{ opacity:1; transform:translateX(0); }

#bs-aside .sb-nav{
  padding:6px 0;
  flex:1;
  display:flex;
  flex-direction:column;
  gap:4px;
  overflow-y:auto;
  overflow-x:hidden;
}
#bs-aside .sb-nav::-webkit-scrollbar{ width:0; }

#bs-aside .bs-nav-link{
  display:flex;
  align-items:center;
  gap:14px;
  height:54px;
  border-radius:var(--radius-pill);
  text-decoration:none;
  cursor:pointer;
  font-size:17px;
  color:#ffffff;
  white-space:nowrap;
  margin:0 10px;
  padding:0 15px;
  transition:background .16s;
  position:relative;
}
#bs-aside .bs-nav-link:hover{ background:rgba(255,255,255,.06); }
#bs-aside .bs-nav-link.is-active{ font-weight:700; background:#18181b; }
#bs-aside .bs-nav-link:not(.is-active){ font-weight:600; }

#bs-aside .sb-nav-label{
  opacity:0;
  transform:translateX(-6px);
  transition:opacity .16s ease, transform .16s ease;
}
#bs-aside:hover .sb-nav-label{ opacity:1; transform:translateX(0); transition-delay:.05s; }

#bs-aside .sb-bottom{
  padding:14px 18px 18px;
}

#bs-aside .sb-user-card{
  display:flex;align-items:center;gap:0;
  padding:6px;
  background:rgba(255,255,255,.04);
  border:1px solid rgba(255,255,255,.08);
  border-radius:var(--radius-pill);
  cursor:default;
  transition:background .18s, padding .2s, border-radius .2s;
  overflow:hidden;
  white-space:nowrap;
}
#bs-aside:hover .sb-user-card{ padding:8px 12px 8px 8px; border-radius:var(--radius-btn); }
#bs-aside .sb-user-card:hover{ background:rgba(255,255,255,.07); }

#bs-aside .sb-user-avatar{
  width:36px;height:36px;border-radius:50%;overflow:hidden;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
  font-weight:700;font-size:15px;
  background:#ffffff;color:#000;
}
#bs-aside .sb-user-text{
  margin-left:12px;
  opacity:0;
  max-width:0;
  transition:opacity .16s ease, max-width .2s ease;
  overflow:hidden;
}
#bs-aside:hover .sb-user-text{ opacity:1; max-width:160px; transition-delay:.05s; }

#bs-aside .sb-discord-btn{
  display:flex;align-items:center;justify-content:center;gap:0;
  height:48px;border-radius:var(--radius-pill);
  background:#5865f2;color:#fff;
  font-family:'GT America Mono',monospace;font-size:12px;font-weight:500;
  text-decoration:none;transition:background .2s, padding .2s;
  overflow:hidden;white-space:nowrap;
}
#bs-aside .sb-discord-btn:hover{ background:#4752c4; }
#bs-aside .sb-discord-text{
  opacity:0; max-width:0;
  transition:opacity .16s ease, max-width .2s ease;
  overflow:hidden;
}
#bs-aside:hover .sb-discord-text{ opacity:1; max-width:160px; margin-left:8px; transition-delay:.05s; }
</style>

<aside id="bs-aside">
  <div class="sb-logo-row">
    <div class="sb-logo-gif">
      <img src="/assets/blockstards.gif?v=2" alt="Blockstards">
    </div>
    <div class="sb-logo-text">
      <div style="font-weight:700;font-size:17px;letter-spacing:-.01em;line-height:1">Blockstards</div>
      <div style="font-family:'GT America Mono',monospace;font-size:9px;letter-spacing:.3em;color:#8b93a0;margin-top:5px">WEB3 CLUB</div>
    </div>
  </div>

  <nav class="sb-nav">
    <?php foreach ($_nav_items as $_item):
      $on = ($active_page ?? '') === $_item['k'];
    ?>
    <a href="<?= htmlspecialchars($_item['href']) ?>" class="bs-nav-link<?= $on ? ' is-active' : '' ?>">
      <?= _bs_svg_icon($_item, $on) ?>
      <span class="sb-nav-label"><?= htmlspecialchars($_item['t']) ?></span>
    </a>
    <?php endforeach; ?>
  </nav>

  <div class="sb-bottom">
    <?php if ($user ?? null): ?>
    <?php
      $_dd_id = 'bs-avatar-dd-sidebar';
      $_dd_align = 'left';
      $_dd_custom_trigger = true;
      require __DIR__.'/../bs_avatar_dropdown.php';
    ?>
    <?php else: ?>
    <a href="/bs-auth/discord.php" class="sb-discord-btn">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0"><path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057.101 18.08.114 18.102.132 18.115a19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03z"/></svg>
      <span class="sb-discord-text">Sign in with Discord</span>
    </a>
    <?php endif; ?>
  </div>
</aside>

<style>
#bs-aside.collapsing{ width:var(--bs-sb-collapsed) !important; }
#bs-aside.collapsing .sb-nav-label,
#bs-aside.collapsing .sb-logo-text,
#bs-aside.collapsing .sb-user-text,
#bs-aside.collapsing .sb-discord-text{ opacity:0 !important; }
</style>
<script src="/wallet/bs_wallet.js" defer></script>
<script>
document.querySelectorAll('.bs-nav-link').forEach(function(link){
  link.addEventListener('click', function(){
    var aside = document.getElementById('bs-aside');
    if(aside){ aside.classList.add('collapsing'); }
  });
});
</script>