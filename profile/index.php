<?php
require_once __DIR__.'/../config.php';

$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (preg_match('/Mobile|Android|iPhone|iPad|webOS|BlackBerry/i', $ua)) {
    header('Location: /mobile-profile.php'); exit;
}

$user = get_user();
if (!$user) { header('Location: /bs-auth/discord.php?redirect=/profile/'); exit; }

$active_page = 'profile';
$uid         = $user['discord_id'];
$tab         = $_GET['tab'] ?? 'items';

// ── POST: save wallets ─────────────────────────────────────────────────────
$msg = ''; $msg_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_wallets') {
    $eth = trim($_POST['eth_wallet'] ?? '');
    $sol = trim($_POST['sol_wallet'] ?? '');
    if ($eth) sb('bs_user_wallets')->upsert(['discord_id'=>$uid,'chain'=>'Ethereum','address'=>$eth,'added_via'=>'website'],'discord_id,chain');
    if ($sol) sb('bs_user_wallets')->upsert(['discord_id'=>$uid,'chain'=>'Solana','address'=>$sol,'added_via'=>'website'],'discord_id,chain');
    $msg = '✓ Wallets saved!'; $msg_type = 'green';
}

// ── Fetch data ─────────────────────────────────────────────────────────────
$wallets = [];
try { foreach (sb('bs_user_wallets')->eq('discord_id',$uid)->get() as $w) $wallets[$w['chain']] = $w['address']; } catch(Exception $e) {}

$twitter_handle = '';
try { $bs_row = sb('bs_users')->eq('discord_id',$uid)->select('twitter_handle')->first(); $twitter_handle = $bs_row['twitter_handle'] ?? ''; } catch(Exception $e) {}

$user_balance = 0;
try { $user_balance = get_balance($uid); } catch(Exception $e) {}

$wins = [];
try { $wins = sb('bs_wins')->eq('discord_id',$uid)->order('won_at',false)->get(); } catch(Exception $e) {}

$entries_count = 0;
try { $entries_count = count(sb('bs_raffle_entries')->eq('discord_id',$uid)->select('raffle_id')->get()); } catch(Exception $e) {}

$user_name       = htmlspecialchars($user['username'] ?? 'User');
$user_initial    = strtoupper(substr($user['username'] ?? 'U', 0, 1));
$user_avatar_url = get_avatar_url($uid, $user['avatar'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $user_name ?> · Blockstards</title>
<link rel="stylesheet" href="/bs_fonts.css?v=1783164697">
<link rel="stylesheet" href="/bs_design.css?v=1783164697">
<style>
  @property --bgAngle{syntax:'<angle>';inherits:false;initial-value:0deg}
  @keyframes borderTravel{to{--bgAngle:360deg}}
  @keyframes livePulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.25;transform:scale(.7)}}
  @keyframes shimmer{0%{background-position:-200% 0}100%{background-position:200% 0}}

  /* ── Hero Banner ── */
  .prof-banner{
    position:relative;height:220px;border-radius:20px;overflow:hidden;margin-bottom:0;
    background:radial-gradient(ellipse 120% 100% at 50% 50%,#1a0a2e,#06070d),
               conic-gradient(from 180deg,#6fe3ff22,#b69cff22,#e4c59022,#6fe3ff22);
    border:1px solid #161a28;
  }
  .prof-banner-grid{
    position:absolute;inset:0;
    background-image:linear-gradient(rgba(111,227,255,.04) 1px,transparent 1px),
                     linear-gradient(90deg,rgba(111,227,255,.04) 1px,transparent 1px);
    background-size:40px 40px;
  }
  .prof-banner-glow{
    position:absolute;inset:0;
    background:radial-gradient(ellipse 70% 80% at 30% 40%,rgba(111,227,255,.15),transparent 60%),
               radial-gradient(ellipse 50% 60% at 70% 60%,rgba(182,156,255,.12),transparent 55%);
  }
  .prof-banner-foil{
    position:absolute;inset:0;
    background:conic-gradient(from var(--bgAngle),transparent 300deg,rgba(111,227,255,.18) 330deg,rgba(182,156,255,.25) 345deg,transparent 360deg);
    animation:borderTravel 8s linear infinite;
    pointer-events:none;
  }

  /* ── Profile header ── */
  .prof-header{position:relative;padding:0 32px 24px;display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-top:-52px}
  .prof-avatar-wrap{position:relative;width:108px;height:108px;flex-shrink:0}
  .prof-avatar-glow{position:absolute;inset:-4px;border-radius:50%;background:linear-gradient(140deg,#6fe3ff,#b69cff);filter:blur(3px);opacity:.8}
  .prof-avatar-ring{position:absolute;inset:0;border-radius:50%;padding:2.5px;background:conic-gradient(from var(--bgAngle),#6fe3ff,#b69cff 30%,#e4c590 50%,#b69cff 70%,#6fe3ff);animation:borderTravel 5s linear infinite}
  .prof-avatar-img{width:100%;height:100%;border-radius:50%;object-fit:cover;display:block;border:3px solid #06070d;position:relative;z-index:1}
  .prof-avatar-inner{position:absolute;inset:2.5px;border-radius:50%;background:linear-gradient(140deg,#6fe3ff,#b69cff);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:36px;color:#06070d;z-index:1}
  .prof-name{font-weight:700;font-size:24px;letter-spacing:-.02em;margin-bottom:6px}
  .prof-id{font-family:'GT America Mono',monospace;font-size:11px;color:#5a6478;letter-spacing:.08em}
  .prof-meta-chips{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
  .prof-chip{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:20px;font-family:'GT America Mono',monospace;font-size:10px;letter-spacing:.1em;border:1px solid #232838;color:#7a8398;background:rgba(255,255,255,.03)}
  .prof-chip.green{border-color:rgba(74,222,128,.3);color:#4ade80;background:rgba(74,222,128,.08)}
  .prof-chip.cyan{border-color:rgba(111,227,255,.3);color:#6fe3ff;background:rgba(111,227,255,.08)}
  .prof-chip.gold{border-color:rgba(228,197,144,.3);color:#e4c590;background:rgba(228,197,144,.08)}

  /* ── Stats bar ── */
  .prof-stats-bar{display:flex;gap:2px;margin-bottom:24px;border:1px solid #161a28;border-radius:16px;overflow:hidden;background:rgba(255,255,255,.015)}
  .prof-stat-item{flex:1;padding:16px 20px;text-align:center;border-right:1px solid #161a28;transition:.2s}
  .prof-stat-item:last-child{border-right:none}
  .prof-stat-item:hover{background:rgba(255,255,255,.03)}
  .prof-stat-val{font-weight:700;font-size:22px;letter-spacing:-.02em;line-height:1}
  .prof-stat-label{font-family:'GT America Mono',monospace;font-size:9px;letter-spacing:.14em;color:#5a6478;margin-top:5px}

  /* ── Tabs ── */
  .prof-tabs{display:flex;gap:0;border-bottom:1px solid #161a28;margin-bottom:24px}
  .prof-tab{display:flex;align-items:center;gap:7px;padding:13px 22px;font-family:'GT America Mono',monospace;font-size:12px;letter-spacing:.06em;color:#5a6478;cursor:pointer;text-decoration:none;border-bottom:2px solid transparent;margin-bottom:-1px;transition:.18s;white-space:nowrap}
  .prof-tab:hover{color:#aab2c5}
  .prof-tab.on{color:#eef1f8;border-bottom-color:#6fe3ff}

  /* ── Win cards ── */
  .wins-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px}
  .win-card{border-radius:16px;padding:1.5px;animation:borderTravel 7s linear infinite;transition:transform .2s}
  .win-card:hover{transform:translateY(-4px)}
  .win-card-inner{border-radius:14.5px;overflow:hidden;background:#0a0d18}
  .win-card-img{height:130px;overflow:hidden;position:relative}
  .win-card-img img{width:100%;height:100%;object-fit:cover}
  .win-card-body{padding:12px}

  /* ── Settings ── */
  .settings-section{border:1px solid #161a28;border-radius:18px;overflow:hidden;margin-bottom:16px}
  .settings-header{padding:16px 20px;border-bottom:1px solid #12151f;display:flex;align-items:center;gap:10px;font-family:'GT America Mono',monospace;font-size:11px;letter-spacing:.12em;color:#aab2c5;background:rgba(255,255,255,.02)}
  .settings-body{padding:20px}
  .conn-row{display:flex;align-items:center;gap:14px;padding:13px 0;border-bottom:1px solid #0d1018}
  .conn-row:last-child{border-bottom:none}
</style>
</head>
<body>
<div class="bs-layout">
<?php require_once __DIR__.'/../includes/bs_sidebar.php'; ?>
<main class="bs-main" style="padding-top:0;max-width:1200px">

  <!-- ═══ BANNER ═══ -->
  <div class="prof-banner">
    <div class="prof-banner-grid"></div>
    <div class="prof-banner-glow"></div>
    <div class="prof-banner-foil"></div>
  </div>

  <!-- ═══ PROFILE HEADER ═══ -->
  <div class="prof-header">
    <div style="display:flex;align-items:flex-end;gap:18px;flex-wrap:wrap">
      <div class="prof-avatar-wrap">
        <div class="prof-avatar-glow"></div>
        <div class="prof-avatar-ring">
          <?php if ($user_avatar_url): ?>
          <img src="<?= htmlspecialchars($user_avatar_url) ?>" class="prof-avatar-img" alt="">
          <?php else: ?>
          <div class="prof-avatar-inner"><?= $user_initial ?></div>
          <?php endif; ?>
        </div>
      </div>
      <div style="padding-bottom:8px">
        <div class="prof-name"><?= $user_name ?></div>
        <div class="prof-id"><?= htmlspecialchars(substr($uid,0,8).'…'.substr($uid,-4)) ?></div>
        <div class="prof-meta-chips">
          <?php if ($twitter_handle): ?>
          <span class="prof-chip cyan">
            <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.543l7.737-8.851L1.215 2.25H8.04l4.265 5.638 5.939-5.638Zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
            @<?= htmlspecialchars(ltrim($twitter_handle,'@')) ?>
          </span>
          <?php endif; ?>
          <span class="prof-chip green">
            <span style="width:5px;height:5px;border-radius:50%;background:#4ade80;animation:livePulse 1.6s infinite"></span>
            DISCORD CONNECTED
          </span>
          <?php if (!empty($wallets['Ethereum'])): ?>
          <span class="prof-chip gold">
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 12l10 10 10-10z"/></svg>
            ETH LINKED
          </span>
          <?php endif; ?>
          <?php if (!empty($wallets['Solana'])): ?>
          <span class="prof-chip" style="border-color:rgba(182,156,255,.3);color:#b69cff;background:rgba(182,156,255,.08)">
            <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>
            SOL LINKED
          </span>
          <?php endif; ?>
          <?php if (is_staff()): ?>
          <span class="prof-chip" style="border-color:rgba(228,197,144,.3);color:#e4c590;background:rgba(228,197,144,.08)">
            <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.3L12 17l-6.2 4.2 2.4-7.3L2 9.4h7.6z"/></svg>
            STAFF
          </span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div style="display:flex;gap:10px;padding-bottom:8px">
      <a href="/bs-auth/logout.php" style="display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border-radius:11px;background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.25);color:#f87171;font-family:'GT America Mono',monospace;font-size:11px;text-decoration:none;transition:.18s">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Sign Out
      </a>
    </div>
  </div>

  <!-- ═══ STATS BAR ═══ -->
  <div class="prof-stats-bar" style="margin:0 0 24px">
    <div class="prof-stat-item">
      <div class="prof-stat-val" style="color:#6fe3ff"><?= number_format($user_balance,2) ?></div>
      <div class="prof-stat-label">$BLOX BALANCE</div>
    </div>
    <div class="prof-stat-item">
      <div class="prof-stat-val" style="color:#e4c590"><?= count($wins) ?></div>
      <div class="prof-stat-label">TOTAL WINS</div>
    </div>
    <div class="prof-stat-item">
      <div class="prof-stat-val"><?= $entries_count ?></div>
      <div class="prof-stat-label">RAFFLE ENTRIES</div>
    </div>
    <div class="prof-stat-item">
      <div class="prof-stat-val" style="color:#b69cff"><?= count($wallets) ?></div>
      <div class="prof-stat-label">WALLETS LINKED</div>
    </div>
  </div>

  <!-- ═══ TABS ═══ -->
  <div class="prof-tabs">
    <a href="?tab=items" class="prof-tab <?= $tab==='items'?'on':'' ?>">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
      Wins
      <?php if (count($wins)): ?><span style="background:rgba(228,197,144,.14);color:#e4c590;border:1px solid rgba(228,197,144,.25);padding:1px 7px;border-radius:10px;font-size:9px"><?= count($wins) ?></span><?php endif; ?>
    </a>
    <a href="?tab=activity" class="prof-tab <?= $tab==='activity'?'on':'' ?>">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      Activity
    </a>
    <a href="?tab=settings" class="prof-tab <?= $tab==='settings'?'on':'' ?>">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
      Settings
    </a>
  </div>

  <?php if ($msg): ?>
  <div class="bs-notice bs-notice-<?= $msg_type ?>" style="margin-bottom:20px">
    <span class="bs-notice-text"><?= htmlspecialchars($msg) ?></span>
  </div>
  <?php endif; ?>

  <!-- ═══ TAB: WINS ═══ -->
  <?php if ($tab === 'items'): ?>
  <?php if (empty($wins)): ?>
  <div class="bs-empty" style="margin-top:40px">
    <span class="bs-empty-icon">🏆</span>
    <p>No wins yet — enter some raffles or bid on auctions to get started!</p>
    <div style="display:flex;gap:10px;justify-content:center;margin-top:16px">
      <a href="/raffles/" class="bs-foil-btn" style="display:inline-flex;width:auto;text-decoration:none"><span class="bs-foil-btn-inner" style="padding:10px 22px">Browse Raffles</span></a>
      <a href="/auctions/" style="display:inline-flex;align-items:center;padding:10px 22px;border-radius:11px;background:rgba(255,255,255,.04);border:1px solid #232838;color:#cdd4e2;font-family:'GT America Mono',monospace;font-size:12px;text-decoration:none">View Auctions</a>
    </div>
  </div>
  <?php else: ?>
  <div class="wins-grid">
    <?php
    $win_accents = ['#6fe3ff','#b69cff','#e4c590','#4ade80'];
    foreach ($wins as $i => $w):
      $accent = $win_accents[$i % count($win_accents)];
      $img    = $w['image_url'] ?? '';
      $title  = $w['title'] ?? 'Win';
      $type   = $w['win_type'] ?? 'raffle';
      $reward = $w['reward_type'] ?? 'GTD WL';
      $date   = $w['won_at'] ? date('M j, Y', strtotime($w['won_at'])) : '';
      $eth_w  = '';
      $sol_w  = '';
      try {
        $ww = sb('bs_user_wallets')->eq('discord_id',$uid)->get();
        foreach ($ww as $wrow) {
          if ($wrow['chain']==='Ethereum') $eth_w = $wrow['address'];
          if ($wrow['chain']==='Solana')   $sol_w = $wrow['address'];
        }
      } catch(Exception $ex) {}
    ?>
    <div class="win-card" style="background:conic-gradient(from var(--bgAngle),rgba(255,255,255,.05) 0deg,rgba(255,255,255,.05) 215deg,<?= $accent ?> 305deg,rgba(182,156,255,.5) 335deg,rgba(255,255,255,.05) 360deg)">
      <div class="win-card-inner">
        <div class="win-card-img">
          <?php if ($img): ?><img src="<?= htmlspecialchars($img) ?>" alt=""><?php else: ?>
          <div style="width:100%;height:100%;background:linear-gradient(135deg,#13243a,<?= $accent ?>55,#06070d)"></div>
          <?php endif; ?>
          <div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(6,7,13,.8),transparent 50%)"></div>
          <div style="position:absolute;top:8px;left:8px;display:flex;align-items:center;gap:4px;padding:3px 8px;border-radius:20px;background:rgba(6,7,13,.6);backdrop-filter:blur(6px);border:1px solid <?= $accent ?>55;font-family:'GT America Mono',monospace;font-size:8.5px;color:<?= $accent ?>">
            <?= strtoupper($type) ?>
          </div>
        </div>
        <div class="win-card-body">
          <div style="font-weight:600;font-size:13.5px;margin-bottom:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($title) ?></div>
          <div style="display:flex;align-items:center;justify-content:space-between">
            <span class="bs-badge bs-badge-<?= $type==='raffle'?'violet':'gold' ?>"><?= htmlspecialchars($reward) ?></span>
            <?php if ($date): ?><span style="font-family:'GT America Mono',monospace;font-size:9px;color:#5a6478"><?= $date ?></span><?php endif; ?>
          </div>
          <?php if ($w['mint_url'] ?? ''): ?>
          <a href="<?= htmlspecialchars($w['mint_url']) ?>" target="_blank" style="display:inline-flex;align-items:center;gap:5px;margin-top:8px;font-family:'GT America Mono',monospace;font-size:10px;color:#e4c590;text-decoration:none">Mint ↗</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- ═══ TAB: ACTIVITY ═══ -->
  <?php elseif ($tab === 'activity'): ?>
  <?php
  $activity = [];
  try {
    $my_raffles = sb('bs_raffle_entries')->eq('discord_id',$uid)->order('created_at',false)->limit(20)->get();
    foreach ($my_raffles as $re) {
      try {
        $raffle_info = sb('bs_raffles')->eq('id',$re['raffle_id'])->select('title,image_url,status')->first();
        $activity[] = ['type'=>'raffle_entry','label'=>'Entered raffle','name'=>$raffle_info['title']??'Raffle','status'=>$raffle_info['status']??'','ts'=>$re['created_at']??''];
      } catch(Exception $ex) {}
    }
  } catch(Exception $e) {}
  foreach ($wins as $w) {
    $activity[] = ['type'=>'win','label'=>ucfirst($w['win_type']??'raffle').' win','name'=>$w['title']??'Win','status'=>'won','ts'=>$w['won_at']??''];
  }
  usort($activity, fn($a,$b) => strtotime($b['ts']??'0') - strtotime($a['ts']??'0'));
  ?>
  <?php if (empty($activity)): ?>
  <div class="bs-empty" style="margin-top:40px">
    <span class="bs-empty-icon">📊</span>
    <p>No activity yet — start entering raffles and bidding on auctions!</p>
  </div>
  <?php else: ?>
  <div style="border:1px solid #161a28;border-radius:16px;overflow:hidden">
    <?php foreach ($activity as $i => $act):
      $icon_color = $act['type']==='win' ? '#e4c590' : '#6fe3ff';
      $ts = $act['ts'] ? date('M j, g:ia', strtotime($act['ts'])) : '';
    ?>
    <div style="display:flex;align-items:center;gap:14px;padding:14px 18px;border-bottom:1px solid #0d1018;<?= $i===0?'':''; ?>background:<?= $act['type']==='win'?'rgba(228,197,144,.03)':'rgba(255,255,255,.01)' ?>;transition:.15s" onmouseover="this.style.background='rgba(255,255,255,.03)'" onmouseout="this.style.background='<?= $act['type']==='win'?'rgba(228,197,144,.03)':'rgba(255,255,255,.01)' ?>'">
      <div style="width:32px;height:32px;border-radius:9px;background:rgba(<?= $act['type']==='win'?'228,197,144':'111,227,255' ?>,.1);display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <?php if ($act['type']==='win'): ?>
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#e4c590" stroke-width="2"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6M18 9h1.5a2.5 2.5 0 0 0 0-5H18M18 2H6v7a6 6 0 0 0 12 0V2z"/><path d="M4 22h16"/></svg>
        <?php else: ?>
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6fe3ff" stroke-width="2"><path d="M20 12V22H4V12"/><path d="M22 7H2v5h20V7z"/></svg>
        <?php endif; ?>
      </div>
      <div style="flex:1;min-width:0">
        <div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($act['name']) ?></div>
        <div style="font-family:'GT America Mono',monospace;font-size:9.5px;color:#5a6478;margin-top:2px"><?= htmlspecialchars($act['label']) ?></div>
      </div>
      <?php if ($act['type']==='win'): ?>
      <span class="bs-badge bs-badge-gold">WON</span>
      <?php elseif ($act['status']==='ended'): ?>
      <span class="bs-badge bs-badge-gray">ENDED</span>
      <?php else: ?>
      <span class="bs-badge bs-badge-cyan">LIVE</span>
      <?php endif; ?>
      <?php if ($ts): ?><span style="font-family:'GT America Mono',monospace;font-size:10px;color:#5a6478;flex-shrink:0"><?= $ts ?></span><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- ═══ TAB: SETTINGS ═══ -->
  <?php elseif ($tab === 'settings'): ?>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start">

    <!-- Wallets -->
    <div class="settings-section">
      <div class="settings-header">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6fe3ff" stroke-width="1.8"><rect x="2" y="6" width="20" height="14" rx="2"/><path d="M2 10h20"/><circle cx="17" cy="14" r="1.2" fill="#6fe3ff" stroke="none"/></svg>
        WALLETS
      </div>
      <div class="settings-body">
        <div data-bs-wallet-trigger class="bs-foil-btn" style="margin-bottom:18px;cursor:pointer">
          <span class="bs-foil-btn-inner">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6fe3ff" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
            Add Wallet
          </span>
        </div>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px">
          <div style="flex:1;height:1px;background:#161a28"></div>
          <span style="font-family:'GT America Mono',monospace;font-size:9px;color:#4a5266">OR PASTE</span>
          <div style="flex:1;height:1px;background:#161a28"></div>
        </div>
        <form method="post">
          <input type="hidden" name="action" value="save_wallets">
          <div class="bs-field">
            <label>ETHEREUM</label>
            <input class="bs-input bs-input-mono" type="text" name="eth_wallet" value="<?= htmlspecialchars($wallets['Ethereum'] ?? '') ?>" placeholder="0x…">
          </div>
          <div class="bs-field">
            <label>SOLANA</label>
            <input class="bs-input bs-input-mono" type="text" name="sol_wallet" value="<?= htmlspecialchars($wallets['Solana'] ?? '') ?>" placeholder="Solana address…">
          </div>
          <button type="submit" class="bs-foil-btn" style="border:none;cursor:pointer">
            <span class="bs-foil-btn-inner">Save Wallets</span>
          </button>
        </form>
      </div>
    </div>

    <!-- Connected accounts -->
    <div style="display:flex;flex-direction:column;gap:16px">
      <div class="settings-section">
        <div class="settings-header">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#b69cff" stroke-width="1.8"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
          CONNECTED ACCOUNTS
        </div>
        <div style="padding:8px 20px">
          <?php
          $connections = [
            ['label'=>'Discord',    'icon'=>'discord', 'val'=>$user_name,'linked'=>true],
            ['label'=>'Twitter / X','icon'=>'twitter', 'val'=>$twitter_handle?'@'.ltrim($twitter_handle,'@'):null,'linked'=>(bool)$twitter_handle],
            ['label'=>'ETH Wallet', 'icon'=>'eth',     'val'=>$wallets['Ethereum']??null,'linked'=>isset($wallets['Ethereum']),'mono'=>true],
            ['label'=>'SOL Wallet', 'icon'=>'sol',     'val'=>$wallets['Solana']??null,  'linked'=>isset($wallets['Solana']),'mono'=>true],
          ];
          foreach ($connections as $c):
            $val = $c['val'] ?? '';
            if (($c['mono']??false) && strlen($val)>16) $val = substr($val,0,6).'…'.substr($val,-4);
          ?>
          <div class="conn-row">
            <span style="width:100px;flex-shrink:0;font-size:12.5px;color:#aab2c5;font-weight:500"><?= htmlspecialchars($c['label']) ?></span>
            <span style="flex:1;font-family:<?= ($c['mono']??false)?'\'GT America Mono\',monospace':'inherit' ?>;font-size:11.5px;color:<?= $c['linked']?'#eef1f8':'#5a6478' ?>;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= $val ? htmlspecialchars($val) : '—' ?></span>
            <span style="flex-shrink:0;display:inline-flex;align-items:center;padding:3px 9px;border-radius:20px;font-size:9px;<?= $c['linked'] ? 'background:rgba(74,222,128,.1);border:1px solid rgba(74,222,128,.3);color:#4ade80' : 'background:rgba(255,255,255,.04);border:1px solid #232838;color:#5a6478' ?>"><?= $c['linked'] ? '✓' : '—' ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Discord Bot Commands -->
      <div class="settings-section">
        <div class="settings-header">
          <svg width="15" height="15" viewBox="0 0 71 55" fill="#7a84f0"><path d="M60.1 4.9A58.6 58.6 0 0 0 45.5.4a40 40 0 0 0-1.8 3.7 54.2 54.2 0 0 0-16.4 0A38.5 38.5 0 0 0 25.5.5 58.5 58.5 0 0 0 10.9 5C1.6 18.9-1 32.5.3 46a59 59 0 0 0 18 9.1 43.2 43.2 0 0 0 3.7-6l-5.8-2.8.6-.5a41.4 41.4 0 0 0 35.5 0l.5.5-5.8 2.8a42 42 0 0 0 3.7 6A58.8 58.8 0 0 0 68.7 46C70.3 30.3 66 16.8 60 4.9zM23.7 37.7a6.8 6.8 0 0 1-6.3-7.1 6.8 6.8 0 0 1 6.3-7.1 6.8 6.8 0 0 1 6.3 7.1 6.8 6.8 0 0 1-6.3 7zm23.6 0a6.8 6.8 0 0 1-6.3-7.1 6.8 6.8 0 0 1 6.3-7.1 6.8 6.8 0 0 1 6.3 7.1 6.8 6.8 0 0 1-6.3 7z"/></svg>
          DISCORD BOT
        </div>
        <div style="padding:14px 20px;display:flex;flex-direction:column;gap:10px">
          <?php foreach ([
            ['/balance','Check $BLOX balance'],
            ['/daily','Claim daily $BLOX'],
            ['/send','Send $BLOX to someone'],
            ['/linktwitter','Link X account'],
          ] as [$cmd,$desc]): ?>
          <div style="display:flex;align-items:center;gap:10px">
            <code style="font-family:'GT America Mono',monospace;font-size:11px;color:#bfe9f5;background:#0a0d18;border:1px solid #232838;padding:3px 9px;border-radius:6px;flex-shrink:0"><?= $cmd ?></code>
            <span style="font-size:12px;color:#7a8398"><?= $desc ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

</main>
</div>
</body>
</html>