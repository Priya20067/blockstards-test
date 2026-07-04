<?php
/**
 * bs_mobile_nav.php — Shared Mobile Topbar + Bottom Nav
 * Expects: $user, $blox_bal, $m_active ('home'|'raffles'|'auctions'|'calendar'|'profile')
 */

$_m_av = '';
if ($user ?? null) {
    $_m_av = get_avatar_url($user['discord_id'], $user['avatar'] ?? '');
}
?>
<!-- TOPBAR -->
<div class="m-topbar">
  <a href="/mobile.php" class="m-topbar-logo">Block<span>stards</span></a>
  <?php if ($user ?? null): ?>
  <div class="m-topbar-blox">
    <span class="m-topbar-blox-dot"></span>
    <span class="m-topbar-blox-val"><?= number_format((float)($blox_bal ?? 0), 2) ?> $BLOX</span>
  </div>
  <a href="/mobile-profile.php" class="m-topbar-avatar">
    <?php if ($_m_av): ?><img src="<?= htmlspecialchars($_m_av) ?>" alt=""><?php else: ?><?= strtoupper(substr($user['username'] ?? 'U', 0, 1)) ?><?php endif; ?>
  </a>
  <?php else: ?>
  <a href="/bs-auth/discord.php" class="m-sign-btn">Sign In</a>
  <?php endif; ?>
</div>

<!-- BOTTOM NAV -->
<nav class="m-bnav">
  <a href="/mobile.php" class="m-bni <?= ($m_active ?? '') === 'home' ? 'on' : '' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
    Home
  </a>
  <a href="/mobile-raffles.php" class="m-bni <?= ($m_active ?? '') === 'raffles' ? 'on' : '' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 12V22H4V12"/><path d="M22 7H2v5h20V7z"/><path d="M12 22V7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg>
    Raffles
  </a>
  <a href="/mobile-auctions.php" class="m-bni <?= ($m_active ?? '') === 'auctions' ? 'on' : '' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
    Auctions
  </a>
  <a href="/mobile-calendar.php" class="m-bni <?= ($m_active ?? '') === 'calendar' ? 'on' : '' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
    Calendar
  </a>
  <a href="<?= ($user ?? null) ? '/mobile-profile.php' : '/bs-auth/discord.php' ?>" class="m-bni <?= ($m_active ?? '') === 'profile' ? 'on' : '' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    <?= ($user ?? null) ? 'Profile' : 'Sign In' ?>
  </a>
  <?php if (function_exists('is_staff') && is_staff()): ?>
  <a href="/mobile-admin.php" class="m-bni <?= ($m_active ?? '') === 'admin' ? 'on' : '' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
    Admin
  </a>
  <?php endif; ?>
</nav>