<?php
require_once __DIR__.'/../config.php';
if (!is_staff()) { header('Location: /'); exit; }

$active_admin     = 'logs';
$admin_user       = get_user();
$admin_name       = htmlspecialchars($admin_user['username'] ?? 'Admin');
$admin_initial    = strtoupper(substr($admin_user['username'] ?? 'A', 0, 1));
$admin_avatar_url = get_avatar_url($admin_user['discord_id'], $admin_user['avatar'] ?? '');

$filter  = $_GET['type']  ?? 'all';
$search  = trim($_GET['q']  ?? '');
$page_n  = max(1, (int)($_GET['page'] ?? 1));
$per     = 50;
$offset  = ($page_n - 1) * $per;

$valid_types = ['all','blox_add','blox_remove','auction_end','auction_cancel','raid_approve','raid_reject','post_approve','post_reject','raffle_end','wallet_update','permission_change'];

$where = []; $params = [];
if ($filter !== 'all') { $where[] = "log_type=?"; $params[] = $filter; }
if ($search) { $where[] = "(actor_id LIKE ? OR target_id LIKE ? OR note LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
$w_sql = $where ? 'WHERE '.implode(' AND ',$where) : '';

$total = 0; $logs = [];
try {
    $t = db()->prepare("SELECT COUNT(*) FROM bs_logs $w_sql"); $t->execute($params); $total = $t->fetchColumn();
    $l = db()->prepare("SELECT * FROM bs_logs $w_sql ORDER BY created_at DESC LIMIT $per OFFSET $offset"); $l->execute($params); $logs = $l->fetchAll();
} catch(Exception $e) {}

$ids = array_unique(array_filter(array_merge(array_column($logs,'actor_id'),array_column($logs,'target_id')),fn($x)=>$x&&$x!=='website'&&is_numeric($x)));
$unames = [];
if ($ids) {
    $pl=implode(',',array_fill(0,count($ids),'?'));
    $us=db()->prepare("SELECT discord_id,username FROM bs_users WHERE discord_id IN ($pl)");
    $us->execute(array_values($ids));
    foreach($us->fetchAll() as $u) $unames[$u['discord_id']]=$u['username'];
}
function log_badge_new($type) {
    $map=['blox_add'=>['adm-badge-green','+ $BLOX'],'blox_remove'=>['adm-badge-red','- $BLOX'],'auction_end'=>['adm-badge-gold','Auction End'],'auction_cancel'=>['adm-badge-gray','Cancelled'],'raid_approve'=>['adm-badge-cyan','Raid ✓'],'raid_reject'=>['adm-badge-red','Raid ✗'],'post_approve'=>['adm-badge-green','Post ✓'],'post_reject'=>['adm-badge-red','Post ✗'],'raffle_end'=>['adm-badge-violet','Raffle End'],'wallet_update'=>['adm-badge-cyan','Wallet'],'permission_change'=>['adm-badge-gold','Permission']];
    $d=$map[$type]??['adm-badge-gray',$type];
    return '<span class="adm-badge '.$d[0].'">'.$d[1].'</span>';
}
$uname_fn = fn($id)=>!$id?'—':($id==='website'?'🌐 website':($unames[$id]??('…'.substr($id,-4))));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Audit Log · Admin</title>
<link rel="stylesheet" href="/bs_fonts.css?v=1783164697">
<link rel="stylesheet" href="/bs_admin.css?v=1783164697">
</head>
<body>
<div class="adm-layout">
<?php require_once __DIR__.'/bs_admin_sidebar.php'; ?>
<main class="adm-main">
  <div class="adm-topbar">
    <div class="adm-breadcrumb">ADMIN / <span class="bc-active">LOGS</span></div>
    <div class="adm-topbar-right"><div class="adm-avatar"><?php if($admin_avatar_url): ?><img src="<?= htmlspecialchars($admin_avatar_url) ?>" alt=""><?php else: ?><?= $admin_initial ?><?php endif; ?></div></div>
  </div>
  <div class="adm-page-header">
    <div><h1 class="adm-page-title">Audit Log</h1><p class="adm-page-sub">Every staff action, recorded with who, what and when.</p></div>
  </div>

  <!-- Filter + search -->
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
    <div class="adm-filter-tabs">
      <a href="?type=all" class="adm-filter-tab <?= $filter==='all'?'active':'' ?>">All</a>
      <a href="?type=raffle_end" class="adm-filter-tab <?= $filter==='raffle_end'?'active':'' ?>">Raffles</a>
      <a href="?type=auction_end" class="adm-filter-tab <?= $filter==='auction_end'?'active':'' ?>">Auctions</a>
      <a href="?type=blox_add" class="adm-filter-tab <?= $filter==='blox_add'?'active':'' ?>">$BLOX</a>
      <a href="?type=permission_change" class="adm-filter-tab <?= $filter==='permission_change'?'active':'' ?>">Perms</a>
    </div>
    <form method="get" style="display:flex;align-items:center;gap:8px">
      <?php if ($filter!=='all'): ?><input type="hidden" name="type" value="<?= htmlspecialchars($filter) ?>"><?php endif; ?>
      <div style="display:flex;align-items:center;gap:8px;padding:8px 14px;background:rgba(255,255,255,.03);border:1px solid #161a28;border-radius:10px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#5a6478" stroke-width="1.8"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        <input name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search actor, target, note…" style="background:transparent;border:none;outline:none;font-family:'GT America Mono',monospace;font-size:12px;color:#eef1f8;min-width:200px">
      </div>
    </form>
  </div>

  <div class="adm-table-wrap">
    <table class="adm-table">
      <thead><tr><th>WHEN</th><th>TYPE</th><th>ACTOR</th><th>TARGET</th><th>NOTE</th></tr></thead>
      <tbody>
      <?php foreach ($logs as $log): ?>
      <tr>
        <td style="font-family:'GT America Mono',monospace;font-size:10.5px;color:#7a8398;white-space:nowrap"><?= $log['created_at']?date('M j, H:i',strtotime($log['created_at'])):'—' ?></td>
        <td><?= log_badge_new($log['log_type']??'') ?></td>
        <td style="font-size:12.5px;font-weight:600"><?= htmlspecialchars($uname_fn($log['actor_id']??'')) ?></td>
        <td style="font-size:12.5px;color:#aab2c5"><?= htmlspecialchars($uname_fn($log['target_id']??'')) ?></td>
        <td style="font-size:12px;color:#7a8398;max-width:300px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($log['note']??'') ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($logs)): ?><tr><td colspan="5" style="text-align:center;padding:40px;color:#5a6478">No logs found.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($total > $per): $pages=ceil($total/$per); ?>
  <div style="display:flex;gap:6px;justify-content:center;margin-top:20px">
    <?php for($i=1;$i<=$pages;$i++): ?>
    <a href="?type=<?= $filter ?>&q=<?= urlencode($search) ?>&page=<?= $i ?>" class="adm-btn <?= $i===$page_n?'adm-btn-ghost':'' ?> adm-btn-sm" style="<?= $i===$page_n?'border-color:rgba(182,156,255,.4);color:#b69cff':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</main>
</div>
</body>
</html>