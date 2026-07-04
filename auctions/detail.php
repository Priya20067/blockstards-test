<?php
require_once __DIR__.'/../config.php';
$page  = 'auctions';
$title = 'Auction Detail';
$user  = get_user();

$aid     = (int)($_GET['id'] ?? 0);
if (!$aid) { header('Location: /auctions/'); exit; }

$auction = sb('bs_auctions')->eq('id', (string)$aid)->first();
if (!$auction) { header('Location: /auctions/'); exit; }

$bids      = is_array($auction['bids_json']??null) ? $auction['bids_json'] : (json_decode($auction['bids_json']??'{}',true)?:[]);
$usernames = is_array($auction['usernames_json']??null) ? $auction['usernames_json'] : (json_decode($auction['usernames_json']??'{}',true)?:[]);
arsort($bids);

$is_active  = $auction['status'] === 'active';
$is_ended   = $auction['status'] === 'ended';
$winners_n  = (int)($auction['winners'] ?? 1);
$my_bid     = $user ? ($bids[$user['discord_id']] ?? 0) : 0;
$my_rank    = 0;
$i = 0;
foreach ($bids as $did => $amt) {
    $i++;
    if ($user && $did === $user['discord_id']) { $my_rank = $i; break; }
}
$balance = $user ? get_balance($user['discord_id']) : 0;

// POST: end auction (staff only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_staff()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'end' && $is_active) {
        $winners_slice = array_slice($bids, 0, $winners_n, true);
        foreach ($winners_slice as $wid => $amt) {
            sb('bs_wins')->insert(['discord_id'=>$wid,'win_type'=>'auction','ref_id'=>(string)$aid,'title'=>$auction['title']??'','reward_type'=>$auction['reward_type']??'GTD WL','chain'=>$auction['chain']??'Ethereum','image_url'=>$auction['image_url']??'','amount_paid'=>(float)$amt]);
        }
        foreach ($bids as $wid => $amt) {
            if (!isset($winners_slice[$wid])) {
                $bal = sb('bs_user_blox')->eq('discord_id',$wid)->eq('guild_id',DISCORD_GUILD_ID)->select('balance')->first();
                $new = ($bal ? (float)$bal['balance'] : 0) + (float)$amt;
                sb('bs_user_blox')->eq('discord_id',$wid)->eq('guild_id',DISCORD_GUILD_ID)->update(['balance'=>$new]);
            }
        }
        sb('bs_auctions')->eq('id',(string)$aid)->update(['status'=>'ended']);
        sb('bs_auction_end_queue')->insert(['auction_id'=>(string)$aid,'processed'=>false]);
        header('Location: /auctions/detail.php?id='.$aid.'&ended=1'); exit;
    }
    if ($action === 'cancel' && $is_active) {
        foreach ($bids as $wid => $amt) {
            $bal = sb('bs_user_blox')->eq('discord_id',$wid)->eq('guild_id',DISCORD_GUILD_ID)->select('balance')->first();
            $new = ($bal ? (float)$bal['balance'] : 0) + (float)$amt;
            sb('bs_user_blox')->eq('discord_id',$wid)->eq('guild_id',DISCORD_GUILD_ID)->update(['balance'=>$new]);
        }
        sb('bs_auctions')->eq('id',(string)$aid)->update(['status'=>'cancelled']);
        header('Location: /auctions/?tab=live'); exit;
    }
}

require_once __DIR__.'/../includes/header.php';
?>
<style>
.detail-grid{display:grid;grid-template-columns:380px 1fr;gap:24px;align-items:start;}
.detail-img{width:100%;border-radius:14px;aspect-ratio:16/9;object-fit:cover;background:var(--bg2);}
.bid-row{display:flex;align-items:center;gap:10px;padding:9px 12px;background:var(--bg2);border-radius:8px;margin-bottom:6px;border:1px solid var(--border);}
.bid-rank{width:24px;font-size:11px;color:var(--g5);font-weight:700;text-align:center;}
.bid-av{width:30px;height:30px;border-radius:50%;background:var(--bg1);overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:11px;color:var(--gold);}
.bid-av img{width:100%;height:100%;object-fit:cover;}
.win-badge{background:rgba(255,215,0,.15);border:1px solid rgba(255,215,0,.3);color:var(--gold);padding:2px 8px;border-radius:8px;font-size:9px;font-weight:700;}
@media(max-width:700px){.detail-grid{grid-template-columns:1fr;}}

/* Hide number input arrows */
input[type=number]::-webkit-inner-spin-button,
input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
input[type=number] { -moz-appearance: textfield; }
</style>

<div style="margin-bottom:16px">
    <a href="/auctions/" style="font-size:12px;color:var(--g5);text-decoration:none">← All Auctions</a>
</div>

<?php if(isset($_GET['ended'])): ?>
<div style="background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:var(--green);padding:14px 18px;border-radius:10px;margin-bottom:20px">
    <div style="font-weight:700;font-size:13px">🏆 Auction Ended — Winners Announced on Discord!</div>
</div>
<?php endif; ?>

<div class="detail-grid">

<!-- Left: Info -->
<div>
    <?php if($auction['image_url']): ?>
    <img class="detail-img" src="<?= htmlspecialchars($auction['image_url']) ?>">
    <?php endif; ?>
    <div style="margin-top:16px">
        <div style="font-family:GT America,sans-serif;font-size:22px;font-weight:800;color:var(--white);margin-bottom:8px"><?= htmlspecialchars($auction['title']) ?></div>
        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px">
            <span class="badge badge-<?= $is_active?'green':($is_ended?'gray':'red') ?>"><?= $auction['status'] ?></span>
            <span class="badge"><?= htmlspecialchars($auction['chain']??'ETH') ?></span>
            <span class="badge badge-gold"><?= htmlspecialchars($auction['reward_type']??'GTD WL') ?></span>
        </div>
        <?php if($auction['description']??''): ?>
        <p style="font-size:12px;color:var(--g6);line-height:1.7;margin-bottom:14px"><?= nl2br(htmlspecialchars($auction['description'])) ?></p>
        <?php endif; ?>
        <div style="background:var(--bg1);border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:16px">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <div><div style="font-size:9px;color:var(--g5);text-transform:uppercase;letter-spacing:.1em;margin-bottom:3px">Winners</div><div style="font-family:GT America,sans-serif;font-weight:700;font-size:18px;color:var(--white)"><?= $winners_n ?></div></div>
                <div><div style="font-size:9px;color:var(--g5);text-transform:uppercase;letter-spacing:.1em;margin-bottom:3px">Min Bid</div><div style="font-family:GT America,sans-serif;font-weight:700;font-size:18px;color:var(--gold)"><?= number_format($auction['starting_bid']??1,2) ?> $BLOX</div></div>
                <div><div style="font-size:9px;color:var(--g5);text-transform:uppercase;letter-spacing:.1em;margin-bottom:3px">Total Bids</div><div style="font-family:GT America,sans-serif;font-weight:700;font-size:18px;color:var(--white)"><?= count($bids) ?></div></div>
                <div><div style="font-size:9px;color:var(--g5);text-transform:uppercase;letter-spacing:.1em;margin-bottom:3px">Ends</div><div style="font-size:12px;color:var(--white);margin-top:2px"><?= $auction['ends_at'] ? date('M j, H:i',strtotime($auction['ends_at'])) : '—' ?></div></div>
            </div>
        </div>

        <?php if($my_bid > 0): ?>
        <div style="background:rgba(255,215,0,.06);border:1px solid rgba(255,215,0,.2);border-radius:10px;padding:12px 14px;margin-bottom:16px">
            <div style="font-size:11px;color:var(--g5);margin-bottom:4px">Your Bid</div>
            <div style="font-family:GT America,sans-serif;font-weight:700;font-size:18px;color:var(--gold)"><?= number_format($my_bid,2) ?> $BLOX</div>
            <div style="font-size:11px;color:var(--g5);margin-top:3px">
                Rank #<?= $my_rank ?> — 
                <?php if($my_rank <= $winners_n): ?>
                <span style="color:#4ade80">✓ Winning!</span>
                <?php else: ?>
                <span style="color:#f87171">Losing — raise your bid</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if($user && $is_active): ?>
        <div style="display:flex;flex-direction:column;gap:8px">
            <div style="display:flex;gap:8px">
                <input type="number" id="bid-amount" min="<?= $auction['starting_bid']??1 ?>" step="0.0001" placeholder="e.g. 5.50" style="flex:1;background:var(--bg2);border:1px solid var(--border);color:var(--white);border-radius:8px;padding:10px 12px;font-family:'GT America Mono',monospace;font-size:13px;-moz-appearance:textfield;appearance:textfield" oninput="this.style.MozAppearance='textfield'">
                <button class="btn btn-gold" onclick="placeBid(<?= $aid ?>)">Place Bid</button>
            </div>
            <?php if($my_bid > 0): ?>
            <button class="btn" style="background:rgba(239,68,68,.1);color:var(--red);border:1px solid rgba(239,68,68,.2)" onclick="withdrawBid(<?= $aid ?>)">Withdraw Bid (refund <?= number_format($my_bid,2) ?> $BLOX)</button>
            <?php endif; ?>
        </div>
        <?php elseif(!$user && $is_active): ?>
        <button class="btn btn-discord" onclick="location.href='/bs-auth/discord.php'">Login to Bid</button>
        <?php endif; ?>

        <?php if(is_staff() && $is_active): ?>
        <div style="display:flex;gap:8px;margin-top:14px;padding-top:14px;border-top:1px solid var(--border)">
            <form method="POST" style="flex:1">
                <input type="hidden" name="action" value="end">
                <button class="btn btn-gold" style="width:100%" onclick="return confirm('End & announce winners?')">🏆 End Auction</button>
            </form>
            <form method="POST">
                <input type="hidden" name="action" value="cancel">
                <button class="btn" style="background:rgba(239,68,68,.1);color:var(--red);border:1px solid rgba(239,68,68,.2)" onclick="return confirm('Cancel & refund all?')">Cancel</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Right: Bids -->
<div>
    <div style="font-family:GT America,sans-serif;font-size:14px;font-weight:700;color:var(--white);margin-bottom:14px">
        Bids (<?= count($bids) ?>)
    </div>
    <?php if(empty($bids)): ?>
    <div style="color:var(--g5);font-size:13px;text-align:center;padding:40px 0">No bids yet. Be the first!</div>
    <?php else: ?>
    <?php $i = 0; foreach($bids as $did => $amt): $i++; ?>
    <div class="bid-row" style="<?= $i<=$winners_n?'border-color:rgba(255,215,0,.25)':'' ?>">
        <div class="bid-rank" style="<?= $i<=$winners_n?'color:var(--gold)':'' ?>">#<?= $i ?></div>
        <div class="bid-av">
            <?php $u = sb('bs_users')->eq('discord_id',$did)->select('username,avatar')->first(); ?>
            <?php if($u&&$u['avatar']): ?>
            <img src="https://cdn.discordapp.com/avatars/<?= $did ?>/<?= $u['avatar'] ?>.png" onerror="this.style.display='none'">
            <?php else: ?><?= strtoupper(substr($usernames[$did]??'?',0,1)) ?><?php endif; ?>
        </div>
        <div style="flex:1">
            <div style="font-size:12px;font-weight:600;color:var(--white)"><?= htmlspecialchars($usernames[$did] ?? ($u['username']??('...' . substr($did,-4)))) ?></div>
        </div>
        <?php if($i<=$winners_n): ?><span class="win-badge">🏆 Win</span><?php endif; ?>
        <div style="font-family:GT America,sans-serif;font-weight:800;font-size:15px;color:var(--gold)"><?= number_format($amt,2) ?> $BLOX</div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

</div>

<script>
const userId = <?= $user ? "'".$user['discord_id']."'" : 'null' ?>;

async function placeBid(aid) {
    const amt = parseFloat(document.getElementById('bid-amount').value);
    if (!amt || amt <= 0) { showToast('Enter a valid bid amount', 'error'); return; }
    const btn = event.target;
    btn.textContent = 'Bidding...'; btn.disabled = true;
    const res = await fetch('/bs-api/auction_bid.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({auction_id:String(aid),amount:amt})});
    const d = await res.json();
    if (d.success) { showToast('Bid placed! ✓', 'success'); setTimeout(()=>location.reload(), 800); }
    else { showToast(d.message||'Failed', 'error'); btn.textContent='Place Bid'; btn.disabled=false; }
}

async function withdrawBid(aid) {
    if (!confirm('Withdraw your bid?')) return;
    const res = await fetch('/bs-api/auction_withdraw.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({auction_id:String(aid)})});
    const d = await res.json();
    if (d.success) { showToast('Bid withdrawn ✓', 'success'); setTimeout(()=>location.reload(), 800); }
    else showToast(d.message||'Failed', 'error');
}

function showToast(msg, type='info') {
    const t = document.createElement('div');
    t.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:'+(type==='success'?'#166534':type==='error'?'#7f1d1d':'#1e1e1e')+';color:#fff;padding:10px 20px;border-radius:8px;font-size:12px;z-index:9999;font-family:"GT America Mono",monospace;';
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(()=>t.remove(), 2500);
}
</script>
<?php require_once __DIR__.'/../includes/footer.php'; ?>