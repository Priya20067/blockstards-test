<?php
require_once __DIR__.'/../config.php';

$page  = 'suggest-date';
$title = 'Suggest Mint Date';

$user = get_user();
if (!$user) { header('Location: /bs-auth/discord.php?next=' . urlencode($_SERVER['REQUEST_URI'])); exit; }

$allowed_tables = ['bs_mints', 'bs_mint_submissions'];

$msg = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $record_id    = trim($_POST['record_id'] ?? '');
    $record_table = trim($_POST['record_table'] ?? '');
    $name         = trim($_POST['name'] ?? '');
    $suggested    = trim($_POST['suggested_date'] ?? '');

    if (!$record_id || !in_array($record_table, $allowed_tables, true) || !$suggested) {
        $msg = 'Missing or invalid fields.';
        $msg_type = 'error';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $suggested)) {
        $msg = 'Invalid date format.';
        $msg_type = 'error';
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
            $msg = 'Save failed: ' . json_encode($r['data'] ?? $r);
            $msg_type = 'error';
        } else {
            $msg = 'Submitted! Staff will review and publish your suggested date.';
            $msg_type = 'success';
        }
    }
}

$record_id    = $_GET['id'] ?? ($_POST['record_id'] ?? '');
$record_table = $_GET['table'] ?? ($_POST['record_table'] ?? '');
$name         = $_GET['name'] ?? ($_POST['name'] ?? '');

$valid = $record_id && in_array($record_table, $allowed_tables, true);

require_once __DIR__.'/../includes/header.php';
?>
<div class="page-header">
    <h1 class="page-title">Suggest Mint Date</h1>
    <p class="page-sub"><?= htmlspecialchars($name ?: 'Project') ?> — date is currently TBA</p>
</div>

<div style="max-width:480px">

<?php if($msg): ?>
<div style="background:<?= $msg_type==='success' ? 'rgba(34,197,94,.1)' : 'rgba(239,68,68,.1)' ?>;border:1px solid <?= $msg_type==='success' ? 'rgba(34,197,94,.3)' : 'rgba(239,68,68,.3)' ?>;color:<?= $msg_type==='success' ? 'var(--green)' : 'var(--red)' ?>;padding:10px 14px;border-radius:8px;font-size:12px;margin-bottom:16px">
    <?= $msg_type==='success' ? '✓ ' : '✗ ' ?><?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<?php if(!$valid): ?>
<div style="text-align:center;padding:40px;color:var(--g5);font-size:12px">Invalid or missing project reference. Go back to the <a href="/calendar" style="color:var(--gold)">Mint Calendar</a> and try again.</div>
<?php elseif($msg_type !== 'success'): ?>
<div class="staff-card" style="background:var(--bg1);border:1px solid var(--border);border-radius:12px;padding:20px">
    <form method="POST">
        <input type="hidden" name="record_id" value="<?= htmlspecialchars($record_id) ?>">
        <input type="hidden" name="record_table" value="<?= htmlspecialchars($record_table) ?>">
        <input type="hidden" name="name" value="<?= htmlspecialchars($name) ?>">

        <div class="form-group" style="margin-bottom:16px">
            <div class="form-label" style="margin-bottom:6px">Project</div>
            <div style="font-family:GT America,sans-serif;font-size:15px;font-weight:700;color:var(--white)"><?= htmlspecialchars($name) ?></div>
        </div>

        <div class="form-group" style="margin-bottom:16px">
            <div class="form-label" style="margin-bottom:6px">Suggested Mint Date</div>
            <input type="date" name="suggested_date" class="form-input" required>
        </div>

        <button type="submit" class="btn btn-gold" style="width:100%">Submit Suggestion</button>
        <p style="font-size:10px;color:var(--g5);margin-top:10px;text-align:center">Staff will review before this goes live on the calendar.</p>
    </form>
</div>
<?php else: ?>
<a href="/calendar" class="btn btn-gold" style="display:inline-block;text-align:center">Back to Calendar</a>
<?php endif; ?>

</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>