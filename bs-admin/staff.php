<?php /* staff.php */
require_once __DIR__.'/../config.php';
if (!is_staff()) { http_response_code(403); die('Access denied.'); }

$active_admin     = 'staff';
$admin_user       = get_user();
$admin_name       = htmlspecialchars($admin_user['username'] ?? 'Admin');
$admin_initial    = strtoupper(substr($admin_user['username'] ?? 'A', 0, 1));
$admin_avatar_url = get_avatar_url($admin_user['discord_id'], $admin_user['avatar'] ?? '');
$uid = $admin_user['discord_id'];

global $STAFF_IDS;
$is_owner = in_array($uid, $STAFF_IDS ?? []);

// AJAX search
if (isset($_GET['search_user'])) {
    header('Content-Type: application/json');
    $q = trim($_GET['search_user']);
    if (strlen($q) < 1) { echo '[]'; exit; }
    $s = db()->prepare("SELECT discord_id,username,avatar FROM bs_users WHERE username LIKE ? ORDER BY username ASC LIMIT 10");
    $s->execute(["%$q%"]);
    echo json_encode($s->fetchAll());
    exit;
}

$msg = ''; $msg_type = 'ok';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$is_owner) { $msg='error:Only owners can manage staff.'; $msg_type='err'; }
    else {
        $action = $_POST['action'] ?? '';
        if ($action === 'add') {
            $did   = trim($_POST['discord_id'] ?? '');
            $uname = trim($_POST['username'] ?? '');
            if (!$did) { $msg='error:No user selected.'; $msg_type='err'; }
            else {
                if (!$uname) { $u=db()->prepare("SELECT username FROM bs_users WHERE discord_id=?");$u->execute([$did]);$uname=$u->fetchColumn()??''; }
                if (!$uname) { $msg='error:User not found — ask them to sign in first.'; $msg_type='err'; }
                else {
                    $perms=['perm_raffles'=>isset($_POST['perm_raffles'])?1:0,'perm_mints'=>isset($_POST['perm_mints'])?1:0,'perm_auctions'=>isset($_POST['perm_auctions'])?1:0,'perm_staff'=>isset($_POST['perm_staff'])?1:0];
                    sb('bs_staff')->upsert(['discord_id'=>$did,'username'=>$uname,'added_by'=>$uid],'discord_id');
                    sb('bs_staff_permissions')->upsert(array_merge(['discord_id'=>$did,'guild_id'=>DISCORD_GUILD_ID],$perms),'discord_id');
                    sb('bs_users')->eq('discord_id',$did)->update(['is_staff'=>1]);
                    header('Location: /bs-admin/staff.php?ok='.urlencode($uname)); exit;
                }
            }
        }
        if ($action === 'update_perms') {
            $did=$_POST['discord_id'];
            $vals=['perm_raffles'=>isset($_POST['perm_raffles'])?1:0,'perm_mints'=>isset($_POST['perm_mints'])?1:0,'perm_auctions'=>isset($_POST['perm_auctions'])?1:0,'perm_staff'=>isset($_POST['perm_staff'])?1:0];
            sb('bs_staff_permissions')->upsert(array_merge(['discord_id'=>$did,'guild_id'=>DISCORD_GUILD_ID],$vals),'discord_id');
            header('Location: /bs-admin/staff.php?ok=saved'); exit;
        }
        if ($action === 'remove') {
            $did=$_POST['discord_id'];
            sb('bs_staff')->eq('discord_id',$did)->delete();
            sb('bs_staff_permissions')->eq('discord_id',$did)->delete();
            sb('bs_users')->eq('discord_id',$did)->update(['is_staff'=>0]);
            header('Location: /bs-admin/staff.php'); exit;
        }
    }
}

// Fetch staff + permissions
$staff_rows = sb('bs_staff')->get();
$staff = [];
foreach ($staff_rows as $s) {
    $p = sb('bs_staff_permissions')->eq('discord_id',$s['discord_id'])->first();
    $av = get_avatar_url($s['discord_id'], '');
    $staff[] = array_merge($s, ['perms'=>$p??[],'avatar_url'=>$av,'is_owner'=>in_array($s['discord_id'],$STAFF_IDS??[])]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Staff · Admin</title>
<link rel="stylesheet" href="/bs_fonts.css?v=1783164697">
<link rel="stylesheet" href="/bs_admin.css?v=1783164697">
</head>
<body>
<div class="adm-layout">
<?php require_once __DIR__.'/bs_admin_sidebar.php'; ?>
<main class="adm-main">

  <div class="adm-topbar">
    <div class="adm-breadcrumb">ADMIN / <span class="bc-active">STAFF</span></div>
    <div class="adm-topbar-right"><div class="adm-avatar"><?php if($admin_avatar_url): ?><img src="<?= htmlspecialchars($admin_avatar_url) ?>" alt=""><?php else: ?><?= $admin_initial ?><?php endif; ?></div></div>
  </div>

  <?php if (isset($_GET['ok'])): ?><div class="adm-notice adm-notice-green">✓ <?= htmlspecialchars($_GET['ok']==='saved'?'Permissions saved!':'Staff member added: '.$_GET['ok']) ?></div><?php endif; ?>
  <?php if ($msg): ?><div class="adm-notice adm-notice-<?= $msg_type==='err'?'red':'green' ?>"><?= htmlspecialchars(str_replace('error:','',$msg)) ?></div><?php endif; ?>

  <div class="adm-page-header">
    <div><h1 class="adm-page-title">Staff</h1><p class="adm-page-sub">Manage your team members and their permissions.</p></div>
    <?php if($is_owner): ?>
    <button onclick="document.getElementById('add-staff-modal').classList.add('open')" class="adm-foil-btn">
      <span class="adm-foil-inner"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6fe3ff" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>Add Staff</span>
    </button>
    <?php endif; ?>
  </div>

  <div class="adm-panel" style="font-size:11.5px;color:#7a8398;padding:10px 16px;margin-bottom:18px">Toggle a permission to grant or revoke access · Owners always have full access.</div>

  <!-- Staff table -->
  <div class="adm-table-wrap">
    <table class="adm-table">
      <thead><tr><th>MEMBER</th><th style="text-align:center">RAFFLES</th><th style="text-align:center">MINTS</th><th style="text-align:center">AUCTIONS</th><th style="text-align:center">STAFF</th><th>ACTIONS</th></tr></thead>
      <tbody>
      <?php foreach ($staff as $s): $p=$s['perms']; ?>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <div class="adm-avatar-sm"><?php if($s['avatar_url']): ?><img src="<?= htmlspecialchars($s['avatar_url']) ?>" style="width:100%;height:100%;object-fit:cover"><?php else: ?><?= strtoupper(substr($s['username']??'?',0,1)) ?><?php endif; ?></div>
            <div>
              <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($s['username']??'—') ?></div>
              <div style="font-family:'GT America Mono',monospace;font-size:9.5px;color:#5a6478"><?= $s['discord_id'] ?></div>
            </div>
            <?php if($s['is_owner']): ?><span class="adm-badge adm-badge-violet">OWNER</span><?php endif; ?>
          </div>
        </td>
        <?php $perms_fields=['perm_raffles','perm_mints','perm_auctions','perm_staff']; ?>
        <?php foreach ($perms_fields as $pf): $val=$s['is_owner']?1:(int)($p[$pf]??0); ?>
        <td style="text-align:center">
          <?php if(!$s['is_owner'] && $is_owner): ?>
          <form method="post" style="display:inline">
            <input type="hidden" name="action" value="update_perms">
            <input type="hidden" name="discord_id" value="<?= $s['discord_id'] ?>">
            <?php foreach ($perms_fields as $pf2): if($pf2!==$pf): ?><input type="hidden" name="<?= $pf2 ?>" value="<?= (int)($p[$pf2]??0) ?>"><?php endif; endforeach; ?>
            <label class="adm-toggle"><input type="checkbox" name="<?= $pf ?>" value="1" <?= $val?'checked':'' ?> onchange="this.form.submit()"><span class="adm-toggle-slider"></span></label>
          </form>
          <?php else: ?>
          <span style="color:<?= $val?'#4ade80':'#5a6478' ?>"><?= $val?'✓':'—' ?></span>
          <?php endif; ?>
        </td>
        <?php endforeach; ?>
        <td>
          <?php if(!$s['is_owner'] && $is_owner): ?>
          <form method="post" style="display:inline"><input type="hidden" name="action" value="remove"><input type="hidden" name="discord_id" value="<?= $s['discord_id'] ?>">
            <button class="adm-btn adm-btn-red adm-btn-sm" onclick="return confirm('Remove from staff?')">Remove</button>
          </form>
          <?php else: ?><span style="color:#5a6478;font-size:11px">—</span><?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($staff)): ?><tr><td colspan="6" style="text-align:center;padding:40px;color:#5a6478">No staff yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

</main>
</div>

<!-- Add Staff Modal -->
<?php if($is_owner): ?>
<div class="adm-modal-overlay" id="add-staff-modal">
  <div class="adm-modal">
    <div class="adm-modal-hdr"><div class="adm-modal-title">Add Staff Member</div><div class="adm-modal-close" onclick="document.getElementById('add-staff-modal').classList.remove('open')">×</div></div>
    <div class="adm-modal-body">
      <form method="post">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="discord_id" id="add-did">
        <input type="hidden" name="username" id="add-uname">
        <div class="adm-field">
          <label>SEARCH BY USERNAME</label>
          <div style="position:relative">
            <input class="adm-input" id="staff-search" placeholder="Type username…" oninput="searchStaff(this.value)" autocomplete="off">
            <div id="staff-results" style="display:none;position:absolute;left:0;right:0;top:100%;margin-top:4px;background:#0d1018;border:1px solid #232838;border-radius:11px;overflow:hidden;z-index:50;box-shadow:0 16px 40px rgba(0,0,0,.6)"></div>
          </div>
          <div id="staff-selected" style="display:none;margin-top:10px;padding:10px 14px;background:rgba(182,156,255,.08);border:1px solid rgba(182,156,255,.25);border-radius:10px;font-size:13px;color:#c9b8ff"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px">
          <?php foreach(['perm_raffles'=>'Raffles','perm_mints'=>'Mints','perm_auctions'=>'Auctions','perm_staff'=>'Staff Mgmt'] as $pf=>$pl): ?>
          <label style="display:flex;align-items:center;gap:10px;padding:10px 13px;border:1px solid #232838;border-radius:10px;cursor:pointer">
            <label class="adm-toggle"><input type="checkbox" name="<?= $pf ?>" value="1"><span class="adm-toggle-slider"></span></label>
            <span style="font-size:13px"><?= $pl ?></span>
          </label>
          <?php endforeach; ?>
        </div>
        <button type="submit" class="adm-foil-btn" style="width:100%;border:none;cursor:pointer"><span class="adm-foil-inner" style="width:100%;justify-content:center">Add to Staff</span></button>
      </form>
    </div>
  </div>
</div>
<script>
document.getElementById('add-staff-modal').addEventListener('click',function(e){if(e.target===this)this.classList.remove('open');});
var _st;
function searchStaff(q){
  clearTimeout(_st);
  var res=document.getElementById('staff-results');
  if(!q){res.style.display='none';return;}
  _st=setTimeout(()=>{
    fetch('/bs-admin/staff.php?search_user='+encodeURIComponent(q)).then(r=>r.json()).then(users=>{
      if(!users.length){res.innerHTML='<div style="padding:14px;font-size:12px;color:#7a8398">No users found</div>';res.style.display='block';return;}
      res.innerHTML=users.map(u=>{const av=u.avatar?`https://cdn.discordapp.com/avatars/${u.discord_id}/${u.avatar}.png`:'';const avHtml=av?`<img src="${av}" style="width:100%;height:100%;object-fit:cover" onerror="this.style.display='none'">`:`<span style="font-weight:700;font-size:11px;color:#06070d">${u.username[0].toUpperCase()}</span>`;return`<div onclick="selectStaff('${u.discord_id}','${u.username.replace(/'/g,"\\'")}','${u.avatar||''}')" style="display:flex;align-items:center;gap:10px;padding:10px 14px;cursor:pointer;border-bottom:1px solid #161a28" onmouseover="this.style.background='rgba(255,255,255,.04)'" onmouseout="this.style.background=''"><div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(140deg,#b69cff,#6fe3ff);display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0">${avHtml}</div><span style="font-size:13px">${u.username}</span></div>`;}).join('');
      res.style.display='block';
    }).catch(()=>{});
  },300);
}
function selectStaff(did,uname,avatar){
  document.getElementById('add-did').value=did;
  document.getElementById('add-uname').value=uname;
  document.getElementById('staff-results').style.display='none';
  document.getElementById('staff-search').value=uname;
  var sel=document.getElementById('staff-selected');
  var avHtml=avatar?`<img src="https://cdn.discordapp.com/avatars/${did}/${avatar}.png" style="width:100%;height:100%;object-fit:cover;border-radius:50%" onerror="this.style.display='none'">`:(uname[0]||'?').toUpperCase();
  sel.innerHTML=`<div style="display:flex;align-items:center;gap:10px"><div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(140deg,#b69cff,#6fe3ff);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;color:#06070d;overflow:hidden;flex-shrink:0">${avHtml}</div><span>Selected: <b>${uname}</b></span></div>`;
  sel.style.display='block';
}
</script>
<?php endif; ?>
</body>
</html>