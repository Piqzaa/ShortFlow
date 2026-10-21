import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static values = {initialPanel:String};
  static targets = ['clip', 'form', 'list', 'format', 'summary', 'notice', 'preview', 'screen', 'canvas', 'stage', 'fit', 'captionStyle', 'intro', 'timeline', 'timelinePanel', 'playhead', 'current', 'captionText', 'hook', 'captionRows', 'music', 'sourceVolume', 'musicVolume', 'musicOffset', 'musicLoop', 'captionTrack', 'cursor', 'tracks', 'ruler', 'undoButton', 'exportPanel', 'exportDialog', 'musicDialog', 'projectDialog'];
  connect() {
    this.playing = false;
    this.history=[];this.timelineNodes=new Map();
    if(this.hasExportDialogTarget)this.exportDialogTarget.close();
    if(this.hasMusicDialogTarget)this.musicDialogTarget.close();
    if(this.hasProjectDialogTarget)this.projectDialogTarget.close();
    this.nextClipKey = this.clipTargets.length;
    this.clipTargets.forEach((row,index) => { row.dataset.key=String(index); });
    this.previewTarget.hidden = false;
    this.element.querySelectorAll('[data-edit-control]').forEach(button => { button.hidden = false; });
    if (this.hasStageTarget) {
      this.element.classList.add('edit-enhanced'); this.screenTarget.hidden = false; this.timelinePanelTarget.hidden = false;
      const initial=this.initialPanelValue||'clip';
      this.element.querySelectorAll('[data-editor-panel]').forEach(panel => { panel.hidden = panel.dataset.editorPanel !== initial; });
      this.element.querySelectorAll('[data-panel]').forEach(button => button.setAttribute('aria-pressed',String(button.dataset.panel===initial)));
    }
    this.refresh();
  }
  disconnect() { this.stop();this.endTrim?.();this.history=[];this.timelineNodes?.clear(); }
  active() { return this.clipTargets.filter(row => !row.querySelector('[data-edit-omit]').checked); }
  bounds(row) { return { start: Number(row.querySelector('[data-edit-start]').value), end: Number(row.querySelector('[data-edit-end]').value) }; }
  refresh() {
    const rows = this.active();
    const total = rows.reduce((sum, row) => { const { start, end } = this.bounds(row); return sum + Math.max(0, end - start); }, 0);
    this.summaryTarget.textContent = `${rows.length} clip${rows.length > 1 ? 's' : ''} · ${total.toLocaleString('fr-FR', {maximumFractionDigits: 1})} s`;
    this.clipTargets.forEach((row, index) => {
      row.querySelectorAll?.('input[name^="clips["]').forEach(input => {input.name=input.name.replace(/^clips\[[^\]]+\]/,`clips[${index}]`);});
      row.querySelector('[data-edit-number]').textContent = index + 1;
      row.querySelector('[data-edit-position]').value = index + 1;
      const up = row.querySelector('[data-action="edit#up"]');
      const down = row.querySelector('[data-action="edit#down"]');
      up.disabled = index === 0; down.disabled = index === this.clipTargets.length - 1;
      up.setAttribute('aria-label', `Monter le clip ${index + 1}`); down.setAttribute('aria-label', `Descendre le clip ${index + 1}`);
      for(const key of ['start','end']) {const slider=row.querySelector(`[data-trim="${key}"]`);if(slider) slider.value=this.bounds(row)[key];}
      const omitted=row.querySelector('[data-edit-omit]').checked;
      for(const key of ['start','end']) row.querySelector(`[data-edit-${key}]`).disabled=omitted;
      const player = row.querySelector('video');
      if (player) {
        player.style.aspectRatio = this.formatTarget.value.replace(':', ' / ');
        const [w, h] = this.formatTarget.value.split(':').map(Number);
        player.style.setProperty('--edit-ratio', String(w / h));
      }
    });
    if (this.hasStageTarget) {
      this.playheadTarget.max = total;
      this.buildTimeline();
      if (!rows.includes(this.selectedRow)) this.selectRow(rows[0]);
      else this.paint();
    }
  }
  change(event, cutChange = false) {
    this.stop();
    if (this.hasCaptionRowsTarget && (cutChange || event?.target.closest('[data-edit-target="clip"]'))) {
      this.captionRowsTarget.replaceChildren();
      this.noticeTarget.textContent = 'Coupes modifiées : les sous-titres seront à régénérer.';
    } else this.noticeTarget.textContent = 'Modifications à enregistrer.';
    this.refresh();
  }
  up(event) { this.move(event.currentTarget.closest('[data-edit-target="clip"]'), -1); }
  down(event) { this.move(event.currentTarget.closest('[data-edit-target="clip"]'), 1); }
  move(row, direction) {
    const rows = this.clipTargets;
    const index = rows.indexOf(row);
    const other = rows[index + direction];
    if (!other) return;
    this.remember();
    if (direction < 0) this.listTarget.insertBefore(row, other);
    else this.listTarget.insertBefore(other, row);
    this.change(null, true);
    row.querySelector(direction < 0 ? '[data-action="edit#down"]' : '[data-action="edit#up"]').focus();
  }
  sort() {
    this.remember();
    [...this.clipTargets].sort((a, b) => Number(a.querySelector('[data-edit-position]').value) - Number(b.querySelector('[data-edit-position]').value)).forEach(row => this.listTarget.append(row));
    this.change(null, true);
  }
  seek(event) {
    const player = event.currentTarget;
    const row = player.closest('[data-edit-target="clip"]');
    const { start, end } = this.bounds(row);
    if (Number.isFinite(start) && Number.isFinite(end) && end > start && (player.currentTime < start || player.currentTime >= end)) player.currentTime = start;
    this.clipTargets.forEach(other => { const video = other.querySelector('video'); if (video && video !== player) video.pause(); });
  }
  trim(event) {
    const input=event.currentTarget;const row=input.closest('[data-edit-target="clip"]');const {start,end}=this.bounds(row);
    const value=Number(input.value);
    const next=input.dataset.trim==='start'?Math.min(value,end-0.1):Math.max(value,start+0.1);
    row.querySelector(`[data-edit-${input.dataset.trim}]`).value=String(Math.max(0,Math.min(Number(input.max),next)));
    this.change(event);
    if(this.hasStageTarget)this.selectRow(row,input.dataset.trim==='start'?this.bounds(row).start:Math.max(this.bounds(row).start,this.bounds(row).end-0.1));
  }
  seekClip(event) { if (this.hasStageTarget) { const row = event.currentTarget.closest('[data-edit-target="clip"]'); this.selectRow(row, this.bounds(row).start); return; } const player = event.currentTarget.closest('[data-edit-target="clip"]').querySelector('video'); if (player && player.readyState > 0) player.currentTime = this.bounds(player.closest('[data-edit-target="clip"]')).start; }
  limit(event) {
    const player = event.currentTarget;
    const row = player.closest('[data-edit-target="clip"]');
    if (player.currentTime < this.bounds(row).end - 0.03) return;
    player.pause();
    if (this.playing) {
      const rows = this.active(); const next = rows[rows.indexOf(row) + 1];
      if (next) this.playRow(next); else this.stop();
    }
  }
  preview() {
    if (this.playing) { this.stop(); return; }
    const rows = this.active();
    if (!rows.length || rows.some(row => { const { start, end } = this.bounds(row); return !row.querySelector('video') || !Number.isFinite(start) || !Number.isFinite(end) || start < 0 || end <= start || end > Number(row.querySelector('[data-edit-end]').max); })) {
      this.noticeTarget.textContent = 'Vérifie les sources et les coupes avant la lecture.'; return;
    }
    if (this.hasStageTarget) {
      const total = Number(this.playheadTarget.max);
      const position = Number(this.playheadTarget.value);
      this.jump(position >= total - 0.03 ? 0 : position, true);
      return;
    }
    this.playing = true; this.previewTarget.textContent = 'Arrêter la lecture ■'; this.playRow(rows[0]);
  }
  playRow(row) {
    const player = row.querySelector('video');
    player.currentTime = this.bounds(row).start;
    row.scrollIntoView({ block: 'nearest', behavior: 'auto' });
    player.play().catch(() => { this.stop(); this.noticeTarget.textContent = 'La lecture est indisponible. Essaie le lecteur de ce clip.'; });
  }
  stop() { this.playing = false; if(this.hasMusicTarget)this.musicTarget.pause(); if (this.hasStageTarget) {this.stageTarget.pause();this.pendingPlay=false;this.mediaVersion=(this.mediaVersion||0)+1;} this.clipTargets.forEach(row => row.querySelector('video')?.pause()); if (this.hasPreviewTarget) this.previewTarget.textContent = 'Lire l’assemblage ▶'; }
  split() {
    this.stop(); const row=this.selectedRow;
    if(!row)return;
    if(this.active().length>=8){this.noticeTarget.textContent='8 morceaux maximum : retire un clip avant de le séparer.';return;}
    const {start,end}=this.bounds(row); const time=Math.round(this.stageTarget.currentTime*1000)/1000;
    if(time-start<0.1 || end-time<0.1){this.noticeTarget.textContent='Place le curseur à au moins 0,1 seconde des bords du clip.';return;}
    this.remember();
    this.clipTargets.filter(clip=>clip.querySelector('[data-edit-omit]').checked).forEach(clip=>clip.remove());
    const copy=row.cloneNode(true);copy.dataset.key=String(this.nextClipKey++);
    copy.querySelector('[data-edit-start]').value=time;
    row.querySelector('[data-edit-end]').value=time;
    this.listTarget.insertBefore(copy,row.nextSibling);
    // Stimulus discovers the new target on the next microtask.
    queueMicrotask(()=>{this.change(null,true);this.selectRow(copy,time);this.showClipPanel();this.noticeTarget.textContent='Clip séparé. Tu peux déplacer ou retirer chaque morceau.';});
  }
  panel(event) {
    this.element.classList.remove('is-media-open');
    this.element.querySelector('[data-action="edit#media"]')?.setAttribute('aria-expanded','false');
    const name = event.currentTarget.dataset.panel;
    this.element.querySelectorAll('[data-panel]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.panel === name)));
    this.element.querySelectorAll('[data-editor-panel]').forEach(panel => { panel.hidden = panel.dataset.editorPanel !== name; });
  }
  media(event) {
    this.stop();const open=this.element.classList.toggle('is-media-open');event.currentTarget.setAttribute('aria-expanded',String(open));
    if(!open)this.showClipPanel();
  }
  buildTimeline() {
    this.timelineNodes??=new Map();
    const used=new Set();
    this.active().forEach((row, index) => {
      const key=row.dataset.key??row.dataset.id;used.add(key);
      let group=this.timelineNodes.get(key);
      if(!group){
        group=document.createElement('div');group.className='editor-timeline-clip';group.draggable=true;group.dataset.clipId=key;
        group.setAttribute('data-action','dragstart->edit#drag');
        const button=document.createElement('button');button.type='button';button.className='editor-timeline-select';button.dataset.clipId=key;button.setAttribute('data-action','edit#choose');
        const image=document.createElement('video');image.muted=true;image.playsInline=true;image.preload='none';image.setAttribute('aria-hidden','true');image.setAttribute('data-video-thumbnails-target','video');image.dataset.previewSrc=row.querySelector('video')?.getAttribute('src')||'';
        const label=document.createElement('strong');const time=document.createElement('small');button.append(image,label,time);group.append(button);
        for(const edge of ['start','end']){const handle=document.createElement('button');handle.type='button';handle.className=`editor-edge editor-edge-${edge}`;handle.dataset.edge=edge;handle.dataset.clipId=key;handle.setAttribute('data-action','pointerdown->edit#beginTrim keydown->edit#trimKey');handle.textContent='⋮';group.append(handle);}
        this.timelineNodes.set(key,group);
      }
      const button=group.querySelector('.editor-timeline-select');
      button.setAttribute('aria-pressed', String(row === this.selectedRow));
      const duration = Math.max(0.1, this.bounds(row).end - this.bounds(row).start);
      group.style.flexGrow = Number.isFinite(duration) ? String(duration) : '1';
      button.querySelector('strong').textContent = `${index + 1}. ${row.querySelector('.edit-clip-top>strong').textContent}`;
      button.querySelector('small').textContent = `${duration.toLocaleString('fr-FR', {maximumFractionDigits:1})} s`;
      group.querySelectorAll('.editor-edge').forEach(handle=>handle.setAttribute('aria-label',`${handle.dataset.edge==='start'?'Début':'Fin'} du clip ${index+1} · flèches pour ajuster`));
      if(this.timelineTarget.children[index]!==group)this.timelineTarget.insertBefore(group,this.timelineTarget.children[index]||null);
    });
    for(const [key,node] of this.timelineNodes)if(!used.has(key)){node.remove();this.timelineNodes.delete(key);}
    if(this.hasRulerTarget){this.rulerTarget.replaceChildren();const duration=Number(this.playheadTarget.max);for(let i=0;i<=4;i++){const tick=document.createElement('span');tick.textContent=`${(duration*i/4).toLocaleString('fr-FR',{maximumFractionDigits:1})} s`;this.rulerTarget.append(tick);}}
  }
  cover(event) {
    const button=event.target.closest('.editor-timeline-select');
    if(button&&event.target.poster?.startsWith('data:image/jpeg')){button.style.backgroundImage=`url("${event.target.poster}")`;event.target.style.opacity='0';}
  }
  choose(event) {
    this.stop(); const row = this.clipTargets.find(row => (row.dataset.key ?? row.dataset.id) === event.currentTarget.dataset.clipId);
    this.selectRow(row); this.showClipPanel();
  }
  showClipPanel() {
    const button = this.element.querySelector('[data-panel="clip"]');
    if (button) this.panel({currentTarget:button});
  }
  selectRow(row, time = null, play = false) {
    if (!row) { this.stageTarget.removeAttribute('src'); return; }
    this.selectedRow = row;
    this.clipTargets.forEach(clip => clip.classList.toggle('is-selected', clip === row));
    this.timelineTarget.querySelectorAll('.editor-timeline-select').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.clipId === (row.dataset.key ?? row.dataset.id))));
    const source = row.querySelector('video'); if (!source) return;
    const url = source.getAttribute('src');
    this.pendingTime = time ?? this.bounds(row).start; this.pendingPlay = play;
    this.playing=play; this.previewTarget.textContent=play?'Pause ■':'Lire l’assemblage ▶';
    this.mediaVersion = (this.mediaVersion || 0) + 1;
    if (this.stageTarget.getAttribute('src') !== url) { this.stageTarget.src = url; this.stageTarget.load(); }
    else this.stageReady();
    this.paint();
  }
  stageReady() {
    if (!this.selectedRow) return;
    const time = this.pendingTime ?? this.bounds(this.selectedRow).start;
    this.stageTarget.currentTime = Math.max(0, time); this.pendingTime = null;
    if (this.pendingPlay) {
      this.pendingPlay = false; this.playing = true; this.previewTarget.textContent = 'Pause ■';
      const version = this.mediaVersion;
      this.stageTarget.play().catch(error => { if (version === this.mediaVersion && error.name !== 'AbortError') { this.stop(); this.noticeTarget.textContent = 'La lecture est indisponible. Vérifie la source.'; } });
    }
    this.stageTime();
  }
  offset(row) {
    let value = 0;
    for (const clip of this.active()) { if (clip === row) break; const {start,end}=this.bounds(clip); value += Math.max(0,end-start); }
    return value;
  }
  stageTime() {
    const row = this.selectedRow; if (!row) return;
    const {start,end} = this.bounds(row); const player = this.stageTarget;
    const position = this.offset(row) + Math.max(0,Math.min(end,player.currentTime)-start);
    this.playheadTarget.value = position;
    if(this.hasCursorTarget)this.cursorTarget.style.left=`${Math.min(100,position/(Number(this.playheadTarget.max)||1)*100)}%`;
    this.currentTarget.textContent = `${Math.floor(position/60)}:${String(Math.floor(position%60)).padStart(2,'0')}`;
    this.paintText(position);
    this.syncMusic(position);
    if (this.playing && player.currentTime >= end - 0.025) this.stageEnd();
  }
  stageEnd() {
    if (!this.playing) return;
    const rows=this.active(); const next=rows[rows.indexOf(this.selectedRow)+1];
    if (next) this.selectRow(next,null,true); else { this.playheadTarget.value=this.playheadTarget.max; this.stop(); }
  }
  scrub(event) { this.stop(); this.jump(Number(event.currentTarget.value),false); }
  jump(time, play) {
    let left = time; const rows=this.active();
    for (let index=0;index<rows.length;index++) {
      const row=rows[index]; const {start,end}=this.bounds(row); const duration=end-start;
      if (left < duration || index === rows.length-1) { this.selectRow(row,Math.min(end-0.01,start+left),play); return; }
      left-=duration;
    }
  }
  paint() {
    const ratio=this.formatTarget.value.replace(':',' / '); this.canvasTarget.style.aspectRatio=ratio;
    const [w,h]=this.formatTarget.value.split(':').map(Number);this.canvasTarget.style.setProperty('--editor-ratio',String(w/h));
    this.stageTarget.style.objectFit=this.fitTarget.value;
    if(this.hasSourceVolumeTarget)this.stageTarget.volume=Math.max(0,Math.min(1,Number(this.sourceVolumeTarget.value)/100));
    this.captionTextTarget.dataset.style=this.captionStyleTarget.value;
    this.paintText(Number(this.playheadTarget.value));
    this.syncMusic(Number(this.playheadTarget.value));
    this.drawCaptionTrack();
  }
  syncMusic(position) {
    if(!this.hasMusicTarget)return;
    const audio=this.musicTarget;
    audio.volume=Math.max(0,Math.min(1,Number(this.musicVolumeTarget.value)/100));
    if(!Number.isFinite(audio.duration)||!audio.duration)return;
    const wanted=position+Number(this.musicOffsetTarget.value);
    const loop=this.musicLoopTarget.checked;
    if(!loop&&wanted>=audio.duration){audio.pause();return;}
    const time=loop?wanted%audio.duration:wanted;
    if(Math.abs(audio.currentTime-time)>0.25)audio.currentTime=time;
    if(this.playing&&audio.paused)audio.play().catch(()=>{this.noticeTarget.textContent='L’aperçu musical est indisponible. Tu peux écouter la piste dans Audio.';});
    else if(!this.playing)audio.pause();
  }
  audioChange() { this.paint(); this.noticeTarget.textContent='Réglages audio à enregistrer.'; }
  openMusic(event) {
    event.preventDefault(); this.stop();
    const section=this.element.querySelector('.edit-music');
    this.musicDialogTarget.showModal();
    section.open=true;section.scrollIntoView({block:'start',behavior:'auto'});
    section.querySelector('input[type=file]').focus();
  }
  remember() {
    if(!this.hasUndoButtonTarget)return;
    const clone=node=>{const copy=node.cloneNode(true);const original=node.querySelectorAll('input,textarea');copy.querySelectorAll('input,textarea').forEach((field,index)=>{field.value=original[index].value;field.checked=original[index].checked;});return copy;};
    this.history.push({clips:this.clipTargets.map(clone),captions:clone(this.captionRowsTarget)});
    if(this.history.length>15)this.history.shift();this.undoButtonTarget.disabled=false;
  }
  undo() {
    const last=this.history.pop();if(!last)return;this.stop();
    this.listTarget.replaceChildren(...last.clips);this.captionRowsTarget.replaceChildren(...last.captions.childNodes);
    queueMicrotask(()=>{this.selectedRow=null;this.refresh();this.undoButtonTarget.disabled=!this.history.length;this.noticeTarget.textContent='Dernier changement de clips annulé.';});
  }
  removeSelected() {
    if(!this.selectedRow)return;
    if(this.active().length<=1){this.noticeTarget.textContent='Garde au moins un clip dans le montage.';return;}
    this.remember();this.selectedRow.querySelector('[data-edit-omit]').checked=true;this.change(null,true);
  }
  rowFor(key) {return this.clipTargets.find(row=>(row.dataset.key??row.dataset.id)===key);}
  beginTrim(event) {
    event.preventDefault();event.stopPropagation();this.stop();this.endTrim?.();
    const row=this.rowFor(event.currentTarget.dataset.clipId);if(!row)return;
    this.remember();this.selectRow(row);this.showClipPanel();
    const edge=event.currentTarget.dataset.edge;const original=this.bounds(row)[edge];
    const seconds=Number(this.playheadTarget.max)/this.timelineTarget.getBoundingClientRect().width;const x=event.clientX;
    const move=next=>{this.resizeClip(row,edge,original+(next.clientX-x)*seconds);};
    const end=()=>{window.removeEventListener('pointermove',move);window.removeEventListener('pointerup',end);window.removeEventListener('pointercancel',end);this.endTrim=null;};
    this.endTrim=end;window.addEventListener('pointermove',move);window.addEventListener('pointerup',end,{once:true});window.addEventListener('pointercancel',end,{once:true});
  }
  trimKey(event) {
    if(!['ArrowLeft','ArrowRight'].includes(event.key))return;
    event.preventDefault();event.stopPropagation();this.remember();this.stop();
    const row=this.rowFor(event.currentTarget.dataset.clipId);if(!row)return;
    const edge=event.currentTarget.dataset.edge;this.selectRow(row);this.resizeClip(row,edge,this.bounds(row)[edge]+(event.key==='ArrowLeft'?-1:1)*(event.shiftKey?1:0.1));
  }
  resizeClip(row,edge,value) {
    const {start,end}=this.bounds(row);const max=Number(row.querySelector('[data-edit-end]').max);
    const next=edge==='start'?Math.max(0,Math.min(end-0.1,value)):Math.min(max,Math.max(start+0.1,value));
    row.querySelector(`[data-edit-${edge}]`).value=String(Math.round(next*1000)/1000);
    this.change(null,true);this.selectRow(row,edge==='start'?this.bounds(row).start:Math.max(this.bounds(row).start,this.bounds(row).end-0.03));
  }
  addSource(event) {
    event.preventDefault();this.stop();
    if(this.active().length>=8){this.noticeTarget.textContent='8 clips maximum : retire un clip pour ajouter celui-ci.';return;}
    this.remember();this.clipTargets.filter(row=>row.querySelector('[data-edit-omit]').checked).forEach(row=>row.remove());
    const row=event.currentTarget.querySelector('[data-clip-template]').content.firstElementChild.cloneNode(true);row.dataset.key=String(this.nextClipKey++);
    row.querySelectorAll('[data-edit-control]').forEach(control=>control.hidden=false);
    this.listTarget.append(row);
    queueMicrotask(()=>{this.change(null,true);this.selectRow(row);this.showClipPanel();this.noticeTarget.textContent='Clip ajouté. Tu peux le déplacer dans la timeline.';});
  }
  async persist() {
    if(this.saving)return false;this.stop();this.saving=true;this.element.inert=true;
    try {
      const data=new FormData(this.formTarget);data.set('intent','save');
      const response=await fetch(this.formTarget.action,{method:'POST',body:data,credentials:'same-origin'});
      if(!response.ok||new URL(response.url).pathname!==new URL(this.formTarget.action).pathname)throw new Error('save');
      this.noticeTarget.textContent='Montage enregistré';return true;
    } catch {this.noticeTarget.textContent='Enregistrement impossible. Vérifie les coupes et les réglages, puis utilise « Enregistrer le montage ».';return false;}
    finally {this.saving=false;this.element.inert=false;}
  }
  async saveBeforeLink(event) {event.preventDefault();const url=event.currentTarget.href;if(await this.persist())window.location.assign(url);}
  async searchMedia(event) {event.preventDefault();const url=new URL(window.location.href);url.searchParams.set('q',new FormData(event.currentTarget).get('q')||'');url.searchParams.delete('page');if(await this.persist())window.location.assign(url);}
  async saveBeforeMusic(event) {if(event.currentTarget.dataset.saved==='yes')return;event.preventDefault();const form=event.currentTarget;if(await this.persist()){form.dataset.saved='yes';form.requestSubmit();}}
  openExport() {this.stop();this.exportDialogTarget.showModal();}
  closeExport() {this.exportDialogTarget.close();}
  closeMusic() {this.musicDialogTarget.close();}
  openProject() {this.stop();this.projectDialogTarget.showModal();}
  closeProject() {this.projectDialogTarget.close();}
  drawCaptionTrack() {
    if(!this.hasCaptionTrackTarget)return;
    this.captionTrackTarget.replaceChildren();
    const duration=Number(this.playheadTarget.max)||1;
    if(this.captionStyleTarget.value==='off'){this.captionTrackTarget.textContent='Sous-titres désactivés';return;}
    this.captionRowsTarget.querySelectorAll('[data-caption-row]').forEach(row=>{
      const start=Number(row.querySelector('[data-caption-start]').value),end=Number(row.querySelector('[data-caption-end]').value);
      const button=document.createElement('button');button.type='button';button.textContent=row.querySelector('[data-caption-text]').value;
      button.style.left=`${Math.max(0,start/duration*100)}%`;button.style.width=`${Math.max(0,Math.min(end,duration)-start)/duration*100}%`;
      button.addEventListener('click',()=>{this.stop();this.jump(start,false);this.panel({currentTarget:this.element.querySelector('[data-panel="captions"]')});});
      this.captionTrackTarget.append(button);
    });
  }
  paintText(position) {
    const rows=this.captionRowsTarget.querySelectorAll('[data-caption-row]'); let text='';
    if (this.captionStyleTarget.value !== 'off') for(const row of rows) {
      const start=Number(row.querySelector('[data-caption-start]').value), end=Number(row.querySelector('[data-caption-end]').value);
      if(position>=start && position<end) {text=row.querySelector('[data-caption-text]').value;break;}
    }
    this.captionTextTarget.textContent=text; this.captionTextTarget.hidden=!text;
    this.hookTarget.textContent=this.formTarget.querySelector('[name="video_edit[title]"]').value.slice(0,60);
    this.hookTarget.hidden=!this.introTarget.checked || position>=2.5;
  }
  captionChange() {
    if (this.captionStyleTarget.value === 'off') this.captionStyleTarget.value='clean';
    this.noticeTarget.textContent='Sous-titres modifiés : pense à enregistrer.'; this.paint();
  }
  addCaption() {
    const rows=this.captionRowsTarget.querySelectorAll('[data-caption-row]');
    if(rows.length>=300) {this.noticeTarget.textContent='300 blocs maximum.';return;}
    const last=rows.length ? Number(rows[rows.length-1].querySelector('[data-caption-end]').value) : 0;
    const start=Math.max(last,Number(this.playheadTarget.value)); const end=Math.min(Number(this.playheadTarget.max),start+2);
    if(end<=start) {this.noticeTarget.textContent='Reviens plus tôt dans la vidéo pour ajouter du texte.';return;}
    this.captionRowsTarget.querySelector('[data-caption-empty]')?.remove();
    const row=document.createElement('div');row.className='editor-caption-row';row.setAttribute('data-caption-row','');
    for(const [field,value] of [['start',start],['end',end],['text','']]) {
      const label=document.createElement('label');label.textContent={start:'Début',end:'Fin',text:'Texte du sous-titre'}[field];
      const input=document.createElement(field==='text'?'textarea':'input');input.name=`captions[${rows.length}][${field}]`;input.value=value;
      input.setAttribute(`data-caption-${field}`,'');input.setAttribute('data-action',`${field==='text'?'input':'change'}->edit#captionChange`);
      if(field==='text') {input.maxLength=160;input.rows=2;} else {input.type='number';input.min=0;input.step='0.001';}
      label.append(input);row.append(label);
    }
    this.captionRowsTarget.append(row);row.querySelector('textarea').focus();
  }
  drag(event) {this.draggedId=event.currentTarget.dataset.clipId;event.dataTransfer?.setData('text/plain',this.draggedId);}
  dragOver(event) {if(this.draggedId) event.preventDefault();}
  drop(event) {
    if(!this.draggedId) return;event.preventDefault();
    const target=event.target.closest('[data-clip-id]');const row=this.clipTargets.find(row=>(row.dataset.key??row.dataset.id)===this.draggedId);const other=this.clipTargets.find(row=>(row.dataset.key??row.dataset.id)===target?.dataset.clipId);
    this.draggedId=null;if(!row||!other||row===other) return;
    this.remember();
    this.listTarget.insertBefore(row,this.clipTargets.indexOf(row)<this.clipTargets.indexOf(other)?other.nextSibling:other);this.change(null,true);
  }
  submit() { this.stop(); this.noticeTarget.textContent = 'Enregistrement en cours…'; }
}
