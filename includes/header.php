<?php $user=get_user();$blox_bal=0;if($user){try{$blox_bal=get_balance($user['discord_id']);}catch(Exception $e){}}?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=isset($title)?htmlspecialchars($title).' — Blockstards':'Blockstards'?></title>
<link rel="stylesheet" href="/bs_fonts.css?v=1783164697">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html,body{background:#06070d;min-height:100vh}
@property --bgAngle{syntax:'<angle>';inherits:false;initial-value:0deg}
@keyframes borderTravel{to{--bgAngle:360deg}}
@keyframes foilSweep{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}
@keyframes shineSweep{0%{transform:translateX(-160%) skewX(-20deg)}55%,100%{transform:translateX(360%) skewX(-20deg)}}
@keyframes livePulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.25;transform:scale(.7)}}
@keyframes meshDrift{0%{transform:translate(0,0) scale(1)}50%{transform:translate(-5%,4%) scale(1.15)}100%{transform:translate(0,0) scale(1)}}
@keyframes meshDrift2{0%{transform:translate(2%,-2%) scale(1.1)}50%{transform:translate(-4%,5%) scale(1)}100%{transform:translate(2%,-2%) scale(1.1)}}
@keyframes modalIn{from{opacity:0;transform:translateY(14px) scale(.98)}to{opacity:1;transform:translateY(0) scale(1)}}
::-webkit-scrollbar{width:9px;height:9px}::-webkit-scrollbar-track{background:#06070d}::-webkit-scrollbar-thumb{background:#1a1d2b;border-radius:9px}::-webkit-scrollbar-thumb:hover{background:#232838}
input::placeholder,textarea::placeholder{color:#4a5266}
select{appearance:none;-webkit-appearance:none}
body{font-family:'GT America',sans-serif;color:#eef1f8;display:flex;min-height:100vh}
.sidebar{width:248px;flex-shrink:0;background:linear-gradient(180deg,#0a0c14,#06070d);border-right:1px solid #161a28;display:flex;flex-direction:column;position:sticky;top:0;height:100vh;z-index:200;transition:left .25s}
.sb-logo{padding:24px 22px;border-bottom:1px solid #12151f;display:flex;align-items:center;gap:13px;text-decoration:none;color:#eef1f8}
.sb-icon{position:relative;width:42px;height:42px;border-radius:12px;background:linear-gradient(150deg,#6fe3ff,#b69cff);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:21px;color:#06070d;box-shadow:0 6px 20px -4px rgba(111,227,255,.5);flex-shrink:0;overflow:hidden}
.sb-icon::after{content:'';position:absolute;top:0;left:0;height:100%;width:45%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.8),transparent);transform:skewX(-20deg);animation:shineSweep 5s ease-in-out infinite}
.sb-name{font-weight:700;font-size:17px;letter-spacing:-.01em;line-height:1}
.sb-sub{font-family:'GT America Mono',monospace;font-size:9px;letter-spacing:.3em;color:#6fe3ff;margin-top:4px}
.sb-admin-badge{display:inline-flex;margin-top:5px;padding:2px 8px;border-radius:20px;background:rgba(182,156,255,.12);border:1px solid rgba(182,156,255,.3);font-family:'GT America Mono',monospace;font-size:8px;letter-spacing:.18em;color:#c9b8ff}
.sb-nav{padding:16px 14px;flex:1;display:flex;flex-direction:column;gap:3px;overflow-y:auto}
.sb-sec{font-family:'GT America Mono',monospace;font-size:9px;letter-spacing:.22em;color:#4a5266;padding:8px 12px 6px}
.nav-link{position:relative;display:flex;align-items:center;gap:12px;padding:11px 13px;border-radius:11px;text-decoration:none;font-size:13.5px;color:#aab2c5;transition:.18s;border:1px solid transparent}
.nav-link:hover{background:#10131d;color:#eef1f8}
.nav-link.active{font-weight:600;color:#eef1f8;background:linear-gradient(120deg,rgba(111,227,255,.14),rgba(182,156,255,.08));border-color:rgba(111,227,255,.2)}
.nav-link.active::before{content:'';position:absolute;left:-1px;top:50%;transform:translateY(-50%);width:3px;height:20px;border-radius:0 3px 3px 0;background:linear-gradient(#6fe3ff,#b69cff);box-shadow:0 0 10px #6fe3ff}
.nav-link.active-admin{background:linear-gradient(120deg,rgba(182,156,255,.16),rgba(111,227,255,.06));border-color:rgba(182,156,255,.22)}
.nav-link.active-admin::before{background:linear-gradient(#b69cff,#6fe3ff);box-shadow:0 0 10px #b69cff}
.sb-user{padding:14px;border-top:1px solid #12151f}
.sb-card{display:flex;align-items:center;gap:11px;padding:11px;background:rgba(255,255,255,.03);border:1px solid #1a1d2b;border-radius:13px;text-decoration:none;backdrop-filter:blur(8px)}
.sb-av{width:34px;height:34px;border-radius:50%;background:linear-gradient(140deg,#6fe3ff,#b69cff);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;color:#06070d;flex-shrink:0;overflow:hidden}
.sb-av img{width:100%;height:100%;object-fit:cover}
.sb-uname{font-size:12.5px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#eef1f8}
.sb-blox{font-family:'GT America Mono',monospace;font-size:10.5px;color:#6fe3ff;margin-top:1px}
.hbtn{display:none;position:fixed;top:14px;left:14px;z-index:300;width:38px;height:38px;border-radius:10px;background:rgba(10,12,20,.9);border:1px solid #232838;align-items:center;justify-content:center;flex-direction:column;gap:4px;cursor:pointer;backdrop-filter:blur(8px)}
.hbtn span{display:block;width:16px;height:1.5px;background:#aab2c5;transition:.2s}
.sbo{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:190;backdrop-filter:blur(2px)}
main.mc{flex:1;min-width:0;padding:22px 40px 60px}
.topbar{display:flex;align-items:center;justify-content:space-between;padding-bottom:20px;border-bottom:1px solid #12151f;margin-bottom:26px}
.tbc{font-family:'GT America Mono',monospace;font-size:11px;letter-spacing:.16em;color:#7a8398}
.tbc span{color:#6fe3ff}
.tbc.adm span{color:#b69cff}
.tbr{display:flex;align-items:center;gap:14px}
.blox-pill{display:flex;align-items:center;gap:8px;padding:8px 14px;border:1px solid #1a1d2b;border-radius:30px;background:rgba(255,255,255,.03)}
.blox-dot{width:6px;height:6px;border-radius:50%;background:#6fe3ff;box-shadow:0 0 8px #6fe3ff}
.blox-val{font-family:'GT America Mono',monospace;font-size:12px;color:#bfe9f5;letter-spacing:.04em}
.topav{width:36px;height:36px;border-radius:50%;background:linear-gradient(140deg,#6fe3ff,#b69cff);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:15px;color:#06070d;overflow:hidden;text-decoration:none;flex-shrink:0}
.topav img{width:100%;height:100%;object-fit:cover}
.bot-pill{display:flex;align-items:center;gap:8px;padding:8px 14px;border:1px solid rgba(74,222,128,.25);border-radius:30px;background:rgba(74,222,128,.06)}
.bot-dot{width:6px;height:6px;border-radius:50%;background:#4ade80;animation:livePulse 1.8s infinite}
.bot-lbl{font-family:'GT America Mono',monospace;font-size:11px;color:#86efac;letter-spacing:.06em}
/* FOIL BUTTON */
.foil{position:relative;overflow:hidden;display:inline-flex;padding:2px;border-radius:13px;text-decoration:none;background:conic-gradient(from var(--bgAngle),rgba(111,227,255,.14),#6fe3ff 15%,#b69cff 29%,rgba(111,227,255,.14) 48%,rgba(111,227,255,.14));animation:borderTravel 4.5s linear infinite;box-shadow:0 0 22px -3px rgba(111,227,255,.5),0 16px 32px -12px rgba(111,227,255,.45);cursor:pointer;transition:transform .12s,box-shadow .2s}
.foil:hover{transform:translateY(-2px);box-shadow:0 0 42px -2px rgba(111,227,255,.9),0 0 16px rgba(182,156,255,.55),0 20px 38px -12px rgba(111,227,255,.55)}
.foil:active{transform:translateY(1px)}
.foil-in{display:inline-flex;align-items:center;justify-content:center;gap:9px;padding:13px 26px;border-radius:11px;background:linear-gradient(180deg,rgba(18,24,38,.86),rgba(9,12,22,.93));backdrop-filter:blur(10px);color:#eef1f8;font-family:'GT America Mono',monospace;font-size:13px;font-weight:500;letter-spacing:.04em;white-space:nowrap}
.foil-full{display:flex;width:100%}.foil-full .foil-in{width:100%}
/* DISCORD BTN */
.btn-dc{display:inline-flex;align-items:center;gap:11px;padding:14px 26px;border-radius:13px;text-decoration:none;font-family:'GT America Mono',monospace;font-size:13px;color:#fff;background:linear-gradient(180deg,#6a76ff,#5865F2 50%,#4451d6);box-shadow:0 1px 0 rgba(255,255,255,.3) inset,0 3px 0 #3a44b0,0 14px 26px -10px rgba(88,101,242,.7);transition:transform .12s,box-shadow .12s;border:none;cursor:pointer}
.btn-dc:hover{transform:translateY(-1px)}.btn-dc:active{transform:translateY(3px)}
/* BADGES */
.badge{display:inline-flex;align-items:center;font-family:'GT America Mono',monospace;font-size:10px;padding:3px 9px;border-radius:20px;letter-spacing:.04em;border:1px solid}
.badge-cyan,.badge-blue{background:rgba(111,227,255,.1);color:#6fe3ff;border-color:rgba(111,227,255,.25)}
.badge-purple{background:rgba(182,156,255,.1);color:#b69cff;border-color:rgba(182,156,255,.25)}
.badge-gold,.badge-yellow{background:rgba(228,197,144,.12);color:#e4c590;border-color:rgba(228,197,144,.25)}
.badge-green{background:rgba(74,222,128,.1);color:#4ade80;border-color:rgba(74,222,128,.25)}
.badge-red{background:rgba(248,113,113,.1);color:#f87171;border-color:rgba(248,113,113,.25)}
.badge-orange{background:rgba(251,146,60,.1);color:#fb923c;border-color:rgba(251,146,60,.25)}
.badge-gray{background:rgba(255,255,255,.04);color:#aab2c5;border-color:#232838}
/* TABS */
.tab-bar{display:inline-flex;gap:3px;padding:4px;background:rgba(255,255,255,.03);border:1px solid #161a28;border-radius:12px}
.tab{display:flex;align-items:center;gap:7px;padding:8px 18px;border-radius:9px;font-family:'GT America Mono',monospace;font-size:12px;letter-spacing:.04em;cursor:pointer;transition:.18s;text-decoration:none;color:#7a8398;border:1px solid transparent;background:transparent;white-space:nowrap;user-select:none}
.tab:hover{color:#eef1f8;background:#10131d}
.tab.active{color:#eef1f8;background:rgba(111,227,255,.1);border-color:rgba(111,227,255,.25)}
.tab-dot{width:6px;height:6px;border-radius:50%;background:#6fe3ff;box-shadow:0 0 7px #6fe3ff}
/* FORMS */
.form-label{font-family:'GT America Mono',monospace;font-size:10px;letter-spacing:.1em;color:#7a8398;margin-bottom:7px;display:block;text-transform:uppercase}
.form-input{width:100%;background:#0a0d18;border:1px solid #232838;border-radius:11px;padding:13px 15px;font-size:14px;color:#eef1f8;outline:none;transition:.16s;font-family:'GT America',sans-serif}
.form-input:focus{border-color:#6fe3ff}
textarea.form-input{resize:vertical;line-height:1.6}
select.form-input{cursor:pointer}
.form-group{margin-bottom:16px}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px}
/* CARDS & MISC */
.stat-card{padding:22px 24px;border:1px solid #161a28;border-radius:16px;background:rgba(255,255,255,.02)}
.stat-val{font-weight:700;font-size:32px;line-height:1}
.stat-label{font-family:'GT America Mono',monospace;font-size:10px;letter-spacing:.14em;color:#7a8398;margin-top:8px}
.ok-msg{background:rgba(74,222,128,.1);border:1px solid rgba(74,222,128,.3);color:#4ade80;padding:10px 16px;border-radius:8px;font-size:12px;margin-bottom:16px}
.err-msg{background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.3);color:#f87171;padding:10px 16px;border-radius:8px;font-size:12px;margin-bottom:16px;word-break:break-all;font-family:'GT America Mono',monospace}
.empty-state{text-align:center;padding:60px 20px;border:1px solid #161a28;border-radius:16px;background:rgba(255,255,255,.02);color:#7a8398;font-family:'GT America Mono',monospace;font-size:13px}
.toast{position:fixed;bottom:24px;right:24px;z-index:9999;padding:12px 18px;border-radius:12px;font-family:'GT America Mono',monospace;font-size:12px;opacity:0;transform:translateY(8px);transition:.3s;pointer-events:none}
.toast.show{opacity:1;transform:translateY(0)}
.toast.ok{background:rgba(74,222,128,.15);border:1px solid rgba(74,222,128,.35);color:#86efac}
.toast.err{background:rgba(248,113,113,.15);border:1px solid rgba(248,113,113,.35);color:#fca5a5}
/* MODAL */
.modal-overlay{display:none;position:fixed;inset:0;z-index:400;align-items:center;justify-content:center;padding:24px;background:rgba(4,5,10,.7);backdrop-filter:blur(6px)}
.modal-overlay.open{display:flex}
.modal{background:linear-gradient(180deg,#0c0e18,#080a12);border:1px solid #232838;border-radius:20px;overflow:hidden;box-shadow:0 40px 90px -30px rgba(0,0,0,.8);animation:modalIn .22s cubic-bezier(.2,.9,.3,1);width:100%;max-width:480px;max-height:90vh;overflow-y:auto}
.modal-header{padding:20px 24px;border-bottom:1px solid #161a28;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#0c0e18;z-index:1}
.modal-title{font-weight:700;font-size:17px}
.modal-close{background:none;border:none;color:#5a6478;cursor:pointer;font-size:22px;line-height:1;padding:4px 8px;transition:.15s}
.modal-close:hover{color:#eef1f8}
.modal-body{padding:20px 24px}
.modal-footer{padding:16px 24px;border-top:1px solid #161a28;display:flex;gap:10px;align-items:center}
/* ICON BTNS */
.ib{width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:transform .15s;flex-shrink:0}
.ib:hover{transform:translateY(-1px)}
.ib-c{background:rgba(111,227,255,.1);border:1px solid rgba(111,227,255,.28)}
.ib-g{background:rgba(255,255,255,.04);border:1px solid #232838}
.ib-r{background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.25)}
.ib-gold{background:rgba(228,197,144,.1);border:1px solid rgba(228,197,144,.28)}
.ib-grn{background:rgba(74,222,128,.12);border:1px solid rgba(74,222,128,.3)}
/* SECTION HDR */
.shdr{font-family:'GT America Mono',monospace;font-size:11px;letter-spacing:.14em;color:#7a8398;margin-bottom:14px}
/* MOBILE */
@media(max-width:900px){
  body{display:block}
  .sidebar{position:fixed;left:-260px;top:0;height:100vh;z-index:250;transition:left .25s}
  .sidebar.open{left:0;box-shadow:0 0 0 100vw rgba(0,0,0,.6)}
  .sbo{display:block!important}
  .hbtn{display:flex!important}
  main.mc{padding:16px 18px 60px!important;padding-top:64px!important}
  .grid-2{grid-template-columns:1fr!important}
  .grid-3{grid-template-columns:1fr 1fr!important}
}
@media(max-width:600px){
  .grid-3{grid-template-columns:1fr!important}
  .blox-pill{display:none}
}
</style>
</head>
<body>
<?php
$pg=$page??'';$is_adm=str_starts_with($pg,'admin');
$u_init=$user?strtoupper(substr($user['username']??$user['discord_id'],0,1)):'?';
$av_url=($user&&($user['avatar']??''))?"https://cdn.discordapp.com/avatars/{$user['discord_id']}/{$user['avatar']}.png":'';
$blox_f=number_format($blox_bal,2);
$pn=[['/',       'home',          'Home',           '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>'],
     ['/raffles/','raffles',       'Raffles',        '<path d="M20 12V22H4V12"/><path d="M22 7H2v5h20V7z"/><path d="M12 22V7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>'],
     ['/auctions/','auctions',     'Auctions',       '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>'],
     ['/auction-form.php','request-auction','Request Auction','<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>'],
     ['/calendar/','calendar',     'Mint Calendar',  '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>'],
     ['/wins/',   'wins',          'My Wins',        '<path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2z"/>'],
     ['/profile/','profile',       'My Profile',     '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>']];
$an=[
  ['/bs-admin/','admin-dashboard','Dashboard','<rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/>'],
  ['/bs-admin/raffles.php','admin-raffles','Raffles','<path d="M20 12V22H4V12"/><path d="M22 7H2v5h20V7z"/><path d="M12 22V7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>'],
  ['/bs-admin/auction.php','admin-auctions','Auctions','<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>'],
  ['/bs-admin/entries.php','admin-entries','Entries','<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>'],
  ['/bs-admin/mints.php','admin-mints','Mints','<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>'],
  ['/bs-admin/mint-suggestion.php','admin-suggestions','Suggestions','<path d="M9 18h6M10 22h4M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.1v.2h6v-.2c0-.8.4-1.6 1-2.1A7 7 0 0 0 12 2z"/>',true],
  ['/bs-admin/staff.php','admin-staff','Staff','<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>'],
  ['/bs-admin/permissions.php','admin-perms','Permissions','<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>'],
  ['/bs-admin/logs.php','admin-logs','Logs','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>']];
function _ic(string $d,string $c='currentColor'):string{return '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="'.$c.'" stroke-width="1.6">'.$d.'</svg>';}
function _dis():string{return '<svg width="16" height="16" viewBox="0 0 71 55" fill="#fff"><path d="M60.1 4.9A58.6 58.6 0 0 0 45.5.4a40 40 0 0 0-1.8 3.7 54.2 54.2 0 0 0-16.4 0A38.5 38.5 0 0 0 25.5.5 58.5 58.5 0 0 0 10.9 5C1.6 18.9-1 32.5.3 46a59 59 0 0 0 18 9.1 43.2 43.2 0 0 0 3.7-6l-5.8-2.8.6-.5a41.4 41.4 0 0 0 35.5 0l.5.5-5.8 2.8a42 42 0 0 0 3.7 6A58.8 58.8 0 0 0 68.7 46C70.3 30.3 66 16.8 60 4.9z"/></svg>';}
?>
<button class="hbtn" id="hbtn" onclick="toggleSb()"><span></span><span></span><span></span></button>
<div class="sbo" id="sbo" onclick="closeSb()"></div>
<aside class="sidebar" id="sb">
  <a href="<?=$is_adm?'/bs-admin/':'/'?>" class="sb-logo">
    <div class="sb-icon">B</div>
    <div><div class="sb-name">Blockstards</div><?php if($is_adm):?><div class="sb-admin-badge">ADMIN</div><?php else:?><div class="sb-sub">WEB3 CLUB</div><?php endif;?></div>
  </a>
  <nav class="sb-nav">
    <?php if($is_adm):
      $secs=[['MANAGE',array_slice($an,0,6)],['TEAM',array_slice($an,6)]];
      foreach($secs as [$sec,$items]):?>
        <div class="sb-sec"><?=$sec?></div>
        <?php foreach($items as $n):$act=$pg===$n[1];?>
          <a href="<?=$n[0]?>" class="nav-link<?=$act?' active active-admin':''?>"><?=_ic($n[3],$act?'#b69cff':'currentColor')?> <?=$n[2]?><?php if(!empty($n[4])):?><span style="margin-left:auto;font-family:'GT America Mono',monospace;font-size:9px;padding:1px 7px;border-radius:10px;background:rgba(251,146,60,.15);color:#fb923c;border:1px solid rgba(251,146,60,.3)" id="sugg-badge"></span><?php endif;?></a>
        <?php endforeach;
      endforeach;?>
      <div style="margin-top:auto;height:8px"></div>
      <a href="/" class="nav-link"><?=_ic('<path d="M19 12H5M12 19l-7-7 7-7"/>')?> Back to Site</a>
    <?php else:?>
      <div class="sb-sec">PLATFORM</div>
      <?php foreach(array_slice($pn,0,5) as $n):$act=$pg===$n[1];?>
        <a href="<?=$n[0]?>" class="nav-link<?=$act?' active':''?>"><?=_ic($n[3],$act?'#6fe3ff':'currentColor')?> <?=$n[2]?></a>
      <?php endforeach;?>
      <div class="sb-sec">ACCOUNT</div>
      <?php foreach(array_slice($pn,5) as $n):$act=$pg===$n[1];?>
        <a href="<?=$n[0]?>" class="nav-link<?=$act?' active':''?>"><?=_ic($n[3],$act?'#6fe3ff':'currentColor')?> <?=$n[2]?></a>
      <?php endforeach;?>
      <?php if(is_staff()):?>
        <div class="sb-sec">ADMIN</div>
        <a href="/bs-admin/" class="nav-link"><?=_ic('<rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/>')?> Dashboard</a>
      <?php endif;?>
    <?php endif;?>
  </nav>
  <div class="sb-user">
    <?php if($user):?>
    <a href="/profile/" class="sb-card"><div class="sb-av"><?php if($av_url):?><img src="<?=$av_url?>" onerror="this.style.display='none'"><?php endif;?><?=$u_init?></div><div style="flex:1;min-width:0"><div class="sb-uname"><?=htmlspecialchars($user['username']??'')?></div><div class="sb-blox"><?=$blox_f?> $BLOX</div></div></a>
    <?php else:?>
    <a href="/bs-auth/discord.php" class="sb-card"><div class="sb-av" style="background:linear-gradient(140deg,#5865F2,#404EED)"><?=_dis()?></div><div style="flex:1;min-width:0"><div class="sb-uname">Sign in with Discord</div><div style="font-family:'GT America Mono',monospace;font-size:10px;color:#5a6478;margin-top:1px">Join the club</div></div></a>
    <?php endif;?>
  </div>
</aside>
<main class="mc">
<?php
$bc=strtoupper(str_replace(['-','admin '],['',''],$pg))?:'HOME';
$bc_base=$is_adm?'ADMIN':'CLUB';
$bc_cls=$is_adm?'tbc adm':'tbc';
?>
<div class="topbar">
  <div class="<?=$bc_cls?>"><?=$bc_base?> / <span><?=$bc?></span></div>
  <div class="tbr">
    <?php if($is_adm):?><div class="bot-pill"><div class="bot-dot"></div><div class="bot-lbl">BOT ONLINE</div></div>
    <?php elseif($user):?><div class="blox-pill"><div class="blox-dot"></div><div class="blox-val" id="blox-balance"><?=$blox_f?> $BLOX</div></div><?php endif;?>
    <?php if($user):?><a href="/profile/" class="topav"><?php if($av_url):?><img src="<?=$av_url?>" onerror="this.style.display='none'"><?php endif;?><?=$u_init?></a><?php endif;?>
  </div>
</div>
