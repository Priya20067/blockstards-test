<?php
require_once __DIR__.'/config.php';
$user=get_user();if(!$user){header('Location: /bs-auth/discord.php');exit;}if(!is_staff()){header('Location: /mobile.php');exit;}
$uid=$user['discord_id'];$blox_bal=0;try{$blox_bal=get_balance($uid);}catch(Exception $e){}$m_active='admin';

global $STAFF_IDS;
$is_owner=in_array($uid,$STAFF_IDS??[]);

// AJAX search
if(isset($_GET['search_user'])){header('Content-Type: application/json');$q=trim($_GET['search_user']);if(strlen($q)<1){echo '[]';exit;}$s=db()->prepare("SELECT discord_id,username FROM bs_users WHERE username LIKE ? ORDER BY username ASC LIMIT 10");$s->execute(["%$q%"]);echo json_encode($s->fetchAll());exit;}

$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'&&$is_owner){
    $action=$_POST['action']??'';
    if($action==='add'){$did=trim($_POST['discord_id']??'');$uname=trim($_POST['username']??'');if($did&&$uname){sb('bs_staff')->upsert(['discord_id'=>$did,'username'=>$uname,'added_by'=>$uid],'discord_id');sb('bs_staff_permissions')->upsert(['discord_id'=>$did,'guild_id'=>DISCORD_GUILD_ID,'perm_raffles'=>0,'perm_mints'=>0,'perm_auctions'=>0,'perm_staff'=>0],'discord_id');sb('bs_users')->eq('discord_id',$did)->update(['is_staff'=>1]);header('Location: /mobile-admin-staff.php?ok='.urlencode($uname));exit;}}
    if($action==='remove'){$did=$_POST['discord_id'];sb('bs_staff')->eq('discord_id',$did)->delete();sb('bs_staff_permissions')->eq('discord_id',$did)->delete();sb('bs_users')->eq('discord_id',$did)->update(['is_staff'=>0]);header('Location: /mobile-admin-staff.php');exit;}
}

$staff_rows=sb('bs_staff')->get();$staff=[];
foreach($staff_rows as $s){$p=sb('bs_staff_permissions')->eq('discord_id',$s['discord_id'])->first();$av=get_avatar_url($s['discord_id'],'');$staff[]=array_merge($s,['perms'=>$p??[],'avatar_url'=>$av,'is_owner'=>in_array($s['discord_id'],$STAFF_IDS??[])]);}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Staff · Admin</title>
<link rel="stylesheet" href="/bs_fonts.css?v=1783164697">
<link rel="stylesheet" href="/bs_mobile.css?v=1783164697">
</head>
<body>
<?php require_once __DIR__.'/includes/bs_mobile_nav.php'; ?>
<div class="m-body">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:18px">
    <a href="/mobile-admin.php" style="width:34px;height:34px;border-radius:9px;background:rgba(255,255,255,.04);border:1px solid #161a28;display:flex;align-items:center;justify-content:center;color:#aab2c5;text-decoration:none;font-size:16px">‹</a>
    <h1 class="m-page-title" style="font-size:22px;margin-bottom:0">Staff</h1>
  </div>
  <?php if(isset($_GET['ok'])): ?><div class="m-notice m-notice-green">✓ Staff member added: <?= htmlspecialchars($_GET['ok']) ?></div><?php endif; ?>

  <!-- Add staff -->
  <?php if($is_owner): ?>
  <details style="margin-bottom:16px">
    <summary style="list-style:none;display:flex;align-items:center;gap:10px;padding:13px 16px;background:rgba(255,255,255,.02);border:1px solid #161a28;border-radius:13px;cursor:pointer;font-family:'GT America Mono',monospace;font-size:11px;color:#aab2c5">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6fe3ff" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>ADD STAFF MEMBER
    </summary>
    <div style="padding:16px;border:1px solid #161a28;border-top:none;border-radius:0 0 13px 13px;background:rgba(255,255,255,.015)">
      <form method="post">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="discord_id" id="add-did">
        <input type="hidden" name="username" id="add-uname">
        <div class="m-field">
          <label>SEARCH BY USERNAME</label>
          <input class="m-input" id="staff-search" placeholder="Type username…" oninput="searchStaff(this.value)" autocomplete="off">
          <div id="staff-results" style="display:none;border:1px solid #232838;border-radius:11px;overflow:hidden;margin-top:4px;background:#0d1018"></div>
          <div id="staff-selected" style="display:none;margin-top:8px;padding:10px 13px;background:rgba(182,156,255,.08);border:1px solid rgba(182,156,255,.25);border-radius:10px;font-size:12px;color:#c9b8ff"></div>
        </div>
        <button type="submit" class="m-foil-btn" style="border:none;cursor:pointer"><span class="m-foil-btn-inner">Add to Staff</span></button>
      </form>
    </div>
  </details>
  <?php endif; ?>

  <!-- Staff list -->
  <?php foreach($staff as $s): $p=$s['perms']; ?>
  <div style="padding:13px 14px;background:rgba(255,255,255,.02);border:1px solid #161a28;border-radius:13px;margin-bottom:10px">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
      <div style="width:38px;height:38px;border-radius:50%;background:linear-gradient(140deg,#6fe3ff,#b69cff);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:15px;color:#06070d;overflow:hidden;flex-shrink:0">
        <?php if($s['avatar_url']??''): ?><img src="<?= htmlspecialchars($s['avatar_url']) ?>" style="width:100%;height:100%;object-fit:cover"><?php else: ?><?= strtoupper(substr($s['username']??'?',0,1)) ?><?php endif; ?>
      </div>
      <div style="flex:1;min-width:0">
        <div style="font-weight:600;font-size:14px"><?= htmlspecialchars($s['username']??'—') ?></div>
        <div style="font-family:'GT America Mono',monospace;font-size:9.5px;color:#5a6478"><?= $s['discord_id'] ?></div>
      </div>
      <?php if($s['is_owner']): ?><span class="m-badge m-badge-violet">OWNER</span><?php elseif($is_owner): ?>
      <form method="post" style="display:inline"><input type="hidden" name="action" value="remove"><input type="hidden" name="discord_id" value="<?= $s['discord_id'] ?>"><button onclick="return confirm('Remove from staff?')" style="background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.3);color:#f87171;padding:5px 11px;border-radius:8px;font-family:'GT America Mono',monospace;font-size:10px;cursor:pointer">Remove</button></form>
      <?php endif; ?>
    </div>
    <div style="display:flex;gap:5px;flex-wrap:wrap">
      <?php foreach(['perm_raffles'=>'Raffles','perm_mints'=>'Mints','perm_auctions'=>'Auctions','perm_staff'=>'Staff'] as $pf=>$pl): $val=$s['is_owner']?1:(int)($p[$pf]??0); ?>
      <span class="m-badge <?= $val?'m-badge-green':'m-badge-gray' ?>"><?= $pl ?></span>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if(empty($staff)): ?><div class="m-empty"><span class="m-empty-icon">👥</span><p>No staff yet.</p></div><?php endif; ?>
</div>
<script>
var _st2;
function searchStaff(q){clearTimeout(_st2);var res=document.getElementById('staff-results');if(!q){res.style.display='none';return;}_st2=setTimeout(()=>{fetch('/mobile-admin-staff.php?search_user='+encodeURIComponent(q)).then(r=>r.json()).then(users=>{if(!users.length){res.style.display='none';return;}res.innerHTML=users.map(u=>'<div onclick="selectStaff(\''+u.discord_id+'\',\''+u.username.replace(/\'/g,"\\'")+'\')" style="padding:10px 14px;cursor:pointer;border-bottom:1px solid #161a28;font-size:13px" onmouseover="this.style.background=\'rgba(255,255,255,.04)\'" onmouseout="this.style.background=\'\'">'+u.username+'</div>').join('');res.style.display='block';});},300);}
function selectStaff(did,uname){document.getElementById('add-did').value=did;document.getElementById('add-uname').value=uname;document.getElementById('staff-results').style.display='none';document.getElementById('staff-search').value=uname;var s=document.getElementById('staff-selected');s.textContent='Selected: '+uname;s.style.display='block';}
</script>
</body>
</html>