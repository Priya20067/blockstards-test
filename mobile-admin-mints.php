<?php /* mobile-admin-mints.php */
require_once __DIR__.'/config.php';
$user=get_user();if(!$user){header('Location: /bs-auth/discord.php');exit;}if(!is_staff()){header('Location: /mobile.php');exit;}
$uid=$user['discord_id'];$blox_bal=0;try{$blox_bal=get_balance($uid);}catch(Exception $e){}$m_active='admin';

if($_SERVER['REQUEST_METHOD']==='POST'){
    $action=$_POST['action']??'';
    if($action==='add'){$md=$_POST['mint_date']?:null;$st=$md?'approved':'tba';$tw=ltrim($_POST['twitter']??'','@');$img=trim($_POST['image_url']??'');if(!$img&&$tw)$img="https://unavatar.io/twitter/$tw";sb('bs_mints')->insert(['name'=>$_POST['name'],'description'=>'','image_url'=>$img,'mint_url'=>$_POST['mint_url']??'','chain'=>$_POST['chain']??'Ethereum','mint_date'=>$md,'price'=>$_POST['price']??'','supply'=>$_POST['supply']??'','twitter'=>$tw,'status'=>$st,'submitted_by'=>$uid,'approved_by'=>$uid]);header('Location: /mobile-admin-mints.php?ok=added');exit;}
    if($action==='delete'){sb('bs_mints')->eq('id',(int)$_POST['id'])->delete();header('Location: /mobile-admin-mints.php');exit;}
    if($action==='approve_sub'){$s=sb('bs_mint_submissions')->eq('id',(int)$_POST['sub_id'])->first();if($s){$st=$s['mint_date']?'approved':'tba';sb('bs_mints')->insert(['name'=>$s['name'],'description'=>'','image_url'=>$s['image_url']??'','mint_url'=>$s['mint_url']??'','chain'=>$s['chain']??'Ethereum','mint_date'=>$s['mint_date']?:null,'price'=>$s['price']??'','supply'=>$s['supply']??'','twitter'=>ltrim($s['twitter']??'','@'),'status'=>$st,'submitted_by'=>$s['discord_id'],'approved_by'=>$uid]);sb('bs_mint_submissions')->eq('id',(int)$_POST['sub_id'])->update(['status'=>'approved']);}header('Location: /mobile-admin-mints.php?ok=sub_approved');exit;}
    if($action==='reject_sub'){sb('bs_mint_submissions')->eq('id',(int)$_POST['sub_id'])->update(['status'=>'rejected']);header('Location: /mobile-admin-mints.php');exit;}
    if($action==='update_date'){$id=(int)$_POST['id'];$md=$_POST['mint_date']?:null;sb('bs_mints')->eq('id',(string)$id)->update(['mint_date'=>$md,'status'=>$md?'approved':'tba']);header('Location: /mobile-admin-mints.php?ok=updated');exit;}
}

try{$mints=sb('bs_mints')->order('mint_date',false)->get();}catch(Exception $e){$mints=[];}
try{$subs=sb('bs_mint_submissions')->eq('status','pending')->get();}catch(Exception $e){$subs=[];}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Mints · Admin</title>
<link rel="stylesheet" href="/bs_fonts.css?v=1783108465">
<link rel="stylesheet" href="/bs_mobile.css?v=1783108465">
</head>
<body>
<?php require_once __DIR__.'/includes/bs_mobile_nav.php'; ?>
<div class="m-body">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:18px">
    <a href="/mobile-admin.php" style="width:34px;height:34px;border-radius:9px;background:rgba(255,255,255,.04);border:1px solid #161a28;display:flex;align-items:center;justify-content:center;color:#aab2c5;text-decoration:none;font-size:16px">‹</a>
    <h1 class="m-page-title" style="font-size:22px;margin-bottom:0">Manage Mints</h1>
  </div>
  <?php if(isset($_GET['ok'])): ?><div class="m-notice m-notice-green">✓ <?= ['added'=>'Added!','sub_approved'=>'Approved!','updated'=>'Updated!'][$_GET['ok']]??'Done!' ?></div><?php endif; ?>

  <!-- Pending subs -->
  <?php if(!empty($subs)): ?>
  <div style="font-family:'GT America Mono',monospace;font-size:9.5px;letter-spacing:.1em;color:#fb923c;margin-bottom:10px">PENDING (<?= count($subs) ?>)</div>
  <?php foreach($subs as $s): ?>
  <div style="padding:12px 14px;background:rgba(251,146,60,.04);border:1px solid rgba(251,146,60,.2);border-radius:12px;margin-bottom:10px">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
      <div style="width:36px;height:36px;border-radius:8px;flex-shrink:0;overflow:hidden;background:#1a1d2b"><?php if($s['image_url']??''): ?><img src="<?= htmlspecialchars($s['image_url']) ?>" style="width:100%;height:100%;object-fit:cover"><?php else: ?>📅<?php endif; ?></div>
      <div style="flex:1;min-width:0"><div style="font-weight:600;font-size:13px"><?= htmlspecialchars($s['name']) ?></div><div style="font-size:11px;color:#7a8398"><?= htmlspecialchars($s['chain']??'ETH') ?> · <?= $s['mint_date']?date('M j',strtotime($s['mint_date'])):'TBA' ?></div></div>
    </div>
    <div style="display:flex;gap:8px">
      <form method="post" style="flex:1"><input type="hidden" name="action" value="approve_sub"><input type="hidden" name="sub_id" value="<?= $s['id'] ?>"><button class="m-foil-btn" style="border:none;cursor:pointer;width:100%"><span class="m-foil-btn-inner" style="padding:10px">✓ Approve</span></button></form>
      <form method="post" style="flex:1"><input type="hidden" name="action" value="reject_sub"><input type="hidden" name="sub_id" value="<?= $s['id'] ?>"><button style="width:100%;padding:10px;border-radius:11px;background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.3);color:#f87171;font-family:'GT America Mono',monospace;font-size:12px;cursor:pointer" onclick="return confirm('Reject?')">✕ Reject</button></form>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <!-- Add mint -->
  <details style="margin-bottom:16px">
    <summary style="list-style:none;display:flex;align-items:center;gap:10px;padding:13px 16px;background:rgba(255,255,255,.02);border:1px solid #161a28;border-radius:13px;cursor:pointer;font-family:'GT America Mono',monospace;font-size:11px;color:#aab2c5">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6fe3ff" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>ADD MINT
    </summary>
    <div style="padding:16px;border:1px solid #161a28;border-top:none;border-radius:0 0 13px 13px;background:rgba(255,255,255,.015)">
      <form method="post">
        <input type="hidden" name="action" value="add">
        <div class="m-field"><label>NAME *</label><input class="m-input" name="name" required placeholder="Project Name"></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div class="m-field"><label>CHAIN</label><div class="m-select-wrap"><select class="m-input" name="chain"><option>Ethereum</option><option>Solana</option><option>Bitcoin</option><option>Base</option><option>Other</option></select></div></div>
          <div class="m-field"><label>DATE</label><input class="m-input" name="mint_date" type="date"></div>
          <div class="m-field"><label>PRICE</label><input class="m-input" name="price" placeholder="0.05 ETH" style="font-family:'GT America Mono',monospace"></div>
          <div class="m-field"><label>SUPPLY</label><input class="m-input" name="supply" placeholder="3838" style="font-family:'GT America Mono',monospace"></div>
        </div>
        <div class="m-field"><label>X HANDLE</label><input class="m-input" name="twitter" placeholder="ProjectHandle" style="font-family:'GT America Mono',monospace"><input type="hidden" name="image_url" value=""></div>
        <div class="m-field"><label>MINT URL</label><input class="m-input" name="mint_url" placeholder="https://…"></div>
        <button type="submit" class="m-foil-btn" style="border:none;cursor:pointer"><span class="m-foil-btn-inner">Add to Calendar</span></button>
      </form>
    </div>
  </details>

  <!-- Mints list -->
  <div style="font-family:'GT America Mono',monospace;font-size:9.5px;letter-spacing:.1em;color:#7a8398;margin-bottom:10px">ALL MINTS (<?= count($mints) ?>)</div>
  <?php foreach($mints as $m): ?>
  <div style="display:flex;align-items:center;gap:10px;padding:11px 13px;background:rgba(255,255,255,.02);border:1px solid #161a28;border-radius:12px;margin-bottom:8px">
    <div style="width:36px;height:36px;border-radius:8px;flex-shrink:0;overflow:hidden;background:#1a1d2b"><?php if($m['image_url']??''): ?><img src="<?= htmlspecialchars($m['image_url']) ?>" style="width:100%;height:100%;object-fit:cover"><?php else: ?>📅<?php endif; ?></div>
    <div style="flex:1;min-width:0">
      <div style="font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($m['name']) ?></div>
      <div style="font-size:11px;color:#7a8398"><?= htmlspecialchars($m['chain']??'ETH') ?> · <?= $m['mint_date']?date('M j, Y',strtotime($m['mint_date'])):'TBA' ?></div>
    </div>
    <span class="m-badge m-badge-<?= $m['status']==='approved'?'green':($m['status']==='tba'?'gold':'gray') ?>"><?= $m['status'] ?></span>
    <form method="post" style="display:inline"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $m['id'] ?>"><button onclick="return confirm('Delete?')" style="background:none;border:none;color:#5a6478;font-size:20px;cursor:pointer;line-height:1">×</button></form>
  </div>
  <?php endforeach; ?>
  <?php if(empty($mints)): ?><div class="m-empty"><span class="m-empty-icon">📅</span><p>No mints yet.</p></div><?php endif; ?>
</div>
</body>
</html>