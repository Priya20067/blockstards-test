<?php
/**
 * bs_avatar_dropdown.php — Shared topbar avatar + dropdown menu
 * Expects: $user, $user_avatar_url, $user_initial, $user_name, $user_balance ($wallets optional array)
 * Optional: $_dd_id (string) — unique ID suffix when including more than once on a page (e.g. topbar + sidebar)
 * Optional: $_dd_align ('right'|'left') — menu alignment, default 'right'
 */
$_dd_id    = $_dd_id    ?? 'bs-avatar-dd';
$_dd_align = $_dd_align ?? 'right';
$_dd_custom_trigger = $_dd_custom_trigger ?? false;
$_wallets_dd = [];
if ($user ?? null) {
    try {
        $_wr = sb('bs_user_wallets')->eq('discord_id', $user['discord_id'])->get();
        foreach ($_wr as $w) $_wallets_dd[] = $w;
    } catch (Exception $e) {}
}
$_primary_wallet = $_wallets_dd[0] ?? null;
?>
<style>
.bs-avatar-dd-wrap{position:relative}
.bs-avatar-dd-trigger{display:flex;align-items:center;gap:5px;cursor:pointer;background:none;border:none;padding:0}
.bs-avatar-dd-chevron{transition:transform .18s}
.bs-avatar-dd-wrap.open .bs-avatar-dd-chevron{transform:rotate(180deg)}
.bs-avatar-dd-menu{position:absolute;width:270px;background:#0c0e18;border:1px solid #232838;border-radius:16px;box-shadow:0 24px 60px -20px rgba(0,0,0,.7);overflow:hidden;display:none;z-index:200;font-family:'GT America',sans-serif}
.bs-avatar-dd-menu.dd-down{top:calc(100% + 10px)}
.bs-avatar-dd-menu.dd-up{bottom:calc(100% + 10px)}
.bs-avatar-dd-menu.dd-right{right:0}
.bs-avatar-dd-menu.dd-left{left:0}
.bs-avatar-dd-wrap.open .bs-avatar-dd-menu{display:block;animation:bsAvatarDdIn .16s ease}
@keyframes bsAvatarDdIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.bs-avatar-dd-header{display:flex;align-items:center;gap:11px;padding:14px;border-bottom:1px solid #161a28;text-decoration:none;color:inherit;transition:background .15s}
.bs-avatar-dd-header:hover{background:rgba(255,255,255,.03)}
.bs-avatar-dd-wallet-row{display:flex;align-items:center;gap:11px;padding:12px 14px;border-bottom:1px solid #161a28}
.bs-avatar-dd-item{display:flex;align-items:center;gap:12px;padding:13px 14px;text-decoration:none;color:#eef1f8;font-size:13.5px;font-weight:500;cursor:pointer;transition:background .15s;border:none;background:none;width:100%;text-align:left}
.bs-avatar-dd-item:hover{background:rgba(255,255,255,.04)}
.bs-avatar-dd-item.danger{color:#f87171}
.bs-avatar-dd-divider{height:1px;background:#161a28;margin:4px 0}
</style>

<div class="bs-avatar-dd-wrap" id="<?= htmlspecialchars($_dd_id) ?>">
  <?php if (!empty($_dd_custom_trigger)): ?>
  <div class="sb-user-card" onclick="document.getElementById('<?= htmlspecialchars($_dd_id) ?>').classList.toggle('open')" style="cursor:pointer">
    <div class="sb-user-avatar">
      <?php if ($user_avatar_url ?? ''): ?><img src="<?= htmlspecialchars($user_avatar_url) ?>" alt="" style="width:100%;height:100%;object-fit:cover"><?php else: ?><?= htmlspecialchars($user_initial ?? '?') ?><?php endif; ?>
    </div>
    <div class="sb-user-text" style="flex:1;min-width:0">
      <div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($user_name ?? 'User') ?></div>
      <?php if ($user_balance ?? null): ?>
      <div style="font-family:'GT America Mono',monospace;font-size:11px;color:#6fe3ff;margin-top:1px;white-space:nowrap"><?= htmlspecialchars(number_format((float)$user_balance, 2)).' $BLOX' ?></div>
      <?php endif; ?>
    </div>
    <svg class="bs-avatar-dd-chevron sb-dd-chevron" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#7a8398" stroke-width="2.2" style="flex-shrink:0;margin-left:4px"><path d="m18 15-6-6-6 6"/></svg>
  </div>
  <?php else: ?>
  <button class="bs-avatar-dd-trigger" onclick="document.getElementById('<?= htmlspecialchars($_dd_id) ?>').classList.toggle('open')">
    <span class="topbar-avatar bs-topbar-avatar" style="cursor:pointer">
      <?php if ($user_avatar_url ?? ''): ?><img src="<?= htmlspecialchars($user_avatar_url) ?>" alt=""><?php else: ?><?= htmlspecialchars($user_initial ?? '?') ?><?php endif; ?>
    </span>
    <svg class="bs-avatar-dd-chevron" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#7a8398" stroke-width="2.2"><path d="m6 9 6 6 6-6"/></svg>
  </button>
  <?php endif; ?>

  <div class="bs-avatar-dd-menu <?= $_dd_align==='left'?'dd-left':'dd-right' ?> <?= ($_dd_id==='bs-avatar-dd-sidebar')?'dd-up':'dd-down' ?>">
    <a href="/profile/" class="bs-avatar-dd-header">
      <span class="topbar-avatar bs-topbar-avatar" style="width:38px;height:38px">
        <?php if ($user_avatar_url ?? ''): ?><img src="<?= htmlspecialchars($user_avatar_url) ?>" alt=""><?php else: ?><?= htmlspecialchars($user_initial ?? '?') ?><?php endif; ?>
      </span>
      <div style="flex:1;min-width:0">
        <div style="font-weight:600;font-size:13.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($user_name ?? 'User') ?></div>
        <div style="font-family:'GT America Mono',monospace;font-size:11px;color:#7a8398"><?= count($_wallets_dd) ?> wallet<?= count($_wallets_dd)===1?'':'s' ?> · <?= htmlspecialchars(number_format((float)($user_balance ?? 0),2)) ?> $BLOX</div>
      </div>
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#5a6478" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
    </a>

    <?php if ($_primary_wallet): ?>
    <div class="bs-avatar-dd-wallet-row">
      <span style="width:8px;height:8px;border-radius:50%;background:<?= $_primary_wallet['chain']==='Solana'?'#b69cff':'#6fe3ff' ?>;box-shadow:0 0 6px currentColor;flex-shrink:0"></span>
      <div style="flex:1;min-width:0">
        <div style="font-family:'GT America Mono',monospace;font-size:12px;color:#eef1f8"><?= htmlspecialchars(substr($_primary_wallet['address'],0,6).'…'.substr($_primary_wallet['address'],-4)) ?></div>
      </div>
      <span style="width:6px;height:6px;border-radius:50%;background:#4ade80;flex-shrink:0"></span>
    </div>
    <?php endif; ?>

    <div data-bs-wallet-trigger class="bs-avatar-dd-item">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6fe3ff" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
      Add Wallet
    </div>
    <a href="/profile/" class="bs-avatar-dd-item">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#b69cff" stroke-width="2"><rect x="2" y="6" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
      Manage Wallets
    </a>

    <div class="bs-avatar-dd-divider"></div>

    <a href="/profile/" class="bs-avatar-dd-item">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#aab2c5" stroke-width="2"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="11" r="4"/></svg>
      Profile
    </a>
    <a href="/profile/" class="bs-avatar-dd-item">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#aab2c5" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
      Settings
    </a>

    <div class="bs-avatar-dd-divider"></div>

    <a href="/bs-auth/logout.php" class="bs-avatar-dd-item danger">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Log Out
    </a>
  </div>
</div>

<script>
document.addEventListener('click', function(e){
  document.querySelectorAll('.bs-avatar-dd-wrap.open').forEach(function(wrap){
    if (!wrap.contains(e.target)) wrap.classList.remove('open');
  });
});
// Close any open avatar dropdown when the page is scrolled
window.addEventListener('scroll', function(){
  document.querySelectorAll('.bs-avatar-dd-wrap.open').forEach(function(wrap){
    wrap.classList.remove('open');
  });
}, { passive: true });
</script>