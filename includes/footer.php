<?php if(!get_user()): ?>
<div class="modal-overlay" id="login-popup">
  <div class="modal" style="max-width:400px">
    <div class="modal-header"><div class="modal-title">Sign in to continue</div><button class="modal-close" onclick="closeModal('login-popup')">✕</button></div>
    <div class="modal-body" style="text-align:center;padding:28px 24px">
      <div style="width:60px;height:60px;border-radius:50%;background:linear-gradient(140deg,#5865F2,#404EED);display:flex;align-items:center;justify-content:center;margin:0 auto 16px"><svg width="28" height="28" viewBox="0 0 71 55" fill="#fff"><path d="M60.1 4.9A58.6 58.6 0 0 0 45.5.4a40 40 0 0 0-1.8 3.7 54.2 54.2 0 0 0-16.4 0A38.5 38.5 0 0 0 25.5.5 58.5 58.5 0 0 0 10.9 5C1.6 18.9-1 32.5.3 46a59 59 0 0 0 18 9.1 43.2 43.2 0 0 0 3.7-6l-5.8-2.8.6-.5a41.4 41.4 0 0 0 35.5 0l.5.5-5.8 2.8a42 42 0 0 0 3.7 6A58.8 58.8 0 0 0 68.7 46C70.3 30.3 66 16.8 60 4.9z"/></svg></div>
      <div style="font-weight:700;font-size:18px;margin-bottom:8px">Join Blockstards</div>
      <div style="font-size:13px;color:#7a8398;margin-bottom:24px;line-height:1.6">Sign in with Discord to enter raffles, bid on auctions, and earn $BLOX.</div>
      <a href="/auth/" class="btn-dc" style="width:100%;justify-content:center;display:flex"><svg width="19" height="19" viewBox="0 0 71 55" fill="#fff"><path d="M60.1 4.9A58.6 58.6 0 0 0 45.5.4a40 40 0 0 0-1.8 3.7 54.2 54.2 0 0 0-16.4 0A38.5 38.5 0 0 0 25.5.5 58.5 58.5 0 0 0 10.9 5C1.6 18.9-1 32.5.3 46a59 59 0 0 0 18 9.1 43.2 43.2 0 0 0 3.7-6l-5.8-2.8.6-.5a41.4 41.4 0 0 0 35.5 0l.5.5-5.8 2.8a42 42 0 0 0 3.7 6A58.8 58.8 0 0 0 68.7 46C70.3 30.3 66 16.8 60 4.9z"/></svg>Sign in with Discord</a>
      <button onclick="closeModal('login-popup')" style="margin-top:12px;background:none;border:none;color:#5a6478;cursor:pointer;font-size:11px;font-family:'GT America Mono',monospace">Maybe later</button>
    </div>
  </div>
</div>
<?php endif; ?>
<div class="toast" id="toast"></div>
</main>
</body>
<script>
function openModal(id){document.getElementById(id)?.classList.add('open')}
function closeModal(id){document.getElementById(id)?.classList.remove('open')}
function showLoginPopup(){openModal('login-popup')}
function showToast(msg,type='ok'){const t=document.getElementById('toast');t.textContent=msg;t.className='toast '+type;setTimeout(()=>t.classList.add('show'),10);setTimeout(()=>t.classList.remove('show'),3200)}
document.querySelectorAll('.modal-overlay').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('open')}));
window.addEventListener('pageshow',()=>document.querySelectorAll('.modal-overlay').forEach(m=>m.classList.remove('open')));
document.addEventListener('keydown',e=>{if(e.key==='Escape')document.querySelectorAll('.modal-overlay.open').forEach(m=>m.classList.remove('open'))});
<?php if(!get_user()):?>
document.addEventListener('click',function(e){
  const sels=['.foil','.foil-full','button[onclick*="enterRaffle"]','button[onclick*="placeBid"]'];
  for(const s of sels){if((e.target.matches(s)||e.target.closest(s))&&!e.target.closest('#login-popup')){e.preventDefault();e.stopPropagation();showLoginPopup();return}}
});
<?php endif;?>
const _p=new URLSearchParams(location.search);
if(_p.get('ok'))showToast('✓ '+decodeURIComponent(_p.get('ok')));
if(_p.get('error'))showToast(decodeURIComponent(_p.get('error')),'err');
if(_p.get('success'))showToast(decodeURIComponent(_p.get('success')));
function toggleSb(){const s=document.getElementById('sb'),o=document.getElementById('sbo'),b=document.getElementById('hbtn');const op=s.classList.toggle('open');o.style.display=op?'block':'none';b.classList.toggle('open',op);document.body.style.overflow=op?'hidden':''}
function closeSb(){document.getElementById('sb')?.classList.remove('open');const o=document.getElementById('sbo');if(o)o.style.display='none';document.getElementById('hbtn')?.classList.remove('open');document.body.style.overflow=''}
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeSb()});
</script>
</html>
