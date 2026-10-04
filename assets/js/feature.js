(async()=>{
 try {
  const response=await fetch('/api/feature/',{cache:'no-store'}); if(!response.ok)return;
  const f=await response.json(); if(!f.enabled)return;
  const slot=document.getElementById('church-feature');
  if(slot){slot.hidden=false;slot.innerHTML='<div class="occ-banner"><img src="/assets/operation-christmas-child.jpg" alt="Liberty Church Operation Christmas Child. Pack a shoebox. Share the love of Christ. Shoeboxes due Sunday, November 15."><a class="occ-learn" href="/operation-christmas-child">Learn More <span aria-hidden="true">›</span></a></div>';}
  const nav=document.querySelector('#mainNav ul'); if(nav){const li=document.createElement('li');const a=document.createElement('a');a.href='/operation-christmas-child';a.textContent=f.title;li.append(a);nav.insertBefore(li,nav.lastElementChild);}
 } catch(e){console.warn('Feature is unavailable.');}
})();
