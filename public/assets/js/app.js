function confirmDelete(message='Hapus data ini?'){return window.confirm(message)}
function autoHideToast(){const t=document.querySelector('.toast');if(t){window.setTimeout(()=>t.remove(),3500)}}
function initReveal(){
  const items=document.querySelectorAll('.card,.feature-card,.kpi-card,.pattern-card,.notice,.asset-placeholder');
  if(!('IntersectionObserver' in window)){items.forEach(x=>x.classList.add('is-visible'));return}
  const io=new IntersectionObserver((entries,obs)=>{entries.forEach(e=>{if(e.isIntersecting){e.target.classList.add('is-visible');obs.unobserve(e.target)}})},{threshold:.08});
  items.forEach((x,i)=>{x.classList.add('reveal');x.style.transitionDelay=`${Math.min(i*35,280)}ms`;io.observe(x)});
}
document.addEventListener('DOMContentLoaded',()=>{autoHideToast();initReveal()});
