document.querySelectorAll('[data-business-carousel]').forEach(carousel=>{
 const track=carousel.querySelector('.business-carousel-track'),slides=[...track.children],status=carousel.querySelector('[data-slide-status]');
 const current=()=>Math.round(track.scrollLeft/Math.max(1,track.clientWidth));
 carousel.querySelectorAll('[data-slide-step]').forEach(button=>button.addEventListener('click',()=>{const index=(current()+Number(button.dataset.slideStep)+slides.length)%slides.length;track.scrollTo({left:index*track.clientWidth,behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth'});}));
 track.addEventListener('scroll',()=>{status.textContent=`${current()+1} / ${slides.length}`;},{passive:true});
});
