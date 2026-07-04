<?php
require_once __DIR__.'/../config.php';
require_staff();
require_perm('perm_mints');

$active_admin     = 'mints';
$admin_user       = get_user();
$admin_name       = htmlspecialchars($admin_user['username'] ?? 'Admin');
$admin_initial    = strtoupper(substr($admin_user['username'] ?? 'A', 0, 1));
$admin_avatar_url = get_avatar_url($admin_user['discord_id'], $admin_user['avatar'] ?? '');
$uid = $admin_user['discord_id'];
$msg = ''; $msg_type = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $md = $_POST['mint_date']??'';
        $status = $md ? 'approved' : 'tba';
        sb('bs_mints')->insert(['name'=>$_POST['name'],'description'=>$_POST['description']??'','image_url'=>$_POST['image_url']??'','mint_url'=>$_POST['mint_url']??'','chain'=>$_POST['chain']??'Ethereum','mint_date'=>($md?:null),'mint_time'=>($_POST['mint_time']?:null),'price'=>$_POST['price']??'','supply'=>$_POST['supply']??'','twitter'=>ltrim($_POST['twitter']??'','@'),'status'=>$status,'submitted_by'=>$uid,'approved_by'=>$uid]);
        header('Location: /bs-admin/mints.php?ok=added'); exit;
    }
    if ($action === 'update_mint') {
        $id = (int)$_POST['id'];
        $tw = ltrim(trim($_POST['twitter']??''),'@');
        $update = ['name'=>trim($_POST['name']??''),'chain'=>trim($_POST['chain']??'Ethereum'),'price'=>trim($_POST['price']??''),'supply'=>trim($_POST['supply']??''),'twitter'=>$tw,'mint_url'=>trim($_POST['mint_url']??''),'mint_date'=>$_POST['mint_date']?:null,'description'=>trim($_POST['description']??'')];
        if ($tw) {
            $existing = sb('bs_mints')->eq('id',(string)$id)->select('twitter,image_url')->first();
            if (($existing['twitter']??'') !== $tw || !($existing['image_url']??'')) {
                $ctx=stream_context_create(['http'=>['timeout'=>3,'header'=>'X-API-Key: 2d725456-9686-4ecf-9cff-a9e0c8f74041']]);
                $raw=@file_get_contents("https://api.sorsa.io/v3/info?username=$tw",false,$ctx);
                if($raw){$info=json_decode($raw,true);$banner=$info['banner_url']??'';if($banner)$update['image_url']=$banner;}
                if(empty($update['image_url']))$update['image_url']="https://unavatar.io/twitter/$tw";
            }
        }
        $update['status'] = !empty($update['mint_date'])?'approved':'tba';
        sb('bs_mints')->eq('id',(string)$id)->update($update);
        header('Location: /bs-admin/mints.php?ok=updated'); exit;
    }
    if ($action === 'delete') { sb('bs_mints')->eq('id',(int)$_POST['id'])->delete(); header('Location: /bs-admin/mints.php'); exit; }
    if ($action === 'approve_sub') {
        $s = sb('bs_mint_submissions')->eq('id',(int)$_POST['sub_id'])->first();
        if($s){$st=$s['mint_date']?'approved':'tba';sb('bs_mints')->insert(['name'=>$s['name'],'description'=>'','image_url'=>$s['image_url']??'','mint_url'=>$s['mint_url']??'','chain'=>$s['chain']??'Ethereum','mint_date'=>$s['mint_date']?:null,'mint_time'=>$s['mint_time']?:null,'price'=>$s['price']??'','supply'=>$s['supply']??'','twitter'=>ltrim($s['twitter']??'','@'),'status'=>$st,'submitted_by'=>$s['discord_id'],'approved_by'=>$uid]);sb('bs_mint_submissions')->eq('id',(int)$_POST['sub_id'])->update(['status'=>'approved']);}
        header('Location: /bs-admin/mints.php?ok=sub_approved'); exit;
    }
    if ($action === 'reject_sub') { sb('bs_mint_submissions')->eq('id',(int)$_POST['sub_id'])->update(['status'=>'rejected']); header('Location: /bs-admin/mints.php'); exit; }
    if ($action === 'remove_auction') { sb('bs_auctions')->eq('id',(string)(int)$_POST['id'])->update(['status'=>'ended']); header('Location: /bs-admin/mints.php?ok=removed'); exit; }
    if ($action === 'remove_raffle') { sb('bs_raffles')->eq('id',(string)(int)$_POST['id'])->update(['status'=>'cancelled']); header('Location: /bs-admin/mints.php?ok=removed'); exit; }
}

// Data
try { $mints      = sb('bs_mints')->order('mint_date',false)->get(); } catch(Exception $e) { $mints=[]; }
try { $subs       = sb('bs_mint_submissions')->eq('status','pending')->get(); } catch(Exception $e) { $subs=[]; }
try { $live_aucs  = sb('bs_auctions')->eq('status','active')->select('id,title,ends_at,image_url,chain,reward_type')->get(); } catch(Exception $e) { $live_aucs=[]; }
try { $live_rafs  = sb('bs_raffles')->eq('status','active')->select('id,title,end_date,image_url,chain')->get(); } catch(Exception $e) { $live_rafs=[]; }

$view_id = (int)($_GET['view'] ?? 0);
$edit_mint = $view_id ? sb('bs_mints')->eq('id',(string)$view_id)->first() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Mints · Admin</title>
<link rel="stylesheet" href="/bs_fonts.css?v=1783164697">
<link rel="stylesheet" href="/bs_admin.css?v=1783164697">
</head>
<body>
<div class="adm-layout">
<?php require_once __DIR__.'/bs_admin_sidebar.php'; ?>
<main class="adm-main">

  <div class="adm-topbar">
    <div class="adm-breadcrumb">ADMIN / <span class="bc-active">MINTS</span></div>
    <div class="adm-topbar-right">
      <div class="adm-avatar"><?php if($admin_avatar_url): ?><img src="<?= htmlspecialchars($admin_avatar_url) ?>" alt=""><?php else: ?><?= $admin_initial ?><?php endif; ?></div>
    </div>
  </div>

  <?php if (isset($_GET['ok'])): ?>
  <div class="adm-notice adm-notice-green">✓ <?= ['added'=>'Mint added!','updated'=>'Updated!','sub_approved'=>'Submission approved!','removed'=>'Removed!'][$_GET['ok']] ?? 'Done!' ?></div>
  <?php endif; ?>

  <div class="adm-page-header">
    <div>
      <h1 class="adm-page-title">Manage Mints</h1>
      <p class="adm-page-sub">Add mints to the calendar and approve community submissions.</p>
    </div>
    <button onclick="document.getElementById('add-mint-modal').classList.add('open')" class="adm-foil-btn"><span class="adm-foil-inner">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6fe3ff" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>Add Mint
    </span></button>
  </div>

  <!-- Pending submissions -->
  <?php if (!empty($subs)): ?>
  <div class="adm-panel" style="margin-bottom:22px">
    <div class="adm-panel-hdr">
      <span class="adm-panel-hdr-label">PENDING SUBMISSIONS</span>
      <span class="adm-badge adm-badge-orange"><?= count($subs) ?></span>
    </div>
    <?php foreach ($subs as $s): ?>
    <div class="adm-row">
      <?php if($s['image_url']??''): ?><img src="<?= htmlspecialchars($s['image_url']) ?>" class="adm-thumb"><?php endif; ?>
      <div style="flex:1;min-width:0">
        <div style="font-weight:600;font-size:13.5px"><?= htmlspecialchars($s['name']) ?></div>
        <div style="font-family:'GT America Mono',monospace;font-size:10px;color:#7a8398;margin-top:2px">
          <?= htmlspecialchars($s['chain']??'ETH') ?> · <?= $s['mint_date'] ? date('M j, Y',strtotime($s['mint_date'])) : 'TBA' ?><?= !empty($s['price']) ? ' · '.$s['price'] : '' ?> · by @<?= htmlspecialchars(get_username($s['discord_id']??'')) ?>
        </div>
      </div>
      <div class="adm-action-btns">
        <form method="post" style="display:inline"><input type="hidden" name="action" value="approve_sub"><input type="hidden" name="sub_id" value="<?= $s['id'] ?>">
          <button class="adm-btn adm-btn-green adm-btn-sm">✓ Approve</button>
        </form>
        <form method="post" style="display:inline"><input type="hidden" name="action" value="reject_sub"><input type="hidden" name="sub_id" value="<?= $s['id'] ?>">
          <button class="adm-btn adm-btn-red adm-btn-sm">✕ Reject</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Live auctions + raffles on calendar -->
  <?php if (!empty($live_aucs) || !empty($live_rafs)): ?>
  <div class="adm-panel" style="margin-bottom:22px">
    <div class="adm-panel-hdr"><span class="adm-panel-hdr-label">LIVE ON CALENDAR (AUCTIONS & RAFFLES)</span></div>
    <?php foreach (array_merge(array_map(fn($a)=>array_merge($a,['_type'=>'auction']),$live_aucs), array_map(fn($r)=>array_merge($r,['_type'=>'raffle','ends_at'=>$r['end_date']??null]),$live_rafs)) as $it): ?>
    <div class="adm-row">
      <?php if($it['image_url']??''): ?><img src="<?= htmlspecialchars($it['image_url']) ?>" class="adm-thumb"><?php endif; ?>
      <div style="flex:1;min-width:0">
        <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($it['title']) ?></div>
        <div style="font-size:11px;color:#7a8398;margin-top:2px"><?= htmlspecialchars($it['chain']??'ETH') ?> · ends <?= $it['ends_at']?date('M j, H:i',strtotime($it['ends_at'])):'?' ?></div>
      </div>
      <span class="adm-badge <?= $it['_type']==='auction'?'adm-badge-violet':'adm-badge-cyan' ?>"><?= strtoupper($it['_type']) ?></span>
      <form method="post" style="display:inline"><input type="hidden" name="action" value="remove_<?= $it['_type'] ?>"><input type="hidden" name="id" value="<?= $it['id'] ?>">
        <button class="adm-btn adm-btn-ghost adm-btn-sm" onclick="return confirm('Remove from calendar?')">Remove</button>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Mints list -->
  <div class="adm-table-wrap">
    <table class="adm-table">
      <thead><tr><th>MINT</th><th>CHAIN</th><th>DATE</th><th>PRICE</th><th>STATUS</th><th>ACTIONS</th></tr></thead>
      <tbody>
      <?php foreach ($mints as $m): ?>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <?php if($m['image_url']??''): ?><img src="<?= htmlspecialchars($m['image_url']) ?>" class="adm-thumb"><?php endif; ?>
            <div><div style="font-weight:600;font-size:13px"><?= htmlspecialchars($m['name']) ?></div><div style="font-size:10px;color:#5a6478"><?= $m['twitter']?'@'.htmlspecialchars($m['twitter']):'' ?></div></div>
          </div>
        </td>
        <td><span class="adm-badge adm-badge-cyan"><?= htmlspecialchars($m['chain']??'ETH') ?></span></td>
        <td style="font-family:'GT America Mono',monospace;font-size:11px;color:#7a8398"><?= $m['mint_date']?date('M j, Y',strtotime($m['mint_date'])):'TBA' ?></td>
        <td style="font-family:'GT America Mono',monospace;font-size:11px"><?= htmlspecialchars($m['price']??'—') ?></td>
        <td><span class="adm-badge adm-badge-<?= $m['status']==='approved'?'green':($m['status']==='tba'?'orange':'gray') ?>"><?= $m['status'] ?></span></td>
        <td>
          <div class="adm-action-btns">
            <button class="adm-icon-btn adm-icon-btn-ghost" onclick="openEditModal(<?= htmlspecialchars(json_encode($m), ENT_QUOTES) ?>)" title="Edit">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#aab2c5" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <form method="post" style="display:inline"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $m['id'] ?>">
              <button type="submit" class="adm-icon-btn adm-icon-btn-red" onclick="return confirm('Delete mint?')" title="Delete">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
              </button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($mints)): ?><tr><td colspan="6" style="text-align:center;padding:40px;color:#5a6478">No mints yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

</main>
</div>

<!-- Add Mint Modal -->
<div class="adm-modal-overlay" id="add-mint-modal">
  <div class="adm-modal" style="max-height:90vh;overflow-y:auto">
    <div class="adm-modal-hdr">
      <div class="adm-modal-title">Add Mint to Calendar</div>
      <div class="adm-modal-close" onclick="document.getElementById('add-mint-modal').classList.remove('open')">×</div>
    </div>
    <div class="adm-modal-body">
      <form method="post">
        <input type="hidden" name="action" value="add">
        <div class="adm-field adm-field-row" style="grid-template-columns:1fr 1fr">
          <div><label>NAME <span class="req">*</span></label><input class="adm-input" name="name" required placeholder="Project Name"></div>
          <div><label>CHAIN</label><div class="adm-select-wrap"><select class="adm-input" name="chain"><option>Ethereum</option><option>Solana</option><option>Bitcoin</option><option>Base</option><option>Polygon</option><option>Other</option></select><svg class="adm-select-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#5a6478" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></div></div>
        </div>
        <div class="adm-field adm-field-row" style="grid-template-columns:1fr 1fr">
          <div><label>MINT DATE</label><input class="adm-input" name="mint_date" type="date"></div>
          <div><label>MINT TIME</label><input class="adm-input" name="mint_time" type="time"></div>
        </div>
        <div class="adm-field adm-field-row" style="grid-template-columns:1fr 1fr">
          <div><label>PRICE</label><input class="adm-input adm-input-mono" name="price" placeholder="0.05 ETH"></div>
          <div><label>SUPPLY</label><input class="adm-input adm-input-mono" name="supply" placeholder="3838"></div>
        </div>
        <div class="adm-field adm-field-row" style="grid-template-columns:1fr 1fr">
          <div>
            <label>X HANDLE — banner auto-loads</label>
            <input class="adm-input adm-input-mono" id="add_tw" name="twitter" placeholder="ProjectHandle" oninput="doAddBanner()">
            <input type="hidden" name="image_url" id="add_img">
            <img id="add_prev" src="" style="display:none;width:100%;height:50px;object-fit:cover;border-radius:8px;margin-top:6px;border:1px solid #232838">
          </div>
          <div><label>MINT URL</label><input class="adm-input" name="mint_url" placeholder="https://…"></div>
        </div>
        <div class="adm-field"><label>DESCRIPTION</label><textarea class="adm-input" name="description" style="min-height:64px;resize:vertical"></textarea></div>
        <button type="submit" class="adm-foil-btn" style="width:100%;border:none;cursor:pointer"><span class="adm-foil-inner" style="width:100%;justify-content:center">Add to Calendar</span></button>
      </form>
    </div>
  </div>
</div>

<!-- Edit Mint Modal -->
<div class="adm-modal-overlay" id="edit-mint-modal">
  <div class="adm-modal" style="max-height:90vh;overflow-y:auto">
    <div class="adm-modal-hdr">
      <div class="adm-modal-title">Edit Mint</div>
      <div class="adm-modal-close" onclick="document.getElementById('edit-mint-modal').classList.remove('open')">×</div>
    </div>
    <div class="adm-modal-body">
      <form method="post" id="edit-mint-form">
        <input type="hidden" name="action" value="update_mint">
        <input type="hidden" name="id" id="edit-id">
        <div class="adm-field adm-field-row" style="grid-template-columns:1fr 1fr">
          <div><label>NAME</label><input class="adm-input" name="name" id="edit-name"></div>
          <div><label>CHAIN</label><div class="adm-select-wrap"><select class="adm-input" name="chain" id="edit-chain"><option>Ethereum</option><option>Solana</option><option>Bitcoin</option><option>Base</option><option>Polygon</option><option>Other</option></select><svg class="adm-select-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#5a6478" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></div></div>
        </div>
        <div class="adm-field adm-field-row" style="grid-template-columns:1fr 1fr">
          <div><label>MINT DATE</label><input class="adm-input" name="mint_date" id="edit-date" type="date"></div>
          <div><label>PRICE</label><input class="adm-input adm-input-mono" name="price" id="edit-price"></div>
        </div>
        <div class="adm-field adm-field-row" style="grid-template-columns:1fr 1fr">
          <div><label>SUPPLY</label><input class="adm-input adm-input-mono" name="supply" id="edit-supply"></div>
          <div><label>X HANDLE</label><input class="adm-input adm-input-mono" name="twitter" id="edit-twitter"></div>
        </div>
        <div class="adm-field"><label>MINT URL</label><input class="adm-input" name="mint_url" id="edit-mint-url"></div>
        <div class="adm-field"><label>DESCRIPTION</label><textarea class="adm-input" name="description" id="edit-desc" style="min-height:64px;resize:vertical"></textarea></div>
        <button type="submit" class="adm-foil-btn" style="width:100%;border:none;cursor:pointer"><span class="adm-foil-inner" style="width:100%;justify-content:center">Save Changes</span></button>
      </form>
    </div>
  </div>
</div>

<script>
document.getElementById('add-mint-modal').addEventListener('click',function(e){if(e.target===this)this.classList.remove('open');});
document.getElementById('edit-mint-modal').addEventListener('click',function(e){if(e.target===this)this.classList.remove('open');});
var _abt;
function doAddBanner(){clearTimeout(_abt);var h=document.getElementById('add_tw').value.trim().replace('@','');if(h.length<2){return;}_abt=setTimeout(()=>{fetch('/auction-form.php?fetch_banner=1&handle='+encodeURIComponent(h)).then(r=>r.json()).then(d=>{if(d.banner){document.getElementById('add_img').value=d.banner;var p=document.getElementById('add_prev');p.src=d.banner;p.onload=()=>p.style.display='block';}}).catch(()=>{});},800);}
function openEditModal(m){
  document.getElementById('edit-id').value=m.id||'';
  document.getElementById('edit-name').value=m.name||'';
  document.getElementById('edit-chain').value=m.chain||'Ethereum';
  document.getElementById('edit-date').value=m.mint_date?m.mint_date.slice(0,10):'';
  document.getElementById('edit-price').value=m.price||'';
  document.getElementById('edit-supply').value=m.supply||'';
  document.getElementById('edit-twitter').value=(m.twitter||'').replace(/^@/,'');
  document.getElementById('edit-mint-url').value=m.mint_url||'';
  document.getElementById('edit-desc').value=m.description||'';
  document.getElementById('edit-mint-modal').classList.add('open');
}
</script>
</body>
</html>