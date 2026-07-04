<?php
require_once __DIR__.'/config.php';

$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (preg_match('/Mobile|Android|iPhone|iPad|webOS|BlackBerry/i', $ua)) {
    header('Location: /mobile-auction-form.php'); exit;
}

$user = get_user();
if (!$user) { header('Location: /bs-auth/discord.php?redirect=/auction-form.php'); exit; }

$active_page = 'request';
$uid         = $user['discord_id'];
$success     = false;
$error       = '';

// ── Handle form submit ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title_val    = trim($_POST['title']        ?? '');
    $desc         = trim($_POST['description']  ?? '');
    $winners      = max(1, (int)($_POST['winners'] ?? 1));
    $chain        = trim($_POST['chain']        ?? '');
    $reward_type  = trim($_POST['reward_type']  ?? '');
    $starting_bid = 1.0;
    $duration_h   = (int)($_POST['duration_h'] ?? 24);
    $twitter      = trim($_POST['twitter']      ?? '');
    $supply       = trim($_POST['supply']       ?? '');
    $mint_price   = trim($_POST['mint_price']   ?? '');

    if (!$title_val || !$chain || !$reward_type) {
        $error = 'Title, Chain and Reward Type are required.';
    } else {
        $guild_id_sel = in_array($_POST['guild_id'] ?? '', ['1501007433328234576','1518171963028275260'])
            ? $_POST['guild_id'] : DISCORD_GUILD_ID;
        $expires_at = date('c', time() + ($duration_h * 3600));
        $r = sb('bs_auction_requests')->insert([
            'guild_id'     => $guild_id_sel,
            'requester_id' => $uid,
            'title'        => $title_val,
            'description'  => $desc,
            'winners'      => $winners,
            'chain'        => $chain,
            'reward_type'  => $reward_type,
            'starting_bid' => $starting_bid,
            'duration_h'   => $duration_h,
            'expires_at'   => $expires_at,
            'twitter'      => $twitter,
            'supply'       => $supply ? (int)$supply : null,
            'mint_price'   => $mint_price ?: null,
            'image_url'    => '',
            'status'       => 'pending',
        ]);
        if (($r['code'] ?? 200) >= 400) {
            $error = 'Failed to submit: ' . ($r['data']['message'] ?? 'Unknown error');
        } else {
            $success = true;
        }
    }
}

// ── User meta ──────────────────────────────────────────────────────────────
$user_balance    = get_balance($uid);
$user_name       = htmlspecialchars($user['username'] ?? 'User');
$user_initial    = strtoupper(substr($user['username'] ?? 'U', 0, 1));
$user_avatar_url = get_avatar_url($uid, $user['avatar'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Request Auction · Blockstards</title>
<link rel="stylesheet" href="/bs_fonts.css?v=1783108465">
<link rel="stylesheet" href="/bs_design.css?v=1783108465">
</head>
<body>
<div class="bs-layout">
<?php require_once __DIR__.'/includes/bs_sidebar.php'; ?>
<main class="bs-main" style="max-width:1200px">

  <div class="bs-topbar">
    <div class="bs-breadcrumb">CLUB / <span class="bc-active">REQUEST AUCTION</span></div>
    <div class="bs-topbar-right">
      <div class="bs-blox-pill"><span class="bs-blox-dot"></span><span class="bs-blox-val"><?= number_format($user_balance,2) ?> $BLOX</span></div>
      <a href="/profile/" class="bs-topbar-avatar">
        <?php if ($user_avatar_url): ?><img src="<?= htmlspecialchars($user_avatar_url) ?>" alt=""><?php else: ?><?= $user_initial ?><?php endif; ?>
      </a>
    </div>
  </div>

  <div style="margin-bottom:24px">
    <h1 class="bs-page-title">Request an Auction</h1>
    <p class="bs-page-sub">Submit a project to be auctioned — staff review every request before it goes live.</p>
  </div>

  <div style="max-width:680px">

    <?php if ($success): ?>
    <div class="bs-notice bs-notice-green" style="margin-bottom:22px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#4ade80" stroke-width="2" style="flex-shrink:0;margin-top:1px"><path d="M20 6 9 17l-5-5"/></svg>
      <div class="bs-notice-text" style="color:#86efac">Request submitted! Staff will review it and ping you in Discord when it's live.</div>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="bs-notice bs-notice-red" style="margin-bottom:22px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="2" style="flex-shrink:0;margin-top:1px"><path d="M12 9v4M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
      <div class="bs-notice-text" style="color:#fca5a5"><?= htmlspecialchars($error) ?></div>
    </div>
    <?php endif; ?>

    <!-- Form card -->
    <div class="bs-card-panel">
      <!-- Card header with user avatar -->
      <div style="padding:16px 22px;border-bottom:1px solid #12151f;display:flex;align-items:center;gap:11px">
        <div class="bs-user-avatar" style="width:32px;height:32px;font-size:14px">
          <?php if ($user_avatar_url): ?><img src="<?= htmlspecialchars($user_avatar_url) ?>" alt=""><?php else: ?><?= $user_initial ?><?php endif; ?>
        </div>
        <div>
          <div style="font-weight:600;font-size:14px">New Auction Request</div>
          <div style="font-family:'GT America Mono',monospace;font-size:10px;color:#5a6478;margin-top:1px">Submitting as <?= $user_name ?></div>
        </div>
      </div>

      <form method="post" style="padding:24px 22px">
        <!-- Project title -->
        <div class="bs-field">
          <label>PROJECT TITLE <span class="req">*</span></label>
          <input class="bs-input" type="text" name="title" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" placeholder="e.g. Azuki Elementals GTD" required>
        </div>

        <!-- Twitter / X handle -->
        <div class="bs-field">
          <label>PROJECT X HANDLE <span style="color:#5a6478">— banner auto-loads</span></label>
          <div style="display:flex;align-items:center;background:#0a0d18;border:1px solid #232838;border-radius:11px;padding:0 15px">
            <span style="font-family:'GT America Mono',monospace;font-size:14px;color:#5a6478">@</span>
            <input class="bs-input" type="text" name="twitter" value="<?= htmlspecialchars($_POST['twitter'] ?? '') ?>" placeholder="ProjectHandle" style="border:none;background:transparent;padding-left:8px">
          </div>
        </div>

        <!-- Description -->
        <div class="bs-field">
          <label>DESCRIPTION</label>
          <textarea class="bs-input" name="description" placeholder="What is being auctioned? Add any details collectors should know…" style="min-height:96px;resize:vertical"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>

        <div class="bs-divider-line"></div>
        <div class="bs-section-title">AUCTION TERMS</div>

        <!-- Chain + Reward type -->
        <div class="bs-field bs-field-row bs-field-row-2">
          <div>
            <label>CHAIN <span class="req">*</span></label>
            <div class="bs-select-wrap">
              <select class="bs-input" name="chain">
                <?php foreach (['Ethereum','Solana','Bitcoin','Base','Polygon','Other'] as $c): ?>
                <option <?= (($_POST['chain']??'Ethereum')===$c)?'selected':'' ?>><?= $c ?></option>
                <?php endforeach; ?>
              </select>
              <svg class="bs-select-arrow" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#5a6478" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
          </div>
          <div>
            <label>REWARD TYPE <span class="req">*</span></label>
            <div class="bs-select-wrap">
              <select class="bs-input" name="reward_type">
                <?php foreach (['GTD WL','FCFS WL','Raffle WL','Other'] as $rt): ?>
                <option <?= (($_POST['reward_type']??'GTD WL')===$rt)?'selected':'' ?>><?= $rt ?></option>
                <?php endforeach; ?>
              </select>
              <svg class="bs-select-arrow" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#5a6478" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
          </div>
        </div>

        <!-- Winners + Starting bid -->
        <div class="bs-field bs-field-row bs-field-row-2">
          <div>
            <label>WINNERS</label>
            <input class="bs-input bs-input-mono" type="number" name="winners" min="1" value="<?= (int)($_POST['winners'] ?? 3) ?>">
          </div>
          <div>
            <label>STARTING BID ($BLOX)</label>
            <input class="bs-input bs-input-mono" type="number" name="starting_bid" min="1" step="0.01" value="1" readonly style="opacity:0.5;cursor:not-allowed">
          </div>
        </div>

        <!-- Duration + Supply + Mint price -->
        <div class="bs-field bs-field-row bs-field-row-3">
          <div>
            <label>DURATION</label>
            <div class="bs-select-wrap">
              <select class="bs-input" name="duration_h">
                <?php foreach ([12=>12,24=>24,48=>48,72=>72] as $h=>$label): ?>
                <option value="<?= $h ?>" <?= (($_POST['duration_h']??24)==$h)?'selected':'' ?>><?= $label ?> hours</option>
                <?php endforeach; ?>
              </select>
              <svg class="bs-select-arrow" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#5a6478" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
          </div>
          <div>
            <label>SUPPLY</label>
            <input class="bs-input bs-input-mono" type="text" name="supply" value="<?= htmlspecialchars($_POST['supply'] ?? '') ?>" placeholder="3,333">
          </div>
          <div>
            <label>MINT PRICE</label>
            <input class="bs-input bs-input-mono" type="text" name="mint_price" value="<?= htmlspecialchars($_POST['mint_price'] ?? '') ?>" placeholder="0.05 ETH">
          </div>
        </div>

        <!-- Guild selector (staff only or hidden) -->
        <?php if (is_staff()): ?>
        <div class="bs-field">
          <label>SERVER</label>
          <div class="bs-select-wrap">
            <select class="bs-input" name="guild_id">
              <?php foreach (BOT_GUILDS as $g): ?>
              <option value="<?= $g['id'] ?>" <?= (($_POST['guild_id']??DISCORD_GUILD_ID)===$g['id'])?'selected':'' ?>><?= htmlspecialchars($g['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <svg class="bs-select-arrow" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#5a6478" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
          </div>
        </div>
        <?php else: ?>
        <input type="hidden" name="guild_id" value="<?= DISCORD_GUILD_ID ?>">
        <?php endif; ?>

        <!-- Submit -->
        <button type="submit" class="bs-foil-btn" style="border:none;cursor:pointer;width:100%">
          <span class="bs-foil-btn-inner" style="padding:15px">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6fe3ff" stroke-width="2"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
            Submit for Review
          </span>
        </button>
      </form>
    </div>
  </div>

</main>
</div>
</body>
</html>