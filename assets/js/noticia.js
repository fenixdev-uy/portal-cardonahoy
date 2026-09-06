    const articleScript = document.currentScript;
    const articleConfig = {
      voteUrl: articleScript?.dataset.voteUrl || 'votar.php',
      viewUrl: articleScript?.dataset.viewUrl || 'noticia-vista.php',
      shareUrl: articleScript?.dataset.shareUrl || 'noticia-compartir.php',
      noticiaId: articleScript?.dataset.noticiaId || '',
      adPlacementsUrl: articleScript?.dataset.adPlacementsUrl || 'publicidad-ubicaciones.php',
    };
    if (articleConfig.noticiaId) {
      fetch(articleConfig.viewUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ noticia_id: articleConfig.noticiaId }),
        credentials: 'same-origin',
        keepalive: true,
      }).catch((error) => console.error(error));
    }
    document.addEventListener('click', (event) => {
      const shareLink = event.target.closest('.share-btn[data-share-noticia-id][data-share-destino]');
      if (!shareLink) return;
      fetch(articleConfig.shareUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          noticia_id: shareLink.dataset.shareNoticiaId,
          destino: shareLink.dataset.shareDestino,
        }),
        credentials: 'same-origin',
        keepalive: true,
      }).catch((error) => console.error(error));
    }, { capture: true });
    async function copyNewsUrl(url) {
      if (navigator.clipboard?.writeText && window.isSecureContext) {
        await navigator.clipboard.writeText(url);
        return;
      }
      const helper = document.createElement('textarea');
      helper.value = url;
      helper.setAttribute('readonly', '');
      helper.style.position = 'fixed';
      helper.style.opacity = '0';
      document.body.appendChild(helper);
      helper.select();
      const copied = document.execCommand('copy');
      helper.remove();
      if (!copied) throw new Error('El navegador no permitió copiar el enlace.');
    }
    document.addEventListener('click', async (event) => {
      const button = event.target.closest('[data-copy-news-url]');
      if (!button || button.disabled) return;
      const label = button.querySelector('[data-copy-news-label]');
      const originalLabel = label?.textContent || 'Copiar link de noticia';
      button.disabled = true;
      try {
        await copyNewsUrl(button.dataset.copyNewsUrl || '');
        button.classList.add('is-copied');
        if (label) label.textContent = 'Link copiado';
      } catch (error) {
        if (label) label.textContent = 'No se pudo copiar';
        console.error(error);
      } finally {
        window.setTimeout(() => {
          button.disabled = false;
          button.classList.remove('is-copied');
          if (label) label.textContent = originalLabel;
        }, 1800);
      }
    });
    // Accesibilidad de lectura en la noticia individual: escala únicamente el
    // cuerpo periodístico y mantiene los controles entre 90% y 140%.
    document.addEventListener('click', (event) => {
      const button = event.target.closest('.article-reading-button[data-reading-adjust]');
      if (!button || button.disabled) return;

      const tools = button.closest('.article-reading-tools');
      const content = document.querySelector('.article-body');
      if (!tools || !content) return;

      const current = Number(content.dataset.readingScale || 1);
      const adjustment = Number(button.dataset.readingAdjust || 0);
      const next = Math.min(1.4, Math.max(0.9, Math.round((current + adjustment) * 10) / 10));
      content.dataset.readingScale = String(next);
      content.style.setProperty('--reading-scale', String(next));

      tools.querySelectorAll('.article-reading-button').forEach((control) => {
        const delta = Number(control.dataset.readingAdjust || 0);
        control.disabled = (delta < 0 && next <= 0.9) || (delta > 0 && next >= 1.4);
      });

      const status = tools.querySelector('.article-reading-status');
      if (status) status.textContent = `Tamaño de texto ${Math.round(next * 100)}%`;
    });
    let adPlacementsRequest = null;
    async function syncAdPlacements() {
      if (adPlacementsRequest) return adPlacementsRequest;
      adPlacementsRequest = (async () => {
        try {
          const response = await fetch(articleConfig.adPlacementsUrl, {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store'
          });
          const data = await response.json();
          if (!response.ok) throw new Error(data.error || 'No se pudo actualizar la publicidad.');
          const htmlByPlacement = {
            encabezado: typeof data.encabezado_html === 'string' ? data.encabezado_html : '',
            pie: typeof data.pie_html === 'string' ? data.pie_html : '',
          };
          document.querySelectorAll('[data-ad-placement]').forEach((slot) => {
            const html = htmlByPlacement[slot.dataset.adPlacement] ?? '';
            if (slot.innerHTML.trim() !== html.trim()) slot.innerHTML = html;
            slot.hidden = html === '';
          });
        } catch (error) {
          console.error(error);
        } finally {
          adPlacementsRequest = null;
        }
      })();
      return adPlacementsRequest;
    }
    window.addEventListener('storage', event => { if (event.key === 'portal_publicidad_ubicaciones') syncAdPlacements(); });
    window.addEventListener('focus', syncAdPlacements);
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') syncAdPlacements(); });
    let lightbox = null;
    let closeLightbox = null;
    let moveLightbox = null;

    const hamburger=document.getElementById('hamburger'),menuOverlay=document.getElementById('menuOverlay'),closeMenuButton=document.getElementById('closeMenu'),navbar=document.querySelector('.navbar');
    const menuNewsSearch=menuOverlay.querySelector('[data-menu-news-search]'),menuNewsSearchInput=menuNewsSearch?.querySelector('input[type="search"]'),menuNewsSearchResults=menuNewsSearch?.querySelector('.menu-news-search-results');let menuNewsSearchTimer=null,menuNewsSearchController=null;
    function clearMenuSearch(){menuNewsSearchController?.abort();if(menuNewsSearchTimer)clearTimeout(menuNewsSearchTimer);if(menuNewsSearchInput){menuNewsSearchInput.value='';menuNewsSearchInput.setAttribute('aria-expanded','false');}if(menuNewsSearchResults){menuNewsSearchResults.hidden=true;menuNewsSearchResults.replaceChildren();}}
    function setMenu(open){hamburger.classList.toggle('active',open);hamburger.setAttribute('aria-expanded',open?'true':'false');menuOverlay.classList.toggle('open',open);menuOverlay.setAttribute('aria-hidden',open?'false':'true');document.body.classList.toggle('overlay-open',open);if(open){closeMenuButton.focus();if(window.matchMedia('(min-width:769px)').matches)setTimeout(()=>menuNewsSearchInput?.focus(),180);}else clearMenuSearch();}
    hamburger.addEventListener('click',()=>setMenu(!menuOverlay.classList.contains('open')));closeMenuButton.addEventListener('click',()=>setMenu(false));window.addEventListener('scroll',()=>navbar.classList.toggle('scrolled',window.scrollY>10),{passive:true});
    function renderMenuNewsResults(results,message=''){if(!menuNewsSearchResults||!menuNewsSearchInput)return;menuNewsSearchResults.replaceChildren();if(message){const state=document.createElement('span');state.className='menu-news-search-state';state.textContent=message;menuNewsSearchResults.appendChild(state);}else results.slice(0,5).forEach(news=>{const button=document.createElement('button');button.type='button';button.className='menu-news-search-result';button.dataset.storyUrl=news.url||'';const media=document.createElement('span');media.className='menu-news-search-result-media';if(news.miniatura){const image=document.createElement('img');image.src=news.miniatura;image.alt='';image.loading='lazy';media.appendChild(image);}const copy=document.createElement('span');copy.className='menu-news-search-result-copy';const title=document.createElement('strong');title.className='menu-news-search-result-title';title.textContent=news.titulo||'Noticia';const description=document.createElement('span');description.className='menu-news-search-result-description';description.textContent=news.descripcion||'';copy.append(title,description);button.append(media,copy);menuNewsSearchResults.appendChild(button);});menuNewsSearchResults.hidden=false;menuNewsSearchInput.setAttribute('aria-expanded','true');}
    async function searchMenuNews(){if(!menuNewsSearch||!menuNewsSearchInput)return;const query=menuNewsSearchInput.value.trim();menuNewsSearchController?.abort();if(query.length<2){menuNewsSearchResults.hidden=true;menuNewsSearchResults.replaceChildren();menuNewsSearchInput.setAttribute('aria-expanded','false');return;}const controller=new AbortController();menuNewsSearchController=controller;const url=new URL(menuNewsSearch.dataset.searchUrl,location.href);url.searchParams.set('q',query);try{const response=await fetch(url,{credentials:'same-origin',signal:controller.signal});if(!response.ok)throw new Error('No se pudo buscar ('+response.status+')');const data=await response.json(),results=Array.isArray(data.resultados)?data.resultados:[];renderMenuNewsResults(results,results.length?'':'No encontramos noticias.');}catch(error){if(error.name!=='AbortError'){renderMenuNewsResults([],'No pudimos completar la búsqueda.');console.error(error);}}}
    menuNewsSearchInput?.addEventListener('input',()=>{if(menuNewsSearchTimer)clearTimeout(menuNewsSearchTimer);menuNewsSearchTimer=setTimeout(searchMenuNews,240);});menuNewsSearchResults?.addEventListener('click',event=>{const result=event.target.closest('.menu-news-search-result[data-story-url]');if(!result||!result.dataset.storyUrl)return;const url=result.dataset.storyUrl;setMenu(false);setTimeout(()=>{location.href=url;},360);});
    if (document.getElementById('storyGalleryTrack')) {
    const galleryTrack=document.getElementById('storyGalleryTrack'),galleryFrames=[...galleryTrack.querySelectorAll('.story-gallery-frame')],galleryDots=document.getElementById('storyGalleryDots');let galleryIndex=0,galleryTimer=null,galleryTicking=false;
    lightbox=document.getElementById('articleLightbox'),lightboxImage=document.getElementById('articleLightboxImage'),lightboxCounter=document.getElementById('articleLightboxCounter'),lightboxZoom=document.getElementById('articleLightboxZoom'),lightboxStage=document.getElementById('articleLightboxStage');let zoomLevel=1,panX=0,panY=0;
    function setGalleryIndex(index,scroll=false){galleryIndex=(index+galleryFrames.length)%galleryFrames.length;galleryDots?.querySelectorAll('.story-gallery-dot').forEach((dot,i)=>dot.classList.toggle('active',i===galleryIndex));if(scroll)galleryTrack.scrollTo({left:galleryFrames[galleryIndex].offsetLeft,behavior:'smooth'});}
    function stopGallery(){if(galleryTimer){clearInterval(galleryTimer);galleryTimer=null;}}
    function startGallery(){stopGallery();if(galleryFrames.length>1)galleryTimer=setInterval(()=>setGalleryIndex(galleryIndex+1,true),4000);}
    if(galleryDots){galleryFrames.forEach((_,i)=>{const dot=document.createElement('button');dot.type='button';dot.className='story-gallery-dot'+(i===0?' active':'');dot.setAttribute('aria-label','Ir a la imagen '+(i+1));dot.addEventListener('click',()=>{setGalleryIndex(i,true);startGallery();});galleryDots.appendChild(dot);});}
    galleryTrack.addEventListener('scroll',()=>{if(galleryTicking)return;galleryTicking=true;requestAnimationFrame(()=>{setGalleryIndex(Math.round(galleryTrack.scrollLeft/galleryTrack.clientWidth));galleryTicking=false;});},{passive:true});
    galleryTrack.addEventListener('pointerdown',stopGallery);galleryTrack.addEventListener('pointerup',startGallery);galleryTrack.addEventListener('touchend',startGallery,{passive:true});
    function limitPan(){const maxX=Math.max(0,(lightboxImage.offsetWidth*zoomLevel-lightboxStage.clientWidth)/2),maxY=Math.max(0,(lightboxImage.offsetHeight*zoomLevel-lightboxStage.clientHeight)/2);panX=Math.min(maxX,Math.max(-maxX,panX));panY=Math.min(maxY,Math.max(-maxY,panY));}
    function applyImageTransform(){lightboxImage.style.transformOrigin='50% 50%';lightboxImage.style.transform=`translate3d(${panX}px,${panY}px,0) scale(${zoomLevel})`;}
    function updateZoom(nextZoom,originX=50,originY=50){const previousZoom=zoomLevel;zoomLevel=Math.min(4,Math.max(1,nextZoom));if(zoomLevel===1){panX=0;panY=0;}else if(previousZoom>0&&zoomLevel!==previousZoom){const bounds=lightboxStage.getBoundingClientRect(),originPxX=((originX/100)-.5)*bounds.width,originPxY=((originY/100)-.5)*bounds.height,ratio=zoomLevel/previousZoom;panX=originPxX-(originPxX-panX)*ratio;panY=originPxY-(originPxY-panY)*ratio;}limitPan();applyImageTransform();lightboxImage.style.cursor=zoomLevel>1?'zoom-out':'zoom-in';const help=window.matchMedia('(max-width:768px)').matches?(zoomLevel>1?'Arrastrá con un dedo':'Pinza para ampliar'):'Rueda del mouse para ampliar';lightboxZoom.textContent=`${help} · ${Math.round(zoomLevel*100)}%`;}
    function panImage(deltaX,deltaY){if(zoomLevel<=1)return;panX+=deltaX;panY+=deltaY;limitPan();applyImageTransform();}
    function renderLightboxImage(index){setGalleryIndex(index);updateZoom(1);lightboxImage.src=galleryFrames[galleryIndex].querySelector('img').src;lightboxImage.alt='Imagen '+(galleryIndex+1)+' de '+galleryFrames.length;lightboxCounter.textContent=(galleryIndex+1)+' / '+galleryFrames.length;}
    function showLightbox(index){lightbox?.classList.add('open');lightbox.setAttribute('aria-hidden','false');document.body.classList.add('overlay-open');stopGallery();renderLightboxImage(index);document.getElementById('articleLightboxClose').focus();}
    closeLightbox=function(){pointers.clear();initialDistance=0;lightbox?.classList.remove('open','pinching','panning');lightbox.setAttribute('aria-hidden','true');updateZoom(1);lightboxImage.removeAttribute('src');document.body.classList.remove('overlay-open');startGallery();document.getElementById('storyGalleryExpand')?.focus();}
    moveLightbox=function(step){renderLightboxImage(galleryIndex+step);}
    galleryFrames.forEach((frame,i)=>frame.querySelector('img').addEventListener('click',()=>showLightbox(i)));document.getElementById('storyGalleryExpand')?.addEventListener('click',()=>showLightbox(galleryIndex));document.getElementById('articleLightboxClose').addEventListener('click',closeLightbox);document.getElementById('articleLightboxPrev').addEventListener('click',()=>moveLightbox(-1));document.getElementById('articleLightboxNext').addEventListener('click',()=>moveLightbox(1));lightbox.addEventListener('click',event=>{if(event.target===lightbox)closeLightbox();});
    lightboxStage.addEventListener('wheel',event=>{if(!lightbox?.classList.contains('open'))return;event.preventDefault();const bounds=lightboxStage.getBoundingClientRect(),originX=((event.clientX-bounds.left)/bounds.width)*100,originY=((event.clientY-bounds.top)/bounds.height)*100;updateZoom(zoomLevel+(event.deltaY<0?.25:-.25),originX,originY);},{passive:false});
    const pointers=new Map();let initialDistance=0,initialZoom=1;
    function pointerDistance(){const [a,b]=[...pointers.values()];return Math.hypot(a.x-b.x,a.y-b.y);}
    lightboxStage.addEventListener('pointerdown',event=>{if(event.pointerType==='mouse'||!lightbox?.classList.contains('open'))return;pointers.set(event.pointerId,{x:event.clientX,y:event.clientY,startX:event.clientX,startY:event.clientY});if(pointers.size===2){initialDistance=pointerDistance();initialZoom=zoomLevel;lightbox?.classList.remove('panning');lightbox?.classList.add('pinching');}else if(zoomLevel>1){lightbox?.classList.add('panning');}lightboxStage.setPointerCapture?.(event.pointerId);});
    lightboxStage.addEventListener('pointermove',event=>{const pointer=pointers.get(event.pointerId);if(!pointer)return;const deltaX=event.clientX-pointer.x,deltaY=event.clientY-pointer.y;pointer.x=event.clientX;pointer.y=event.clientY;if(pointers.size===2&&initialDistance>0){const bounds=lightboxStage.getBoundingClientRect(),[a,b]=[...pointers.values()],originX=(((a.x+b.x)/2-bounds.left)/bounds.width)*100,originY=(((a.y+b.y)/2-bounds.top)/bounds.height)*100;updateZoom(initialZoom*(pointerDistance()/initialDistance),originX,originY);event.preventDefault();return;}if(pointers.size===1&&zoomLevel>1){panImage(deltaX,deltaY);event.preventDefault();}});
    function finishPointer(event){const pointer=pointers.get(event.pointerId);if(!pointer)return;const wasSimple=pointers.size===1;pointers.delete(event.pointerId);if(pointers.size<2){initialDistance=0;lightbox?.classList.remove('pinching');lightbox?.classList.toggle('panning',pointers.size===1&&zoomLevel>1);}if(pointers.size===0)lightbox?.classList.remove('panning');if(lightboxStage.hasPointerCapture?.(event.pointerId))lightboxStage.releasePointerCapture(event.pointerId);if(!wasSimple||zoomLevel>1)return;const deltaX=pointer.x-pointer.startX,deltaY=pointer.y-pointer.startY;if(Math.abs(deltaX)>50&&Math.abs(deltaX)>Math.abs(deltaY))moveLightbox(deltaX>0?1:-1);else if(deltaY>90)closeLightbox();}
    lightboxStage.addEventListener('pointerup',finishPointer);lightboxStage.addEventListener('pointercancel',finishPointer);startGallery();
    }
    document.addEventListener('keydown',event=>{if(event.key==='Escape'){if(document.getElementById('articleLightbox')?.classList.contains('open'))closeLightbox();else if(menuOverlay.classList.contains('open'))setMenu(false);}if(lightbox?.classList.contains('open')&&event.key==='ArrowRight')moveLightbox(1);if(lightbox?.classList.contains('open')&&event.key==='ArrowLeft')moveLightbox(-1);});
    document.addEventListener('click',async(e)=>{const b=e.target.closest('.vote-btn[data-noticia-id]');if(!b||b.disabled)return;const buttons=[...document.querySelectorAll('.vote-btn[data-noticia-id="'+b.dataset.noticiaId+'"]')];buttons.forEach(x=>x.disabled=true);try{const body=new URLSearchParams({noticia_id:b.dataset.noticiaId,valor:b.dataset.voto});const response=await fetch(articleConfig.voteUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body,credentials:'same-origin'});const data=await response.json();if(!response.ok)throw new Error(data.error||'Error al votar');buttons.forEach(x=>{const value=Number(x.dataset.voto),voted=value===data.mi_voto;x.classList.toggle('voted',voted);x.setAttribute('aria-pressed',voted?'true':'false');x.querySelector('.vote-count').textContent=(value===1?data.me_gusta:data.no_me_gusta)||'';});}catch(error){buttons.forEach(x=>x.disabled=false);console.error(error);}});
