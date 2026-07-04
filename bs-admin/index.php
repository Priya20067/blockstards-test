<?php
require_once __DIR__.'/../config.php';
require_staff();

$active_admin    = 'dashboard';
$admin_user      = get_user();
$admin_name      = htmlspecialchars($admin_user['username'] ?? 'Admin');
$admin_initial   = strtoupper(substr($admin_user['username'] ?? 'A', 0, 1));
$admin_avatar_url = get_avatar_url($admin_user['discord_id'], $admin_user['avatar'] ?? '');

// Stats
try { $active_raffles  = count(sb('bs_raffles')->eq('status','active')->select('id')->get()); } catch(Exception $e) { $active_raffles = 0; }
try { $live_auctions   = count(sb('bs_auctions')->eq('status','active')->select('id')->get()); } catch(Exception $e) { $live_auctions = 0; }
try { $total_mints     = count(sb('bs_mints')->select('id')->get()); } catch(Exception $e) { $total_mints = 0; }
try { $total_users     = count(sb('bs_users')->select('discord_id')->get()); } catch(Exception $e) { $total_users = 0; }

// Review queue: pending auction requests + mint submissions
try { $pending_reqs  = sb('bs_auction_requests')->eq('status','pending')->order('id',false)->limit(4)->get(); } catch(Exception $e) { $pending_reqs = []; }
try { $pending_subs  = sb('bs_mint_submissions')->eq('status','pending')->order('id',false)->limit(4)->get(); } catch(Exception $e) { $pending_subs = []; }
try { $pending_dates = sb('bs_mint_date_suggestions')->eq('status','pending')->select('id,name,suggested_date,suggested_by')->limit(4)->get(); } catch(Exception $e) { $pending_dates = []; }

$queue = [];
foreach ($pending_reqs as $r)  $queue[] = ['title'=>$r['title']??'?', 'sub'=>'Auction request · @'.(get_username($r['requester_id']??'')), 'type'=>'AUCTION', 'c'=>'182,156,255', 'fc'=>'#c9b8ff', 'link'=>'/bs-admin/auction.php'];
foreach ($pending_subs as $s)  $queue[] = ['title'=>$s['name']??'?',  'sub'=>'Mint submission · @'.(get_username($s['discord_id']??'')),  'type'=>'MINT',    'c'=>'228,197,144', 'fc'=>'#e4c590', 'link'=>'/bs-admin/mints.php'];
foreach ($pending_dates as $d) $queue[] = ['title'=>$d['name']??'?',  'sub'=>'Date: '.htmlspecialchars($d['suggested_date']??'?'),        'type'=>'DATE',    'c'=>'111,227,255', 'fc'=>'#6fe3ff', 'link'=>'/bs-admin/mint-suggestion.php'];
$queue = array_slice($queue, 0, 6);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Dashboard · Blockstards</title>
<link rel="stylesheet" href="/bs_fonts.css?v=1783164697">
<link rel="stylesheet" href="/bs_admin.css?v=1783164697">
</head>
<body>
<div class="adm-layout">
<?php require_once __DIR__.'/bs_admin_sidebar.php'; ?>
<main class="adm-main">

  <div class="adm-topbar">
    <div class="adm-breadcrumb">ADMIN / <span class="bc-active">DASHBOARD</span></div>
    <div class="adm-topbar-right">
      <div class="adm-bot-pill"><span class="adm-bot-dot"></span><span class="adm-bot-label">BOT ONLINE</span></div>
      <div class="adm-avatar">
        <?php if ($admin_avatar_url): ?><img src="<?= htmlspecialchars($admin_avatar_url) ?>" alt=""><?php else: ?><?= $admin_initial ?><?php endif; ?>
      </div>
    </div>
  </div>

  <div class="adm-page-header">
    <div>
      <h1 class="adm-page-title">Admin Dashboard</h1>
      <p class="adm-page-sub">Manage raffles, auctions, mints and your team.</p>
    </div>
  </div>

  <!-- Stats -->
  <div class="adm-stats adm-stats-4">
    <div class="adm-stat"><div class="adm-stat-val" style="color:#6fe3ff"><?= $active_raffles ?></div><div class="adm-stat-label">ACTIVE RAFFLES</div></div>
    <div class="adm-stat"><div class="adm-stat-val" style="color:#b69cff"><?= $live_auctions ?></div><div class="adm-stat-label">LIVE AUCTIONS</div></div>
    <div class="adm-stat"><div class="adm-stat-val" style="color:#e4c590"><?= $total_mints ?></div><div class="adm-stat-label">TOTAL MINTS</div></div>
    <div class="adm-stat"><div class="adm-stat-val"><?= number_format($total_users) ?></div><div class="adm-stat-label">USERS</div></div>
  </div>

  <div style="display:grid;grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);gap:20px;align-items:start">

    <!-- Quick actions -->
    <div>
      <div style="font-family:'GT America Mono',monospace;font-size:11px;letter-spacing:.14em;color:#7a8398;margin-bottom:14px">QUICK ACTIONS</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <?php
        $actions = [
          ['href'=>'/bs-admin/raffles.php',        'title'=>'Manage Raffles',   'desc'=>'Create raffles, pick winners, view entries', 'c'=>'111,227,255', 'fc'=>'#6fe3ff',  'svg'=>'<path d="M20 12V22H4V12"/><path d="M22 7H2v5h20V7z"/><path d="M12 22V7"/>'],
          ['href'=>'/bs-admin/auction.php',         'title'=>'Manage Auctions',  'desc'=>'Approve requests, end auctions, announce winners', 'c'=>'182,156,255', 'fc'=>'#b69cff', 'svg'=>'<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>'],
          ['href'=>'/bs-admin/mints.php',           'title'=>'Manage Mints',     'desc'=>'Add mints to calendar, approve community submissions', 'c'=>'228,197,144', 'fc'=>'#e4c590', 'svg'=>'<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>'],
          ['href'=>'/bs-admin/staff.php',           'title'=>'Manage Staff',     'desc'=>'Add team members and set permissions', 'c'=>'74,222,128', 'fc'=>'#4ade80', 'svg'=>'<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>'],
        ];
        foreach ($actions as $a): ?>
        <a href="<?= $a['href'] ?>" style="display:block;text-decoration:none;padding:20px;border:1px solid #161a28;border-radius:16px;background:rgba(255,255,255,.02);transition:transform .18s,border-color .18s,box-shadow .18s" onmouseover="this.style.transform='translateY(-3px)';this.style.borderColor='rgba(<?= $a['c'] ?>,.5)';this.style.boxShadow='0 18px 38px -22px rgba(<?= $a['c'] ?>,.6)'" onmouseout="this.style.transform='';this.style.borderColor='#161a28';this.style.boxShadow=''">
          <div style="width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;margin-bottom:14px;background:rgba(<?= $a['c'] ?>,.12);border:1px solid rgba(<?= $a['c'] ?>,.25)">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="<?= $a['fc'] ?>" stroke-width="1.6"><?= $a['svg'] ?></svg>
          </div>
          <div style="font-weight:600;font-size:15px;margin-bottom:5px"><?= $a['title'] ?></div>
          <div style="font-size:12px;color:#7a8398;line-height:1.5"><?= $a['desc'] ?></div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Review queue -->
    <div>
      <div style="font-family:'GT America Mono',monospace;font-size:11px;letter-spacing:.14em;color:#7a8398;margin-bottom:14px">REVIEW QUEUE <?php if (count($queue)): ?><span class="adm-badge adm-badge-orange" style="margin-left:6px"><?= count($queue) ?></span><?php endif; ?></div>
      <div class="adm-panel">
        <?php if (empty($queue)): ?>
        <div style="padding:32px;text-align:center;color:#5a6478;font-size:13px">All caught up! No pending items.</div>
        <?php else: foreach ($queue as $q): ?>
        <a href="<?= $q['link'] ?>" style="display:flex;align-items:center;gap:13px;padding:14px 18px;border-bottom:1px solid #0d1018;text-decoration:none;transition:background .15s" onmouseover="this.style.background='rgba(255,255,255,.03)'" onmouseout="this.style.background=''">
          <div style="width:36px;height:36px;border-radius:9px;flex-shrink:0;background:rgba(<?= $q['c'] ?>,.12);border:1px solid rgba(<?= $q['c'] ?>,.25);display:flex;align-items:center;justify-content:center">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="<?= $q['fc'] ?>" stroke-width="1.7"><circle cx="12" cy="12" r="9"/><path d="M12 8v4l3 2"/></svg>
          </div>
          <div style="flex:1;min-width:0">
            <div style="font-size:13.5px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($q['title']) ?></div>
            <div style="font-family:'GT America Mono',monospace;font-size:10.5px;color:#7a8398;margin-top:2px"><?= htmlspecialchars($q['sub']) ?></div>
          </div>
          <span class="adm-badge" style="background:rgba(<?= $q['c'] ?>,.1);color:<?= $q['fc'] ?>;border:1px solid rgba(<?= $q['c'] ?>,.25)"><?= $q['type'] ?></span>
        </a>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>

</main>
</div>
</body>
</html>