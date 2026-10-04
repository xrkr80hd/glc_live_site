const squareUIStyles=document.createElement('link');squareUIStyles.rel='stylesheet';squareUIStyles.href='/assets/ui-preferences.css';document.head.append(squareUIStyles);
(async()=>{
 try {
  const response=await fetch('/api/feature/',{cache:'no-store'}); if(!response.ok)return;
  const f=await response.json(); if(!f.enabled)return;
  const slot=document.getElementById('church-feature');
  if(slot){slot.hidden=false;slot.innerHTML='<div class="occ-banner"><img src="/assets/operation-christmas-child.jpg" alt="Liberty Church Operation Christmas Child. Pack a shoebox. Share the love of Christ. Shoeboxes due Sunday, November 15."><a class="occ-learn" href="/operation-christmas-child">Learn More <span aria-hidden="true">›</span></a></div>';}
  const nav=document.querySelector('#mainNav ul'); if(nav){const li=document.createElement('li');const a=document.createElement('a');a.href='/operation-christmas-child';a.textContent='Christmas Child';a.className='occ-nav-tab';a.setAttribute('aria-label',f.title);const style=document.createElement('style');style.textContent='#mainNav a.occ-nav-tab{background:#b5222b!important;color:#fff!important;border:1px solid #b5222b;border-radius:0;padding:9px 12px;white-space:nowrap;font-weight:700}#mainNav a.occ-nav-tab:hover{background:#8f1820!important}#mainNav a.occ-nav-tab:focus-visible{outline:3px solid #e4b95b;outline-offset:3px}';document.head.append(style);li.append(a);nav.insertBefore(li,nav.lastElementChild);}
 } catch(e){console.warn('Feature is unavailable.');}
})();
