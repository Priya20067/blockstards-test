<?php
require_once __DIR__.'/config.php';

$user = get_user();
if (!$user) { header('Location: /bs-auth/discord.php?next=' . urlencode($_SERVER['REQUEST_URI'])); exit; }

$allowed_tables = ['bs_mints', 'bs_mint_submissions'];
$msg = ''; $msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $record_id    = trim($_POST['record_id'] ?? '');
    $record_table = trim($_POST['record_table'] ?? '');
    $name         = trim($_POST['name'] ?? '');
    $suggested    = trim($_POST['suggested_date'] ?? '');

    if (!$record_id || !in_array($record_table, $allowed_tables, true) || !$suggested) {
        $msg = 'Missing or invalid fields.'; $msg_type = 'error';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $suggested)) {
        $msg = 'Invalid date format.'; $msg_type = 'error';
    } else {
        $r = sb('bs_mint_date_suggestions')->insert([
            'record_id'      => $record_id,
            'record_table'   => $record_table,
            'name'           => $name,
            'suggested_date' => $suggested,
            'suggested_by'   => $user['discord_id'],
            'status'         => 'pending',
        ]);
        if (($r['code'] ?? 0) >= 400) {
            $msg = 'Save failed: ' . json_encode($r['data'] ?? $r); $msg_type = 'error';
        } else {
            $msg = 'Submitted! Staff will review and publish your suggested date.'; $msg_type = 'success';
        }
    }
}

$record_id    = $_GET['id'] ?? ($_POST['record_id'] ?? '');
$record_table = $_GET['table'] ?? ($_POST['record_table'] ?? '');
$name         = $_GET['name'] ?? ($_POST['name'] ?? '');
$valid = $record_id && in_array($record_table, $allowed_tables, true);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>Suggest Mint Date — Blockstards</title>
<link rel="stylesheet" href="/bs_fonts.css?v=1783164697">
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
html,body{background:#080808;color:#fff;font-family:'GT America Mono',monospace;font-size:14px;min-height:100vh;}
a{text-decoration:none;color:inherit;}
.top-bar{background:#111;border-bottom:1px solid #1e1e1e;padding:12px 16px;display:flex;align-items:center;gap:10px;position:sticky;top:0;z-index:100;}
.back-btn{color:#888;text-decoration:none;display:flex;align-items:center;justify-content:center;width:30px;height:30px;}
.back-btn svg{width:20px;height:20px;}
.top-title{font-family:'GT America',sans-serif;font-weight:800;font-size:16px;}
.content{padding:18px 16px 100px;}
.ok-bar{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#4ade80;padding:12px 14px;border-radius:8px;font-size:12px;margin-bottom:16px;}
.err-bar{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#f87171;padding:12px 14px;border-radius:8px;font-size:12px;margin-bottom:16px;}
.card{background:#111;border:1px solid #1e1e1e;border-radius:14px;padding:20px;}
.proj-row{display:flex;align-items:center;gap:12px;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid #1e1e1e;}
.proj-icon{width:44px;height:44px;border-radius:10px;background:#1a1a1a;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;}
.proj-name{font-family:'GT America',sans-serif;font-size:16px;font-weight:800;color:#fff;}
.proj-sub{font-size:10px;color:#f97316;margin-top:2px;}
.field-label{font-size:10px;text-transform:uppercase;letter-spacing:.1em;color:#888;margin-bottom:8px;font-weight:600;}
.date-input{width:100%;background:#0c0c0c;border:1px solid #2a2a2a;border-radius:10px;padding:14px;color:#fff;font-size:15px;font-family:'GT America Mono',monospace;margin-bottom:18px;}
.date-input:focus{outline:none;border-color:#FFD700;}
.btn-sub{width:100%;padding:14px;background:#FFD700;color:#000;border:none;border-radius:10px;font-family:'GT America',sans-serif;font-weight:800;font-size:14px;cursor:pointer;}
.hint{font-size:10px;color:#555;margin-top:12px;text-align:center;line-height:1.5;}
.empty{text-align:center;padding:50px 20px;color:#555;font-size:12px;line-height:1.6;}
.empty a{color:#FFD700;}
.back-link{display:block;width:100%;text-align:center;padding:13px;background:#FFD700;color:#000;border-radius:10px;font-family:'GT America',sans-serif;font-weight:800;font-size:13px;margin-top:18px;}
</style>
</head>
<body>

<div class="top-bar">
  <a href="/mobile-calendar.php" class="back-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg></a>
  <div class="top-title">Suggest Mint Date</div>
</div>

<div class="content">

<?php if($msg): ?>
<div class="<?= $msg_type==='success' ? 'ok-bar' : 'err-bar' ?>"><?= $msg_type==='success' ? '✓ ' : '✗ ' ?><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if(!$valid): ?>
<div class="empty">Invalid or missing project reference.<br>Go back to the <a href="/mobile-calendar.php">Mint Calendar</a> and try again.</div>

<?php elseif($msg_type !== 'success'): ?>
<div class="card">
  <div class="proj-row">
    <div class="proj-icon">🪙</div>
    <div>
      <div class="proj-name"><?= htmlspecialchars($name) ?></div>
      <div class="proj-sub">Date is currently TBA</div>
    </div>
  </div>

  <form method="POST">
    <input type="hidden" name="record_id" value="<?= htmlspecialchars($record_id) ?>">
    <input type="hidden" name="record_table" value="<?= htmlspecialchars($record_table) ?>">
    <input type="hidden" name="name" value="<?= htmlspecialchars($name) ?>">

    <div class="field-label">Suggested Mint Date</div>
    <input type="date" name="suggested_date" class="date-input" required>

    <button type="submit" class="btn-sub">Submit Suggestion</button>
  </form>
  <div class="hint">Staff will review before this goes live on the calendar.</div>
</div>

<?php else: ?>
<a href="/mobile-calendar.php" class="back-link">Back to Calendar</a>
<?php endif; ?>

</div>

</body>
</html>