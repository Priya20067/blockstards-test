<?php
require_once __DIR__.'/../config.php';
if (!is_staff()) { http_response_code(403); die('Access denied.'); }

$active_admin     = 'suggestions';
$admin_user       = get_user();
$admin_name       = htmlspecialchars($admin_user['username'] ?? 'Admin');
$admin_initial    = strtoupper(substr($admin_user['username'] ?? 'A', 0, 1));
$admin_avatar_url = get_avatar_url($admin_user['discord_id'], $admin_user['avatar'] ?? '');
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $sid    = $_POST['suggestion_id'] ?? '';
    if ($action === 'approve' && $sid) {
        $sugg = sb('bs_mint_date_suggestions')->eq('id',$sid)->first();
        if ($sugg && in_array($sugg['record_table']??'',['bs_mints','bs_mint_submissions'],true)) {
            $upd = ['mint_date'=>$sugg['suggested_date']];
            if ($sugg['record_table']==='bs_mints') $upd['status']='approved';
            sb($sugg['record_table'])->eq('id',$sugg['record_id'])->update($upd);
            sb('bs_mint_date_suggestions')->eq('id',$sid)->update(['status'=>'approved']);
            $msg = '✓ Date published for "'.htmlspecialchars($sugg['name']??'').'"';
        }
    }
    if ($action === 'reject' && $sid) {
        sb('bs_mint_date_suggestions')->eq('id',$sid)->update(['status'=>'rejected']);
        $msg = '✓ Suggestion rejected.';
    }
}

$pending = sb('bs_mint_date_suggestions')->eq('status','pending')->get();
$unames = [];
if (!empty($pending)) {
    $ids = array_unique(array_column($pending,'suggested_by'));
    if ($ids) { $pl=implode(',',array_fill(0,count($ids),'?'));$s=db()->prepare("SELECT discord_id,username FROM bs_users WHERE discord_id IN ($pl)");$s->execute($ids);foreach($s->fetchAll() as $u) $unames[$u['discord_id']]=$u['username']; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Date Suggestions · Admin</title>
<link rel="stylesheet" href="/bs_fonts.css?v=1783164697">
<link rel="stylesheet" href="/bs_admin.css?v=1783164697">
</head>
<body>
<div class="adm-layout">
<?php require_once __DIR__.'/bs_admin_sidebar.php'; ?>
<main class="adm-main">
  <div class="adm-topbar">
    <div class="adm-breadcrumb">ADMIN / <span class="bc-active">SUGGESTIONS</span></div>
    <div class="adm-topbar-right"><div class="adm-avatar"><?php if($admin_avatar_url): ?><img src="<?= htmlspecialchars($admin_avatar_url) ?>" alt=""><?php else: ?><?= $admin_initial ?><?php endif; ?></div></div>
  </div>
  <?php if ($msg): ?><div class="adm-notice adm-notice-green"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <div class="adm-page-header">
    <div>
      <h1 class="adm-page-title">Date Suggestions</h1>
      <p class="adm-page-sub">Community-submitted mint dates for TBA projects. Approve to publish to the calendar.</p>
    </div>
    <?php if (!empty($pending)): ?><span class="adm-badge adm-badge-orange" style="padding:6px 14px;font-size:12px"><?= count($pending) ?> pending</span><?php endif; ?>
  </div>

  <?php if (empty($pending)): ?>
  <div style="text-align:center;padding:60px;color:#5a6478;font-size:13px">
    <div style="font-size:36px;margin-bottom:14px">✅</div>
    No pending suggestions — all caught up!
  </div>
  <?php else: foreach ($pending as $sugg):
    $by = $unames[$sugg['suggested_by']??''] ?? ('…'.substr($sugg['suggested_by']??'',- 4));
  ?>
  <div style="display:flex;align-items:center;gap:16px;padding:16px 18px;background:rgba(255,255,255,.02);border:1px solid #161a28;border-radius:14px;margin-bottom:10px;flex-wrap:wrap">
    <div style="flex:1;min-width:0">
      <div style="font-weight:600;font-size:14px;margin-bottom:4px"><?= htmlspecialchars($sugg['name']??'?') ?></div>
      <div style="font-size:12px;color:#7a8398">
        Suggested date: <strong style="color:#e4c590"><?= htmlspecialchars($sugg['suggested_date']??'') ?></strong>
        · by <span style="color:#aab2c5">@<?= htmlspecialchars($by) ?></span>
        <?php if($sugg['suggested_price']??''): ?> · <span style="color:#aab2c5">Price: <?= htmlspecialchars($sugg['suggested_price']) ?></span><?php endif; ?>
        <?php if($sugg['suggested_mint_url']??''): ?> · <a href="<?= htmlspecialchars($sugg['suggested_mint_url']) ?>" target="_blank" style="color:#6fe3ff;font-size:12px">Mint page ↗</a><?php endif; ?>
      </div>
    </div>
    <div class="adm-action-btns">
      <form method="post" style="display:inline">
        <input type="hidden" name="action" value="approve"><input type="hidden" name="suggestion_id" value="<?= $sugg['id'] ?>">
        <button class="adm-btn adm-btn-green" onclick="return confirm('Publish this date?')">✓ Approve & Publish</button>
      </form>
      <form method="post" style="display:inline">
        <input type="hidden" name="action" value="reject"><input type="hidden" name="suggestion_id" value="<?= $sugg['id'] ?>">
        <button class="adm-btn adm-btn-red">✕ Reject</button>
      </form>
    </div>
  </div>
  <?php endforeach; endif; ?>
</main>
</div>
</body>
</html>