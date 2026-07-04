<?php
require_once __DIR__.'/config.php';
$user=get_user();if(!$user){header('Location: /bs-auth/discord.php');exit;}if(!is_staff()){header('Location: /mobile.php');exit;}
$uid=$user['discord_id'];$blox_bal=0;try{$blox_bal=get_balance($uid);}catch(Exception $e){}$m_active='admin';

$filter=$_GET['type']??'all';$page_n=max(1,(int)($_GET['page']??1));$per=30;$offset=($page_n-1)*$per;
$where=[];$params=[];
if($filter!=='all'){$where[]="log_type=?";$params[]=$filter;}
$w_sql=$where?'WHERE '.implode(' AND ',$where):'';
$total=0;$logs=[];
try{$t=db()->prepare("SELECT COUNT(*) FROM bs_logs $w_sql");$t->execute($params);$total=$t->fetchColumn();$l=db()->prepare("SELECT * FROM bs_logs $w_sql ORDER BY created_at DESC LIMIT $per OFFSET $offset");$l->execute($params);$logs=$l->fetchAll();}catch(Exception $e){}

$ids=array_unique(array_filter(array_merge(array_column($logs,'actor_id'),array_column($logs,'target_id')),fn($x)=>$x&&$x!=='website'&&is_numeric($x)));
$unames=[];
if($ids){$pl=implode(',',array_fill(0,count($ids),'?'));$us=db()->prepare("SELECT discord_id,username FROM bs_users WHERE discord_id IN ($pl)");$us->execute(array_values($ids));foreach($us->fetchAll() as $u)$unames[$u['discord_id']]=$u['username'];}

$badge_map=['blox_add'=>['m-badge-green','+ $BLOX'],'blox_remove'=>['m-badge-red','- $BLOX'],'auction_end'=>['m-badge-gold','Auction End'],'auction_cancel'=>['m-badge-gray','Cancelled'],'raffle_end'=>['m-badge-violet','Raffle End'],'permission_change'=>['m-badge-gold','Permission'],'wallet_update'=>['m-badge-cyan','Wallet']];
$uname_fn=fn($id)=>!$id?'—':($id==='website'?'🌐':($unames[$id]??('…'.substr($id,-4))));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Logs · Admin</title>
<link rel="stylesheet" href="/bs_fonts.css?v=1783164697">
<link rel="stylesheet" href="/bs_mobile.css?v=1783164697">
</head>
<body>
<?php require_once __DIR__.'/includes/bs_mobile_nav.php'; ?>
<div class="m-body">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:18px">
    <a href="/mobile-admin.php" style="width:34px;height:34px;border-radius:9px;background:rgba(255,255,255,.04);border:1px solid #161a28;display:flex;align-items:center;justify-content:center;color:#aab2c5;text-decoration:none;font-size:16px">‹</a>
    <h1 class="m-page-title" style="font-size:22px;margin-bottom:0">Audit Log</h1>
  </div>

  <!-- Filter tabs -->
  <div class="m-tabs">
    <a href="?type=all" class="m-tab <?= $filter==='all'?'on':'' ?>">All</a>
    <a href="?type=raffle_end" class="m-tab <?= $filter==='raffle_end'?'on':'' ?>">Raffles</a>
    <a href="?type=auction_end" class="m-tab <?= $filter==='auction_end'?'on':'' ?>">Auctions</a>
    <a href="?type=blox_add" class="m-tab <?= $filter==='blox_add'?'on':'' ?>">$BLOX</a>
    <a href="?type=permission_change" class="m-tab <?= $filter==='permission_change'?'on':'' ?>">Perms</a>
  </div>

  <?php if(empty($logs)): ?>
  <div class="m-empty"><span class="m-empty-icon">📋</span><p>No logs found.</p></div>
  <?php else: foreach($logs as $log):
    $bd=$badge_map[$log['log_type']??'']??['m-badge-gray',$log['log_type']??'?'];
  ?>
  <div style="padding:11px 13px;background:rgba(255,255,255,.02);border:1px solid #161a28;border-radius:12px;margin-bottom:8px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px">
      <span class="m-badge <?= $bd[0] ?>"><?= $bd[1] ?></span>
      <span style="font-family:'GT America Mono',monospace;font-size:9.5px;color:#5a6478"><?= $log['created_at']?date('M j, H:i',strtotime($log['created_at'])):'—' ?></span>
    </div>
    <div style="font-size:12.5px;margin-bottom:2px"><strong><?= htmlspecialchars($uname_fn($log['actor_id']??'')) ?></strong> → <span style="color:#aab2c5"><?= htmlspecialchars($uname_fn($log['target_id']??'')) ?></span></div>
    <?php if($log['note']??''): ?><div style="font-size:11px;color:#7a8398;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($log['note']) ?></div><?php endif; ?>
  </div>
  <?php endforeach; endif; ?>

  <!-- Pagination -->
  <?php if($total>$per): $pages=ceil($total/$per); ?>
  <div style="display:flex;gap:6px;justify-content:center;margin-top:14px;flex-wrap:wrap">
    <?php for($i=1;$i<=$pages;$i++): ?>
    <a href="?type=<?= $filter ?>&page=<?= $i ?>" style="width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-family:'GT America Mono',monospace;font-size:11px;text-decoration:none;<?= $i===$page_n?'background:rgba(182,156,255,.12);border:1px solid rgba(182,156,255,.3);color:#b69cff':'background:rgba(255,255,255,.03);border:1px solid #161a28;color:#7a8398' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
</body>
</html>