<?php
require_once __DIR__.'/../config.php';
if (!is_staff()) { header('Location: /'); exit; }

$active_admin     = 'auctions';
$admin_user       = get_user();
$admin_name       = htmlspecialchars($admin_user['username'] ?? 'Admin');
$admin_initial    = strtoupper(substr($admin_user['username'] ?? 'A', 0, 1));
$admin_avatar_url = get_avatar_url($admin_user['discord_id'], $admin_user['avatar'] ?? '');
$msg = ''; $msg_type = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $aid    = (string)(int)($_POST['id'] ?? 0);

    if ($action === 'end' && $aid) {
        $auction = sb('bs_auctions')->eq('id',$aid)->first();
        if ($auction && $auction['status']==='active') {
            $bids = json_decode($auction['bids_json']??'{}',true)?:[];
            arsort($bids);
            $wc = (int)($auction['winners']??1);
            $winners = array_slice($bids, 0, $wc, true);
            foreach ($winners as $wid=>$amt) {
                sb('bs_wins')->insert(['discord_id'=>$wid,'win_type'=>'auction','ref_id'=>$aid,'title'=>$auction['title']??'','reward_type'=>$auction['reward_type']??'GTD WL','chain'=>$auction['chain']??'Ethereum','image_url'=>$auction['image_url']??'','amount_paid'=>(float)$amt]);
            }
            foreach ($bids as $wid=>$amt) {
                if (!isset($winners[$wid])) {
                    $b = sb('bs_user_blox')->eq('discord_id',$wid)->eq('guild_id',DISCORD_GUILD_ID)->select('balance')->first();
                    sb('bs_user_blox')->eq('discord_id',$wid)->eq('guild_id',DISCORD_GUILD_ID)->update(['balance'=>round(($b['balance']??0)+(float)$amt,4)]);
                }
            }
            sb('bs_auctions')->eq('id',$aid)->update(['status'=>'ended']);
            sb('bs_auction_end_queue')->insert(['auction_id'=>$aid,'processed'=>false]);
            $msg='ok:Auction ended! Winners announced on Discord.';
        }
    }

    if ($action === 'cancel' && $aid) {
        $auction = sb('bs_auctions')->eq('id',$aid)->first();
        if ($auction) {
            $bids = json_decode($auction['bids_json']??'{}',true)?:[];
            foreach ($bids as $wid=>$amt) {
                $b = sb('bs_user_blox')->eq('discord_id',$wid)->eq('guild_id',DISCORD_GUILD_ID)->select('balance')->first();
                sb('bs_user_blox')->eq('discord_id',$wid)->eq('guild_id',DISCORD_GUILD_ID)->update(['balance'=>round(($b['balance']??0)+(float)$amt,4)]);
            }
            sb('bs_auctions')->eq('id',$aid)->update(['status'=>'cancelled']);
            $msg='ok:Auction cancelled & all bids refunded.';
        }
    }

    // Approve auction request → create live auction
    if ($action === 'approve_req') {
        $req = sb('bs_auction_requests')->eq('id',(string)(int)$_POST['req_id'])->first();
        if ($req) {
            $dur   = (int)($req['duration_h']??24);
            $ends  = date('c', time() + $dur*3600);
            sb('bs_auctions')->insert(['title'=>$req['title'],'description'=>$req['description']??'','chain'=>$req['chain'],'reward_type'=>$req['reward_type'],'winners'=>(int)($req['winners']??1),'starting_bid'=>(float)($req['starting_bid']??1),'ends_at'=>$ends,'image_url'=>$req['image_url']??'','mint_url'=>$req['mint_url']??'','twitter'=>$req['twitter']??'','supply'=>$req['supply']??null,'mint_price'=>$req['mint_price']??null,'status'=>'active','guild_id'=>$req['guild_id']??DISCORD_GUILD_ID,'bids_json'=>'{}','usernames_json'=>'{}']);
            sb('bs_auction_requests')->eq('id',(string)(int)$_POST['req_id'])->update(['status'=>'approved']);
            $msg = 'ok:Auction approved and now live!';
        }
    }
    if ($action === 'reject_req') {
        sb('bs_auction_requests')->eq('id',(string)(int)$_POST['req_id'])->update(['status'=>'rejected']);
        $msg = 'ok:Request rejected.';
    }
}

$filter  = $_GET['filter'] ?? 'active';
$view_id = (int)($_GET['view'] ?? 0);
$view_auction = null; $bids_detail = [];

if ($view_id) {
    $view_auction = sb('bs_auctions')->eq('id',(string)$view_id)->first();
    if ($view_auction) {
        $bj = json_decode($view_auction['bids_json']??'{}',true)?:[];
        $nj = json_decode($view_auction['usernames_json']??'{}',true)?:[];
        arsort($bj);
        foreach ($bj as $did=>$amt) {
            $u = sb('bs_users')->eq('discord_id',$did)->select('username,avatar')->first();
            $bids_detail[] = ['discord_id'=>$did,'amount'=>$amt,'username'=>$nj[$did]??($u['username']??('…'.substr($did,-4))),'avatar'=>$u['avatar']??''];
        }
    }
} else {
    $rows = $filter==='all' ? sb('bs_auctions')->order('id',false)->get() : sb('bs_auctions')->eq('status',$filter)->order('id',false)->get();
    $auctions = [];
    foreach ($rows as $a) {
        $bids = json_decode($a['bids_json']??'{}',true)?:[];
        arsort($bids);
        $a['bid_count'] = count($bids);
        $a['top_bid']   = $bids?max($bids):0;
        $auctions[] = $a;
    }
    // Pending requests
    try { $pending_reqs = sb('bs_auction_requests')->eq('status','pending')->order('id',false)->get(); } catch(Exception $e) { $pending_reqs=[]; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Auctions · Admin</title>
<link rel="stylesheet" href="/bs_fonts.css?v=1783164697">
<link rel="stylesheet" href="/bs_admin.css?v=1783164697">
</head>
<body>
<div class="adm-layout">
<?php require_once __DIR__.'/bs_admin_sidebar.php'; ?>
<main class="adm-main">

  <div class="adm-topbar">
    <div class="adm-breadcrumb">ADMIN / <span class="bc-active">AUCTIONS</span></div>
    <div class="adm-topbar-right">
      <div class="adm-avatar"><?php if($admin_avatar_url): ?><img src="<?= htmlspecialchars($admin_avatar_url) ?>" alt=""><?php else: ?><?= $admin_initial ?><?php endif; ?></div>
    </div>
  </div>

  <?php if ($msg): [$mt,$mm]=explode(':',$msg,2); ?>
  <div class="adm-notice adm-notice-<?= $mt==='ok'?'green':'red' ?>"><?= htmlspecialchars($mm) ?></div>
  <?php endif; ?>

  <?php if ($view_id && $view_auction): ?>
  <!-- Detail view -->
  <div style="display:grid;grid-template-columns:260px 1fr;gap:20px;align-items:start">
    <div class="adm-panel">
      <?php if($view_auction['image_url']): ?><img src="<?= htmlspecialchars($view_auction['image_url']) ?>" style="width:100%;height:140px;object-fit:cover"><?php endif; ?>
      <div style="padding:16px">
        <div style="font-weight:600;font-size:15px;margin-bottom:10px"><?= htmlspecialchars($view_auction['title']) ?></div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px">
          <span class="adm-badge adm-badge-<?= $view_auction['status']==='active'?'green':($view_auction['status']==='ended'?'gray':'red') ?>"><?= $view_auction['status'] ?></span>
          <span class="adm-badge adm-badge-gold"><?= htmlspecialchars($view_auction['reward_type']??'GTD WL') ?></span>
          <span class="adm-badge adm-badge-cyan"><?= htmlspecialchars($view_auction['chain']??'ETH') ?></span>
        </div>
        <div style="font-size:12px;color:#7a8398;line-height:2">
          <div>🏆 <strong style="color:#eef1f8"><?= $view_auction['winners']??1 ?></strong> winners</div>
          <div>💰 <strong style="color:#e4c590"><?= number_format($view_auction['starting_bid']??1,2) ?> $BLOX</strong> min bid</div>
          <div>👥 <strong style="color:#eef1f8"><?= count($bids_detail) ?></strong> bids</div>
          <div>📅 <?= $view_auction['ends_at']?date('M j Y H:i',strtotime($view_auction['ends_at'])):'—' ?></div>
        </div>
        <?php if($view_auction['status']==='active'): ?>
        <div style="margin-top:14px;display:flex;flex-direction:column;gap:8px">
          <form method="post"><input type="hidden" name="action" value="end"><input type="hidden" name="id" value="<?= $view_id ?>">
            <button class="adm-btn adm-btn-green" style="width:100%" onclick="return confirm('End & announce winners?')">🏆 End Auction</button>
          </form>
          <form method="post"><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= $view_id ?>">
            <button class="adm-btn adm-btn-red" style="width:100%" onclick="return confirm('Cancel & refund all bids?')">✕ Cancel & Refund</button>
          </form>
        </div>
        <?php endif; ?>
        <div style="margin-top:12px"><a href="/bs-admin/auction.php" class="adm-btn adm-btn-ghost" style="width:100%;justify-content:center">← Back to list</a></div>
      </div>
    </div>
    <div>
      <div style="font-family:'GT America Mono',monospace;font-size:10px;letter-spacing:.12em;color:#7a8398;margin-bottom:12px">BIDS (<?= count($bids_detail) ?>)</div>
      <?php if(empty($bids_detail)): ?><div style="text-align:center;padding:40px;color:#5a6478">No bids yet.</div>
      <?php else: $wc=(int)($view_auction['winners']??1);$i=0; foreach($bids_detail as $b): $i++; ?>
      <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:rgba(255,255,255,.02);border:1px solid <?= $i<=$wc?'rgba(228,197,144,.25)':'#161a28' ?>;border-radius:11px;margin-bottom:7px">
        <div style="width:22px;font-family:'GT America Mono',monospace;font-size:11px;font-weight:700;color:<?= $i<=$wc?'#e4c590':'#5a6478' ?>">#<?= $i ?></div>
        <div class="adm-avatar-sm"><?php if($b['avatar']): ?><img src="https://cdn.discordapp.com/avatars/<?= $b['discord_id'] ?>/<?= $b['avatar'] ?>.png" style="width:100%;height:100%;object-fit:cover"><?php else: ?><?= strtoupper(substr($b['username'],0,1)) ?><?php endif; ?></div>
        <div style="flex:1;font-size:13px;font-weight:600"><?= htmlspecialchars($b['username']) ?></div>
        <?php if($i<=$wc): ?><span class="adm-badge adm-badge-gold">🏆 WIN</span><?php endif; ?>
        <div style="font-weight:700;font-size:15px;color:#e4c590"><?= number_format($b['amount'],2) ?> $BLOX</div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <?php else: ?>
  <!-- List view -->
  <div class="adm-page-header">
    <div>
      <h1 class="adm-page-title">Manage Auctions</h1>
      <p class="adm-page-sub">Approve requests, monitor bids, end auctions and announce winners.</p>
    </div>
  </div>

  <!-- Pending requests -->
  <?php if (!empty($pending_reqs)): ?>
  <div class="adm-panel" style="margin-bottom:24px">
    <div class="adm-panel-hdr">
      <span class="adm-panel-hdr-label">PENDING AUCTION REQUESTS</span>
      <span class="adm-badge adm-badge-orange"><?= count($pending_reqs) ?></span>
    </div>
    <?php foreach ($pending_reqs as $req): ?>
    <div class="adm-row">
      <div style="flex:1;min-width:0">
        <div style="font-weight:600;font-size:13.5px"><?= htmlspecialchars($req['title']) ?></div>
        <div style="font-family:'GT America Mono',monospace;font-size:10px;color:#7a8398;margin-top:2px">
          <?= htmlspecialchars($req['chain']??'ETH') ?> · <?= htmlspecialchars($req['reward_type']??'GTD WL') ?> · <?= (int)($req['winners']??1) ?> winners · <?= number_format((float)($req['starting_bid']??1),2) ?> $BLOX min · <?= (int)($req['duration_h']??24) ?>h · by @<?= htmlspecialchars(get_username($req['requester_id']??'')) ?>
        </div>
        <?php if ($req['description']??''): ?><div style="font-size:11px;color:#7a8398;margin-top:3px"><?= htmlspecialchars(substr($req['description'],0,80)) ?><?= strlen($req['description']??'')>80?'…':'' ?></div><?php endif; ?>
      </div>
      <div class="adm-action-btns">
        <form method="post" style="display:inline"><input type="hidden" name="action" value="approve_req"><input type="hidden" name="req_id" value="<?= $req['id'] ?>">
          <button type="submit" class="adm-btn adm-btn-green adm-btn-sm" onclick="return confirm('Approve and go live?')">✓ Approve</button>
        </form>
        <form method="post" style="display:inline"><input type="hidden" name="action" value="reject_req"><input type="hidden" name="req_id" value="<?= $req['id'] ?>">
          <button type="submit" class="adm-btn adm-btn-red adm-btn-sm">✕ Reject</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Filter tabs -->
  <div class="adm-filter-tabs">
    <a href="?filter=active" class="adm-filter-tab <?= $filter==='active'?'active':'' ?>">🟢 Active</a>
    <a href="?filter=ended"  class="adm-filter-tab <?= $filter==='ended'?'active':'' ?>">⚫ Ended</a>
    <a href="?filter=all"    class="adm-filter-tab <?= $filter==='all'?'active':'' ?>">All</a>
  </div>

  <div class="adm-table-wrap">
    <table class="adm-table">
      <thead><tr><th>AUCTION</th><th>CHAIN</th><th>BIDS</th><th>TOP BID</th><th>WINNERS</th><th>ENDS</th><th>STATUS</th><th>ACTION</th></tr></thead>
      <tbody>
      <?php foreach($auctions as $a): ?>
      <tr onclick="location.href='?view=<?= $a['id'] ?>'">
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <?php if($a['image_url']): ?><img src="<?= htmlspecialchars($a['image_url']) ?>" class="adm-thumb"><?php endif; ?>
            <div><div style="font-weight:600;font-size:13px"><?= htmlspecialchars($a['title']) ?></div><div style="font-size:10px;color:#5a6478">#<?= $a['id'] ?></div></div>
          </div>
        </td>
        <td><span class="adm-badge adm-badge-cyan"><?= htmlspecialchars($a['chain']??'ETH') ?></span></td>
        <td style="font-weight:700"><?= $a['bid_count'] ?></td>
        <td style="color:#e4c590;font-weight:700"><?= number_format($a['top_bid'],2) ?></td>
        <td><?= $a['winners']??1 ?></td>
        <td style="font-family:'GT America Mono',monospace;font-size:11px;color:#7a8398"><?= $a['ends_at']?date('M j, H:i',strtotime($a['ends_at'])):'—' ?></td>
        <td><span class="adm-badge adm-badge-<?= $a['status']==='active'?'green':($a['status']==='ended'?'gray':'red') ?>"><?= $a['status'] ?></span></td>
        <td onclick="event.stopPropagation()">
          <?php if($a['status']==='active'): ?>
          <form method="post" style="display:inline"><input type="hidden" name="action" value="end"><input type="hidden" name="id" value="<?= $a['id'] ?>">
            <button class="adm-btn adm-btn-green adm-btn-sm" onclick="return confirm('End #<?= $a['id'] ?>?')">🏆 End</button>
          </form>
          <?php else: ?>
          <a href="?view=<?= $a['id'] ?>" class="adm-btn adm-btn-ghost adm-btn-sm">View</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($auctions)): ?><tr><td colspan="8" style="text-align:center;padding:40px;color:#5a6478">No auctions found.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

</main>
</div>
</body>
</html>