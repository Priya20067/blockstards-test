<?php
require_once __DIR__.'/config.php';
$user=get_user();if(!$user){header('Location: /bs-auth/discord.php');exit;}if(!is_staff()){header('Location: /mobile.php');exit;}
$uid=$user['discord_id'];$blox_bal=0;try{$blox_bal=get_balance($uid);}catch(Exception $e){}$m_active='admin';
$gid=DISCORD_GUILD_ID;

$ALL_PERMS=['auction_approval'=>['label'=>'Auction Approval','emoji'=>'🔨'],'raid_approval'=>['label'=>'Raid Approval','emoji'=>'⚔️'],'post_approval'=>['label'=>'Post Approval','emoji'=>'📋'],'raffle_manage'=>['label'=>'Raffle Management','emoji'=>'🎟️'],'blox_add'=>['label'=>'$BLOX Add/Remove','emoji'=>'💰'],'moderation'=>['label'=>'Moderation','emoji'=>'🛡️'],'xp_manage'=>['label'=>'XP Management','emoji'=>'⚡'],'twitter_manage'=>['label'=>'Twitter Campaigns','emoji'=>'🐦'],'perm_entries'=>['label'=>'Entries Viewer','emoji'=>'👥']];

// AJAX search
if(isset($_GET['search_user'])){header('Content-Type: application/json');$q=trim($_GET['search_user']);if(strlen($q)<1){echo '[]';exit;}$s=db()->prepare("SELECT discord_id,username FROM bs_users WHERE username LIKE ? ORDER BY username ASC LIMIT 10");$s->execute(["%$q%"]);echo json_encode($s->fetchAll());exit;}

$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $target_id=trim($_POST['target_id']??'');$action=$_POST['action']??'';
    if($action==='set'&&$target_id){
        $selected=array_keys(array_filter($_POST['perms']??[]));
        sb('bs_permissions')->eq('guild_id',$gid)->eq('discord_id',$target_id)->delete();
        foreach($selected as $pk) if(isset($ALL_PERMS[$pk])) sb('bs_permissions')->upsert(['guild_id'=>$gid,'discord_id'=>$target_id,'perm_key'=>$pk],'guild_id,discord_id,perm_key');
        $uname=db()->prepare("SELECT username FROM bs_users WHERE discord_id=?");$uname->execute([$target_id]);$uname=$uname->fetchColumn();
        $msg='✓ Saved for '.($uname?:"user $target_id");
    }
    if($action==='revoke'&&$target_id){sb('bs_permissions')->eq('guild_id',$gid)->eq('discord_id',$target_id)->delete();$msg='✓ Permissions revoked.';}
}

$all_rows=sb('bs_permissions')->eq('guild_id',$gid)->get();$approved=[];
foreach($all_rows as $p){$did=$p['discord_id'];$pk=$p['perm_key']??'';if($pk&&!in_array($pk,$approved[$did]??[]))$approved[$did][]=$pk;}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Permissions · Admin</title>
<link rel="stylesheet" href="/bs_fonts.css?v=1783108465">
<link rel="stylesheet" href="/bs_mobile.css?v=1783108465">
</head>
<body>
<?php require_once __DIR__.'/includes/bs_mobile_nav.php'; ?>
<div class="m-body">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:18px">
    <a href="/mobile-admin.php" style="width:34px;height:34px;border-radius:9px;background:rgba(255,255,255,.04);border:1px solid #161a28;display:flex;align-items:center;justify-content:center;color:#aab2c5;text-decoration:none;font-size:16px">‹</a>
    <h1 class="m-page-title" style="font-size:22px;margin-bottom:0">Permissions</h1>
  </div>
  <?php if($msg): ?><div class="m-notice m-notice-green"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <!-- Grant perms -->
  <div class="m-panel" style="margin-bottom:16px">
    <div class="m-panel-hdr">GRANT PERMISSIONS</div>
    <div style="padding:14px">
      <div class="m-field">
        <label>SEARCH USER</label>
        <input class="m-input" id="perm-search" placeholder="Type username…" oninput="searchPerms(this.value)" autocomplete="off">
        <div id="perm-results" style="display:none;border:1px solid #232838;border-radius:11px;overflow:hidden;margin-top:4px;background:#0d1018"></div>
      </div>
      <div id="perm-form-wrap" style="display:none">
        <div id="perm-sel-info" style="padding:10px 13px;background:rgba(182,156,255,.07);border:1px solid rgba(182,156,255,.2);border-radius:11px;margin-bottom:14px;font-size:12px;color:#c9b8ff"></div>
        <form method="post" id="perm-form">
          <input type="hidden" name="action" value="set">
          <input type="hidden" name="target_id" id="perm-target-id">
          <div id="perms-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px"></div>
          <div style="display:flex;gap:10px">
            <button type="submit" class="m-foil-btn" style="border:none;cursor:pointer;flex:1"><span class="m-foil-btn-inner">Save</span></button>
            <button type="button" onclick="revokeAll()" style="flex:0 0 auto;padding:13px 18px;border-radius:11px;background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.3);color:#f87171;font-family:'GT America Mono',monospace;font-size:12px;cursor:pointer">Revoke All</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Current permissions -->
  <div style="font-family:'GT America Mono',monospace;font-size:9.5px;letter-spacing:.1em;color:#7a8398;margin-bottom:10px">CURRENT PERMISSIONS (<?= count($approved) ?>)</div>
  <?php foreach($approved as $did=>$pks):
    $u=db()->prepare("SELECT username FROM bs_users WHERE discord_id=?");$u->execute([$did]);$uname=$u->fetchColumn()??('…'.substr($did,-4));
  ?>
  <div style="padding:12px 14px;background:rgba(255,255,255,.02);border:1px solid #161a28;border-radius:12px;margin-bottom:8px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
      <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($uname) ?></div>
      <button onclick="loadPerms('<?= $did ?>','<?= addslashes($uname) ?>',<?= htmlspecialchars(json_encode($pks)) ?>)" style="background:rgba(255,255,255,.04);border:1px solid #232838;color:#aab2c5;padding:5px 12px;border-radius:8px;font-family:'GT America Mono',monospace;font-size:10px;cursor:pointer">Edit</button>
    </div>
    <div style="display:flex;gap:5px;flex-wrap:wrap">
      <?php foreach($pks as $pk): if(isset($ALL_PERMS[$pk])): ?><span class="m-badge m-badge-violet"><?= $ALL_PERMS[$pk]['emoji'] ?> <?= $ALL_PERMS[$pk]['label'] ?></span><?php endif; endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if(empty($approved)): ?><div class="m-empty"><span class="m-empty-icon">🔒</span><p>No permissions granted yet.</p></div><?php endif; ?>
</div>
<script>
var _ALL_PERMS=<?= json_encode($ALL_PERMS) ?>;var _pt2;
function searchPerms(q){clearTimeout(_pt2);var res=document.getElementById('perm-results');if(!q){res.style.display='none';return;}_pt2=setTimeout(()=>{fetch('/mobile-admin-permissions.php?search_user='+encodeURIComponent(q)).then(r=>r.json()).then(users=>{if(!users.length){res.style.display='none';return;}res.innerHTML=users.map(u=>'<div onclick="selectPerm(\''+u.discord_id+'\',\''+u.username.replace(/\'/g,"\\'")+'\',[])" style="padding:10px 14px;cursor:pointer;border-bottom:1px solid #161a28;font-size:13px" onmouseover="this.style.background=\'rgba(255,255,255,.04)\'" onmouseout="this.style.background=\'\'">'+u.username+'</div>').join('');res.style.display='block';});},300);}
function selectPerm(did,uname,current){document.getElementById('perm-results').style.display='none';loadPerms(did,uname,current);}
function loadPerms(did,uname,current){document.getElementById('perm-target-id').value=did;document.getElementById('perm-sel-info').textContent='Editing: '+uname;document.getElementById('perm-search').value=uname;var g=document.getElementById('perms-grid');g.innerHTML=Object.entries(_ALL_PERMS).map(([k,v])=>'<label style="display:flex;align-items:center;gap:8px;padding:10px 12px;border:1px solid #232838;border-radius:10px;cursor:pointer"><input type="checkbox" name="perms['+k+']" value="1" '+(current.includes(k)?'checked':'')+'> <span style="font-size:12px">'+v.emoji+' '+v.label+'</span></label>').join('');document.getElementById('perm-form-wrap').style.display='block';}
function revokeAll(){if(!confirm('Revoke all?'))return;document.querySelector('#perm-form input[name="action"]').value='revoke';document.getElementById('perm-form').submit();}
</script>
</body>
</html>