<?php
require_once __DIR__.'/config.php';
$user  = get_user();
$tab   = $_GET['tab'] ?? 'all';
$today = date('Y-m-d');
$uid   = $user ? $user['discord_id'] : null;
$blox_bal = 0;
if ($user) { try { $blox_bal = get_balance($uid); } catch(Exception $e){} }
$m_active = 'calendar';

$month     = $_GET['month'] ?? date('Y-m');
[$yr, $mo] = explode('-', $month);
$first_day = "$yr-$mo-01";
$last_day  = date('Y-m-t', strtotime($first_day));
$prev_month= date('Y-m', strtotime('-1 month', strtotime($first_day)));
$next_month= date('Y-m', strtotime('+1 month', strtotime($first_day)));
$month_label = date('F Y', strtotime($first_day));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $action = $_POST['action'] ?? '';
    if ($action === 'submit_nft') {
        $md=$_POST['mint_date']?:null;$tw=ltrim(trim($_POST['twitter']??''),'@');
        $img=trim($_POST['image_url']??'');if(!$img&&$tw)$img="https://unavatar.io/twitter/$tw";
        $nm=trim($_POST['name']??'');
        if($nm){sb('bs_mint_submissions')->insert(['discord_id'=>$uid,'name'=>$nm,'chain'=>$_POST['chain']??'Ethereum','mint_date'=>$md,'price'=>$_POST['price']??'','supply'=>$_POST['supply']??'','mint_url'=>$_POST['mint_url']??'','image_url'=>$img,'twitter'=>$tw,'status'=>'pending']);}
        header('Location: ?tab=all&month='.$month.'&ok=submitted');exit;
    }
    if ($action === 'add_my_mint') {
        $md=$_POST['mint_date']?:null;$tw=ltrim(trim($_POST['twitter']??''),'@');
        $img=trim($_POST['image_url']??'');if(!$img&&$tw)$img="https://unavatar.io/twitter/$tw";
        // mint_id is a composite primary key column (negative = manual/custom entry, avoids clashing with real bs_mints ids)
        $custom_mint_id = -1 * (time() % 100000000 + rand(1,999));
        sb('bs_user_calendar')->insert(['discord_id'=>$uid,'mint_id'=>$custom_mint_id,'name'=>trim($_POST['name']??''),'mint_url'=>$_POST['mint_url']??'','image_url'=>$img,'chain'=>$_POST['chain']??'Ethereum','mint_date'=>$md,'price'=>$_POST['price']??'','supply'=>'','notes'=>'']);
        header('Location: ?tab=mine&month='.$month.'&ok=1');exit;
    }
    if ($action === 'delete_my_mint') { sb('bs_user_calendar')->eq('mint_id',(int)$_POST['id'])->eq('discord_id',$uid)->delete(); header('Location: ?tab=mine&month='.$month);exit; }
    if ($action === 'delete_wl')      { sb('bs_user_wl')->eq('mint_id',(int)$_POST['id'])->eq('discord_id',$uid)->delete(); header('Location: ?tab=mine&month='.$month);exit; }
    if ($action === 'add_from_cal') {
        if (!$uid) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'message'=>'Not logged in']); exit; }
        $name = trim($_POST['name'] ?? '');
        if (!$name) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'message'=>'No name']); exit; }
        // Validate mint_date — accept YYYY-MM-DD only
        $raw_date = trim($_POST['mint_date'] ?? '');
        $md = null;
        if ($raw_date) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw_date)) {
                $md = $raw_date;
            } else {
                $ts = strtotime($raw_date);
                if ($ts) $md = date('Y-m-d', $ts);
            }
        }
        try {
            $custom_mint_id = -1 * (time() % 100000000 + rand(1,999));
            sb('bs_user_calendar')->insert([
                'discord_id' => $uid,
                'mint_id'    => $custom_mint_id,
                'name'       => $name,
                'mint_url'   => trim($_POST['mint_url']   ?? ''),
                'image_url'  => trim($_POST['image_url']  ?? ''),
                'chain'      => trim($_POST['chain']      ?? 'Ethereum'),
                'mint_date'  => $md,
                'price'      => trim($_POST['price']      ?? ''),
                'supply'     => trim($_POST['supply']     ?? ''),
                'notes'      => 'Added from Full Calendar',
            ]);
            header('Content-Type: application/json');
            echo json_encode(['ok'=>true,'mint_date'=>$md,'name'=>$name]); exit;
        } catch(Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['ok'=>false,'message'=>$e->getMessage()]); exit;
        }
    }
}

$full_by_date=[]; $full_tba=[];
try {
    foreach(sb('bs_mints')->eq('status','approved')->get() as $m){
        $d=$m['mint_date']?substr($m['mint_date'],0,10):null;
        $row=['name'=>$m['name'],'chain'=>$m['chain']??'ETH','mint_date'=>$d,'image_url'=>$m['image_url']??'','mint_url'=>$m['mint_url']??'','price'=>$m['price']??'','src'=>'mint','description'=>$m['description']??'','reward_type'=>''];
        if($d&&$d>=$first_day&&$d<=$last_day)$full_by_date[$d][]=$row; elseif(!$d)$full_tba[]=$row;
    }
    foreach(sb('bs_auctions')->eq('status','active')->get() as $a){
        $d=$a['ends_at']?substr($a['ends_at'],0,10):null;
        $row=['name'=>$a['title']??'','chain'=>$a['chain']??'ETH','mint_date'=>$d,'image_url'=>$a['image_url']??'','mint_url'=>$a['mint_url']??'','price'=>$a['mint_price']??'','src'=>'auction','description'=>$a['description']??'','reward_type'=>$a['reward_type']??''];
        if($d&&$d>=$first_day&&$d<=$last_day)$full_by_date[$d][]=$row;
    }
    foreach(sb('bs_raffles')->eq('status','active')->get() as $r){
        $d=$r['end_date']?substr($r['end_date'],0,10):null;
        $row=['name'=>$r['title']??'','chain'=>$r['chain']??'ETH','mint_date'=>$d,'image_url'=>$r['image_url']??'','mint_url'=>$r['mint_url']??'','price'=>'','src'=>'raffle','description'=>$r['description']??'','reward_type'=>$r['reward_type']??''];
        if($d&&$d>=$first_day&&$d<=$last_day)$full_by_date[$d][]=$row;
    }
    foreach(sb('bs_mint_submissions')->eq('status','approved')->get() as $s){
        $d=$s['mint_date']?substr($s['mint_date'],0,10):null;
        $row=['name'=>$s['name'],'chain'=>$s['chain']??'ETH','mint_date'=>$d,'image_url'=>$s['image_url']??'','mint_url'=>$s['mint_url']??'','price'=>$s['price']??'','src'=>'sub','description'=>'','reward_type'=>''];
        if($d&&$d>=$first_day&&$d<=$last_day)$full_by_date[$d][]=$row; elseif(!$d)$full_tba[]=$row;
    }
} catch(Exception $e){}

$my_by_date=[]; $my_tba=[];
if($user){
    try {
        $wins=sb('bs_wins')->eq('discord_id',$uid)->get();
        $manual=sb('bs_user_calendar')->eq('discord_id',$uid)->get();
        $wl=sb('bs_user_wl')->eq('discord_id',$uid)->get();
        foreach($wins as $w){
            $w['src']=$w['win_type']??'auction';
            $w['name']=$w['title']??'';
            // If win has no mint_date, try to find from bs_mints
            if(empty($w['mint_date'])){
                try{
                    $mi=sb('bs_mints')->eq('name',$w['title']??'')->eq('status','approved')->select('mint_date,price')->first();
                    if($mi&&$mi['mint_date']) $w['mint_date']=$mi['mint_date'];
                }catch(Exception $ex){}
            }
            if(!empty($w['mint_date'])){$w['mint_date']=substr($w['mint_date'],0,10);$my_by_date[$w['mint_date']][]=$w;} else $my_tba[]=$w;
        }
        foreach($manual as $r){ $r['src']='manual'; if(!empty($r['mint_date'])){$r['mint_date']=substr($r['mint_date'],0,10);$my_by_date[$r['mint_date']][]=$r;} else $my_tba[]=$r; }
        foreach($wl as $r){ $r['src']='wl'; if(!empty($r['mint_date'])){$r['mint_date']=substr($r['mint_date'],0,10);$my_by_date[$r['mint_date']][]=$r;} else $my_tba[]=$r; }
    } catch(Exception $e){}
}

$src_colors=['mint'=>'#e4c590','sub'=>'#e4c590','auction'=>'#e4737d','raffle'=>'#9c9cf0','manual'=>'#fbbf24','wl'=>'#fbbf24'];

function cal_grid(string $yr, string $mo, string $first_day, string $today, array $by_date, array $src_colors): void {
    $first_dow=(int)date('w',strtotime($first_day));
    $days_in_mo=(int)date('t',strtotime($first_day));
    foreach(['S','M','T','W','T','F','S'] as $dh) echo '<div class="m-cal-hdr">'.$dh.'</div>';
    for($i=0;$i<$first_dow;$i++) echo '<div class="m-cal-cell m-cal-blank"></div>';
    for($d=1;$d<=$days_in_mo;$d++){
        $ds=sprintf('%s-%02d-%02d',$yr,$mo,$d);
        $is_today=($ds===$today);
        $items=$by_date[$ds]??[];
        $has=!empty($items);
        $cls='m-cal-cell'.($is_today?' m-cal-today':'').($has?' m-cal-has':'');
        $json=htmlspecialchars(json_encode(array_values($items)),ENT_QUOTES,'UTF-8');
        $lbl=htmlspecialchars(date('M j, Y',mktime(0,0,0,(int)$mo,$d,(int)$yr)),ENT_QUOTES);
        echo '<div class="'.$cls.'"'.($has?' onclick="openDaySheet(this)"':'').' data-items="'.$json.'" data-label="'.$lbl.'">';
        echo '<div class="m-cal-date'.($is_today?' m-cal-date-today':'').'">'.$d.'</div>';
        if($has){
            echo '<div class="m-cal-dots">';
            foreach(array_slice($items,0,4) as $it) echo '<div class="m-cal-dot" style="background:'.htmlspecialchars($src_colors[$it['src']]??'#6fe3ff').'"></div>';
            echo '</div>';
            if(count($items)>4) echo '<div class="m-cal-more">+'.(count($items)-4).'</div>';
        }
        echo '</div>';
    }
    $used=$first_dow+$days_in_mo;$rem=$used%7;
    if($rem>0) for($i=$rem;$i<7;$i++) echo '<div class="m-cal-cell m-cal-blank"></div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Calendar · Blockstards</title>
<link rel="stylesheet" href="/bs_fonts.css?v=1783108465">
<link rel="stylesheet" href="/bs_mobile.css?v=1783108465">
<style>
.m-cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:2px;margin-bottom:16px}
.m-cal-hdr{font-family:'GT America Mono',monospace;font-size:8.5px;letter-spacing:.1em;color:#5a6478;text-align:center;padding:6px 0}
.m-cal-cell{min-height:52px;padding:4px 3px;display:flex;flex-direction:column;background:rgba(255,255,255,.015);border-radius:8px;transition:background .15s}
.m-cal-blank{background:transparent}
.m-cal-today{background:rgba(111,227,255,.1);box-shadow:inset 0 0 0 1px rgba(111,227,255,.3)}
.m-cal-has{cursor:pointer}.m-cal-has:active{background:rgba(111,227,255,.08)}
.m-cal-date{font-family:'GT America Mono',monospace;font-size:10px;color:#7a8398;padding:1px 3px;align-self:flex-start}
.m-cal-date-today{color:#06070d!important;background:#6fe3ff;border-radius:5px;padding:1px 5px;font-weight:600}
.m-cal-dots{display:flex;flex-wrap:wrap;gap:2px;justify-content:center;margin-top:2px}
.m-cal-dot{width:5px;height:5px;border-radius:50%}
.m-cal-more{font-family:'GT America Mono',monospace;font-size:7.5px;color:#5a6478;text-align:center}
.tba-item{display:flex;align-items:center;gap:10px;padding:10px 14px;border-bottom:1px solid #0d1018;cursor:pointer}
.tba-thumb{width:28px;height:28px;border-radius:7px;flex-shrink:0;overflow:hidden;background:#1a1d2b}
.tba-thumb img{width:100%;height:100%;object-fit:cover}
.m-sheet-overlay{position:fixed;inset:0;z-index:200;display:none;align-items:flex-end;background:rgba(4,5,10,.78);backdrop-filter:blur(6px)}
.m-sheet-overlay.open{display:flex}
.m-sheet{width:100%;border-radius:22px 22px 0 0;background:linear-gradient(180deg,#0d1018,#080a12);border-top:1px solid #232838;max-height:88vh;overflow-y:auto;padding-bottom:env(safe-area-inset-bottom)}
.m-sheet-pull{width:36px;height:4px;border-radius:2px;background:#232838;margin:12px auto 8px}
</style>
</head>
<body>
<?php require_once __DIR__.'/includes/bs_mobile_nav.php'; ?>
<div class="m-body">

<h1 class="m-page-title">Mint Calendar</h1>
<p class="m-page-sub">Every auction, raffle and NFT mint in one place.</p>

<div class="m-tabs">
  <a href="?tab=all&month=<?= $month ?>"  class="m-tab <?= $tab==='all'?'on':'' ?>">Full Calendar</a>
  <a href="?tab=mine&month=<?= $month ?>" class="m-tab <?= $tab==='mine'?'on':'' ?>">My Calendar</a>
</div>

<?php if(isset($_GET['ok'])): ?>
<div class="m-notice m-notice-green">✓ <?= $_GET['ok']==='submitted'?'Submitted for review!':'Added to your calendar!' ?></div>
<?php endif; ?>

<?php if($tab==='all'): ?>

<div style="display:flex;gap:14px;margin-bottom:14px;flex-wrap:wrap">
  <?php foreach(['Mint'=>'#e4c590','Auction'=>'#e4737d','Raffle'=>'#9c9cf0'] as $l=>$c): ?>
  <div style="display:flex;align-items:center;gap:5px;font-family:'GT America Mono',monospace;font-size:10px;color:#7a8398">
    <span style="width:8px;height:8px;border-radius:3px;background:<?= $c ?>"></span><?= $l ?>
  </div>
  <?php endforeach; ?>
</div>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
  <a href="?tab=all&month=<?= $prev_month ?>" style="width:34px;height:34px;border-radius:9px;background:rgba(255,255,255,.04);border:1px solid #161a28;display:flex;align-items:center;justify-content:center;color:#aab2c5;text-decoration:none;font-size:18px;line-height:1">&#8249;</a>
  <span style="font-weight:700;font-size:17px"><?= $month_label ?></span>
  <a href="?tab=all&month=<?= $next_month ?>" style="width:34px;height:34px;border-radius:9px;background:rgba(255,255,255,.04);border:1px solid #161a28;display:flex;align-items:center;justify-content:center;color:#aab2c5;text-decoration:none;font-size:18px;line-height:1">&#8250;</a>
</div>

<div class="m-cal-grid">
<?php cal_grid($yr,$mo,$first_day,$today,$full_by_date,$src_colors); ?>
</div>

<?php if(!empty($full_tba)): ?>
<div class="m-panel">
  <div class="m-panel-hdr" style="display:flex;justify-content:space-between"><span>DATE TBA</span><span style="color:#e4c590"><?= count($full_tba) ?></span></div>
  <?php foreach($full_tba as $t):
    $col=$src_colors[$t['src']]??'#e4c590';
    $b64=base64_encode(json_encode($t));
  ?>
  <div class="tba-item" onclick="openItemSheetB64('<?= $b64 ?>')">
    <div class="tba-thumb"><?php if($t['image_url']??''): ?><img src="<?= htmlspecialchars($t['image_url']) ?>" alt=""><?php else: ?>📅<?php endif; ?></div>
    <div style="flex:1;min-width:0">
      <div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($t['name']) ?></div>
      <span style="font-family:'GT America Mono',monospace;font-size:8.5px;padding:1px 6px;border-radius:8px;background:<?= $col ?>22;color:<?= $col ?>"><?= ucfirst($t['src']) ?></span>
    </div>
    <span style="color:#5a6478">&#8250;</span>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<details style="margin-top:16px">
  <summary style="list-style:none;display:flex;align-items:center;gap:10px;padding:13px 16px;background:rgba(255,255,255,.02);border:1px solid #161a28;border-radius:13px;cursor:pointer;font-family:'GT America Mono',monospace;font-size:11px;color:#aab2c5">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6fe3ff" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
    SUBMIT NFT PROJECT
  </summary>
  <div style="padding:16px;border:1px solid #161a28;border-top:none;border-radius:0 0 13px 13px;background:rgba(255,255,255,.015)">
    <?php if(!$user): ?>
    <a href="/bs-auth/discord.php" class="m-discord-btn">Sign in to submit</a>
    <?php else: ?>
    <form method="post">
      <input type="hidden" name="action" value="submit_nft">
      <div class="m-field"><label>PROJECT NAME *</label><input class="m-input" name="name" required placeholder="Blockstards"></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div class="m-field"><label>CHAIN</label><div class="m-select-wrap"><select class="m-input" name="chain"><option>Ethereum</option><option>Solana</option><option>Bitcoin</option><option>Base</option><option>Other</option></select></div></div>
        <div class="m-field"><label>MINT DATE</label><input class="m-input" name="mint_date" type="date"></div>
        <div class="m-field"><label>MINT TIME <span style="font-family:'GT America Mono',monospace;font-size:9px;color:#5a6478">(optional)</span></label><div class="m-select-wrap"><select class="m-input" name="mint_time">
            <option value="">TBA</option>
            <option value="00:00">12:00 AM UTC</option>
            <option value="04:00">4:00 AM UTC</option>
            <option value="08:00">8:00 AM UTC</option>
            <option value="12:00">12:00 PM UTC</option>
            <option value="14:00">2:00 PM UTC</option>
            <option value="16:00">4:00 PM UTC</option>
            <option value="18:00">6:00 PM UTC</option>
            <option value="20:00">8:00 PM UTC</option>
            <option value="22:00">10:00 PM UTC</option>
          </select></div></div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div class="m-field"><label>PRICE</label><input class="m-input" name="price" placeholder="0.1" style="font-family:'GT America Mono',monospace"></div>
        <div class="m-field"><label>SUPPLY</label><input class="m-input" name="supply" placeholder="Supply" style="font-family:'GT America Mono',monospace"></div>
      </div>
      <div class="m-field"><label>X HANDLE (no @) <span style="font-family:'GT America Mono',monospace;font-size:9px;color:#5a6478">(optional)</span></label><input class="m-input" name="twitter" placeholder="Blockstards" style="font-family:'GT America Mono',monospace"><input type="hidden" name="image_url" value=""></div>
      <div class="m-field"><label>MINT URL</label><input class="m-input" name="mint_url" placeholder="https://..."></div>
      <button type="submit" class="m-foil-btn" style="border:none;cursor:pointer"><span class="m-foil-btn-inner">Submit for Review</span></button>
    </form>
    <?php endif; ?>
  </div>
</details>

<?php else: ?>
<?php if(!$user): ?>
<div style="text-align:center;padding:40px 20px">
  <p style="color:#7a8398;margin-bottom:16px">Sign in to view your calendar</p>
  <a href="/bs-auth/discord.php" class="m-discord-btn">Sign in with Discord</a>
</div>
<?php else: ?>
<?php
  $flat=[];
  foreach($my_by_date as $day_items) foreach($day_items as $it) $flat[]=$it;
  $all_mine=array_merge($flat,$my_tba);
  $upcoming=array_filter($all_mine,fn($m)=>!empty($m['mint_date'])&&$m['mint_date']>=$today);
?>
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:16px">
  <div class="m-stat" style="padding:11px"><div class="m-stat-val" style="font-size:20px"><?= count($all_mine) ?></div><div class="m-stat-label" style="font-size:8px">TOTAL</div></div>
  <div class="m-stat" style="padding:11px"><div class="m-stat-val" style="font-size:20px;color:#e4c590"><?= count($upcoming) ?></div><div class="m-stat-label" style="font-size:8px">UPCOMING</div></div>
  <div class="m-stat" style="padding:11px"><div class="m-stat-val" style="font-size:20px;color:#4ade80"><?= count(array_filter($all_mine,fn($m)=>($m['mint_date']??'')===$today)) ?></div><div class="m-stat-label" style="font-size:8px">TODAY</div></div>
</div>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
  <a href="?tab=mine&month=<?= $prev_month ?>" style="width:34px;height:34px;border-radius:9px;background:rgba(255,255,255,.04);border:1px solid #161a28;display:flex;align-items:center;justify-content:center;color:#aab2c5;text-decoration:none;font-size:18px;line-height:1">&#8249;</a>
  <span style="font-weight:700;font-size:17px"><?= $month_label ?></span>
  <a href="?tab=mine&month=<?= $next_month ?>" style="width:34px;height:34px;border-radius:9px;background:rgba(255,255,255,.04);border:1px solid #161a28;display:flex;align-items:center;justify-content:center;color:#aab2c5;text-decoration:none;font-size:18px;line-height:1">&#8250;</a>
</div>
<div class="m-cal-grid">
<?php cal_grid($yr,$mo,$first_day,$today,$my_by_date,$src_colors); ?>
</div>

<?php
  $with_date=array_filter($all_mine,fn($m)=>!empty($m['mint_date']));
  usort($with_date,fn($a,$b)=>strcmp($a['mint_date'],$b['mint_date']));
  if(!empty($with_date)):
?>
<div style="font-family:'GT America Mono',monospace;font-size:10px;letter-spacing:.12em;color:#5a6478;margin-bottom:10px">SCHEDULED (<?= count($with_date) ?>)</div>
<?php foreach($with_date as $m):
  $src=$m['src']??'manual';$col=$src_colors[$src]??'#fbbf24';
  $name=$m['name']??($m['title']??'');
  $date_f=date('M j',strtotime($m['mint_date']));
  $b64=base64_encode(json_encode($m));
?>
<div style="display:flex;align-items:center;gap:11px;padding:12px 14px;background:rgba(255,255,255,.02);border:1px solid #161a28;border-radius:13px;margin-bottom:8px;cursor:pointer" onclick="openItemSheetB64('<?= $b64 ?>')">
  <div style="width:44px;height:44px;border-radius:10px;flex-shrink:0;overflow:hidden;background:#1a1d2b">
    <?php if($m['image_url']??''): ?><img src="<?= htmlspecialchars($m['image_url']) ?>" style="width:100%;height:100%;object-fit:cover"><?php else: ?><div style="width:100%;height:100%;background:linear-gradient(135deg,#13243a,#5aa9d8)"></div><?php endif; ?>
  </div>
  <div style="flex:1;min-width:0">
    <div style="font-weight:600;font-size:13.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($name) ?></div>
    <div style="display:flex;gap:5px;flex-wrap:wrap;margin-top:4px">
      <span class="m-badge m-badge-gold"><?= $date_f ?></span>
      <?php if($m['chain']??''): ?><span class="m-badge m-badge-gray"><?= htmlspecialchars($m['chain']) ?></span><?php endif; ?>
      <?php if($m['wl_type']??''): ?><span class="m-badge m-badge-green"><?= htmlspecialchars($m['wl_type']) ?></span><?php endif; ?>
    </div>
  </div>
  <div style="flex-shrink:0;display:flex;align-items:center;gap:8px">
    <?php if($m['mint_url']??''): ?><a href="<?= htmlspecialchars($m['mint_url']) ?>" target="_blank" onclick="event.stopPropagation()" style="font-family:'GT America Mono',monospace;font-size:10px;color:#e4c590">Mint</a><?php endif; ?>
    <?php if($src==='manual'): ?>
    <form method="post" style="display:inline" onclick="event.stopPropagation()"><input type="hidden" name="action" value="delete_my_mint"><input type="hidden" name="id" value="<?= $m['id'] ?>"><button onclick="return confirm('Remove?')" style="background:none;border:none;color:#5a6478;font-size:20px;cursor:pointer;line-height:1">x</button></form>
    <?php elseif($src==='wl'): ?>
    <form method="post" style="display:inline" onclick="event.stopPropagation()"><input type="hidden" name="action" value="delete_wl"><input type="hidden" name="id" value="<?= $m['id'] ?>"><button onclick="return confirm('Remove?')" style="background:none;border:none;color:#5a6478;font-size:20px;cursor:pointer;line-height:1">x</button></form>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; endif; ?>

<?php if(empty($all_mine)): ?>
<div class="m-empty"><span class="m-empty-icon">📅</span><p>No mints yet.</p></div>
<?php endif; ?>

<details style="margin-top:16px">
  <summary style="list-style:none;display:flex;align-items:center;gap:10px;padding:13px 16px;background:rgba(255,255,255,.02);border:1px solid #161a28;border-radius:13px;cursor:pointer;font-family:'GT America Mono',monospace;font-size:11px;color:#aab2c5">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#b69cff" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
    ADD TO MY CALENDAR
  </summary>
  <div style="padding:16px;border:1px solid #161a28;border-top:none;border-radius:0 0 13px 13px;background:rgba(255,255,255,.015)">
    <form method="post">
      <input type="hidden" name="action" value="add_my_mint">
      <div class="m-field"><label>PROJECT NAME *</label><input class="m-input" name="name" required placeholder="Project Name"></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div class="m-field"><label>CHAIN</label><div class="m-select-wrap"><select class="m-input" name="chain"><option>Ethereum</option><option>Solana</option><option>Bitcoin</option><option>Base</option><option>Other</option></select></div></div>
        <div class="m-field"><label>MINT DATE</label><input class="m-input" name="mint_date" type="date"></div>
        <div class="m-field"><label>MINT TIME <span style="font-family:'GT America Mono',monospace;font-size:9px;color:#5a6478">(optional)</span></label><div class="m-select-wrap"><select class="m-input" name="mint_time">
            <option value="">TBA</option>
            <option value="00:00">12:00 AM UTC</option>
            <option value="04:00">4:00 AM UTC</option>
            <option value="08:00">8:00 AM UTC</option>
            <option value="12:00">12:00 PM UTC</option>
            <option value="14:00">2:00 PM UTC</option>
            <option value="16:00">4:00 PM UTC</option>
            <option value="18:00">6:00 PM UTC</option>
            <option value="20:00">8:00 PM UTC</option>
            <option value="22:00">10:00 PM UTC</option>
          </select></div></div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div class="m-field"><label>PRICE <span style="font-family:'GT America Mono',monospace;font-size:9px;color:#5a6478">(optional)</span></label><input class="m-input" name="price" placeholder="0.1" style="font-family:'GT America Mono',monospace"></div>
        <div class="m-field"><label>X HANDLE</label><input class="m-input" name="twitter" placeholder="Handle" style="font-family:'GT America Mono',monospace"><input type="hidden" name="image_url" value=""></div>
      </div>
      <div class="m-field"><label>MINT URL</label><input class="m-input" name="mint_url" placeholder="https://..."></div>
      <button type="submit" class="m-foil-btn" style="border:none;cursor:pointer"><span class="m-foil-btn-inner">Add to My Calendar</span></button>
    </form>
  </div>
</details>

<?php endif; ?>
<?php endif; ?>

</div>

<!-- DAY SHEET -->
<div class="m-sheet-overlay" id="day-sheet" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="m-sheet">
    <div class="m-sheet-pull"></div>
    <div style="padding:4px 18px 12px;display:flex;align-items:center;justify-content:space-between">
      <div id="day-sheet-title" style="font-weight:700;font-size:17px"></div>
      <div onclick="document.getElementById('day-sheet').classList.remove('open')" style="width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;font-size:17px;color:#aab2c5;cursor:pointer">x</div>
    </div>
    <div id="day-sheet-list" style="padding:0 14px 24px"></div>
  </div>
</div>

<!-- ITEM SHEET -->
<div class="m-sheet-overlay" id="item-sheet" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="m-sheet">
    <div class="m-sheet-pull"></div>
    <div style="position:relative;height:160px;margin:0 14px;border-radius:14px;overflow:hidden;background:#1a1d2b;margin-bottom:14px">
      <div id="item-banner" style="position:absolute;inset:0;background-size:cover;background-position:center"></div>
      <div style="position:absolute;inset:0;background:radial-gradient(circle at 30% 20%,rgba(255,255,255,.22),transparent 55%);mix-blend-mode:screen;pointer-events:none"></div>
      <div style="position:absolute;inset:0;background:linear-gradient(to bottom,transparent 35%,rgba(8,10,18,.95))"></div>
      <div onclick="document.getElementById('item-sheet').classList.remove('open')" style="position:absolute;top:10px;right:10px;width:30px;height:30px;border-radius:50%;background:rgba(6,7,13,.6);border:1px solid rgba(255,255,255,.12);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;font-size:16px;color:#aab2c5;cursor:pointer;z-index:2">&#215;</div>
      <!-- Source badge -->
      <div id="item-src-badge" style="position:absolute;top:10px;left:10px;display:flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;border:1px solid;backdrop-filter:blur(6px);font-family:'GT America Mono',monospace;font-size:9.5px;letter-spacing:.12em;z-index:2">
        <span id="item-src-dot" style="width:5px;height:5px;border-radius:50%"></span>
        <span id="item-src-label"></span>
      </div>
      <div style="position:absolute;bottom:0;left:0;right:0;padding:12px 14px;z-index:2">
        <div id="item-title" style="font-weight:700;font-size:20px;letter-spacing:-.01em;margin-bottom:5px"></div>
        <div id="item-meta" style="display:flex;align-items:center;gap:4px;flex-wrap:wrap"></div>
      </div>
    </div>
    <div id="item-body" style="padding:0 14px 24px"></div>
  </div>
</div>

<script>
var _isStaff=<?= (function_exists('is_staff')&&is_staff())?'true':'false' ?>;
var _C={'mint':'#e4c590','sub':'#e4c590','auction':'#e4737d','raffle':'#9c9cf0','manual':'#fbbf24','wl':'#fbbf24'};
var _L={'mint':'NFT MINT','sub':'NFT MINT','auction':'AUCTION','raffle':'RAFFLE','manual':'MY CALENDAR','wl':'WL ENTRY'};

function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

function openDaySheet(cell){
  var raw=cell.getAttribute('data-items');
  var label=cell.getAttribute('data-label');
  if(!raw)return;
  var items;try{items=JSON.parse(raw);}catch(e){return;}
  if(!items||!items.length)return;
  document.getElementById('day-sheet-title').textContent=label||'';
  var html='';
  for(var i=0;i<items.length;i++){
    var it=items[i];
    var c=_C[it.src]||'#e4c590';
    var lbl=_L[it.src]||'MINT';
    var img=it.image_url?'<img src="'+esc(it.image_url)+'" style="width:100%;height:100%;object-fit:cover">':'';
    var b64=btoa(unescape(encodeURIComponent(JSON.stringify(it))));
    html+='<div onclick="openItemSheetB64(\''+b64+'\')" style="display:flex;align-items:center;gap:11px;padding:11px 14px;border:1px solid #161a28;border-radius:13px;margin-bottom:8px;cursor:pointer;background:rgba(255,255,255,.02)">';
    html+='<div style="width:44px;height:44px;border-radius:10px;flex-shrink:0;overflow:hidden;background:#1a1d2b">'+img+'</div>';
    html+='<div style="flex:1;min-width:0"><div style="font-weight:600;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+esc(it.name||'')+'</div>';
    html+='<span style="font-family:GT America Mono,monospace;font-size:8.5px;padding:1px 7px;border-radius:8px;background:'+c+'22;color:'+c+'">'+lbl+'</span></div>';
    html+='<span style="color:#5a6478;font-size:18px">&#8250;</span></div>';
  }
  document.getElementById('day-sheet-list').innerHTML=html;
  document.getElementById('day-sheet').classList.add('open');
}

function openItemSheetB64(b64){
  try{
    var json=decodeURIComponent(escape(atob(b64)));
    var d=JSON.parse(json);
    document.getElementById('day-sheet').classList.remove('open');
    renderItem(d);
    document.getElementById('item-sheet').classList.add('open');
  }catch(e){console.error(e);}
}

function renderItem(d){
  var c=_C[d.src]||'#e4c590';
  // Banner
  document.getElementById('item-banner').style.background=d.image_url?'url("'+esc(d.image_url)+'") center/cover':'linear-gradient(135deg,#13243a,#5aa9d8 55%,#1f3f6b)';
  // Source badge
  document.getElementById('item-src-label').textContent=_L[d.src]||'NFT MINT';
  document.getElementById('item-src-badge').style.background='rgba('+hexToRgb(c)+',0.15)';
  document.getElementById('item-src-badge').style.color=c;
  document.getElementById('item-src-badge').style.borderColor='rgba('+hexToRgb(c)+',0.35)';
  document.getElementById('item-src-dot').style.background=c;
  // Title + meta
  document.getElementById('item-title').textContent=d.name||d.title||'';
  var dateStr=d.mint_date?new Date(d.mint_date+'T12:00:00').toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}):'';
  var chainCol=d.chain==='Solana'?'#b69cff':d.chain==='Bitcoin'?'#e4c590':'#6fe3ff';
  document.getElementById('item-meta').innerHTML=
    (d.chain?'<span style="display:inline-flex;align-items:center;gap:4px;font-family:GT America Mono,monospace;font-size:11px;color:'+chainCol+'"><span style="width:5px;height:5px;border-radius:50%;background:'+chainCol+'"></span>'+esc(d.chain)+'</span>':'')+
    (dateStr?'<span style="color:#5a6478;margin:0 6px">&#183;</span><span style="font-family:GT America Mono,monospace;font-size:11px;color:#9aa3b8">'+dateStr+'</span>':'');
  // Info grid — 6 cells like desktop
  var cells=[
    (d.chain&&String(d.chain).trim()!==''&&d.chain!=='TBA') ? ['CHAIN', d.chain, chainCol] : null,
    (d.price&&String(d.price).trim()!==''&&d.price!=='TBA'&&d.price!=='0') ? ['MINT PRICE', d.price, '#eef1f8'] : null,
    (d.supply&&String(d.supply).trim()!==''&&d.supply!=='TBA'&&d.supply!=='0') ? ['SUPPLY', d.supply, '#eef1f8'] : null,
    ['TYPE',       _L[d.src]||(d.src==='mint'?'Public Mint':'GTD WL'), c],
    d.twitter    ? ['PROJECT',    '@'+String(d.twitter).replace(/^@/,''), '#bfe9f5'] : null,
    d.mint_url   ? ['MINT PAGE',  (function(u){u=u.replace(/^https?:\/\//,'').replace(/^www\./,'').split('/')[0];return u.length>18?u.slice(0,18)+'...':u;})(d.mint_url), '#6fe3ff'] : null,
  ].filter(Boolean);
  var html='<div style="display:grid;grid-template-columns:1fr 1fr;gap:1px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.07);border-radius:14px;overflow:hidden;margin-bottom:16px">';
  for(var i=0;i<cells.length;i++){
    html+='<div style="background:rgba(255,255,255,.02);padding:12px 14px">';
    html+='<div style="font-family:GT America Mono,monospace;font-size:8.5px;letter-spacing:.12em;color:#5a6478;margin-bottom:5px">'+cells[i][0]+'</div>';
    if(i===5&&d.mint_url)
      html+='<div style="font-size:13px;font-weight:500;color:'+cells[i][2]+';white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><a href="'+esc(d.mint_url)+'" target="_blank" style="color:#6fe3ff;text-decoration:none">'+esc(cells[i][1])+'</a></div>';
    else
      html+='<div style="font-size:13px;font-weight:500;color:'+cells[i][2]+';white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+esc(cells[i][1])+'</div>';
    html+='</div>';
  }
  html+='</div>';
  if(d.description) html+='<p style="font-size:12px;color:#9aa3b8;line-height:1.6;margin-bottom:14px">'+esc(d.description)+'</p>';
  // Buttons
  html+='<div style="display:flex;gap:10px;align-items:center;margin-bottom:16px">';
  html+='<div id="m-add-cal-btn" onclick="addToMyCalendar()" style="flex:1;position:relative;overflow:hidden;display:flex;align-items:center;justify-content:center;padding:2px;border-radius:13px;background:conic-gradient(from 0deg,rgba(111,227,255,.14),#6fe3ff 15%,#b69cff 29%,rgba(111,227,255,.14) 48%,rgba(111,227,255,.14));cursor:pointer;max-width:220px"><span style="display:flex;align-items:center;justify-content:center;gap:7px;width:100%;padding:12px 16px;border-radius:11px;background:linear-gradient(180deg,rgba(18,24,38,.9),rgba(9,12,22,.95));color:#eef1f8;font-family:GT America Mono,monospace;font-size:12px;font-weight:500;white-space:nowrap">&#128197; Add to Calendar</span></div>';
  if(d.mint_url) html+='<a href="'+esc(d.mint_url)+'" target="_blank" style="flex-shrink:0;display:flex;align-items:center;justify-content:center;padding:12px 16px;border-radius:13px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);color:#c8cedd;font-family:GT America Mono,monospace;font-size:12px;text-decoration:none;white-space:nowrap">Mint &#8599;</a>';
  html+='</div>';
  // Staff edit section
  html+='<div id="item-staff-section"></div>';
  document.getElementById('item-body').innerHTML=html;
  // Store current item for add-to-calendar
  window._currentItem=d;
  // Render staff edit if staff
  renderStaffEdit(d);
}

function hexToRgb(hex){
  var r=parseInt(hex.slice(1,3),16),g=parseInt(hex.slice(3,5),16),b=parseInt(hex.slice(5,7),16);
  return r+','+g+','+b;
}

async function addToMyCalendar(){
  var d=window._currentItem;
  if(!d) return;
  var btn=document.getElementById('m-add-cal-btn');
  if(btn && btn.dataset.added==='1') return;
  if(btn){
    btn.style.pointerEvents='none';
    btn.querySelector('span').innerHTML='Adding\u2026';
  }
  var fd=new FormData();
  fd.append('action','add_from_cal');
  fd.append('name',       d.name||d.title||'');
  fd.append('chain',      d.chain||'Ethereum');
  fd.append('mint_date',  d.mint_date||'');
  fd.append('image_url',  d.image_url||d.image||'');
  fd.append('mint_url',   d.mint_url||'');
  fd.append('price',      d.price||'');
  fd.append('supply',     d.supply||'');
  try{
    var res=await fetch('/mobile-calendar.php?tab=mine',{method:'POST',body:fd});
    var j=await res.json();
    if(j.ok){
      if(btn){
        btn.dataset.added='1';
        btn.style.cursor='default';
        btn.querySelector('span').innerHTML='&#10003; Added to My Calendar';
        btn.querySelector('span').style.color='#4ade80';
      }
    } else {
      alert(j.message||'Failed to add.');
      if(btn){ btn.style.pointerEvents=''; btn.querySelector('span').innerHTML='&#128197; Add to Calendar'; }
    }
  }catch(e){
    alert('Network error. Please try again.');
    if(btn){ btn.style.pointerEvents=''; btn.querySelector('span').innerHTML='&#128197; Add to Calendar'; }
  }
}

function renderStaffEdit(d){
  var el=document.getElementById('item-staff-section');
  if(!el||!_isStaff) return;
  if(d.src!=='mint'&&d.src!=='sub') return;
  el.innerHTML='<div style="margin-top:16px;padding-top:14px;border-top:1px solid rgba(255,255,255,.07)">'+
    '<div style="font-family:GT America Mono,monospace;font-size:9.5px;letter-spacing:.1em;color:#5a6478;margin-bottom:12px">STAFF &#183; EDIT</div>'+
    '<form onsubmit="submitStaffEdit(event)">'+
    '<input type="hidden" name="mint_id" id="se-id" value="'+(d.id||'')+'">'+
    '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px">'+
    '<input class="m-input" name="name" placeholder="Name" value="'+esc(d.name||'')+'" style="font-size:12px;padding:9px 12px">'+
    '<input class="m-input" name="twitter" placeholder="Twitter handle" value="'+esc((d.twitter||'').replace(/^@/,''))+'" style="font-size:12px;padding:9px 12px">'+
    '</div>'+
    '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px">'+
    '<input class="m-input" name="price" placeholder="Price" value="'+esc(d.price||'')+'" style="font-size:12px;padding:9px 12px">'+
    '<input class="m-input" name="supply" placeholder="Supply" value="'+esc(d.supply||'')+'" style="font-size:12px;padding:9px 12px">'+
    '</div>'+
    '<input class="m-input" name="mint_url" placeholder="Mint URL" value="'+esc(d.mint_url||'')+'" style="font-size:12px;padding:9px 12px;width:100%;margin-bottom:8px">'+
    '<div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">'+
    '<span style="font-family:GT America Mono,monospace;font-size:10px;color:#7a8398;white-space:nowrap;flex-shrink:0">MINT DATE</span>'+
    '<input class="m-input" name="mint_date" type="date" value="'+(d.mint_date||'')+'" style="font-size:12px;padding:8px 12px;flex:1">'+
    '</div>'+
    '<div style="position:relative;overflow:hidden;display:flex;align-items:center;justify-content:center;padding:2px;border-radius:13px;background:conic-gradient(from 0deg,rgba(182,156,255,.14),#b69cff 15%,#6fe3ff 29%,rgba(182,156,255,.14) 48%,rgba(182,156,255,.14));cursor:pointer"><button type="submit" style="display:flex;align-items:center;justify-content:center;width:100%;padding:12px;border-radius:11px;background:linear-gradient(180deg,rgba(18,24,38,.9),rgba(9,12,22,.95));color:#eef1f8;font-family:GT America Mono,monospace;font-size:12px;border:none;cursor:pointer">Save Changes</button></div>'+
    '<div id="se-msg" style="text-align:center;font-size:11px;margin-top:8px;display:none"></div>'+
    '</form></div>';
}

async function submitStaffEdit(e){
  e.preventDefault();
  var form=e.target;
  var data={mint_id:form.mint_id.value,name:form.name.value,twitter:form.twitter.value,price:form.price.value,supply:form.supply.value,mint_url:form.mint_url.value,mint_date:form.mint_date.value};
  var msg=document.getElementById('se-msg');
  try{
    var res=await fetch('/bs-api/update_mint.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
    var j=await res.json();
    msg.style.display='block';msg.style.color=j.success?'#e4c590':'#f87171';
    msg.textContent=j.success?'Saved!':'Error: '+(j.message||'Failed');
    if(j.success) setTimeout(function(){document.getElementById('item-sheet').classList.remove('open');location.reload();},1000);
  }catch(err){msg.style.display='block';msg.style.color='#f87171';msg.textContent='Network error';}
}
</script>
</body>
</html>