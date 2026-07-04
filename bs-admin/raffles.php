<?php
require_once __DIR__.'/../config.php';
if (!is_staff()) { header('Location: /'); exit; }

$active_admin     = 'raffles';
$admin_user       = get_user();
$admin_name       = htmlspecialchars($admin_user['username'] ?? 'Admin');
$admin_initial    = strtoupper(substr($admin_user['username'] ?? 'A', 0, 1));
$admin_avatar_url = get_avatar_url($admin_user['discord_id'], $admin_user['avatar'] ?? '');
$uid              = $admin_user['discord_id'];
$msg = ''; $msg_type = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title_val   = trim($_POST['title'] ?? '');
        $spots       = max(1,(int)($_POST['spots'] ?? 1));
        $chain       = $_POST['chain'] ?? 'Ethereum';
        $reward_type = $_POST['reward_type'] ?? 'GTD WL';
        $entry_type  = $_POST['entry_type'] ?? 'free';
        $blox_cost   = max(0,(float)($_POST['blox_cost'] ?? 0));
        $twitter     = ltrim(trim($_POST['twitter'] ?? ''), '@');
        $image_url   = trim($_POST['image_url'] ?? '');
        $mint_url    = trim($_POST['mint_url'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $guild_id    = in_array($_POST['guild_id']??'', array_column(BOT_GUILDS,'id')) ? $_POST['guild_id'] : DISCORD_GUILD_ID;
        $supply      = trim($_POST['supply'] ?? '');
        $price       = trim($_POST['price'] ?? '');
        $mint_date   = trim($_POST['mint_date'] ?? '');

        $dur = strtolower(trim($_POST['duration'] ?? '24h'));
        if (strpos($dur,'d') !== false)     $secs = (float)$dur * 86400;
        elseif (strpos($dur,'h') !== false) $secs = (float)$dur * 3600;
        elseif (strpos($dur,'m') !== false) $secs = (float)$dur * 60;
        else                                $secs = 24 * 3600;
        $secs = max(300, min($secs, 86400));

        $parts = [];
        if ($supply)    $parts[] = "Supply: $supply";
        if ($price)     $parts[] = "Mint Price: $price";
        if ($mint_date) $parts[] = "Mint Date: $mint_date";
        if ($chain)     $parts[] = "Chain: $chain";
        if ($description) $parts[] = $description;

        $r = sb('bs_raffles')->insert([
            'title'=>$title_val,'description'=>implode("\n",$parts),'spots'=>$spots,'chain'=>$chain,
            'reward_type'=>$reward_type,'entry_type'=>$entry_type,'blox_cost'=>$blox_cost,
            'end_date'=>date('Y-m-d H:i:s', time()+(int)$secs),'image_url'=>$image_url,
            'mint_url'=>$mint_url,'project_twitter'=>$twitter,'status'=>'pending_approval',
            'created_by'=>$uid,'guild_id'=>$guild_id,
        ]);
        if (($r['code']??0) >= 400) { $msg = 'Create failed: '.json_encode($r['data']); $msg_type='err'; }
        else { header('Location: /bs-admin/raffles.php?ok=created'); exit; }
    }

    if ($action === 'pick_winners') {
        $rid    = (int)$_POST['raffle_id'];
        $raffle = sb('bs_raffles')->eq('id',(string)$rid)->first();
        $entries = sb('bs_raffle_entries')->eq('raffle_id',(string)$rid)->select('discord_id')->get();
        $dids = array_unique(array_column($entries,'discord_id'));
        shuffle($dids);
        $winners = array_slice($dids, 0, (int)($raffle['spots']??1));
        foreach ($winners as $wid) {
            sb('bs_raffle_winners')->upsert(['raffle_id'=>$rid,'discord_id'=>$wid],'raffle_id,discord_id');
            sb('bs_wins')->insert(['discord_id'=>$wid,'win_type'=>'raffle','ref_id'=>(string)$rid,'title'=>$raffle['title']??'Raffle','reward_type'=>$raffle['reward_type']??'GTD WL','chain'=>$raffle['chain']??'Ethereum','image_url'=>$raffle['image_url']??'']);
        }
        sb('bs_raffles')->eq('id',(string)$rid)->update(['status'=>'ended']);
        sb('bs_raffle_announce_queue')->insert(['raffle_id'=>$rid,'guild_id'=>$raffle['guild_id']??DISCORD_GUILD_ID]);
        header('Location: /bs-admin/raffles.php?ok=winners&rid='.$rid); exit;
    }

    if ($action === 'cancel') {
        $rid_c = (int)$_POST['id'];
        sb('bs_raffles')->eq('id',(string)$rid_c)->update(['status'=>'cancelled']);
        header('Location: /bs-admin/raffles.php?ok=cancelled'); exit;
    }
}

// View entrants detail
$view_rid = (int)($_GET['rid'] ?? 0);
$view_raffle = null; $entrants = [];
if ($view_rid) {
    $view_raffle = sb('bs_raffles')->eq('id',(string)$view_rid)->first();
    $entry_rows  = sb('bs_raffle_entries')->eq('raffle_id',(string)$view_rid)->get();
    $winner_ids  = array_column(sb('bs_raffle_winners')->eq('raffle_id',(string)$view_rid)->select('discord_id')->get(),'discord_id');
    foreach ($entry_rows as $e) {
        $u    = sb('bs_users')->eq('discord_id',$e['discord_id'])->select('username,avatar')->first();
        $weth = sb('bs_user_wallets')->eq('discord_id',$e['discord_id'])->eq('chain','Ethereum')->select('address')->first();
        $wsol = sb('bs_user_wallets')->eq('discord_id',$e['discord_id'])->eq('chain','Solana')->select('address')->first();
        $entrants[] = ['discord_id'=>$e['discord_id'],'username'=>$u['username']??('…'.substr($e['discord_id'],-4)),'avatar'=>$u['avatar']??'','eth_wallet'=>$weth['address']??'','sol_wallet'=>$wsol['address']??'','entered_at'=>$e['entered_at']??'','is_winner'=>in_array($e['discord_id'],$winner_ids)?1:0];
    }
    usort($entrants, fn($a,$b)=>$b['is_winner']<=>$a['is_winner']);
}

// Raffle list
$raffle_rows = sb('bs_raffles')->order('id',false)->get();
$raffles = [];
foreach ($raffle_rows as $r) {
    $r['entries'] = count(sb('bs_raffle_entries')->eq('raffle_id',$r['id'])->select('discord_id')->get());
    $raffles[] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Raffles · Admin</title>
<link rel="stylesheet" href="/bs_fonts.css?v=1783164697">
<link rel="stylesheet" href="/bs_admin.css?v=1783164697">
</head>
<body>
<div class="adm-layout">
<?php require_once __DIR__.'/bs_admin_sidebar.php'; ?>
<main class="adm-main">

  <div class="adm-topbar">
    <div class="adm-breadcrumb">ADMIN / <span class="bc-active">RAFFLES</span></div>
    <div class="adm-topbar-right">
      <div class="adm-avatar"><?php if($admin_avatar_url): ?><img src="<?= htmlspecialchars($admin_avatar_url) ?>" alt=""><?php else: ?><?= $admin_initial ?><?php endif; ?></div>
    </div>
  </div>

  <?php if (isset($_GET['ok'])): ?>
  <div class="adm-notice adm-notice-green">✓ <?= $_GET['ok']==='winners'?'Winners picked for raffle #'.$_GET['rid']:'Done!' ?></div>
  <?php endif; if ($msg): ?>
  <div class="adm-notice adm-notice-<?= $msg_type==='ok'?'green':'red' ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <?php if ($view_rid && $view_raffle): ?>
  <!-- ── ENTRANT DETAIL VIEW ── -->
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
    <div>
      <h1 class="adm-page-title" style="font-size:24px"><?= htmlspecialchars($view_raffle['title']) ?></h1>
      <p class="adm-page-sub"><?= count($entrants) ?> entrants · <?= $view_raffle['spots'] ?> spots · <span class="adm-badge adm-badge-<?= $view_raffle['status']==='active'?'green':($view_raffle['status']==='ended'?'gray':'red') ?>"><?= $view_raffle['status'] ?></span></p>
    </div>
    <a href="/bs-admin/raffles.php" class="adm-btn adm-btn-ghost">← Back to list</a>
  </div>

  <?php $winner_list = array_filter($entrants, fn($e)=>$e['is_winner']); ?>
  <?php if ($winner_list): ?>
  <div style="margin-bottom:24px">
    <div style="font-family:'GT America Mono',monospace;font-size:10px;letter-spacing:.12em;color:#e4c590;margin-bottom:12px">🏆 WINNERS (<?= count($winner_list) ?>)</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px">
      <?php foreach ($winner_list as $e): ?>
      <div style="position:relative;display:flex;align-items:center;gap:10px;padding:12px;background:rgba(228,197,144,.05);border:1px solid rgba(228,197,144,.25);border-radius:12px">
        <div style="position:absolute;top:-6px;right:8px;font-size:14px">👑</div>
        <div class="adm-avatar-sm"><?php if($e['avatar']): ?><img src="https://cdn.discordapp.com/avatars/<?= $e['discord_id'] ?>/<?= $e['avatar'] ?>.png" style="width:100%;height:100%;object-fit:cover" onerror="this.style.display='none'"><?php else: ?><?= strtoupper(substr($e['username'],0,1)) ?><?php endif; ?></div>
        <div style="flex:1;min-width:0">
          <div style="font-size:12.5px;font-weight:600"><?= htmlspecialchars($e['username']) ?></div>
          <div style="font-family:'GT America Mono',monospace;font-size:9.5px;color:#5a6478"><?= $e['discord_id'] ?></div>
          <?php if($e['eth_wallet']): ?><div style="font-family:'GT America Mono',monospace;font-size:9px;color:#6fe3ff">⟠ <?= substr($e['eth_wallet'],0,8).'…'.substr($e['eth_wallet'],-4) ?></div><?php endif; ?>
          <?php if($e['sol_wallet']): ?><div style="font-family:'GT America Mono',monospace;font-size:9px;color:#b69cff">◎ <?= substr($e['sol_wallet'],0,8).'…'.substr($e['sol_wallet'],-4) ?></div><?php endif; ?>
          <?php if(!$e['eth_wallet']&&!$e['sol_wallet']): ?><div style="font-size:9px;color:#f87171">No wallet</div><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div style="font-family:'GT America Mono',monospace;font-size:10px;letter-spacing:.12em;color:#7a8398;margin-bottom:12px">ALL ENTRANTS (<?= count($entrants) ?>)</div>
  <?php if (empty($entrants)): ?>
  <div style="text-align:center;padding:40px;color:#5a6478">No entries yet.</div>
  <?php else: ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px">
    <?php foreach ($entrants as $e): ?>
    <div style="display:flex;align-items:center;gap:10px;padding:11px;background:rgba(255,255,255,.02);border:1px solid <?= $e['is_winner']?'rgba(228,197,144,.2)':'#161a28' ?>;border-radius:12px">
      <div class="adm-avatar-sm"><?php if($e['avatar']): ?><img src="https://cdn.discordapp.com/avatars/<?= $e['discord_id'] ?>/<?= $e['avatar'] ?>.png" style="width:100%;height:100%;object-fit:cover" onerror="this.style.display='none'"><?php else: ?><?= strtoupper(substr($e['username'],0,1)) ?><?php endif; ?></div>
      <div style="flex:1;min-width:0">
        <div style="font-size:12.5px;font-weight:600"><?= htmlspecialchars($e['username']) ?></div>
        <div style="font-family:'GT America Mono',monospace;font-size:9px;color:#5a6478"><?= $e['discord_id'] ?></div>
        <?php if($e['eth_wallet']): ?><div style="font-family:'GT America Mono',monospace;font-size:9px;color:#6fe3ff">⟠ <?= substr($e['eth_wallet'],0,6).'…' ?></div><?php endif; ?>
        <?php if($e['sol_wallet']): ?><div style="font-family:'GT America Mono',monospace;font-size:9px;color:#b69cff">◎ <?= substr($e['sol_wallet'],0,6).'…' ?></div><?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php else: ?>
  <!-- ── LIST VIEW ── -->
  <div class="adm-page-header">
    <div>
      <h1 class="adm-page-title">Manage Raffles</h1>
      <p class="adm-page-sub">Create raffles, view entrants and pick winners.</p>
    </div>
    <button onclick="document.getElementById('create-modal').classList.add('open')" class="adm-foil-btn">
      <span class="adm-foil-inner">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6fe3ff" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>Create Raffle
      </span>
    </button>
  </div>

  <!-- Stats -->
  <?php
  $act_cnt = count(array_filter($raffles,fn($r)=>$r['status']==='active'));
  $end_cnt = count(array_filter($raffles,fn($r)=>$r['status']==='ended'));
  $pend_cnt= count(array_filter($raffles,fn($r)=>in_array($r['status'],['pending_approval','pending'])));
  ?>
  <div class="adm-stats adm-stats-3" style="margin-bottom:20px">
    <div class="adm-stat"><div class="adm-stat-val" style="color:#4ade80"><?= $act_cnt ?></div><div class="adm-stat-label">ACTIVE</div></div>
    <div class="adm-stat"><div class="adm-stat-val" style="color:#7a8398"><?= $end_cnt ?></div><div class="adm-stat-label">ENDED</div></div>
    <div class="adm-stat"><div class="adm-stat-val" style="color:#fb923c"><?= $pend_cnt ?></div><div class="adm-stat-label">PENDING</div></div>
  </div>

  <div class="adm-table-wrap">
    <table class="adm-table">
      <thead><tr>
        <th>RAFFLE</th><th>CHAIN</th><th>ENTRY</th><th>SPOTS</th><th>ENTRIES</th><th>ENDS</th><th>STATUS</th><th>ACTIONS</th>
      </tr></thead>
      <tbody>
      <?php foreach ($raffles as $r): ?>
      <tr onclick="location.href='/bs-admin/raffles.php?rid=<?= $r['id'] ?>'">
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <?php if($r['image_url']): ?><img src="<?= htmlspecialchars($r['image_url']) ?>" class="adm-thumb"><?php endif; ?>
            <div>
              <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($r['title']) ?></div>
              <div style="font-size:10px;color:#5a6478">#<?= $r['id'] ?></div>
            </div>
          </div>
        </td>
        <td><span class="adm-badge adm-badge-cyan"><?= htmlspecialchars($r['chain']??'ETH') ?></span></td>
        <td><span class="adm-badge <?= $r['entry_type']==='blox'?'adm-badge-gold':'adm-badge-green' ?>"><?= $r['entry_type']==='blox'?$r['blox_cost'].' $BLOX':'Free' ?></span></td>
        <td style="font-weight:700"><?= $r['spots'] ?></td>
        <td style="font-weight:700;color:#6fe3ff"><?= $r['entries'] ?></td>
        <td style="font-family:'GT America Mono',monospace;font-size:11px;color:#7a8398"><?= $r['end_date']?date('M j, H:i',strtotime($r['end_date'])):'—' ?></td>
        <td><span class="adm-badge adm-badge-<?= $r['status']==='active'?'green':($r['status']==='ended'?'gray':($r['status']==='cancelled'?'red':'orange')) ?>"><?= $r['status'] ?></span></td>
        <td onclick="event.stopPropagation()">
          <div class="adm-action-btns">
            <?php if ($r['status']==='active'): ?>
            <form method="post" style="display:inline">
              <input type="hidden" name="action" value="pick_winners">
              <input type="hidden" name="raffle_id" value="<?= $r['id'] ?>">
              <button type="submit" class="adm-btn adm-btn-green adm-btn-sm" onclick="return confirm('Pick winners?')">🏆 Winners</button>
            </form>
            <form method="post" style="display:inline">
              <input type="hidden" name="action" value="cancel">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <button type="submit" class="adm-btn adm-btn-red adm-btn-sm" onclick="return confirm('Cancel raffle?')">Cancel</button>
            </form>
            <?php else: ?>
            <a href="/bs-admin/raffles.php?rid=<?= $r['id'] ?>" class="adm-btn adm-btn-ghost adm-btn-sm">View</a>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($raffles)): ?>
      <tr><td colspan="8" style="text-align:center;padding:40px;color:#5a6478">No raffles yet. Create one!</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

</main>
</div>

<!-- Create Raffle Modal -->
<div class="adm-modal-overlay" id="create-modal">
  <div class="adm-modal" style="width:600px;max-height:90vh;overflow-y:auto">
    <div class="adm-modal-hdr">
      <div class="adm-modal-title">Create New Raffle</div>
      <div class="adm-modal-close" onclick="document.getElementById('create-modal').classList.remove('open')">×</div>
    </div>
    <div class="adm-modal-body">
      <form method="post">
        <input type="hidden" name="action" value="create">
        <!-- Guild -->
        <div class="adm-field" style="margin-bottom:14px">
          <label>POST TO SERVER</label>
          <div class="adm-guild-sel" id="guild-sel">
            <?php foreach (BOT_GUILDS as $i => $g): ?>
            <div class="adm-guild-btn <?= $i===0?'active':'' ?>" onclick="selGuild('<?= $g['id'] ?>',this)"><?= htmlspecialchars($g['name']) ?></div>
            <?php endforeach; ?>
          </div>
          <input type="hidden" name="guild_id" id="guild_id" value="<?= BOT_GUILDS[0]['id'] ?>">
        </div>
        <div class="adm-field adm-field-row adm-field-row-3">
          <div><label>TITLE <span class="req">*</span></label><input class="adm-input" name="title" required placeholder="Project WL"></div>
          <div><label>CHAIN</label><div class="adm-select-wrap"><select class="adm-input" name="chain"><option>Ethereum</option><option>Solana</option><option>Bitcoin</option><option>Base</option><option>Other</option></select><svg class="adm-select-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#5a6478" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></div></div>
          <div><label>REWARD</label><div class="adm-select-wrap"><select class="adm-input" name="reward_type"><option>GTD WL</option><option>FCFS WL</option><option>USDC</option><option>Other</option></select><svg class="adm-select-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#5a6478" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></div></div>
        </div>
        <div class="adm-field adm-field-row adm-field-row-3">
          <div><label>SPOTS</label><input class="adm-input adm-input-mono" name="spots" type="number" min="1" value="5"></div>
          <div><label>DURATION (max 24h)</label><input class="adm-input adm-input-mono" name="duration" placeholder="10m / 10h / 24h" value="24h"></div>
          <div><label>ENTRY TYPE</label>
            <div class="adm-select-wrap"><select class="adm-input" name="entry_type" onchange="document.getElementById('blox_row').style.display=this.value==='blox'?'block':'none'"><option value="free">Free</option><option value="blox">$BLOX</option></select><svg class="adm-select-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#5a6478" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></div>
          </div>
        </div>
        <div id="blox_row" style="display:none" class="adm-field"><label>$BLOX COST</label><input class="adm-input adm-input-mono" name="blox_cost" type="number" step="0.01" value="1"></div>
        <div class="adm-field adm-field-row adm-field-row-3">
          <div><label>SUPPLY</label><input class="adm-input adm-input-mono" name="supply" placeholder="3838"></div>
          <div><label>PRICE</label><input class="adm-input adm-input-mono" name="price" placeholder="0.05 ETH"></div>
          <div><label>MINT DATE</label><input class="adm-input adm-input-mono" name="mint_date" placeholder="TBA / Dec 25"></div>
        </div>
        <div class="adm-field adm-field-row" style="grid-template-columns:1fr 1fr">
          <div>
            <label>X HANDLE — banner auto-loads</label>
            <input class="adm-input adm-input-mono" id="tw_handle" name="twitter" placeholder="ProjectHandle" oninput="autoLoadBanner()">
            <input type="hidden" name="image_url" id="image_url_inp">
            <img id="banner_preview" src="" style="display:none;width:100%;height:60px;object-fit:cover;border-radius:8px;margin-top:8px;border:1px solid #232838">
          </div>
          <div><label>MINT URL</label><input class="adm-input" name="mint_url" placeholder="https://…"></div>
        </div>
        <div class="adm-field"><label>DESCRIPTION</label><textarea class="adm-input" name="description" placeholder="Any relevant details…" style="min-height:72px;resize:vertical"></textarea></div>
        <button type="submit" class="adm-foil-btn" style="width:100%;border:none;cursor:pointer"><span class="adm-foil-inner" style="width:100%;justify-content:center">🎟 Post Raffle to Discord</span></button>
      </form>
    </div>
  </div>
</div>

<script>
document.getElementById('create-modal').addEventListener('click',function(e){if(e.target===this)this.classList.remove('open');});
function selGuild(id,el){document.querySelectorAll('.adm-guild-btn').forEach(b=>b.classList.remove('active'));el.classList.add('active');document.getElementById('guild_id').value=id;}
var _bt;
function autoLoadBanner(){
  clearTimeout(_bt);
  var h=document.getElementById('tw_handle').value.trim().replace('@','');
  if(h.length<2){hideBanner();return;}
  _bt=setTimeout(()=>{
    fetch('/auction-form.php?fetch_banner=1&handle='+encodeURIComponent(h)).then(r=>r.json()).then(d=>{
      if(d.banner){document.getElementById('image_url_inp').value=d.banner;var img=document.getElementById('banner_preview');img.src=d.banner;img.onload=()=>img.style.display='block';img.onerror=()=>img.style.display='none';}
    }).catch(()=>{});
  },800);
}
function hideBanner(){document.getElementById('banner_preview').style.display='none';document.getElementById('image_url_inp').value='';}
</script>
</body>
</html>