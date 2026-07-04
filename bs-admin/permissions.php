<?php /* permissions.php */
require_once __DIR__.'/../config.php';
if (!is_staff()) { header('Location: /'); exit; }

$active_admin     = 'permissions';
$admin_user       = get_user();
$admin_name       = htmlspecialchars($admin_user['username'] ?? 'Admin');
$admin_initial    = strtoupper(substr($admin_user['username'] ?? 'A', 0, 1));
$admin_avatar_url = get_avatar_url($admin_user['discord_id'], $admin_user['avatar'] ?? '');
$gid = DISCORD_GUILD_ID;

$ALL_PERMS = [
    'auction_approval' => ['label'=>'Auction Approval',  'desc'=>'Approve/reject auction requests',        'emoji'=>'🔨'],
    'raid_approval'    => ['label'=>'Raid Approval',     'desc'=>'Approve/reject raids & smart follow',    'emoji'=>'⚔️'],
    'post_approval'    => ['label'=>'Post Approval',     'desc'=>'Approve/reject $BLOX post submissions',  'emoji'=>'📋'],
    'raffle_manage'    => ['label'=>'Raffle Management', 'desc'=>'Create and end raffles',                  'emoji'=>'🎟️'],
    'blox_add'         => ['label'=>'$BLOX Add/Remove',  'desc'=>'Add or remove $BLOX from any user',      'emoji'=>'💰'],
    'moderation'       => ['label'=>'Moderation',        'desc'=>'Warn, clearwarns, unverify users',       'emoji'=>'🛡️'],
    'xp_manage'        => ['label'=>'XP Management',     'desc'=>'Give/reset XP for any user',             'emoji'=>'⚡'],
    'twitter_manage'   => ['label'=>'Twitter Campaigns', 'desc'=>'Post tweet campaigns, check claims',     'emoji'=>'🐦'],
    'perm_entries'     => ['label'=>'Entries Viewer',    'desc'=>'View raffle/auction entries with wallets','emoji'=>'👥'],
];

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_id = trim($_POST['target_id'] ?? '');
    $action    = $_POST['action'] ?? '';

    if ($action === 'set' && $target_id) {
        $selected = array_keys(array_filter($_POST['perms'] ?? []));
        sb('bs_permissions')->eq('guild_id',$gid)->eq('discord_id',$target_id)->delete();
        foreach ($selected as $pk) {
            if (isset($ALL_PERMS[$pk])) sb('bs_permissions')->upsert(['guild_id'=>$gid,'discord_id'=>$target_id,'perm_key'=>$pk],'guild_id,discord_id,perm_key');
        }
        $uname = db()->prepare("SELECT username FROM bs_users WHERE discord_id=?"); $uname->execute([$target_id]); $uname=$uname->fetchColumn();
        $msg = $selected ? '✓ Permissions saved for '.($uname?:"user $target_id") : '✓ All permissions revoked for '.($uname?:"user $target_id");
    }
    if ($action === 'revoke' && $target_id) {
        sb('bs_permissions')->eq('guild_id',$gid)->eq('discord_id',$target_id)->delete();
        $msg = '✓ All permissions revoked.';
    }
}

// Load current permissions grouped by user
$all_rows = sb('bs_permissions')->eq('guild_id',$gid)->get();
$approved = [];
foreach ($all_rows as $p) {
    $did=$p['discord_id']; $pk=$p['perm_key']??'';
    if ($pk && !in_array($pk,$approved[$did]??[])) $approved[$did][]=$pk;
}

// AJAX user search
if (isset($_GET['search_user'])) {
    header('Content-Type: application/json');
    $q=trim($_GET['search_user']);
    if(strlen($q)<1){echo '[]';exit;}
    $s=db()->prepare("SELECT discord_id,username,avatar FROM bs_users WHERE username LIKE ? ORDER BY username ASC LIMIT 10");
    $s->execute(["%$q%"]);
    echo json_encode($s->fetchAll()); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Permissions · Admin</title>
<link rel="stylesheet" href="/bs_fonts.css?v=1783164697">
<link rel="stylesheet" href="/bs_admin.css?v=1783164697">
<style>@keyframes dropIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}</style>
</head>
<body>
<div class="adm-layout">
<?php require_once __DIR__.'/bs_admin_sidebar.php'; ?>
<main class="adm-main">

  <div class="adm-topbar">
    <div class="adm-breadcrumb">ADMIN / <span class="bc-active">PERMISSIONS</span></div>
    <div class="adm-topbar-right"><div class="adm-avatar"><?php if($admin_avatar_url): ?><img src="<?= htmlspecialchars($admin_avatar_url) ?>" alt=""><?php else: ?><?= $admin_initial ?><?php endif; ?></div></div>
  </div>

  <?php if ($msg): ?><div class="adm-notice adm-notice-green"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <div class="adm-page-header">
    <div>
      <h1 class="adm-page-title">Permissions</h1>
      <p class="adm-page-sub">Grant specific users access to approve auctions, raids, posts and manage $BLOX.</p>
    </div>
  </div>

  <div class="adm-panel" style="max-width:720px;margin-bottom:32px">
    <div class="adm-panel-hdr"><span class="adm-panel-hdr-label">GRANT PERMISSIONS</span></div>
    <div class="adm-panel-body">
      <p style="font-size:12px;color:#7a8398;margin-bottom:16px;line-height:1.6">Search for a user, then toggle which capabilities to grant. Owners always retain full access regardless.</p>
      <!-- Search -->
      <div class="adm-field">
        <label>SEARCH USER</label>
        <div style="position:relative">
          <input class="adm-input" id="perm-search" placeholder="Type username to search…" oninput="searchPerms(this.value)" autocomplete="off">
          <div id="perm-results" style="display:none;position:absolute;left:0;right:0;top:100%;margin-top:4px;background:#0d1018;border:1px solid #232838;border-radius:11px;overflow:hidden;z-index:50;box-shadow:0 16px 40px rgba(0,0,0,.6);animation:dropIn .16s ease"></div>
        </div>
      </div>
      <!-- Selected user + perms form -->
      <div id="perm-form-wrap" style="display:none">
        <div id="perm-selected-info" style="display:flex;align-items:center;gap:12px;padding:12px 14px;background:rgba(182,156,255,.07);border:1px solid rgba(182,156,255,.2);border-radius:12px;margin-bottom:18px">
          <div id="perm-sel-avatar" style="width:32px;height:32px;border-radius:50%;background:linear-gradient(140deg,#b69cff,#6fe3ff);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:#06070d;flex-shrink:0"></div>
          <div><div id="perm-sel-name" style="font-weight:600;font-size:14px"></div><div id="perm-sel-id" style="font-family:'GT America Mono',monospace;font-size:10px;color:#7a8398;margin-top:2px"></div></div>
          <button type="button" onclick="clearPermsUser()" style="margin-left:auto;background:none;border:none;color:#7a8398;cursor:pointer;font-size:18px">×</button>
        </div>
        <form method="post" id="perm-form">
          <input type="hidden" name="action" value="set">
          <input type="hidden" name="target_id" id="perm-target-id">
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:18px" id="perms-grid"></div>
          <div style="display:flex;gap:10px">
            <button type="submit" class="adm-foil-btn" style="flex:1;border:none;cursor:pointer"><span class="adm-foil-inner" style="width:100%;justify-content:center">Save Permissions</span></button>
            <button type="button" onclick="revokeAll()" class="adm-btn adm-btn-red" style="flex-shrink:0">Revoke All</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Role defaults reference -->
  <div class="adm-panel">
    <div class="adm-panel-hdr"><span class="adm-panel-hdr-label">CURRENT PERMISSIONS</span><span style="font-family:'GT America Mono',monospace;font-size:11px;color:#7a8398"><?= count($approved) ?> users</span></div>
    <?php if(empty($approved)): ?>
    <div style="padding:32px;text-align:center;color:#5a6478;font-size:13px">No permissions granted yet.</div>
    <?php else: ?>
    <div class="adm-table-wrap" style="border:none;border-radius:0;margin-bottom:0">
      <table class="adm-table">
        <thead><tr><th>USER</th><th>CAPABILITIES</th><th>ACTIONS</th></tr></thead>
        <tbody>
        <?php foreach ($approved as $did=>$pks):
          $u=db()->prepare("SELECT username,avatar FROM bs_users WHERE discord_id=?");$u->execute([$did]);$u=$u->fetch();
          $uname=$u['username']??('…'.substr($did,-4));
          $av_url = $u ? get_avatar_url($did,$u['avatar']??'') : '';
        ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div class="adm-avatar-sm"><img src="<?= htmlspecialchars($av_url) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"><span style="display:none;width:100%;height:100%;align-items:center;justify-content:center;font-weight:700;font-size:12px;color:#06070d"><?= strtoupper(substr($uname,0,1)) ?></span></div>
              <div><div style="font-weight:600;font-size:13px"><?= htmlspecialchars($uname) ?></div><div style="font-family:'GT America Mono',monospace;font-size:9.5px;color:#5a6478"><?= $did ?></div></div>
            </div>
          </td>
          <td>
            <div style="display:flex;gap:5px;flex-wrap:wrap">
              <?php foreach ($pks as $pk): if(isset($ALL_PERMS[$pk])): ?>
              <span class="adm-badge adm-badge-violet"><?= $ALL_PERMS[$pk]['emoji'] ?> <?= $ALL_PERMS[$pk]['label'] ?></span>
              <?php endif; endforeach; ?>
            </div>
          </td>
          <td>
            <button onclick="loadUserPerms('<?= $did ?>','<?= addslashes($uname) ?>',<?= htmlspecialchars(json_encode($pks)) ?>)" class="adm-btn adm-btn-ghost adm-btn-sm">Edit</button>
            <form method="post" style="display:inline"><input type="hidden" name="action" value="revoke"><input type="hidden" name="target_id" value="<?= $did ?>">
              <button class="adm-btn adm-btn-red adm-btn-sm" onclick="return confirm('Revoke all for this user?')">Revoke</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

</main>
</div>

<script>
var _ALL_PERMS = <?= json_encode($ALL_PERMS) ?>;
var _pt;
function searchPerms(q){
  clearTimeout(_pt);
  var res=document.getElementById('perm-results');
  if(!q){res.style.display='none';return;}
  _pt=setTimeout(()=>{
    fetch('/bs-admin/permissions.php?search_user='+encodeURIComponent(q)).then(r=>r.json()).then(users=>{
      if(!users.length){res.innerHTML='<div style="padding:14px;font-size:12px;color:#7a8398">No users found.</div>';res.style.display='block';return;}
      res.innerHTML=users.map(u=>{const av=u.avatar?`https://cdn.discordapp.com/avatars/${u.discord_id}/${u.avatar}.png`:'';const avHtml=av?`<img src="${av}" style="width:100%;height:100%;object-fit:cover;border-radius:50%" onerror="this.style.display='none'">`:`<span style="font-weight:700;font-size:11px;color:#06070d">${u.username[0].toUpperCase()}</span>`;return`<div onclick="selectPermUser('${u.discord_id}','${u.username.replace(/'/g,"\\'")}',[])" style="display:flex;align-items:center;gap:10px;padding:10px 14px;cursor:pointer;border-bottom:1px solid #161a28" onmouseover="this.style.background='rgba(255,255,255,.04)'" onmouseout="this.style.background=''"><div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(140deg,#b69cff,#6fe3ff);display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0">${avHtml}</div><span style="font-size:13px">${u.username}</span></div>`;}).join('');
      res.style.display='block';
    });
  },300);
}
function selectPermUser(did,uname,currentPerms){
  document.getElementById('perm-results').style.display='none';
  document.getElementById('perm-search').value=uname;
  loadUserPerms(did,uname,currentPerms);
}
function loadUserPerms(did,uname,currentPerms){
  document.getElementById('perm-target-id').value=did;
  document.getElementById('perm-sel-name').textContent=uname;
  document.getElementById('perm-sel-id').textContent=did;
  document.getElementById('perm-sel-avatar').textContent=uname[0].toUpperCase();
  var grid=document.getElementById('perms-grid');
  grid.innerHTML=Object.entries(_ALL_PERMS).map(([k,v])=>`<label style="display:flex;align-items:flex-start;gap:10px;padding:12px;border:1px solid #232838;border-radius:11px;cursor:pointer"><label class="adm-toggle" style="margin-top:1px"><input type="checkbox" name="perms[${k}]" value="1" ${currentPerms.includes(k)?'checked':''}><span class="adm-toggle-slider"></span></label><div><div style="font-size:13px;font-weight:500">${v.label}</div><div style="font-size:11px;color:#7a8398;margin-top:2px">${v.desc}</div></div></label>`).join('');
  document.getElementById('perm-form-wrap').style.display='block';
  document.getElementById('perm-search').value=uname;
}
function clearPermsUser(){ document.getElementById('perm-form-wrap').style.display='none'; document.getElementById('perm-search').value=''; }
function revokeAll(){
  if(!confirm('Revoke all permissions?'))return;
  document.querySelector('#perm-form input[name="action"]').value='revoke';
  document.getElementById('perm-form').submit();
}
</script>
</body>
</html>