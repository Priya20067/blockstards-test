<?php
require_once __DIR__.'/config.php';
$user=get_user();if(!$user){header('Location: /bs-auth/discord.php');exit;}if(!is_staff()){header('Location: /mobile.php');exit;}
$uid=$user['discord_id'];$blox_bal=0;try{$blox_bal=get_balance($uid);}catch(Exception $e){}
$m_active='admin';$msg='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    $action=$_POST['action']??'';$aid=(string)(int)($_POST['id']??0);
    if($action==='end'&&$aid){
        $auction=sb('bs_auctions')->eq('id',$aid)->first();
        if($auction&&$auction['status']==='active'){
            $bids=json_decode($auction['bids_json']??'{}',true)?:[];arsort($bids);
            $wc=(int)($auction['winners']??1);$winners=array_slice($bids,0,$wc,true);
            foreach($winners as $wid=>$amt) sb('bs_wins')->insert(['discord_id'=>$wid,'win_type'=>'auction','ref_id'=>$aid,'title'=>$auction['title']??'','reward_type'=>$auction['reward_type']??'GTD WL','chain'=>$auction['chain']??'ETH','image_url'=>$auction['image_url']??'','amount_paid'=>(float)$amt]);
            foreach($bids as $wid=>$amt) if(!isset($winners[$wid])){$b=sb('bs_user_blox')->eq('discord_id',$wid)->eq('guild_id',DISCORD_GUILD_ID)->select('balance')->first();sb('bs_user_blox')->eq('discord_id',$wid)->eq('guild_id',DISCORD_GUILD_ID)->update(['balance'=>round(($b['balance']??0)+(float)$amt,4)]);}
            sb('bs_auctions')->eq('id',$aid)->update(['status'=>'ended']);
            sb('bs_auction_end_queue')->insert(['auction_id'=>$aid,'processed'=>false]);
            header('Location: /mobile-admin-auctions.php?ok=ended');exit;
        }
    }
    if($action==='cancel'&&$aid){
        $auction=sb('bs_auctions')->eq('id',$aid)->first();
        if($auction){$bids=json_decode($auction['bids_json']??'{}',true)?:[];foreach($bids as $wid=>$amt){$b=sb('bs_user_blox')->eq('discord_id',$wid)->eq('guild_id',DISCORD_GUILD_ID)->select('balance')->first();sb('bs_user_blox')->eq('discord_id',$wid)->eq('guild_id',DISCORD_GUILD_ID)->update(['balance'=>round(($b['balance']??0)+(float)$amt,4)]);}sb('bs_auctions')->eq('id',$aid)->update(['status'=>'cancelled']);header('Location: /mobile-admin-auctions.php?ok=cancelled');exit;}
    }
    if($action==='approve_req'){
        $req=sb('bs_auction_requests')->eq('id',(string)(int)$_POST['req_id'])->first();
        if($req){$ends=date('c',time()+(int)($req['duration_h']??24)*3600);sb('bs_auctions')->insert(['title'=>$req['title'],'description'=>$req['description']??'','chain'=>$req['chain'],'reward_type'=>$req['reward_type'],'winners'=>(int)($req['winners']??1),'starting_bid'=>(float)($req['starting_bid']??1),'ends_at'=>$ends,'image_url'=>$req['image_url']??'','mint_url'=>$req['mint_url']??'','status'=>'active','guild_id'=>$req['guild_id']??DISCORD_GUILD_ID,'bids_json'=>'{}','usernames_json'=>'{}']);sb('bs_auction_requests')->eq('id',(string)(int)$_POST['req_id'])->update(['status'=>'approved']);header('Location: /mobile-admin-auctions.php?ok=approved');exit;}
    }
    if($action==='reject_req'){sb('bs_auction_requests')->eq('id',(string)(int)$_POST['req_id'])->update(['status'=>'rejected']);header('Location: /mobile-admin-auctions.php?ok=rejected');exit;}
}

$view_id=(int)($_GET['view']??0);$view_auction=null;$bids_detail=[];
if($view_id){
    $view_auction=sb('bs_auctions')->eq('id',(string)$view_id)->first();
    if($view_auction){$bj=json_decode($view_auction['bids_json']??'{}',true)?:[];$nj=json_decode($view_auction['usernames_json']??'{}',true)?:[];arsort($bj);foreach($bj as $did=>$amt){$u=sb('bs_users')->eq('discord_id',$did)->select('username')->first();$bids_detail[]=['discord_id'=>$did,'amount'=>$amt,'username'=>$nj[$did]??($u['username']??('…'.substr($did,-4)))];}
    }
}
$rows=sb('bs_auctions')->order('id',false)->get();$auctions=[];
foreach($rows as $a){$bids=json_decode($a['bids_json']??'{}',true)?:[];arsort($bids);$a['bid_count']=count(array_filter($bids,fn($v)=>floatval($v)>0));$a['top_bid']=$bids?reset($bids):0;$auctions[]=$a;}
try{$pending_reqs=sb('bs_auction_requests')->eq('status','pending')->order('id',false)->get();}catch(Exception $e){$pending_reqs=[];}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Auctions · Admin</title>
<link rel="stylesheet" href="/bs_fonts.css?v=1783108465">
<link rel="stylesheet" href="/bs_mobile.css?v=1783108465">
</head>
<body>
<?php require_once __DIR__.'/includes/bs_mobile_nav.php'; ?>
<div class="m-body">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:18px">
    <a href="<?= $view_id?'/mobile-admin-auctions.php':'/mobile-admin.php' ?>" style="width:34px;height:34px;border-radius:9px;background:rgba(255,255,255,.04);border:1px solid #161a28;display:flex;align-items:center;justify-content:center;color:#aab2c5;text-decoration:none;font-size:16px">‹</a>
    <h1 class="m-page-title" style="font-size:22px;margin-bottom:0"><?= $view_id&&$view_auction?htmlspecialchars($view_auction['title']):'Manage Auctions' ?></h1>
  </div>
  <?php if(isset($_GET['ok'])): ?><div class="m-notice m-notice-green">✓ <?= ['ended'=>'Auction ended!','cancelled'=>'Cancelled & refunded!','approved'=>'Auction now live!','rejected'=>'Rejected.'][$_GET['ok']]??'Done!' ?></div><?php endif; ?>

  <?php if($view_id&&$view_auction): ?>
  <!-- Detail view -->
  <div style="margin-bottom:16px">
    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px">
      <span class="m-badge m-badge-<?= $view_auction['status']==='active'?'green':($view_auction['status']==='ended'?'gray':'red') ?>"><?= $view_auction['status'] ?></span>
      <span class="m-badge m-badge-gold"><?= htmlspecialchars($view_auction['reward_type']??'GTD WL') ?></span>
      <span class="m-badge m-badge-cyan"><?= htmlspecialchars($view_auction['chain']??'ETH') ?></span>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px">
      <div class="m-stat" style="padding:11px"><div class="m-stat-val" style="font-size:18px;color:#e4c590"><?= number_format($view_auction['starting_bid']??1,2) ?></div><div class="m-stat-label" style="font-size:8px">MIN BID</div></div>
      <div class="m-stat" style="padding:11px"><div class="m-stat-val" style="font-size:18px"><?= count($bids_detail) ?></div><div class="m-stat-label" style="font-size:8px">BIDS</div></div>
    </div>
    <?php if($view_auction['status']==='active'): ?>
    <div style="display:flex;gap:10px;margin-bottom:16px">
      <form method="post" style="flex:1"><input type="hidden" name="action" value="end"><input type="hidden" name="id" value="<?= $view_id ?>"><button class="m-foil-btn" style="border:none;cursor:pointer;width:100%" onclick="return confirm('End & announce?')"><span class="m-foil-btn-inner">🏆 End Auction</span></button></form>
      <form method="post" style="flex:1"><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= $view_id ?>"><button style="width:100%;padding:13px;border-radius:11px;background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.3);color:#f87171;font-family:'GT America Mono',monospace;font-size:12px;cursor:pointer" onclick="return confirm('Cancel & refund all?')">✕ Cancel & Refund</button></form>
    </div>
    <?php endif; ?>
    <?php $wc=(int)($view_auction['winners']??1);$i=0;foreach($bids_detail as $b):$i++; ?>
    <div style="display:flex;align-items:center;gap:10px;padding:10px 13px;background:rgba(255,255,255,.02);border:1px solid <?= $i<=$wc?'rgba(228,197,144,.25)':'#161a28' ?>;border-radius:11px;margin-bottom:6px">
      <span style="font-family:'GT America Mono',monospace;font-size:10px;color:<?= $i<=$wc?'#e4c590':'#5a6478' ?>;width:20px">#<?= $i ?></span>
      <span style="flex:1;font-size:13px;font-weight:600"><?= htmlspecialchars($b['username']) ?></span>
      <?php if($i<=$wc): ?><span class="m-badge m-badge-gold">WIN</span><?php endif; ?>
      <span style="font-family:'GT America Mono',monospace;font-size:12px;color:#e4c590"><?= number_format($b['amount'],2) ?></span>
    </div>
    <?php endforeach; ?>
    <?php if(empty($bids_detail)): ?><div class="m-empty"><span class="m-empty-icon">💰</span><p>No bids yet.</p></div><?php endif; ?>

  <?php else: ?>
  <!-- Pending requests -->
  <?php if(!empty($pending_reqs)): ?>
  <div style="font-family:'GT America Mono',monospace;font-size:9.5px;letter-spacing:.1em;color:#fb923c;margin-bottom:10px">PENDING REQUESTS (<?= count($pending_reqs) ?>)</div>
  <?php foreach($pending_reqs as $req): ?>
  <div style="padding:12px 14px;background:rgba(251,146,60,.04);border:1px solid rgba(251,146,60,.2);border-radius:12px;margin-bottom:10px">
    <div style="font-weight:600;font-size:14px;margin-bottom:4px"><?= htmlspecialchars($req['title']) ?></div>
    <div style="font-size:11px;color:#7a8398;margin-bottom:10px"><?= htmlspecialchars($req['chain']??'ETH') ?> · <?= htmlspecialchars($req['reward_type']??'WL') ?> · <?= (int)($req['winners']??1) ?> winners · <?= number_format((float)($req['starting_bid']??1),0) ?> $BLOX min</div>
    <div style="display:flex;gap:8px">
      <form method="post" style="flex:1"><input type="hidden" name="action" value="approve_req"><input type="hidden" name="req_id" value="<?= $req['id'] ?>"><button class="m-foil-btn" style="border:none;cursor:pointer;width:100%" onclick="return confirm('Approve & go live?')"><span class="m-foil-btn-inner" style="padding:10px">✓ Approve</span></button></form>
      <form method="post" style="flex:1"><input type="hidden" name="action" value="reject_req"><input type="hidden" name="req_id" value="<?= $req['id'] ?>"><button style="width:100%;padding:10px;border-radius:11px;background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.3);color:#f87171;font-family:'GT America Mono',monospace;font-size:12px;cursor:pointer">✕ Reject</button></form>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <!-- Auction list -->
  <div style="font-family:'GT America Mono',monospace;font-size:9.5px;letter-spacing:.1em;color:#7a8398;margin-bottom:10px">ALL AUCTIONS</div>
  <?php foreach($auctions as $a): ?>
  <div style="display:flex;align-items:center;gap:10px;padding:11px 13px;background:rgba(255,255,255,.02);border:1px solid #161a28;border-radius:12px;margin-bottom:8px" onclick="location.href='?view=<?= $a['id'] ?>'">
    <div style="width:36px;height:36px;border-radius:8px;flex-shrink:0;overflow:hidden;background:#1a1d2b"><?php if($a['image_url']??''): ?><img src="<?= htmlspecialchars($a['image_url']) ?>" style="width:100%;height:100%;object-fit:cover"><?php else: ?>⭐<?php endif; ?></div>
    <div style="flex:1;min-width:0">
      <div style="font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($a['title']) ?></div>
      <div style="font-size:11px;color:#7a8398"><?= $a['bid_count'] ?> bids<?= $a['top_bid']?' · '.number_format($a['top_bid'],2).' top':'' ?></div>
    </div>
    <span class="m-badge m-badge-<?= $a['status']==='active'?'green':($a['status']==='ended'?'gray':'red') ?>"><?= $a['status'] ?></span>
    <span style="color:#5a6478">›</span>
  </div>
  <?php endforeach; ?>
  <?php if(empty($auctions)): ?><div class="m-empty"><span class="m-empty-icon">🔨</span><p>No auctions yet.</p></div><?php endif; ?>
  <?php endif; ?>
</div>
</body>
</html>