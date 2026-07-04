<?php
require_once __DIR__.'/../config.php';

$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (preg_match('/Mobile|Android|iPhone|iPad|webOS|BlackBerry/i', $ua)) {
    header('Location: /m/calendar.php'); exit;
}

$user        = get_user();
$active_page = 'calendar';
$tab         = $_GET['tab'] ?? 'all';
$today       = date('Y-m-d');
$uid         = $user ? $user['discord_id'] : null;

// ── Month navigation ───────────────────────────────────────────────────────
$month      = $_GET['month'] ?? date('Y-m');
[$yr, $mo]  = explode('-', $month);
$first_day  = "$yr-$mo-01";
$last_day   = date('Y-m-t', strtotime($first_day));
$prev_month = date('Y-m', strtotime('-1 month', strtotime($first_day)));
$next_month = date('Y-m', strtotime('+1 month', strtotime($first_day)));
$month_label = date('F Y', strtotime($first_day));

// ── Handle POST actions ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $action = $_POST['action'] ?? '';

    // Submit NFT to full calendar (pending review)
    if ($action === 'submit_nft') {
        $name      = trim($_POST['name'] ?? '');
        $chain     = $_POST['chain'] ?? 'Ethereum';
        $mint_date = ($_POST['mint_date'] ?? '') ?: null;
        $mint_time = ($_POST['mint_time'] ?? '') ?: null;
        $price     = trim($_POST['price'] ?? '');
        $supply    = trim($_POST['supply'] ?? '');
        $mint_url  = trim($_POST['mint_url'] ?? '');
        $image_url = trim($_POST['image_url'] ?? '');
        $twitter   = ltrim(trim($_POST['twitter'] ?? ''), '@');
        if (!$image_url && $twitter) {
            $sk  = '2d725456-9686-4ecf-9cff-a9e0c8f74041';
            $ctx = stream_context_create(['http'=>['timeout'=>5,'header'=>"ApiKey: $sk\r\nAccept: application/json\r\n"]]);
            $raw = @file_get_contents("https://api.sorsa.io/v3/info?username=$twitter", false, $ctx);
            if ($raw) {
                $sd = json_decode($raw, true);
                foreach (['profile_banner_url','banner_url','profile_background_image_url'] as $k) {
                    if (!empty($sd[$k])) { $image_url = $sd[$k]; break; }
                }
                if (!$image_url && !empty($sd['profile_image_url']))
                    $image_url = str_replace('_normal','_400x400',$sd['profile_image_url']);
            }
        }
        if (!$image_url && $twitter) $image_url = "https://unavatar.io/twitter/$twitter";
        if ($name) {
            sb('bs_mint_submissions')->insert(['discord_id'=>$uid,'name'=>$name,'chain'=>$chain,'mint_date'=>$mint_date,'mint_time'=>$mint_time,'price'=>$price,'supply'=>$supply,'mint_url'=>$mint_url,'image_url'=>$image_url,'twitter'=>$twitter]);
            header('Location: ?tab=all&month='.$month.'&ok=submitted'); exit;
        }
    }

    // Add to personal calendar
    if ($action === 'add_my_mint') {
        $mint_date = ($_POST['mint_date'] ?? '') ?: null;
        $twitter   = ltrim(trim($_POST['twitter'] ?? ''), '@');
        $image_url = trim($_POST['image_url'] ?? '');
        if (!$image_url && $twitter) $image_url = "https://unavatar.io/twitter/$twitter";
        $custom_mint_id = -1 * (time() % 100000000 + rand(1,999));
        sb('bs_user_calendar')->insert(['discord_id'=>$uid,'mint_id'=>$custom_mint_id,'name'=>trim($_POST['name']??''),'mint_url'=>$_POST['mint_url']??'','image_url'=>$image_url,'chain'=>$_POST['chain']??'Ethereum','mint_date'=>$mint_date,'price'=>$_POST['price']??'','supply'=>$_POST['supply']??'','notes'=>'']);
        header('Location: ?tab=mine&month='.$month.'&ok=1'); exit;
    }

    if ($action === 'delete_my_mint') {
        sb('bs_user_calendar')->eq('mint_id',(int)$_POST['id'])->eq('discord_id',$uid)->delete();
        header('Location: ?tab=mine&month='.$month); exit;
    }
    if ($action === 'delete_wl') {
        sb('bs_user_wl')->eq('mint_id',(int)$_POST['id'])->eq('discord_id',$uid)->delete();
        header('Location: ?tab=mine&month='.$month); exit;
    }

    // Add from full calendar (AJAX)
    if ($action === 'add_from_full') {
        if (!$uid) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'message'=>'Not logged in']); exit; }
        $name = trim($_POST['name'] ?? '');
        if (!$name) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'message'=>'No name']); exit; }
        // Validate and format mint_date
        $raw_date  = trim($_POST['mint_date'] ?? '');
        $mint_date = null;
        if ($raw_date) {
            // Accept YYYY-MM-DD format
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw_date)) {
                $mint_date = $raw_date;
            } else {
                // Try to parse other formats
                $ts = strtotime($raw_date);
                if ($ts) $mint_date = date('Y-m-d', $ts);
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
                'mint_date'  => $mint_date,
                'price'      => trim($_POST['price']      ?? ''),
                'supply'     => trim($_POST['supply']     ?? ''),
                'notes'      => 'Added from Full Calendar',
            ]);
            header('Content-Type: application/json');
            echo json_encode(['ok'=>true, 'mint_date'=>$mint_date, 'name'=>$name]); exit;
        } catch(Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['ok'=>false,'message'=>$e->getMessage()]); exit;
        }
    }
}

// ── Full Calendar Data ─────────────────────────────────────────────────────
$full_by_date = [];
$full_tba     = [];

// Raffle entry counts bulk
$raffle_entry_counts = [];
try {
    $ec = sb('bs_raffle_entries')->select('raffle_id')->get();
    foreach ($ec as $e) { $raffle_entry_counts[$e['raffle_id']] = ($raffle_entry_counts[$e['raffle_id']] ?? 0) + 1; }
} catch(Exception $e) {}

try {
    // Approved mints
    foreach (sb('bs_mints')->eq('status','approved')->get() as $m) {
        $d = $m['mint_date'] ? substr($m['mint_date'],0,10) : null;
        $row = ['id'=>$m['id'],'table'=>'bs_mints','name'=>$m['name'],'chain'=>$m['chain']??'Ethereum','mint_date'=>$d,'mint_time'=>$m['mint_time']??'','price'=>$m['price']??'','supply'=>$m['supply']??'','twitter'=>$m['twitter']??'','image_url'=>$m['image_url']??'','mint_url'=>$m['mint_url']??'','src'=>'mint','description'=>$m['description']??'','reward_type'=>''];
        if ($d && $d >= $first_day && $d <= $last_day) $full_by_date[$d][] = $row;
        elseif (!$d) $full_tba[] = $row;
    }
    // Active auctions ending this month
    foreach (sb('bs_auctions')->eq('status','active')->get() as $a) {
        $d  = $a['ends_at'] ? substr($a['ends_at'],0,10) : null;
        $bj = json_decode($a['bids_json']??'{}', true) ?: [];
        $row = ['id'=>$a['id'],'name'=>$a['title']??'','chain'=>$a['chain']??'Ethereum','mint_date'=>$d,'image_url'=>$a['image_url']??'','src'=>'auction','reward_type'=>$a['reward_type']??'','description'=>$a['description']??'','price'=>$a['mint_price']??'','supply'=>$a['supply']??'','mint_url'=>$a['mint_url']??'','entry_count'=>count(array_filter($bj,fn($v)=>floatval($v)>0)),'twitter'=>$a['twitter']??''];
        if ($d && $d >= $first_day && $d <= $last_day) $full_by_date[$d][] = $row;
    }
    // Active raffles ending this month
    foreach (sb('bs_raffles')->eq('status','active')->get() as $r) {
        $d    = $r['end_date'] ? substr($r['end_date'],0,10) : null;
        $desc = $r['description'] ?? '';
        $row  = ['id'=>$r['id'],'name'=>$r['title']??'','chain'=>$r['chain']??'Ethereum','mint_date'=>$d,'image_url'=>$r['image_url']??'','src'=>'raffle','reward_type'=>$r['reward_type']??'','description'=>$desc,'price'=>'','supply'=>'','mint_url'=>$r['mint_url']??'','twitter'=>$r['project_twitter']??'','entry_count'=>$raffle_entry_counts[$r['id']] ?? 0];
        if ($d && $d >= $first_day && $d <= $last_day) $full_by_date[$d][] = $row;
    }
    // Approved user submissions
    foreach (sb('bs_mint_submissions')->eq('status','approved')->get() as $s) {
        $d   = $s['mint_date'] ? substr($s['mint_date'],0,10) : null;
        $row = ['id'=>$s['id'],'table'=>'bs_mint_submissions','name'=>$s['name'],'chain'=>$s['chain']??'Ethereum','mint_date'=>$d,'mint_time'=>$s['mint_time']??'','price'=>$s['price']??'','image_url'=>$s['image_url']??'','mint_url'=>$s['mint_url']??'','src'=>'sub','description'=>'','reward_type'=>''];
        if ($d && $d >= $first_day && $d <= $last_day) $full_by_date[$d][] = $row;
        elseif (!$d) $full_tba[] = $row;
    }
    // TBA mints
    foreach (sb('bs_mints')->eq('status','tba')->get() as $m) {
        $full_tba[] = ['id'=>$m['id'],'table'=>'bs_mints','name'=>$m['name'],'chain'=>$m['chain']??'Ethereum','mint_date'=>null,'image_url'=>$m['image_url']??'','mint_url'=>$m['mint_url']??'','price'=>$m['price']??'','supply'=>$m['supply']??'','twitter'=>$m['twitter']??'','src'=>'mint','description'=>$m['description']??'','reward_type'=>''];
    }
} catch(Exception $e) {}

// ── My Calendar Data ───────────────────────────────────────────────────────
$my_by_date = []; $my_tba = [];
$my_wins = []; $my_manual = []; $my_wl = [];

if ($user) {
    try {
        $wins_rows = sb('bs_wins')->eq('discord_id', $uid)->get();
        foreach ($wins_rows as $w) {
            // Try to find mint date from bs_mints by name match
            $mint_date = null;
            $mint_price = '';
            try {
                $mint_info = sb('bs_mints')->eq('name',$w['title'])->eq('status','approved')->select('mint_date,price')->first();
                if (!$mint_info) {
                    // Try partial match via ref_id for auction/raffle
                    if (!empty($w['ref_id']) && ($w['win_type']??'')==='auction') {
                        $auc = sb('bs_auctions')->eq('id',$w['ref_id'])->select('mint_date,mint_price')->first();
                        $mint_date = $auc['mint_date']??null;
                        $mint_price = $auc['mint_price']??'';
                    }
                } else {
                    $mint_date = $mint_info['mint_date']??null;
                    $mint_price = $mint_info['price']??'';
                }
            } catch(Exception $ex){}
            $my_wins[] = ['name'=>$w['title'],'wl_type'=>$w['reward_type']??'GTD WL','chain'=>$w['chain']??'Ethereum','image_url'=>$w['image_url']??'','mint_url'=>$w['mint_url']??'','src'=>$w['win_type']??'auction','mint_date'=>$mint_date,'price'=>$mint_price,'won_at'=>$w['won_at']??''];
        }
        $my_manual = array_map(fn($r)=>array_merge($r,['src'=>'manual']), sb('bs_user_calendar')->eq('discord_id',$uid)->get());
        $my_wl     = array_map(fn($r)=>array_merge($r,['src'=>'wl']),     sb('bs_user_wl')->eq('discord_id',$uid)->get());
        foreach (array_merge($my_wins,$my_manual,$my_wl) as $m) {
            // Normalize mint_date to YYYY-MM-DD (Supabase may return datetime string)
            if (!empty($m['mint_date'])) {
                $m['mint_date'] = substr($m['mint_date'], 0, 10);
                $my_by_date[$m['mint_date']][] = $m;
            } else {
                $my_tba[] = $m;
            }
        }
    } catch(Exception $e) {}
}

// ── User meta ──────────────────────────────────────────────────────────────
$user_balance    = $user ? get_balance($uid) : 0;
$user_name       = $user ? htmlspecialchars($user['username'] ?? 'User') : 'Guest';
$user_initial    = $user ? strtoupper(substr($user['username'] ?? 'U', 0, 1)) : '?';
$user_avatar_url = $user ? get_avatar_url($uid, $user['avatar'] ?? '') : '';

// ── Calendar grid helper ───────────────────────────────────────────────────
function render_cal_grid(string $yr, string $mo, string $first_day, string $today, array $by_date): void {
    $src_colors = ['mint'=>'#e4c590','sub'=>'#e4c590','auction'=>'#e4737d','raffle'=>'#9c9cf0','manual'=>'#fbbf24','wl'=>'#fbbf24'];
    $first_dow  = (int)date('w', strtotime($first_day));
    $days_in_mo = (int)date('t', strtotime($first_day));
    echo '<div class="cal-grid-inner">';
    // Header
    foreach (['SUN','MON','TUE','WED','THU','FRI','SAT'] as $wd)
        echo '<div class="cal-hdr">'.$wd.'</div>';
    // Blanks
    for ($i = 0; $i < $first_dow; $i++)
        echo '<div class="cal-cell cal-blank"></div>';
    // Days
    for ($d = 1; $d <= $days_in_mo; $d++) {
        $ds       = sprintf('%s-%02d-%02d', $yr, $mo, $d);
        $is_today = $ds === $today;
        $items    = $by_date[$ds] ?? [];
        // Build JSON for day popup
        $all_json = [];
        foreach ($items as $it) {
            $all_json[] = ['name'=>$it['name'],'src'=>$it['src']??'mint','chain'=>$it['chain']??'','date'=>$it['mint_date']??'','image'=>$it['image_url']??'','mint_url'=>$it['mint_url']??'','desc'=>$it['description']??'','reward'=>$it['reward_type']??'','price'=>$it['price']??'','supply'=>$it['supply']??'','twitter'=>$it['twitter']??'','entry_count'=>$it['entry_count']??null,'id'=>$it['id']??null,'table'=>$it['table']??null];
        }
        $cell_json = htmlspecialchars(json_encode($all_json), ENT_QUOTES);
        $has_class = !empty($items) ? ' cal-has-items' : '';
        $td_class  = 'cal-cell' . ($is_today ? ' cal-today' : '') . $has_class;
        $date_label = htmlspecialchars(date('F j, Y', strtotime($ds)), ENT_QUOTES);
        echo '<div class="'.$td_class.'" data-dayitems="'.$cell_json.'" data-daylabel="'.$date_label.'" onclick="showDayPopup(this)">';
        echo '<div class="cal-date'.($is_today?' cal-date-today':'').'">'.$d.'</div>';
        $shown = 0;
        foreach ($items as $it) {
            if ($shown >= 3) break;
            $src = $it['src'] ?? 'mint';
            $col = $src_colors[$src] ?? '#e4c590';
            $item_json = htmlspecialchars(json_encode(['name'=>$it['name'],'src'=>$src,'chain'=>$it['chain']??'','date'=>$it['mint_date']??'','image'=>$it['image_url']??'','mint_url'=>$it['mint_url']??'','desc'=>$it['description']??'','reward'=>$it['reward_type']??'','price'=>$it['price']??'','supply'=>$it['supply']??'','twitter'=>$it['twitter']??'','entry_count'=>$it['entry_count']??null,'id'=>$it['id']??null,'table'=>$it['table']??null]), ENT_QUOTES);
            echo '<div class="cal-ev" style="background:'.htmlspecialchars($col).'22;border:1px solid '.htmlspecialchars($col).'44;color:'.htmlspecialchars($col).'" data-caljson="'.$item_json.'" onclick="event.stopPropagation();showCalModalFromAttr(this)" title="'.htmlspecialchars($it['name']).'"><span class="cal-ev-dot" style="background:'.htmlspecialchars($col).'"></span>'.htmlspecialchars($it['name']).'</div>';
            $shown++;
        }
        if (count($items) > 3)
            echo '<div class="cal-more" onclick="event.stopPropagation();showDayPopup(this.closest(\'.cal-cell\'))">+'.(count($items)-3).' more ›</div>';
        echo '</div>';
    }
    // Trailing blanks
    $used = $first_dow + $days_in_mo;
    $rem  = $used % 7;
    if ($rem > 0) for ($i = $rem; $i < 7; $i++) echo '<div class="cal-cell cal-blank"></div>';
    echo '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mint Calendar · Blockstards</title>
<link rel="stylesheet" href="/bs_fonts.css?v=1783164697">
<link rel="stylesheet" href="/bs_design.css?v=1783164697">
<style>
  @property --bgAngle{syntax:'<angle>';inherits:false;initial-value:0deg}
  @keyframes borderTravel{to{--bgAngle:360deg}}
  @keyframes modalIn{from{opacity:0;transform:translateY(14px) scale(.98)}to{opacity:1;transform:translateY(0) scale(1)}}
  @keyframes fadeIn{from{opacity:0}to{opacity:1}}

  /* ── Calendar layout ── */
  .cal-layout{display:grid;grid-template-columns:1fr 280px;gap:20px;align-items:start}
  @media(max-width:900px){.cal-layout{grid-template-columns:1fr}}

  /* ── Calendar grid ── */
  .cal-frame{border-radius:18px;padding:1px;background:linear-gradient(160deg,rgba(255,255,255,.1),rgba(255,255,255,.02))}
  .cal-frame-inner{position:relative;border-radius:17px;overflow:hidden;background:rgba(255,255,255,.018);backdrop-filter:blur(14px)}
  .cal-grid-inner{display:grid;grid-template-columns:repeat(7,minmax(0,1fr))}
  .cal-hdr{padding:13px 4px;font-family:'GT America Mono',monospace;font-size:9.5px;letter-spacing:.18em;color:#6b7488;text-align:center;border-bottom:1px solid rgba(255,255,255,.06)}
  .cal-cell{min-height:110px;padding:8px;display:flex;flex-direction:column;border-right:1px solid rgba(255,255,255,.045);border-bottom:1px solid rgba(255,255,255,.045);min-width:0;overflow:hidden;transition:background .18s}
  .cal-blank{background:rgba(0,0,0,.22)}
  .cal-today{box-shadow:inset 0 0 0 1px rgba(111,227,255,.32);background:rgba(111,227,255,.06)}
  .cal-has-items{cursor:pointer}
  .cal-has-items:hover{background:rgba(111,227,255,.05)}
  .cal-date{font-family:'GT America Mono',monospace;font-size:12px;font-weight:500;color:#8b94a8;padding:2px 5px;align-self:flex-start}
  .cal-date-today{color:#06070d!important;background:#6fe3ff;border-radius:7px;padding:2px 8px;font-weight:600;box-shadow:0 0 12px -2px rgba(111,227,255,.85)}
  .cal-ev{display:flex;align-items:center;gap:5px;font-family:'GT America Mono',monospace;font-size:9px;border-radius:6px;padding:3px 7px;cursor:pointer;min-width:0;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;margin-top:4px;transition:filter .15s}
  .cal-ev:hover{filter:brightness(1.2)}
  .cal-ev-dot{width:5px;height:5px;border-radius:50%;flex-shrink:0}
  .cal-more{font-family:'GT America Mono',monospace;font-size:8.5px;color:#5a6478;cursor:pointer;margin-top:3px;padding-left:5px}
  .cal-more:hover{color:#aab2c5}

  /* ── Legend ── */
  .cal-legend{display:flex;gap:16px;margin-bottom:14px;flex-wrap:wrap}
  .leg{display:flex;align-items:center;gap:6px;font-family:'GT America Mono',monospace;font-size:10.5px;color:#7a8398}
  .leg-dot{width:9px;height:9px;border-radius:3px}

  /* ── Nav ── */
  .cal-nav{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
  .cal-nav-btn{width:30px;height:30px;border-radius:9px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center;color:#aab2c5;text-decoration:none;transition:.15s;font-size:16px}
  .cal-nav-btn:hover{border-color:rgba(111,227,255,.4);color:#eef1f8}

  /* ── TBA rail ── */
  .tba-rail{border-radius:16px;padding:1px;background:linear-gradient(180deg,rgba(255,255,255,.1),rgba(255,255,255,.02))}
  .tba-rail-inner{border-radius:15px;overflow:hidden;background:rgba(255,255,255,.025);backdrop-filter:blur(14px)}
  .tba-hdr{padding:14px 16px;border-bottom:1px solid rgba(255,255,255,.06);display:flex;align-items:center;justify-content:space-between}
  .tba-item{display:flex;align-items:center;gap:10px;padding:11px 14px;border-bottom:1px solid rgba(255,255,255,.04);cursor:pointer;transition:.15s}
  .tba-item:hover{background:rgba(255,255,255,.04)}
  .tba-thumb{width:30px;height:30px;border-radius:8px;flex-shrink:0;overflow:hidden;background:#1a1d2b}
  .tba-thumb img{width:100%;height:100%;object-fit:cover}

  /* ── Submit form ── */
  .submit-form-wrap{border:1px solid #161a28;border-radius:18px;background:rgba(255,255,255,.02);overflow:hidden;margin-top:16px}
  .submit-form-hdr{padding:14px 20px;border-bottom:1px solid #12151f;display:flex;align-items:center;gap:10px;font-family:'GT America Mono',monospace;font-size:11px;letter-spacing:.12em;color:#aab2c5}

  /* ── Mine stats ── */
  .mine-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px}
  .mine-stat{background:rgba(255,255,255,.025);border:1px solid #161a28;border-radius:12px;padding:14px;text-align:center}

  /* ── Mint row ── */
  .mint-row{display:flex;align-items:center;gap:10px;padding:12px 14px;background:rgba(255,255,255,.02);border:1px solid #161a28;border-radius:12px;margin-bottom:8px;transition:border-color .18s}
  .mint-row:hover{border-color:#232838}
  .mint-thumb{width:40px;height:40px;border-radius:9px;background:#1a1d2b;flex-shrink:0;overflow:hidden;display:flex;align-items:center;justify-content:center;font-size:18px}
  .mint-thumb img{width:100%;height:100%;object-fit:cover}

  /* ── Detail Modal ── */
  .cal-modal-overlay{position:fixed;inset:0;z-index:1000;display:none;align-items:center;justify-content:center;padding:24px;background:rgba(4,5,10,.75);backdrop-filter:blur(8px)}
  .cal-modal-overlay.open{display:flex;animation:fadeIn .2s ease}
  .cal-modal-box{position:relative;width:100%;max-width:470px;border-radius:22px;padding:1.5px;background:conic-gradient(from var(--bgAngle),rgba(255,255,255,.06) 0deg,rgba(255,255,255,.06) 200deg,#6fe3ff 300deg,rgba(182,156,255,.55) 332deg,rgba(255,255,255,.06) 360deg);animation:borderTravel 9s linear infinite;box-shadow:0 40px 90px -30px rgba(0,0,0,.9)}
  .cal-modal-inner{border-radius:20.5px;overflow:hidden;background:linear-gradient(180deg,rgba(15,18,28,.96),rgba(9,11,19,.98));backdrop-filter:blur(24px) saturate(150%);animation:modalIn .28s cubic-bezier(.2,.8,.2,1)}
  .cal-modal-banner{position:relative;height:152px;overflow:hidden}
  .cal-modal-banner-bg{position:absolute;inset:0;background-size:cover;background-position:center}
  .cal-modal-banner-shine{position:absolute;inset:0;background:radial-gradient(circle at 28% 20%,rgba(255,255,255,.3),transparent 55%);mix-blend-mode:screen}
  .cal-modal-banner-fade{position:absolute;inset:0;background:linear-gradient(180deg,transparent 35%,rgba(9,11,19,.96))}
  .cal-modal-close{position:absolute;top:14px;right:14px;width:30px;height:30px;border-radius:50%;background:rgba(6,7,13,.55);border:1px solid rgba(255,255,255,.12);backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:center;cursor:pointer;color:#c8cedd;font-size:17px;line-height:1;z-index:2}
  .cal-modal-close:hover{background:rgba(228,115,125,.2)}
  .cal-modal-src-badge{position:absolute;top:14px;left:14px;display:flex;align-items:center;gap:6px;padding:5px 11px;border-radius:20px;background:rgba(6,7,13,.6);backdrop-filter:blur(8px);z-index:2}
  .cal-modal-title-wrap{position:absolute;left:18px;right:18px;bottom:14px;z-index:2}
  .cal-modal-body{padding:18px}
  .cal-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:1px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.06);border-radius:13px;overflow:hidden;margin-bottom:16px}
  .cal-info-cell{background:rgba(255,255,255,.015);padding:12px 14px}
  .cal-info-label{font-family:'GT America Mono',monospace;font-size:9px;letter-spacing:.13em;color:#5a6478;margin-bottom:5px}
  .cal-info-val{font-size:13.5px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

  /* ── Day popup ── */
  .day-popup-overlay{position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:999;display:none;align-items:center;justify-content:center;padding:24px}
  .day-popup-overlay.open{display:flex}
  .day-popup-box{background:linear-gradient(180deg,#0c0e18,#080a12);border:1px solid #232838;border-radius:16px;width:100%;max-width:400px;max-height:75vh;overflow:hidden;display:flex;flex-direction:column}
  .day-popup-hdr{padding:14px 18px;border-bottom:1px solid #161a28;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
  .day-popup-list{overflow-y:auto}
</style>
</head>
<body>
<div class="bs-layout">
<?php require_once __DIR__.'/../includes/bs_sidebar.php'; ?>

<main class="bs-main" style="max-width:1320px">

  <!-- Topbar -->
  <div class="bs-topbar">
    <div class="bs-breadcrumb">CLUB / <span class="bc-active">CALENDAR</span></div>
    <?php if (!$user): ?>
    <div class="bs-topbar-right">
      <a href="/bs-auth/discord.php" class="bs-discord-btn" style="padding:9px 18px;font-size:12px">Sign in</a>
    </div>
    <?php endif; ?>
  </div>

  <!-- Page title -->
  <div style="margin-bottom:22px">
    <h1 class="bs-page-title">Mint Calendar</h1>
    <p class="bs-page-sub">Every auction, raffle and NFT mint — tracked in one place.</p>
  </div>

  <!-- Tabs -->
  <div class="bs-tabs" style="margin-bottom:22px">
    <a href="?tab=all&month=<?= $month ?>"  class="bs-tab <?= $tab==='all'?'active':'' ?>">Full Calendar</a>
    <a href="?tab=mine&month=<?= $month ?>" class="bs-tab <?= $tab==='mine'?'active':'' ?>">My Calendar</a>
  </div>

  <?php if (isset($_GET['ok'])): ?>
  <div class="bs-notice bs-notice-green" style="margin-bottom:18px">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4ade80" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
    <span class="bs-notice-text" style="color:#86efac">
      <?= $_GET['ok']==='submitted' ? 'NFT submitted for review! Staff will add it to the Full Calendar.' : 'Added to your calendar!' ?>
    </span>
  </div>
  <?php endif; ?>

  <?php if ($tab === 'all'): ?>
  <!-- ═══ FULL CALENDAR ═══ -->
  <div class="cal-layout">
    <div>
      <!-- Nav -->
      <div class="cal-nav">
        <div style="display:flex;gap:16px">
          <div class="leg"><span class="leg-dot" style="background:#e4c590"></span>Mint</div>
          <div class="leg"><span class="leg-dot" style="background:#e4737d"></span>Auction</div>
          <div class="leg"><span class="leg-dot" style="background:#9c9cf0"></span>Raffle</div>
        </div>
        <div style="display:flex;align-items:center;gap:12px">
          <a href="?tab=all&month=<?= $prev_month ?>" class="cal-nav-btn">‹</a>
          <span style="font-weight:700;font-size:17px;letter-spacing:-.01em"><?= $month_label ?></span>
          <a href="?tab=all&month=<?= $next_month ?>" class="cal-nav-btn">›</a>
        </div>
      </div>
      <!-- Grid -->
      <div class="cal-frame">
        <div class="cal-frame-inner" id="calFrame">
          <div id="calSpot" style="position:absolute;left:0;top:0;width:300px;height:300px;border-radius:50%;background:radial-gradient(circle,rgba(111,227,255,.13),rgba(182,156,255,.05) 48%,transparent 70%);transform:translate(-50%,-50%);pointer-events:none;opacity:0;transition:opacity .3s;z-index:0;will-change:left,top"></div>
          <?php render_cal_grid($yr, $mo, $first_day, $today, $full_by_date); ?>
        </div>
      </div>
      <!-- Submit NFT form -->
      <div class="submit-form-wrap">
        <div class="submit-form-hdr">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6fe3ff" stroke-width="1.6"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
          SUBMIT NFT PROJECT
        </div>
        <div style="padding:18px 20px">
          <p style="font-size:12px;color:#7a8398;margin-bottom:16px;line-height:1.6">Submit any NFT to the Full Calendar — staff review first. No date = goes to TBA.</p>
          <?php if (!$user): ?>
          <a href="/bs-auth/discord.php" class="bs-discord-btn" style="width:auto;display:inline-flex">Sign in to submit</a>
          <?php else: ?>
          <form method="post">
            <input type="hidden" name="action" value="submit_nft">
            <div class="bs-field bs-field-row bs-field-row-2">
              <div><label>PROJECT NAME <span class="req">*</span></label><input class="bs-input" name="name" required placeholder="Blockstards"></div>
              <div><label>CHAIN</label>
                <div class="bs-select-wrap"><select class="bs-input" name="chain"><option>Ethereum</option><option>Solana</option><option>Bitcoin</option><option>Base</option><option>Polygon</option><option>Other</option></select><svg class="bs-select-arrow" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#5a6478" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></div>
              </div>
            </div>
            <div class="bs-field bs-field-row bs-field-row-2">
              <div><label>MINT DATE <span style="color:#5a6478">(blank=TBA)</span></label><input class="bs-input" name="mint_date" type="date"></div>
              <div><label>MINT TIME <span style="font-family:'GT America Mono',monospace;font-size:9px;color:#5a6478">(optional)</span></label><div class="bs-select-wrap"><select class="bs-input" name="mint_time">
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
                </select><svg class="bs-select-arrow" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#5a6478" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></div></div>
            </div>
            <div class="bs-field bs-field-row bs-field-row-2">
              <div><label>PRICE <span style="font-family:'GT America Mono',monospace;font-size:9px;color:#5a6478;margin-left:4px">(optional)</span></label><input class="bs-input bs-input-mono" name="price" placeholder="0.1"></div>
              <div><label>SUPPLY <span style="font-family:'GT America Mono',monospace;font-size:9px;color:#5a6478;margin-left:4px">(optional)</span></label><input class="bs-input bs-input-mono" name="supply" placeholder="Supply"></div>
            </div>
            <div class="bs-field bs-field-row bs-field-row-2">
              <div>
                <label>X HANDLE (no @) <span class="adm-badge" style="background:rgba(255,255,255,.04);color:#7a8398;border:1px solid #232838;margin-left:4px;font-size:9px;padding:1px 7px;border-radius:10px">OPTIONAL</span></label>
                <input class="bs-input bs-input-mono" name="twitter" id="sub_tw" placeholder="Blockstards" oninput="doBanner('sub_tw','sub_img','sub_prev')">
                <input type="hidden" name="image_url" id="sub_img">
                <img id="sub_prev" src="" style="display:none;width:100%;height:50px;object-fit:cover;border-radius:8px;margin-top:6px;border:1px solid #232838">
              </div>
              <div><label>MINT URL</label><input class="bs-input" name="mint_url" placeholder="https://…"></div>
            </div>
            <button type="submit" class="bs-foil-btn" style="border:none;cursor:pointer;width:100%">
              <span class="bs-foil-btn-inner">Submit for Review →</span>
            </button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <!-- TBA rail -->
    <div>
      <div class="tba-rail">
        <div class="tba-rail-inner">
          <div class="tba-hdr">
            <span style="font-family:'GT America Mono',monospace;font-size:10px;letter-spacing:.16em;color:#7a8398">DATE TBA</span>
            <span style="font-family:'GT America Mono',monospace;font-size:11px;color:#e4c590;font-weight:500"><?= count($full_tba) ?></span>
          </div>
          <?php if (empty($full_tba)): ?>
          <div style="padding:24px;text-align:center;font-size:12px;color:#5a6478">No TBA projects</div>
          <?php else: ?>
          <div style="max-height:500px;overflow-y:auto">
          <?php foreach ($full_tba as $t):
            $src_t = $t['src'] ?? 'mint';
            $src_color = ['mint'=>'#e4c590','auction'=>'#e4737d','raffle'=>'#9c9cf0'][$src_t] ?? '#e4c590';
            $src_label = ucfirst($src_t);
            $data_json = htmlspecialchars(json_encode(['name'=>$t['name'],'src'=>$src_t,'chain'=>$t['chain']??'','date'=>'','image'=>$t['image_url']??'','mint_url'=>$t['mint_url']??'','desc'=>$t['description']??'','reward'=>$t['reward_type']??'','price'=>$t['price']??'','supply'=>$t['supply']??'','twitter'=>$t['twitter']??'','id'=>$t['id']??null,'table'=>$t['table']??null]), ENT_QUOTES);
          ?>
          <div class="tba-item" data-caljson="<?= $data_json ?>" onclick="showCalModalFromAttr(this)">
            <div class="tba-thumb">
              <?php if (!empty($t['image_url'])): ?><img src="<?= htmlspecialchars($t['image_url']) ?>" alt=""><?php else: ?>🖼<?php endif; ?>
            </div>
            <div style="flex:1;min-width:0">
              <div style="font-size:12.5px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($t['name']) ?></div>
              <div style="display:flex;gap:6px;align-items:center;margin-top:2px">
                <span style="font-family:'GT America Mono',monospace;font-size:8.5px;padding:1px 6px;border-radius:8px;background:<?= htmlspecialchars($src_color) ?>22;color:<?= htmlspecialchars($src_color) ?>"><?= $src_label ?></span>
                <span style="font-family:'GT America Mono',monospace;font-size:9px;color:#5a6478"><?= htmlspecialchars($t['chain']??'') ?></span>
              </div>
            </div>
            <span style="color:#5a6478">›</span>
          </div>
          <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <?php elseif ($tab === 'mine'): ?>
  <!-- ═══ MY CALENDAR ═══ -->
  <?php if (!$user): ?>
  <div style="text-align:center;padding:60px 20px">
    <p style="color:#7a8398;margin-bottom:16px">Sign in to view your personal calendar</p>
    <a href="/bs-auth/discord.php" class="bs-discord-btn" style="display:inline-flex;width:auto">Sign in with Discord</a>
  </div>
  <?php else:
    $all_mine_flat = array_merge($my_wins, $my_manual, $my_wl);
    $total_mine    = count($all_mine_flat);
    $upcoming_cnt  = 0; $today_cnt = 0;
    foreach ($all_mine_flat as $m) {
        if (!empty($m['mint_date']) && $m['mint_date'] >= $today) $upcoming_cnt++;
        if (!empty($m['mint_date']) && $m['mint_date'] === $today) $today_cnt++;
    }
    $with_date = array_filter($all_mine_flat, fn($m)=>!empty($m['mint_date']));
    usort($with_date, fn($a,$b)=>strcmp($a['mint_date'],$b['mint_date']));
  ?>
  <!-- Stats -->
  <div class="mine-stats">
    <div class="mine-stat"><div style="font-weight:700;font-size:24px"><?= $total_mine ?></div><div style="font-family:'GT America Mono',monospace;font-size:9px;letter-spacing:.12em;color:#7a8398;margin-top:3px">TOTAL</div></div>
    <div class="mine-stat"><div style="font-weight:700;font-size:24px;color:#e4c590"><?= $upcoming_cnt ?></div><div style="font-family:'GT America Mono',monospace;font-size:9px;letter-spacing:.12em;color:#7a8398;margin-top:3px">UPCOMING</div></div>
    <div class="mine-stat"><div style="font-weight:700;font-size:24px;color:#4ade80"><?= $today_cnt ?></div><div style="font-family:'GT America Mono',monospace;font-size:9px;letter-spacing:.12em;color:#7a8398;margin-top:3px">TODAY</div></div>
  </div>
  <div class="cal-layout">
    <div>
      <div class="cal-nav">
        <div style="display:flex;gap:16px">
          <div class="leg"><span class="leg-dot" style="background:#e4737d"></span>Auction Win</div>
          <div class="leg"><span class="leg-dot" style="background:#9c9cf0"></span>Raffle Win</div>
          <div class="leg"><span class="leg-dot" style="background:#fbbf24"></span>Manual</div>
        </div>
        <div style="display:flex;align-items:center;gap:12px">
          <a href="?tab=mine&month=<?= $prev_month ?>" class="cal-nav-btn">‹</a>
          <span style="font-weight:700;font-size:17px;letter-spacing:-.01em"><?= $month_label ?></span>
          <a href="?tab=mine&month=<?= $next_month ?>" class="cal-nav-btn">›</a>
        </div>
      </div>
      <div class="cal-frame"><div class="cal-frame-inner"><?php render_cal_grid($yr,$mo,$first_day,$today,$my_by_date); ?></div></div>

      <!-- Upcoming list -->
      <?php if (!empty($with_date)): ?>
      <div style="font-family:'GT America Mono',monospace;font-size:10px;letter-spacing:.14em;color:#5a6478;margin:18px 0 10px">SCHEDULED (<?= count($with_date) ?>)</div>
      <?php foreach ($with_date as $m):
        $src2 = $m['src'] ?? 'manual';
        $col2 = ['auction'=>'#e4737d','raffle'=>'#9c9cf0','wl'=>'#9c9cf0'][$src2] ?? '#fbbf24';
        $mint_date_f = date('M j', strtotime($m['mint_date']));
      ?>
      <div class="mint-row">
        <div class="mint-thumb">
          <?php if (!empty($m['image_url'])): ?><img src="<?= htmlspecialchars($m['image_url']) ?>" alt=""><?php else: ?>📅<?php endif; ?>
        </div>
        <div style="flex:1;min-width:0">
          <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($m['name']) ?></div>
          <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:4px">
            <span class="bs-badge bs-badge-gold"><?= $mint_date_f ?><?= !empty($m['mint_time']) ? ' '.date('H:i',strtotime($m['mint_time'])) : '' ?></span>
            <?php if (!empty($m['chain'])): ?><span class="bs-badge bs-badge-gray"><?= htmlspecialchars($m['chain']) ?></span><?php endif; ?>
            <?php if ($src2==='auction'||$src2==='raffle'): ?><span class="bs-badge" style="background:<?= $col2 ?>22;color:<?= $col2 ?>;border:1px solid <?= $col2 ?>44"><?= ucfirst($src2) ?> Win</span><?php endif; ?>
            <?php if (!empty($m['wl_type']??'')): ?><span class="bs-badge bs-badge-green"><?= htmlspecialchars($m['wl_type']) ?></span><?php endif; ?>
          </div>
        </div>
        <div style="flex-shrink:0;display:flex;align-items:center;gap:8px">
          <?php if (!empty($m['mint_url']??'')): ?><a href="<?= htmlspecialchars($m['mint_url']) ?>" target="_blank" style="font-family:'GT America Mono',monospace;font-size:11px;color:#e4c590;text-decoration:none">Mint →</a><?php endif; ?>
          <?php if ($src2==='manual'): ?>
          <form method="post" style="display:inline"><input type="hidden" name="action" value="delete_my_mint"><input type="hidden" name="id" value="<?= $m['mint_id'] ?? $m['id'] ?? '' ?>"><button type="submit" onclick="return confirm('Remove?')" style="background:none;border:none;color:#5a6478;cursor:pointer;font-size:18px;line-height:1">×</button></form>
          <?php elseif ($src2==='wl'): ?>
          <form method="post" style="display:inline"><input type="hidden" name="action" value="delete_wl"><input type="hidden" name="id" value="<?= $m['mint_id'] ?? $m['id'] ?? '' ?>"><button type="submit" onclick="return confirm('Remove?')" style="background:none;border:none;color:#5a6478;cursor:pointer;font-size:18px;line-height:1">×</button></form>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>

      <?php if (empty($all_mine_flat)): ?>
      <div class="bs-empty"><span class="bs-empty-icon">📅</span><p>No mints yet — win auctions/raffles or add manually below.</p></div>
      <?php endif; ?>

      <!-- Add manually -->
      <div class="submit-form-wrap">
        <div class="submit-form-hdr">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#b69cff" stroke-width="1.6"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
          ADD TO MY CALENDAR
        </div>
        <div style="padding:18px 20px">
          <p style="font-size:12px;color:#7a8398;margin-bottom:16px;line-height:1.6">Add any NFT you're tracking — personal to you only.</p>
          <form method="post">
            <input type="hidden" name="action" value="add_my_mint">
            <div class="bs-field bs-field-row bs-field-row-2">
              <div><label>PROJECT NAME <span class="req">*</span></label><input class="bs-input" name="name" required placeholder="Project Name"></div>
              <div><label>CHAIN</label><div class="bs-select-wrap"><select class="bs-input" name="chain"><option>Ethereum</option><option>Solana</option><option>Bitcoin</option><option>Base</option><option>Polygon</option><option>Other</option></select><svg class="bs-select-arrow" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#5a6478" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></div></div>
            </div>
            <div class="bs-field bs-field-row bs-field-row-2">
              <div><label>MINT DATE <span style="color:#5a6478">(blank=TBA)</span></label><input class="bs-input" name="mint_date" type="date"></div>
              <div><label>MINT TIME <span style="font-family:'GT America Mono',monospace;font-size:9px;color:#5a6478">(optional)</span></label><div class="bs-select-wrap"><select class="bs-input" name="mint_time">
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
                </select><svg class="bs-select-arrow" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#5a6478" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></div></div>
            </div>
            <div class="bs-field bs-field-row bs-field-row-2">
              <div><label>PRICE</label><input class="bs-input bs-input-mono" name="price" placeholder="TBA"></div>
              <div><label>SUPPLY</label><input class="bs-input bs-input-mono" name="supply" placeholder="TBA"></div>
            </div>
            <div class="bs-field bs-field-row bs-field-row-2">
              <div>
                <label>X HANDLE (no @) <span style="font-family:'GT America Mono',monospace;font-size:9px;color:#5a6478;margin-left:4px">(optional)</span></label>
                <input class="bs-input bs-input-mono" id="mine_tw" name="twitter" placeholder="Blockstards" oninput="doBanner('mine_tw','mine_img','mine_prev')">
                <input type="hidden" name="image_url" id="mine_img">
                <img id="mine_prev" src="" style="display:none;width:100%;height:50px;object-fit:cover;border-radius:8px;margin-top:6px;border:1px solid #232838">
              </div>
              <div><label>MINT URL</label><input class="bs-input" name="mint_url" placeholder="https://…"></div>
            </div>
            <button type="submit" class="bs-foil-btn" style="border:none;cursor:pointer;width:100%">
              <span class="bs-foil-btn-inner">Add to My Calendar</span>
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- My TBA rail -->
    <div>
      <div class="tba-rail">
        <div class="tba-rail-inner">
          <div class="tba-hdr">
            <span style="font-family:'GT America Mono',monospace;font-size:10px;letter-spacing:.16em;color:#7a8398">TBA PROJECTS</span>
            <span style="font-family:'GT America Mono',monospace;font-size:11px;color:#e4c590;font-weight:500"><?= count($my_tba) ?></span>
          </div>
          <?php if (empty($my_tba)): ?>
          <div style="padding:24px;text-align:center;font-size:12px;color:#5a6478">No TBA items</div>
          <?php else: foreach ($my_tba as $m):
            $src2 = $m['src'] ?? 'manual';
          ?>
          <div class="tba-item">
            <div class="tba-thumb"><?php if (!empty($m['image_url'])): ?><img src="<?= htmlspecialchars($m['image_url']) ?>" alt=""><?php else: ?>📅<?php endif; ?></div>
            <div style="flex:1;min-width:0">
              <div style="font-size:12.5px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($m['name']) ?></div>
              <div style="display:flex;gap:6px;margin-top:2px">
                <span style="font-family:'GT America Mono',monospace;font-size:8.5px;padding:1px 6px;border-radius:8px;background:rgba(228,197,144,.1);border:1px solid rgba(228,197,144,.25);color:#e4c590">TBA</span>
                <?php if (!empty($m['wl_type']??'')): ?><span style="font-size:8px;color:#4ade80"><?= htmlspecialchars($m['wl_type']) ?></span><?php endif; ?>
              </div>
            </div>
            <?php if ($src2==='manual'): ?>
            <form method="post" style="display:inline"><input type="hidden" name="action" value="delete_my_mint"><input type="hidden" name="id" value="<?= $m['mint_id'] ?? $m['id'] ?? '' ?>"><button type="submit" onclick="return confirm('Remove?')" style="background:none;border:none;color:#5a6478;cursor:pointer;font-size:18px;line-height:1">×</button></form>
            <?php elseif ($src2==='wl'): ?>
            <form method="post" style="display:inline"><input type="hidden" name="action" value="delete_wl"><input type="hidden" name="id" value="<?= $m['mint_id'] ?? $m['id'] ?? '' ?>"><button type="submit" onclick="return confirm('Remove?')" style="background:none;border:none;color:#5a6478;cursor:pointer;font-size:18px;line-height:1">×</button></form>
            <?php endif; ?>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <?php endif; ?>

</main>
</div>

<!-- ═══ DAY POPUP ═══ -->
<div class="day-popup-overlay" id="day-popup">
  <div class="day-popup-box">
    <div class="day-popup-hdr">
      <div id="day-popup-date" style="font-weight:700;font-size:15px"></div>
      <button onclick="document.getElementById('day-popup').classList.remove('open')" style="background:none;border:none;color:#5a6478;cursor:pointer;font-size:22px;line-height:1;padding:0">×</button>
    </div>
    <div id="day-popup-list" class="day-popup-list"></div>
  </div>
</div>

<!-- ═══ NFT DETAIL MODAL ═══ -->
<div class="cal-modal-overlay" id="cal-modal">
  <div class="cal-modal-box" id="cal-modal-box">
    <div class="cal-modal-inner">
      <div class="cal-modal-banner">
        <div id="cal-modal-banner-bg" class="cal-modal-banner-bg"></div>
        <div class="cal-modal-banner-shine"></div>
        <div class="cal-modal-banner-fade"></div>
        <div id="cal-modal-src-badge" class="cal-modal-src-badge">
          <span id="cal-modal-src-dot" style="width:6px;height:6px;border-radius:50%"></span>
          <span id="cal-modal-src-label" style="font-family:'GT America Mono',monospace;font-size:9.5px;letter-spacing:.14em"></span>
        </div>
        <div class="cal-modal-close" onclick="closeCalModal()">×</div>
        <div class="cal-modal-title-wrap">
          <div id="cal-modal-title" style="font-weight:700;font-size:23px;letter-spacing:-.02em;line-height:1.1;text-shadow:0 2px 12px rgba(0,0,0,.6)"></div>
          <div style="display:flex;align-items:center;gap:8px;margin-top:7px;flex-wrap:wrap">
            <span id="cal-modal-chain" style="font-family:'GT America Mono',monospace;font-size:10.5px;color:#bfe9f5"></span>
            <span id="cal-modal-date-sep" style="color:#3a4254">·</span>
            <span id="cal-modal-date" style="font-family:'GT America Mono',monospace;font-size:10.5px;color:#9aa3b8"></span>
            <span id="cal-modal-count-sep" style="color:#3a4254;display:none">·</span>
            <span id="cal-modal-count" style="font-family:'GT America Mono',monospace;font-size:10.5px;display:none"></span>
          </div>
        </div>
      </div>
      <div class="cal-modal-body">
        <div class="cal-info-grid" id="cal-info-grid"></div>
        <div id="cal-modal-desc" style="font-size:13px;line-height:1.65;color:#9aa3b8;margin-bottom:16px;padding:0 2px;display:none"></div>
        <div style="display:flex;gap:10px">
          <div class="bs-foil-btn" id="cal-add-btn" onclick="addToMyCalendar()" style="flex:1">
            <span class="bs-foil-btn-inner" style="font-size:12.5px">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6fe3ff" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="M12 14v4M10 16h4"/></svg>
              Add to My Calendar
            </span>
          </div>
          <a id="cal-mint-link" href="#" target="_blank" style="display:none;align-items:center;justify-content:center;gap:7px;padding:13px 18px;border-radius:13px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);color:#c8cedd;font-family:'GT America Mono',monospace;font-size:12.5px;cursor:pointer;text-decoration:none;white-space:nowrap">Mint Page ↗</a>
        </div>
        <!-- Admin edit section -->
        <?php if ($user && is_staff()): ?>
        <div id="cal-admin-wrap" style="margin-top:16px;padding-top:14px;border-top:1px solid rgba(255,255,255,.06);display:none">
          <div style="font-family:'GT America Mono',monospace;font-size:9.5px;letter-spacing:.1em;color:#5a6478;margin-bottom:12px">STAFF · EDIT</div>
          <div id="cal-edit-mint-form">
            <form id="cal-edit-form" onsubmit="submitCalEdit(event)" style="display:flex;flex-direction:column;gap:8px">
              <input type="hidden" name="mint_id" id="edit-mint-id">
              <div class="bs-field-row bs-field-row-2" style="display:grid;gap:8px">
                <input class="bs-input" name="name" id="edit-name" placeholder="Name" style="padding:8px 12px;font-size:12px">
                <input class="bs-input bs-input-mono" name="twitter" id="edit-twitter" placeholder="Twitter handle" style="padding:8px 12px;font-size:12px">
              </div>
              <div class="bs-field-row bs-field-row-2" style="display:grid;gap:8px">
                <div class="bs-select-wrap"><select class="bs-input" name="chain" id="edit-chain" style="padding:8px 12px;font-size:12px"><option>Ethereum</option><option>Solana</option><option>Bitcoin</option><option>Base</option><option>Polygon</option><option>Other</option></select><svg class="bs-select-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#5a6478" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></div>
                <input class="bs-input" name="mint_url" id="edit-mint-url" placeholder="Mint URL" style="padding:8px 12px;font-size:12px">
              </div>
              <div class="bs-field-row bs-field-row-2" style="display:grid;gap:8px">
                <input class="bs-input bs-input-mono" name="price" id="edit-price" placeholder="Price" style="padding:8px 12px;font-size:12px">
                <input class="bs-input bs-input-mono" name="supply" id="edit-supply" placeholder="Supply" style="padding:8px 12px;font-size:12px">
              </div>
              <input class="bs-input" name="mint_date" id="edit-mint-date" type="date" style="padding:8px 12px;font-size:12px">
              <button type="submit" class="bs-foil-btn" style="border:none;cursor:pointer;width:100%"><span class="bs-foil-btn-inner" style="font-size:12px">Save Changes</span></button>
              <div id="edit-msg" style="font-size:11px;text-align:center;display:none"></div>
            </form>
          </div>
          <div id="cal-edit-link" style="display:none">
            <a id="cal-edit-goto" href="#" target="_blank" class="bs-foil-btn" style="text-decoration:none"><span class="bs-foil-btn-inner" style="font-size:12px">Edit in Admin Panel ↗</span></a>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
var _calItem   = null;
var _dpItems   = [];
var _isLoggedIn = <?= $user ? 'true' : 'false' ?>;
var _isStaff    = <?= (is_staff() ? 'true' : 'false') ?>;
var _srcMeta = {
  mint:    { label:'NFT MINT',   color:'#e4c590', glow:'rgba(228,197,144,.4)' },
  sub:     { label:'NFT MINT',   color:'#e4c590', glow:'rgba(228,197,144,.4)' },
  auction: { label:'AUCTION',    color:'#e4737d', glow:'rgba(228,115,125,.4)' },
  raffle:  { label:'RAFFLE',     color:'#9c9cf0', glow:'rgba(156,156,240,.4)' },
  manual:  { label:'MY CALENDAR',color:'#fbbf24', glow:'rgba(251,191,36,.3)' },
  wl:      { label:'WL ENTRY',   color:'#9c9cf0', glow:'rgba(156,156,240,.4)' },
};

function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// ── Cursor spotlight ──────────────────────────────────────────────────────
(function() {
  var f = document.getElementById('calFrame');
  var s = document.getElementById('calSpot');
  if (!f || !s) return;
  f.addEventListener('mousemove', function(e) {
    var r = f.getBoundingClientRect();
    s.style.left = (e.clientX - r.left) + 'px';
    s.style.top  = (e.clientY - r.top)  + 'px';
    s.style.opacity = '1';
  });
  f.addEventListener('mouseleave', function(){ s.style.opacity = '0'; });
})();

// ── Day popup ─────────────────────────────────────────────────────────────
function showDayPopup(cell) {
  var raw = cell.getAttribute('data-dayitems');
  var label = cell.getAttribute('data-daylabel') || '';
  if (!raw) return;
  var items; try { items = JSON.parse(raw); } catch(e) { return; }
  if (!items.length) return;
  _dpItems = items;
  document.getElementById('day-popup-date').textContent = label;
  var list = document.getElementById('day-popup-list');
  list.innerHTML = items.map(function(it, i) {
    var m = _srcMeta[it.src] || _srcMeta.mint;
    var countStr = '';
    if (it.entry_count != null && (it.src==='auction'||it.src==='raffle'))
      countStr = '<span style="font-size:9px;color:#5a6478;margin-left:6px">'+(it.src==='auction'?'🔨':'🎟')+' '+it.entry_count+'</span>';
    return '<div onclick="openItemFromDay('+i+')" style="display:flex;align-items:center;gap:10px;padding:10px 16px;cursor:pointer;border-bottom:1px solid #161a28;transition:.1s" onmouseover="this.style.background=\'rgba(255,255,255,.04)\'" onmouseout="this.style.background=\'\'">'+
      '<div style="width:8px;height:8px;border-radius:50%;background:'+m.color+';flex-shrink:0"></div>'+
      '<div style="flex:1;min-width:0">'+
        '<div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+esc(it.name)+'</div>'+
        '<div style="display:flex;gap:5px;align-items:center;margin-top:2px">'+
          '<span style="font-family:\'GT America Mono\',monospace;font-size:9px;padding:1px 6px;border-radius:8px;border:1px solid '+m.color+'44;color:'+m.color+';background:'+m.color+'18">'+m.label+'</span>'+
          (it.chain?'<span style="font-size:9px;color:#5a6478">'+esc(it.chain)+'</span>':'')+
          countStr+
        '</div>'+
      '</div>'+
      '<span style="color:#5a6478">›</span>'+
    '</div>';
  }).join('');
  document.getElementById('day-popup').classList.add('open');
}
function openItemFromDay(i) {
  document.getElementById('day-popup').classList.remove('open');
  showCalModal(_dpItems[i]);
}
document.getElementById('day-popup').addEventListener('click', function(e){ if(e.target===this) this.classList.remove('open'); });

// ── Modal from attr ───────────────────────────────────────────────────────
function showCalModalFromAttr(el) {
  var raw = el.getAttribute('data-caljson');
  if (!raw) return;
  try { showCalModal(JSON.parse(raw)); } catch(e) { console.error(e); }
}

// ── Detail modal ──────────────────────────────────────────────────────────
function showCalModal(d) {
  if (typeof d === 'string') { try { d = JSON.parse(d); } catch(e){ return; } }
  _calItem = d;
  var m = _srcMeta[d.src] || _srcMeta.mint;

  // Banner
  var bg = document.getElementById('cal-modal-banner-bg');
  bg.style.background = d.image ? 'url('+JSON.stringify(d.image)+') center/cover' : 'linear-gradient(135deg,#13243a,#5aa9d8 55%,#1f3f6b)';

  // Box glow
  var box = document.getElementById('cal-modal-box');
  box.style.boxShadow = '0 40px 90px -30px rgba(0,0,0,.9), 0 0 60px -20px '+m.glow;

  // Source badge
  document.getElementById('cal-modal-src-dot').style.background   = m.color;
  document.getElementById('cal-modal-src-label').style.color       = m.color;
  document.getElementById('cal-modal-src-label').textContent        = m.label;
  document.getElementById('cal-modal-src-badge').style.borderColor = m.color+'50';

  // Title + meta
  document.getElementById('cal-modal-title').textContent = d.name || 'Untitled';
  var chainSpan = document.getElementById('cal-modal-chain');
  var chainColor = d.chain==='Solana'?'#b69cff':d.chain==='Bitcoin'?'#e4c590':'#6fe3ff';
  chainSpan.innerHTML = '<span style="width:5px;height:5px;border-radius:2px;background:'+chainColor+';display:inline-block;margin-right:5px;vertical-align:middle"></span>'+esc(d.chain||'TBA');
  document.getElementById('cal-modal-date').textContent = d.date ? new Date(d.date+'T12:00:00').toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : 'Date TBA';
  var countEl  = document.getElementById('cal-modal-count');
  var countSep = document.getElementById('cal-modal-count-sep');
  if (d.entry_count != null && (d.src==='auction'||d.src==='raffle')) {
    countEl.textContent  = (d.src==='auction'?d.entry_count+' bids':d.entry_count+' entries');
    countEl.style.color  = m.color;
    countEl.style.display=''; countSep.style.display='';
  } else { countEl.style.display='none'; countSep.style.display='none'; }

  // Info grid
  var dash = function(v){ return (v===undefined||v===null||v===''||v==='nan'||v==='NaN')?null:v; };
  var supply = dash(d.supply);
  if (supply && supply.trim()!=='' && !isNaN(Number(String(supply).replace(/,/g,'').trim())) && String(supply).trim()!=='0') supply = Number(String(supply).replace(/,/g,'')).toLocaleString('en-US');
  if (!supply || supply==='0' || supply.trim()==='' || supply==='TBA') supply = null;
  var infoRows = [
    dash(d.chain)    ? ['CHAIN',      d.chain,   '#eef1f8'] : null,
    (dash(d.price)&&d.price!=='TBA'&&d.price!=='0') ? ['MINT PRICE', d.price, '#eef1f8'] : null,
    supply           ? ['SUPPLY',     supply,    '#eef1f8'] : null,
    ['TYPE',       dash(d.reward)||(d.src==='mint'?'Public Mint':'GTD WL'), '#bfe9f5'],
    dash(d.twitter)  ? ['PROJECT',    '@'+String(d.twitter).replace(/^@/,''), '#bfe9f5'] : null,
    dash(d.mint_url) ? ['MINT PAGE',  (function(u){u=u.replace(/^https?:\/\//,'').replace(/^www\./,'').replace(/\/$/,'');return u.length>24?u.slice(0,24)+'\u2026':u;})(d.mint_url), '#6fe3ff'] : null,
  ].filter(Boolean);
  document.getElementById('cal-info-grid').innerHTML = infoRows.map(function(r){
    var val = r[2]==='#6fe3ff' && d.mint_url ? '<a href="'+esc(d.mint_url)+'" target="_blank" style="color:#6fe3ff;text-decoration:none">'+esc(r[1])+'</a>' : '<span style="color:'+r[2]+'">'+esc(r[1])+'</span>';
    return '<div class="cal-info-cell"><div class="cal-info-label">'+esc(r[0])+'</div><div class="cal-info-val">'+val+'</div></div>';
  }).join('');

  // Desc
  var descEl = document.getElementById('cal-modal-desc');
  if (d.desc && d.desc.trim()) { descEl.textContent = d.desc.trim(); descEl.style.display='block'; }
  else descEl.style.display='none';

  // Mint link button
  var mintLink = document.getElementById('cal-mint-link');
  if (d.mint_url) { mintLink.href = d.mint_url; mintLink.style.display='flex'; }
  else mintLink.style.display='none';

  // Add button
  var addBtn = document.getElementById('cal-add-btn');
  if (!_isLoggedIn) {
    addBtn.querySelector('.bs-foil-btn-inner').textContent = 'Sign in to Add';
    addBtn.onclick = function(){ window.location.href='/bs-auth/discord.php'; };
  } else {
    addBtn.querySelector('.bs-foil-btn-inner').innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6fe3ff" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="M12 14v4M10 16h4"/></svg> Add to My Calendar';
    addBtn.style.background = '';
    addBtn.style.cursor = '';
    delete addBtn.dataset.added;
    addBtn.onclick = addToMyCalendar;
  }

  // Admin
  populateAdminEdit(d);

  document.getElementById('cal-modal').classList.add('open');
}

function closeCalModal() { document.getElementById('cal-modal').classList.remove('open'); _calItem=null; }
document.getElementById('cal-modal').addEventListener('click', function(e){ if(e.target===this) closeCalModal(); });

// ── Admin populate ────────────────────────────────────────────────────────
function populateAdminEdit(d) {
  var wrap = document.getElementById('cal-admin-wrap');
  if (!wrap) return;
  if (!d || !d.id) { wrap.style.display='none'; return; }
  var mintForm = document.getElementById('cal-edit-mint-form');
  var linkDiv  = document.getElementById('cal-edit-link');
  var gotoLink = document.getElementById('cal-edit-goto');
  wrap.style.display = 'block';
  if (d.src==='mint'||d.src==='sub') {
    mintForm.style.display='block'; linkDiv.style.display='none';
    document.getElementById('edit-mint-id').value   = d.id||'';
    document.getElementById('edit-name').value      = d.name||'';
    document.getElementById('edit-twitter').value   = (d.twitter||'').replace(/^@/,'');
    document.getElementById('edit-chain').value     = d.chain||'Ethereum';
    document.getElementById('edit-mint-url').value  = d.mint_url||'';
    document.getElementById('edit-price').value     = d.price||'';
    document.getElementById('edit-supply').value    = d.supply||'';
    document.getElementById('edit-mint-date').value = d.date||'';
    document.getElementById('edit-msg').style.display='none';
  } else if (d.src==='auction') {
    mintForm.style.display='none'; linkDiv.style.display='block';
    gotoLink.href='/bs-admin/auction.php?id='+d.id;
    gotoLink.querySelector('.bs-foil-btn-inner').textContent='✏️ Edit Auction in Admin Panel ↗';
  } else if (d.src==='raffle') {
    mintForm.style.display='none'; linkDiv.style.display='block';
    gotoLink.href='/bs-admin/raffles.php?id='+d.id;
    gotoLink.querySelector('.bs-foil-btn-inner').textContent='✏️ Edit Raffle in Admin Panel ↗';
  } else { wrap.style.display='none'; }
}

async function submitCalEdit(e) {
  e.preventDefault();
  var data = Object.fromEntries(new FormData(document.getElementById('cal-edit-form')));
  var msg  = document.getElementById('edit-msg');
  try {
    var res = await fetch('/bs-api/update_mint.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
    var j   = await res.json();
    msg.style.display='block'; msg.style.color = j.success?'#e4c590':'#f87171';
    msg.textContent = j.success?'✓ Saved!':('Error: '+(j.message||'Failed'));
    if (j.success) setTimeout(()=>location.reload(), 800);
  } catch(err) { msg.style.display='block'; msg.style.color='#f87171'; msg.textContent='Network error'; }
}

// ── Add to my calendar ────────────────────────────────────────────────────
async function addToMyCalendar() {
  if (!_calItem || !_isLoggedIn) return;
  var btn = document.getElementById('cal-add-btn');
  if (btn.dataset.added === '1') return; // already added, do nothing
  btn.querySelector('.bs-foil-btn-inner').innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:spin .8s linear infinite"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4"/></svg> Adding…';
  btn.style.pointerEvents = 'none';
  try {
    var fd = new FormData();
    fd.append('action','add_from_full');
    fd.append('name',        _calItem.name     || _calItem.title || '');
    fd.append('chain',       _calItem.chain    || 'Ethereum');
    fd.append('mint_date',   _calItem.date     || '');
    fd.append('image_url',   _calItem.image    || _calItem.image_url || '');
    fd.append('mint_url',    _calItem.mint_url || '');
    fd.append('price',       _calItem.price    || '');
    fd.append('supply',      _calItem.supply   || '');
    var res  = await fetch('?tab=mine', {method:'POST',body:fd});
    var text = await res.text();
    var data;
    try { data = JSON.parse(text); } catch(e) { throw new Error('Server error: ' + text.slice(0,100)); }
    if (data.ok) {
      btn.dataset.added = '1';
      btn.querySelector('.bs-foil-btn-inner').innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#4ade80" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg> Added to My Calendar';
      btn.style.background = 'rgba(74,222,128,.12)';
      btn.style.cursor = 'default';
    } else {
      throw new Error(data.message||'Failed');
    }
  } catch(e) {
    btn.querySelector('.bs-foil-btn-inner').textContent = '✗ ' + (e.message||'Failed');
    btn.style.pointerEvents = '';
    setTimeout(function(){ btn.querySelector('.bs-foil-btn-inner').innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6fe3ff" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="M12 14v4M10 16h4"/></svg> Add to My Calendar'; btn.style.background=''; btn.style.pointerEvents=''; }, 2000);
  }
}

// ── Twitter banner auto-fetch ─────────────────────────────────────────────
var _bbt = {};
function doBanner(twId, imgId, prevId) {
  clearTimeout(_bbt[twId]);
  var h = document.getElementById(twId).value.trim().replace('@','');
  if (!h || h.length < 2) { document.getElementById(prevId).style.display='none'; document.getElementById(imgId).value=''; return; }
  _bbt[twId] = setTimeout(function(){
    fetch('/auction-form.php?fetch_banner=1&handle='+encodeURIComponent(h))
      .then(function(r){return r.json();})
      .then(function(d){
        if(d.banner){
          document.getElementById(imgId).value=d.banner;
          var p=document.getElementById(prevId); p.src=d.banner; p.onload=function(){p.style.display='block';}; p.onerror=function(){p.style.display='none';};
        }
      }).catch(function(){ document.getElementById(imgId).value='https://unavatar.io/twitter/'+h; });
  }, 800);
}
document.querySelectorAll('form').forEach(function(f){
  f.addEventListener('submit',function(){
    [['sub_tw','sub_img'],['mine_tw','mine_img']].forEach(function(p){
      var tw=document.getElementById(p[0]),img=document.getElementById(p[1]);
      if(tw&&img&&tw.value.trim()&&!img.value) img.value='https://unavatar.io/twitter/'+tw.value.trim().replace('@','');
    });
  });
});
</script>
</body>
</html>