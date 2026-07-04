<?php
/**
 * bs_admin_sidebar.php — Admin Sidebar (Black/Pill design)
 * Expects: $active_admin (dashboard|raffles|auctions|entries|mints|suggestions|staff|permissions|logs)
 */

$sugg_count = 0;
try { $sugg_count = count(sb('bs_mint_date_suggestions')->eq('status','pending')->select('id')->get()); } catch(Exception $e) {}
$req_count = 0;
try { $req_count = count(sb('bs_auction_requests')->eq('status','pending')->select('id')->get()); } catch(Exception $e) {}

$_adm_items = [
  ['k'=>'dashboard',   't'=>'Dashboard',   'g'=>'MANAGE',
    'paths'=>['M3 3h7v9H3z','M14 3h7v5h-7z','M14 12h7v9h-7z','M3 16h7v5H3z'],
    'filled'=>'M3 3h7v9H3z M14 3h7v5h-7z M14 12h7v9h-7z M3 16h7v5H3z',
    'href'=>'/bs-admin/'],
  ['k'=>'raffles',     't'=>'Raffles',
    'paths'=>['M20 12v10H4V12','M2 7h20v5H2z','M12 22V7','M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z','M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z'],
    'filled'=>'M3 8h8v3H3V8zm10 0h8v2a1 1 0 0 1-1 1h-7V8zM4 12h7v9H5a1 1 0 0 1-1-1v-8zm9 0h7v8a1 1 0 0 1-1 1h-6v-9zM11 7H7.3A2.3 2.3 0 1 1 9.5 2.9C11 2.9 11 5.5 11 7zm2 0V5.5c0-1.5 0-2.6 1.5-2.6A2.3 2.3 0 1 1 16.7 7H13z',
    'href'=>'/bs-admin/raffles.php'],
  ['k'=>'auctions',    't'=>'Auctions',
    'paths'=>['M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z'],
    'filled'=>'M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z',
    'href'=>'/bs-admin/auction.php', 'badge'=>$req_count>0?(string)$req_count:''],
  ['k'=>'entries',     't'=>'Entries',
    'paths'=>['M9 11l3 3L22 4','M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11'],
    'filled'=>'M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zm-9 14l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z',
    'href'=>'/bs-admin/entries.php'],
  ['k'=>'mints',       't'=>'Mints',
    'paths'=>['M8 2v4','M16 2v4','M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z','M3 10h18'],
    'filled'=>'M7 2h2v3H7zM15 2h2v3h-2zM3 6h18v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z',
    'href'=>'/bs-admin/mints.php'],
  ['k'=>'suggestions', 't'=>'Suggestions',
    'paths'=>['M9 18h6','M10 22h4','M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.1v.2h6v-.2c0-.8.4-1.6 1-2.1A7 7 0 0 0 12 2z'],
    'filled'=>'M9 21c0 .55.45 1 1 1h4c.55 0 1-.45 1-1v-1H9v1zm3-19C8.14 2 5 5.14 5 9c0 2.38 1.19 4.47 3 5.74V17c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-2.26c1.81-1.27 3-3.36 3-5.74 0-3.86-3.14-7-7-7z',
    'href'=>'/bs-admin/mint-suggestion.php', 'badge'=>$sugg_count>0?(string)$sugg_count:''],
  ['k'=>'staff',       't'=>'Staff',       'g'=>'TEAM',
    'paths'=>['M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2','M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z','M23 21v-2a4 4 0 0 0-3-3.87','M16 3.13a4 4 0 0 1 0 7.75'],
    'filled'=>'M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z',
    'href'=>'/bs-admin/staff.php'],
  ['k'=>'permissions', 't'=>'Permissions',
    'paths'=>['M5 11h14a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-9a1 1 0 0 1 1-1z','M7 11V7a5 5 0 0 1 10 0v4'],
    'filled'=>'M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM9 6c0-1.66 1.34-3 3-3s3 1.34 3 3v2H9V6zm3 11c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z',
    'href'=>'/bs-admin/permissions.php'],
  ['k'=>'logs',        't'=>'Logs',
    'paths'=>['M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z','M14 2v6h6','M16 13H8','M16 17H8','M10 9H8'],
    'filled'=>'M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z',
    'href'=>'/bs-admin/logs.php'],
];

function _adm_svg_grad(string $id): string {
  return '<defs><linearGradient id="'.htmlspecialchars($id).'" x1="3" y1="3" x2="21" y2="21" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#22d3ee"/><stop offset="1" stop-color="#c4a8ff"/></linearGradient></defs>';
}

function _adm_svg_icon(array $item, bool $on): string {
  $gid = 'ang-'.$item['k'];
  $grad = _adm_svg_grad($gid);
  if ($on && !empty($item['filled'])) {
    return '<svg width="24" height="24" viewBox="0 0 24 24" fill="url(#'.$gid.')" stroke="none" style="flex-shrink:0">'.$grad.'<path d="'.htmlspecialchars($item['filled']).'"/></svg>';
  }
  $p = $grad;
  foreach ($item['paths'] as $d) $p .= '<path d="'.htmlspecialchars($d).'"/>';
  return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#'.$gid.')" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0">'.$p.'</svg>';
}
?>
<link rel="stylesheet" href="/bs_fonts.css?v=1783164697">
<style>
.bs-nav-link, .bs-nav-link:focus, .bs-nav-link:focus-visible, .bs-nav-link svg, .bs-nav-link svg:focus { outline: none !important; box-shadow: none !important; }
</style>
<aside style="width:280px;flex-shrink:0;background:#06070d;border-right:none;display:flex;flex-direction:column;position:sticky;top:0;height:100vh;font-family:'GT America',sans-serif;color:#ffffff">
  <div style="padding:26px 24px 22px;display:flex;flex-direction:column;gap:8px">
    <div style="font-weight:700;font-size:17px;letter-spacing:-.01em;line-height:1">Blockstards</div>
    <div style="display:inline-flex;align-self:flex-start;align-items:center;padding:3px 9px;border-radius:20px;background:rgba(196,168,255,.12);border:1px solid rgba(196,168,255,.3)"><span style="font-family:'GT America Mono',monospace;font-size:8px;letter-spacing:.2em;color:#c4a8ff">ADMIN PANEL</span></div>
  </div>
  <nav style="padding:14px 24px;flex:1;display:flex;flex-direction:column;gap:10px;overflow-y:auto">
    <?php
    $_last_grp = null;
    foreach ($_adm_items as $_it):
      $on = ($active_admin ?? '') === $_it['k'];
      if (!empty($_it['g']) && $_it['g'] !== $_last_grp):
        $_last_grp = $_it['g'];
        $_gpt = $_last_grp === 'MANAGE' ? '4px 20px 2px' : '14px 20px 2px';
    ?>
    <div style="font-family:'GT America Mono',monospace;font-size:10px;letter-spacing:.22em;color:#4a5266;padding:<?= $_gpt ?>"><?= htmlspecialchars($_last_grp) ?></div>
    <?php endif; ?>
    <a href="<?= htmlspecialchars($_it['href']) ?>" class="bs-nav-link" style="display:flex;align-items:center;gap:16px;padding:12px 20px;border-radius:9999px;text-decoration:none;cursor:pointer;font-size:17px;color:#ffffff;font-weight:<?= $on?'700':'600' ?>;background:transparent;transition:background .18s" onmouseover="this.style.background='rgba(255,255,255,.05)'" onmouseout="this.style.background='transparent'">
      <?= _adm_svg_icon($_it, $on) ?>
      <span style="flex:1"><?= htmlspecialchars($_it['t']) ?></span>
      <?php if (!empty($_it['badge'])): ?>
      <span style="font-family:'GT America Mono',monospace;font-size:10px;padding:2px 8px;border-radius:10px;background:rgba(196,168,255,.16);color:#c4a8ff;border:1px solid rgba(196,168,255,.3)"><?= htmlspecialchars($_it['badge']) ?></span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </nav>
  <div style="padding:16px">
    <a href="/" style="display:flex;align-items:center;gap:14px;padding:12px 20px;border-radius:9999px;text-decoration:none;color:#aab2c5;font-size:15px;font-weight:600;transition:.16s" onmouseover="this.style.background='rgba(255,255,255,.05)';this.style.color='#ffffff'" onmouseout="this.style.background='transparent';this.style.color='#aab2c5'">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>Back to Site
    </a>
  </div>
</aside>