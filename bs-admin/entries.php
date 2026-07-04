<?php /* entries.php */
require_once __DIR__.'/../config.php';
if (!is_staff()) { header('Location: /'); exit; }

// Check perm
$uid = get_user()['discord_id'];
$gid = DISCORD_GUILD_ID;
$can_view = false;
try { $o=db()->prepare("SELECT 1 FROM bs_permissions WHERE guild_id=? AND discord_id=? AND perm_key='owner'");$o->execute([$gid,$uid]);$can_view=(bool)$o->fetch(); } catch(Exception $e){}
if (!$can_view) { try { $p=db()->prepare("SELECT 1 FROM bs_permissions WHERE guild_id=? AND discord_id=? AND perm_key='perm_entries'");$p->execute([$gid,$uid]);$can_view=(bool)$p->fetch(); } catch(Exception $e){} }

$active_admin     = 'entries';
$admin_user       = get_user();
$admin_name       = htmlspecialchars($admin_user['username'] ?? 'Admin');
$admin_initial    = strtoupper(substr($admin_user['username'] ?? 'A', 0, 1));
$admin_avatar_url = get_avatar_url($admin_user['discord_id'], $admin_user['avatar'] ?? '');

if (!$can_view) {
    include_page_header('Entries · Admin');
    echo '<main class="adm-main"><div style="text-align:center;padding:80px 20px"><div style="font-size:48px;margin-bottom:16px">🔒</div><h2 style="font-weight:700;font-size:22px;color:#eef1f8">Access Denied</h2><p style="color:#7a8398;margin-top:8px;font-size:13px">You need <strong>perm_entries</strong> permission to view this page.</p></div></main>';
    exit;
}

$type = $_GET['type'] ?? 'raffle';
$id   = (int)($_GET['id'] ?? 0);

$raffles  = sb('bs_raffles')->order('id',false)->select('id,title,spots,status')->limit(50)->get();
$auctions_list = sb('bs_auctions')->order('id',false)->select('id,title,winners,status')->limit(50)->get();
foreach ($raffles as &$r) { $r['cnt']=count(sb('bs_raffle_entries')->eq('raffle_id',$r['id'])->select('discord_id')->get()); } unset($r);

$entries = []; $item = null; $winners = [];
if ($id && $type === 'raffle') {
    $item = sb('bs_raffles')->eq('id',(string)$id)->first();
    $winner_ids = array_column(sb('bs_raffle_winners')->eq('raffle_id',(string)$id)->select('discord_id')->get(),'discord_id');
    $entry_rows = sb('bs_raffle_entries')->eq('raffle_id',(string)$id)->get();
    foreach ($entry_rows as $e) {
        $u=sb('bs_users')->eq('discord_id',$e['discord_id'])->select('username,avatar')->first();
        $weth=sb('bs_user_wallets')->eq('discord_id',$e['discord_id'])->eq('chain','Ethereum')->select('address')->first();
        $wsol=sb('bs_user_wallets')->eq('discord_id',$e['discord_id'])->eq('chain','Solana')->select('address')->first();
        $entries[]=['discord_id'=>$e['discord_id'],'username'=>$u['username']??('…'.substr($e['discord_id'],-4)),'avatar'=>$u['avatar']??'','eth_wallet'=>$weth['address']??'','sol_wallet'=>$wsol['address']??'','entered_at'=>$e['entered_at']??'','is_winner'=>in_array($e['discord_id'],$winner_ids)?1:0];
    }
    usort($entries,fn($a,$b)=>$b['is_winner']<=>$a['is_winner']);
} elseif ($id && $type === 'auction') {
    $item = sb('bs_auctions')->eq('id',(string)$id)->first();
    if ($item) {
        $bids=json_decode($item['bids_json']??'{}',true)?:[];
        $unames=json_decode($item['usernames_json']??'{}',true)?:[];
        arsort($bids);
        foreach ($bids as $did=>$amt) {
            $weth=sb('bs_user_wallets')->eq('discord_id',$did)->eq('chain','Ethereum')->select('address')->first();
            $wsol=sb('bs_user_wallets')->eq('discord_id',$did)->eq('chain','Solana')->select('address')->first();
            $entries[]=['discord_id'=>$did,'username'=>$unames[$did]??('…'.substr($did,-4)),'amount'=>$amt,'eth_wallet'=>$weth['address']??'','sol_wallet'=>$wsol['address']??''];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Entries · Admin</title>
<link rel="stylesheet" href="/bs_fonts.css?v=1783164697">
<link rel="stylesheet" href="/bs_admin.css?v=1783164697">
</head>
<body>
<div class="adm-layout">
<?php require_once __DIR__.'/bs_admin_sidebar.php'; ?>
<main class="adm-main">
  <div class="adm-topbar">
    <div class="adm-breadcrumb">ADMIN / <span class="bc-active">ENTRIES</span></div>
    <div class="adm-topbar-right"><div class="adm-avatar"><?php if($admin_avatar_url): ?><img src="<?= htmlspecialchars($admin_avatar_url) ?>" alt=""><?php else: ?><?= $admin_initial ?><?php endif; ?></div></div>
  </div>
  <div class="adm-page-header">
    <div><h1 class="adm-page-title">Raffle Entries</h1><p class="adm-page-sub">Browse and export entrant wallets for any raffle.</p></div>
    <?php if ($id && !empty($entries)): ?>
    <a href="data:text/csv,<?= urlencode("Discord ID,Username,ETH Wallet,SOL Wallet\n".implode("\n",array_map(fn($e)=>implode(',',$e['discord_id']??'',$e['username']??'',$e['eth_wallet']??'',$e['sol_wallet']??''),array_map(fn($e)=>[$e['discord_id'],$e['username'],$e['eth_wallet']??'',$e['sol_wallet']??''],$entries)))) ?>" download="entries_<?= $id ?>.csv" class="adm-btn adm-btn-ghost">⬇ Export CSV</a>
    <?php endif; ?>
  </div>

  <div style="display:grid;grid-template-columns:240px 1fr;gap:20px;align-items:start">
    <!-- Sidebar: raffle/auction selector -->
    <div class="adm-panel">
      <div class="adm-panel-hdr"><span class="adm-panel-hdr-label">RAFFLES</span></div>
      <?php foreach ($raffles as $r): ?>
      <a href="?type=raffle&id=<?= $r['id'] ?>" style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:10px 14px;border-bottom:1px solid #0d1018;text-decoration:none;background:<?= ($type==='raffle'&&$id==$r['id'])?'rgba(111,227,255,.07)':'transparent' ?>;transition:background .15s" onmouseover="this.style.background='rgba(255,255,255,.03)'" onmouseout="this.style.background='<?= ($type==='raffle'&&$id==$r['id'])?'rgba(111,227,255,.07)':'transparent' ?>'">
        <span style="font-size:12.5px;color:#eef1f8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($r['title']) ?></span>
        <span style="font-family:'GT America Mono',monospace;font-size:10px;color:#6fe3ff;flex-shrink:0"><?= $r['cnt'] ?></span>
      </a>
      <?php endforeach; ?>
      <div class="adm-panel-hdr" style="margin-top:6px"><span class="adm-panel-hdr-label">AUCTIONS</span></div>
      <?php foreach ($auctions_list as $a): ?>
      <a href="?type=auction&id=<?= $a['id'] ?>" style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:10px 14px;border-bottom:1px solid #0d1018;text-decoration:none;background:<?= ($type==='auction'&&$id==$a['id'])?'rgba(182,156,255,.07)':'transparent' ?>;transition:background .15s" onmouseover="this.style.background='rgba(255,255,255,.03)'" onmouseout="this.style.background='<?= ($type==='auction'&&$id==$a['id'])?'rgba(182,156,255,.07)':'transparent' ?>'">
        <span style="font-size:12.5px;color:#eef1f8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($a['title']) ?></span>
        <span class="adm-badge adm-badge-<?= $a['status']==='active'?'green':'gray' ?>"><?= $a['status'] ?></span>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Main: entries -->
    <div>
      <?php if (!$id): ?>
      <div style="text-align:center;padding:60px;color:#5a6478">← Select a raffle or auction to view entries.</div>
      <?php elseif ($item): ?>
      <div style="margin-bottom:16px">
        <div style="font-weight:600;font-size:18px;margin-bottom:4px"><?= htmlspecialchars($item['title']??'') ?></div>
        <div style="font-size:12px;color:#7a8398"><?= count($entries) ?> <?= $type==='raffle'?'entrants':'bids' ?></div>
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px">
        <?php foreach ($entries as $e): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:11px;background:rgba(255,255,255,.02);border:1px solid <?= ($e['is_winner']??0)?'rgba(228,197,144,.2)':'#161a28' ?>;border-radius:11px">
          <div class="adm-avatar-sm"><?php if($e['avatar']??''): ?><img src="https://cdn.discordapp.com/avatars/<?= $e['discord_id'] ?>/<?= $e['avatar'] ?>.png" style="width:100%;height:100%;object-fit:cover"><?php else: ?><?= strtoupper(substr($e['username'],0,1)) ?><?php endif; ?></div>
          <div style="flex:1;min-width:0">
            <div style="font-size:12.5px;font-weight:600"><?= htmlspecialchars($e['username']) ?><?= ($e['is_winner']??0)?' 👑':'' ?></div>
            <?php if($e['eth_wallet']??''): ?><div style="font-family:'GT America Mono',monospace;font-size:9px;color:#6fe3ff">⟠ <?= substr($e['eth_wallet'],0,8).'…'.substr($e['eth_wallet'],-4) ?></div><?php endif; ?>
            <?php if($e['sol_wallet']??''): ?><div style="font-family:'GT America Mono',monospace;font-size:9px;color:#b69cff">◎ <?= substr($e['sol_wallet'],0,8).'…'.substr($e['sol_wallet'],-4) ?></div><?php endif; ?>
            <?php if($type==='auction'&&isset($e['amount'])): ?><div style="font-family:'GT America Mono',monospace;font-size:10px;color:#e4c590"><?= number_format($e['amount'],2) ?> $BLOX</div><?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if(empty($entries)): ?><div style="text-align:center;padding:40px;color:#5a6478">No entries yet.</div><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</main>
</div>
</body>
</html>
<?php exit; ?>