<?php /* mobile-admin-raffles.php */
require_once __DIR__.'/config.php';
$user=get_user();if(!$user){header('Location: /bs-auth/discord.php');exit;}if(!is_staff()){header('Location: /mobile.php');exit;}
$uid=$user['discord_id'];$blox_bal=0;try{$blox_bal=get_balance($uid);}catch(Exception $e){}
$m_active='admin';$msg='';$msg_type='ok';

if($_SERVER['REQUEST_METHOD']==='POST'){
    $action=$_POST['action']??'';
    if($action==='create'){
        $title=trim($_POST['title']??'');$spots=max(1,(int)($_POST['spots']??1));$chain=$_POST['chain']??'Ethereum';
        $reward_type=$_POST['reward_type']??'GTD WL';$entry_type=$_POST['entry_type']??'free';
        $blox_cost=max(0,(float)($_POST['blox_cost']??0));$twitter=ltrim(trim($_POST['twitter']??''),'@');
        $image_url=trim($_POST['image_url']??'');$guild_id=DISCORD_GUILD_ID;
        $dur=strtolower(trim($_POST['duration']??'24h'));
        if(strpos($dur,'d')!==false)$secs=(float)$dur*86400;
        elseif(strpos($dur,'h')!==false)$secs=(float)$dur*3600;
        else$secs=24*3600;
        $secs=max(300,min($secs,86400));
        if(!$image_url&&$twitter)$image_url="https://unavatar.io/twitter/$twitter";
        $r=sb('bs_raffles')->insert(['title'=>$title,'spots'=>$spots,'chain'=>$chain,'reward_type'=>$reward_type,'entry_type'=>$entry_type,'blox_cost'=>$blox_cost,'end_date'=>date('Y-m-d H:i:s',time()+(int)$secs),'image_url'=>$image_url,'project_twitter'=>$twitter,'status'=>'pending_approval','created_by'=>$uid,'guild_id'=>$guild_id]);
        if(($r['code']??0)>=400){$msg='Create failed';$msg_type='err';}
        else{header('Location: /mobile-admin-raffles.php?ok=1');exit;}
    }
    if($action==='pick_winners'){
        $rid=(int)$_POST['raffle_id'];$raffle=sb('bs_raffles')->eq('id',(string)$rid)->first();
        $entries=sb('bs_raffle_entries')->eq('raffle_id',(string)$rid)->select('discord_id')->get();
        $dids=array_unique(array_column($entries,'discord_id'));shuffle($dids);
        $winners=array_slice($dids,0,(int)($raffle['spots']??1));
        foreach($winners as $wid){sb('bs_raffle_winners')->upsert(['raffle_id'=>$rid,'discord_id'=>$wid],'raffle_id,discord_id');sb('bs_wins')->insert(['discord_id'=>$wid,'win_type'=>'raffle','ref_id'=>(string)$rid,'title'=>$raffle['title']??'','reward_type'=>$raffle['reward_type']??'GTD WL','chain'=>$raffle['chain']??'ETH','image_url'=>$raffle['image_url']??'']);}
        sb('bs_raffles')->eq('id',(string)$rid)->update(['status'=>'ended']);
        sb('bs_raffle_announce_queue')->insert(['raffle_id'=>$rid,'guild_id'=>$raffle['guild_id']??DISCORD_GUILD_ID]);
        header('Location: /mobile-admin-raffles.php?ok=winners');exit;
    }
    if($action==='cancel'){$rid_c=(int)$_POST['id'];sb('bs_raffles')->eq('id',(string)$rid_c)->update(['status'=>'cancelled']);header('Location: /mobile-admin-raffles.php');exit;}
}

$view_rid=(int)($_GET['rid']??0);$view_raffle=null;$entrants=[];
if($view_rid){
    $view_raffle=sb('bs_raffles')->eq('id',(string)$view_rid)->first();
    $entry_rows=sb('bs_raffle_entries')->eq('raffle_id',(string)$view_rid)->get();
    $winner_ids=array_column(sb('bs_raffle_winners')->eq('raffle_id',(string)$view_rid)->select('discord_id')->get(),'discord_id');
    foreach($entry_rows as $e){$u=sb('bs_users')->eq('discord_id',$e['discord_id'])->select('username,avatar')->first();$weth=sb('bs_user_wallets')->eq('discord_id',$e['discord_id'])->eq('chain','Ethereum')->select('address')->first();$entrants[]=['discord_id'=>$e['discord_id'],'username'=>$u['username']??('…'.substr($e['discord_id'],-4)),'avatar'=>$u['avatar']??'','eth_wallet'=>$weth['address']??'','is_winner'=>in_array($e['discord_id'],$winner_ids)?1:0];}
    usort($entrants,fn($a,$b)=>$b['is_winner']<=>$a['is_winner']);
}
$raffle_rows=sb('bs_raffles')->order('id',false)->get();$raffles=[];
foreach($raffle_rows as $r){$r['entries']=count(sb('bs_raffle_entries')->eq('raffle_id',$r['id'])->select('discord_id')->get());$raffles[]=$r;}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Raffles · Admin</title>
<link rel="stylesheet" href="/bs_fonts.css?v=1783108465">
<link rel="stylesheet" href="/bs_mobile.css?v=1783108465">
</head>
<body>
<?php require_once __DIR__.'/includes/bs_mobile_nav.php'; ?>
<div class="m-body">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:18px">
    <a href="/mobile-admin.php" style="width:34px;height:34px;border-radius:9px;background:rgba(255,255,255,.04);border:1px solid #161a28;display:flex;align-items:center;justify-content:center;color:#aab2c5;text-decoration:none;font-size:16px">‹</a>
    <h1 class="m-page-title" style="font-size:22px;margin-bottom:0"><?= $view_rid&&$view_raffle?htmlspecialchars($view_raffle['title']):'Manage Raffles' ?></h1>
  </div>
  <?php if(isset($_GET['ok'])): ?><div class="m-notice m-notice-green">✓ <?= $_GET['ok']==='winners'?'Winners picked & announced!':'Raffle created!' ?></div><?php endif; ?>
  <?php if($msg): ?><div class="m-notice m-notice-<?= $msg_type==='err'?'red':'green' ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <?php if($view_rid&&$view_raffle): ?>
  <!-- Entrant view -->
  <div style="margin-bottom:16px">
    <p class="m-page-sub" style="margin-bottom:0"><?= count($entrants) ?> entrants · <?= $view_raffle['spots'] ?> spots · <span style="color:<?= $view_raffle['status']==='active'?'#4ade80':'#7a8398' ?>"><?= $view_raffle['status'] ?></span></p>
  </div>
  <?php if($view_raffle['status']==='active'): ?>
  <form method="post" style="margin-bottom:16px">
    <input type="hidden" name="action" value="pick_winners"><input type="hidden" name="raffle_id" value="<?= $view_rid ?>">
    <button type="submit" class="m-foil-btn" style="border:none;cursor:pointer;width:100%" onclick="return confirm('Pick winners now?')"><span class="m-foil-btn-inner">🏆 Pick Winners</span></button>
  </form>
  <?php endif; ?>
  <?php $wl=array_filter($entrants,fn($e)=>$e['is_winner']); ?>
  <?php if($wl): ?>
  <div style="font-family:'GT America Mono',monospace;font-size:9.5px;letter-spacing:.1em;color:#e4c590;margin-bottom:10px">🏆 WINNERS (<?= count($wl) ?>)</div>
  <?php foreach($wl as $e): ?>
  <div style="display:flex;align-items:center;gap:10px;padding:10px 13px;background:rgba(228,197,144,.06);border:1px solid rgba(228,197,144,.2);border-radius:12px;margin-bottom:7px">
    <div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(140deg,#6fe3ff,#b69cff);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;color:#06070d;flex-shrink:0"><?= strtoupper(substr($e['username'],0,1)) ?></div>
    <div style="flex:1;min-width:0"><div style="font-weight:600;font-size:13px"><?= htmlspecialchars($e['username']) ?></div><?php if($e['eth_wallet']): ?><div style="font-family:'GT America Mono',monospace;font-size:9px;color:#6fe3ff">⟠ <?= substr($e['eth_wallet'],0,8).'…'.substr($e['eth_wallet'],-4) ?></div><?php endif; ?></div>
    <span style="font-size:16px">👑</span>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
  <div style="font-family:'GT America Mono',monospace;font-size:9.5px;letter-spacing:.1em;color:#5a6478;margin:12px 0 10px">ALL ENTRANTS (<?= count($entrants) ?>)</div>
  <?php foreach($entrants as $e): ?>
  <div style="display:flex;align-items:center;gap:10px;padding:9px 13px;background:rgba(255,255,255,.02);border:1px solid <?= $e['is_winner']?'rgba(228,197,144,.18)':'#161a28' ?>;border-radius:11px;margin-bottom:6px">
    <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(140deg,#1a2030,#0c0e18);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#bfe9f5;flex-shrink:0"><?= strtoupper(substr($e['username'],0,1)) ?></div>
    <div style="flex:1;min-width:0"><div style="font-size:12.5px;font-weight:600"><?= htmlspecialchars($e['username']) ?></div><?php if($e['eth_wallet']): ?><div style="font-family:'GT America Mono',monospace;font-size:9px;color:#6fe3ff">⟠ <?= substr($e['eth_wallet'],0,6).'…' ?></div><?php endif; ?></div>
  </div>
  <?php endforeach; ?>
  <?php if(empty($entrants)): ?><div class="m-empty"><span class="m-empty-icon">👥</span><p>No entries yet.</p></div><?php endif; ?>

  <?php else: ?>
  <!-- Create form -->
  <details style="margin-bottom:16px">
    <summary style="list-style:none;display:flex;align-items:center;gap:10px;padding:13px 16px;background:rgba(255,255,255,.02);border:1px solid #161a28;border-radius:13px;cursor:pointer;font-family:'GT America Mono',monospace;font-size:11px;color:#aab2c5">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6fe3ff" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
      CREATE RAFFLE
    </summary>
    <div style="padding:16px;border:1px solid #161a28;border-top:none;border-radius:0 0 13px 13px;background:rgba(255,255,255,.015)">
      <form method="post">
        <input type="hidden" name="action" value="create">
        <div class="m-field"><label>TITLE *</label><input class="m-input" name="title" required placeholder="Project WL"></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div class="m-field"><label>CHAIN</label><div class="m-select-wrap"><select class="m-input" name="chain"><option>Ethereum</option><option>Solana</option><option>Bitcoin</option><option>Base</option><option>Other</option></select></div></div>
          <div class="m-field"><label>REWARD</label><div class="m-select-wrap"><select class="m-input" name="reward_type"><option>GTD WL</option><option>FCFS WL</option><option>USDC</option><option>Other</option></select></div></div>
          <div class="m-field"><label>SPOTS</label><input class="m-input" name="spots" type="number" min="1" value="5" style="font-family:'GT America Mono',monospace"></div>
          <div class="m-field"><label>DURATION</label><input class="m-input" name="duration" placeholder="24h" value="24h" style="font-family:'GT America Mono',monospace"></div>
          <div class="m-field"><label>ENTRY TYPE</label><div class="m-select-wrap"><select class="m-input" name="entry_type"><option value="free">Free</option><option value="blox">$BLOX</option></select></div></div>
          <div class="m-field"><label>$BLOX COST</label><input class="m-input" name="blox_cost" type="number" step="0.01" value="0" style="font-family:'GT America Mono',monospace"></div>
        </div>
        <div class="m-field"><label>X HANDLE</label><input class="m-input" name="twitter" placeholder="ProjectHandle" style="font-family:'GT America Mono',monospace"><input type="hidden" name="image_url" value=""></div>
        <button type="submit" class="m-foil-btn" style="border:none;cursor:pointer"><span class="m-foil-btn-inner">🎟 Post Raffle to Discord</span></button>
      </form>
    </div>
  </details>

  <!-- Raffle list -->
  <?php foreach($raffles as $r): ?>
  <div style="display:flex;align-items:center;gap:10px;padding:11px 13px;background:rgba(255,255,255,.02);border:1px solid #161a28;border-radius:12px;margin-bottom:8px">
    <div style="width:36px;height:36px;border-radius:8px;flex-shrink:0;overflow:hidden;background:#1a1d2b"><?php if($r['image_url']??''): ?><img src="<?= htmlspecialchars($r['image_url']) ?>" style="width:100%;height:100%;object-fit:cover"><?php else: ?>🎟<?php endif; ?></div>
    <div style="flex:1;min-width:0">
      <div style="font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($r['title']) ?></div>
      <div style="font-size:11px;color:#7a8398"><?= $r['entries'] ?> entries · <?= $r['spots'] ?> spots</div>
    </div>
    <span class="m-badge m-badge-<?= $r['status']==='active'?'green':($r['status']==='ended'?'gray':'gold') ?>"><?= $r['status'] ?></span>
    <div style="display:flex;flex-direction:column;gap:4px;flex-shrink:0">
      <a href="?rid=<?= $r['id'] ?>" style="font-family:'GT America Mono',monospace;font-size:9px;color:#6fe3ff">Entries</a>
      <?php if($r['status']==='active'): ?>
      <form method="post" style="display:inline"><input type="hidden" name="action" value="pick_winners"><input type="hidden" name="raffle_id" value="<?= $r['id'] ?>"><button style="background:none;border:none;font-family:'GT America Mono',monospace;font-size:9px;color:#4ade80;cursor:pointer;padding:0" onclick="return confirm('Pick winners?')">End</button></form>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if(empty($raffles)): ?><div class="m-empty"><span class="m-empty-icon">🎟️</span><p>No raffles yet.</p></div><?php endif; ?>
  <?php endif; ?>
</div>
</body>
</html>