import { Controller } from '@hotwired/stimulus';
import { remapCaptions, layoutClips, timelineDuration, canPlace } from '../edit-timing.js';

export default class extends Controller {
  static values = {initialPanel:String,stateUrl:String,transcribing:Boolean,styles:Object,stylesUrl:String,stylesToken:String,focusClip:Number};
  static targets = ['clip', 'form', 'list', 'format', 'summary', 'notice', 'preview', 'screen', 'canvas', 'stage', 'fit', 'captionStyle', 'intro', 'timeline', 'timelinePanel', 'playhead', 'current', 'captionText', 'hook', 'captionRows', 'music', 'sourceVolume', 'musicVolume', 'musicOffset', 'musicLoop', 'captionTrack', 'cursor', 'tracks', 'ruler', 'undoButton', 'exportPanel', 'exportDialog', 'musicDialog', 'projectDialog', 'revision', 'captionsCurrent', 'textOverlays', 'textRows', 'textTemplate', 'textTrack', 'captionX', 'captionY', 'captionSize', 'captionScope', 'redoButton', 'audioRows', 'audioTemplate', 'audioTrack','snap','timeContent','musicChoice'];
  connect() {
    this.playing = false;this.zoom=1;this.waveforms=new Map();this.waveformRequests=new Map();this.clipSelection=new Set();this.audioPlayers=new Map();
    this.history=[];this.future=[];this.timelineNodes=new Map();
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
    this.captionBasis=this.recipe();
    if(this.hasCaptionsCurrentTarget)this.captionsCurrentTarget.value='1';
    this.refreshTypePresets();this.refresh();if(this.hasStageTarget){const focus=this.active().find(row=>Number(row.dataset.id)===this.focusClipValue);this.jump(focus?this.offset(focus):0,false);}
    if(this.transcribingValue){this.setTranscribing(true);this.pollCaptions();}
    this.visibility=()=>{if(!document.hidden&&this.transcribing)this.pollCaptions();};
    document.addEventListener('visibilitychange',this.visibility);
    this.onResize=()=>{if(this.hasStageTarget)this.paint();};window.addEventListener('resize',this.onResize);
  }
  disconnect() { this.disconnected=true;clearTimeout(this.saveTimer);clearTimeout(this.captionTimer);this.captionAbort?.abort();document.removeEventListener('visibilitychange',this.visibility);window.removeEventListener('resize',this.onResize); this.stop();this.endTrim?.();this.endPan?.();this.endClipMove?.();this.endLayerDrag?.();this.endOverlayDrag?.();this.history=[];this.future=[];this.timelineNodes?.clear();this.releaseAudio?.();this.waveformRequests?.forEach(task=>{task.abort.abort();clearTimeout(task.timer);}); }
  active() { return this.clipTargets.filter(row => !row.querySelector('[data-edit-omit]').checked); }
  bounds(row) { return { start: Number(row.querySelector('[data-edit-start]').value), end: Number(row.querySelector('[data-edit-end]').value) }; }
  refresh() {
    const rows = this.active();
    const total = timelineDuration(this.recipe());
    this.summaryTarget.textContent = `${rows.length} clip${rows.length > 1 ? 's' : ''} · ${total.toLocaleString('fr-FR', {maximumFractionDigits: 1})} s`;
    this.clipTargets.forEach((row, index) => {
      row.querySelectorAll?.('[name^="clips["]').forEach(input => {input.name=input.name.replace(/^clips\[[^\]]+\]/,`clips[${index}]`);});
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
      this.retimeCaptions();
      this.noticeTarget.textContent = 'Coupes modifiées · sous-titres conservés sur les passages restants.';
    } else this.noticeTarget.textContent = 'Modifications à enregistrer.';
    this.refresh();
    this.scheduleSave();
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
    this.packClips();this.change(null, true);
    row.querySelector(direction < 0 ? '[data-action="edit#down"]' : '[data-action="edit#up"]').focus();
  }
  sort() {
    this.remember();
    [...this.clipTargets].sort((a, b) => Number(a.querySelector('[data-edit-position]').value) - Number(b.querySelector('[data-edit-position]').value)).forEach(row => this.listTarget.append(row));
    this.packClips();this.change(null, true);
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
  stop() { if(this.gapFrame)cancelAnimationFrame(this.gapFrame);this.playing = false;this.audioPlayers?.forEach(audio=>audio.pause()); if(this.hasMusicTarget)this.musicTarget.pause(); if (this.hasStageTarget) {this.stageTarget.pause();this.pendingPlay=false;this.mediaVersion=(this.mediaVersion||0)+1;} this.clipTargets.forEach(row => row.querySelector('video')?.pause()); if (this.hasPreviewTarget) this.previewTarget.textContent = 'Lire l’assemblage ▶'; }
  split() {
    if(this.selectedLayer)return this.splitLayer();
    this.stop(); const row=this.selectedRow;
    if(!row)return;
    if(this.active().length>=32){this.noticeTarget.textContent='32 fragments maximum : retire un clip avant de le séparer.';return;}
    const {start,end}=this.bounds(row); const time=Math.round(this.stageTarget.currentTime*1000)/1000;
    if(time-start<0.1 || end-time<0.1){this.noticeTarget.textContent='Place le curseur à au moins 0,1 seconde des bords du clip.';return;}
    this.remember();
    this.clipTargets.filter(clip=>clip.querySelector('[data-edit-omit]').checked).forEach(clip=>clip.remove());
    const copy=this.cloneEditable(row);copy.dataset.key=String(this.nextClipKey++);
    if(copy.querySelector('[data-edit-at]'))copy.querySelector('[data-edit-at]').value=this.offset(row)+(time-start)/this.rate(row);
    copy.querySelector('[data-edit-start]').value=time;
    row.querySelector('[data-edit-end]').value=time;
    if(row.querySelector('[data-look-fadeOut]')){row.querySelector('[data-look-fadeOut]').value=0;copy.querySelector('[data-look-fadeIn]').value=0;}
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
        group=document.createElement('div');group.className='editor-timeline-clip';group.draggable=false;group.dataset.clipId=key;
        group.setAttribute('data-action','dragstart->edit#drag');
        const button=document.createElement('button');button.type='button';button.className='editor-timeline-select';button.dataset.clipId=key;button.setAttribute('data-action','pointerdown->edit#beginClipMove click->edit#choose keydown->edit#clipKey');
        const image=document.createElement('video');image.muted=true;image.playsInline=true;image.preload='none';image.setAttribute('aria-hidden','true');image.setAttribute('data-video-thumbnails-target','video');image.dataset.previewSrc=row.querySelector('video')?.getAttribute('src')||'';
        const label=document.createElement('strong');const time=document.createElement('small');button.append(image,label,time);group.append(button);
        for(const edge of ['start','end']){const handle=document.createElement('button');handle.type='button';handle.className=`editor-edge editor-edge-${edge}`;handle.dataset.edge=edge;handle.dataset.clipId=key;handle.setAttribute('data-action','pointerdown->edit#beginTrim keydown->edit#trimKey');handle.textContent='⋮';group.append(handle);}
        this.timelineNodes.set(key,group);
      }
      const button=group.querySelector('.editor-timeline-select');
      button.setAttribute('aria-pressed', String(row === this.selectedRow||this.clipSelection?.has(key)));
      const duration = this.clipLength(row),total=Number(this.playheadTarget.max)||1;
      group.style.left=`${this.offset(row)/total*100}%`;group.style.width=`${duration/total*100}%`;
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
    if(this.movedClip){this.movedClip=false;return;}
    this.selectedLayer=null;this.stop(); const row = this.clipTargets.find(row => (row.dataset.key ?? row.dataset.id) === event.currentTarget.dataset.clipId);
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
    this.timelineTarget.querySelectorAll('.editor-timeline-select').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.clipId === (row.dataset.key ?? row.dataset.id)||this.clipSelection?.has(button.dataset.clipId))));
    const source = row.querySelector('video'); if (!source) return;
    const url = source.getAttribute('src');
    this.pendingTime = time ?? this.bounds(row).start; this.pendingPlay = play;
    this.gapState=null;this.canvasTarget?.classList.remove('is-gap');this.stageTarget.playbackRate=this.rate(row);
    this.playing=play; this.previewTarget.textContent=play?'Pause ■':'Lire l’assemblage ▶';
    this.mediaVersion = (this.mediaVersion || 0) + 1;
    if (this.stageTarget.getAttribute('src') !== url) { this.stageTarget.src = url; this.stageTarget.load(); }
    else this.stageReady();
    this.paint();
  }
  stageReady() {
    if(this.gapState)return;
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
  rate(row) {return Number(row.querySelector('[data-edit-rate]')?.value||1);}
  clipLength(row) {const {start,end}=this.bounds(row);return Math.max(0,(end-start)/this.rate(row));}
  offset(row) {
    const valueAt=row.querySelector('[data-edit-at]')?.value;if(valueAt!==undefined&&valueAt!=='')return Number(valueAt);
    let value = 0;
    for (const clip of this.active()) { if (clip === row) break; const {start,end}=this.bounds(clip); value += Math.max(0,(end-start)/this.rate(clip)); }
    return value;
  }
  stageTime() {
    const row = this.selectedRow; if (!row||this.gapState) return;
    const {start,end} = this.bounds(row); const player = this.stageTarget;
    const position = this.offset(row) + Math.max(0,Math.min(end,player.currentTime)-start)/this.rate(row);
    this.playheadTarget.value = position;
    if(this.hasCursorTarget)this.cursorTarget.style.left=`${Math.min(100,position/(Number(this.playheadTarget.max)||1)*100)}%`;
    this.currentTarget.textContent = `${Math.floor((position+.001)/60)}:${String(Math.floor((position+.001)%60)).padStart(2,'0')}`;
    this.applyClipAppearance(row);this.paintText(position);
    this.syncMusic(position);
    if (this.playing && player.currentTime >= end - 0.025) this.stageEnd();
  }
  stageEnd() {
    if(!this.playing)return;const next=this.offset(this.selectedRow)+this.clipLength(this.selectedRow)+.001;
    if(next<Number(this.playheadTarget.max))this.jump(next,true);else{this.playheadTarget.value=this.playheadTarget.max;this.stop();}
  }
  scrub(event) {this.stop();this.jump(Number(event.currentTarget.value),false);}
  jump(time,play) {
    const rows=[...this.active()].sort((a,b)=>this.offset(a)-this.offset(b));
    for(const row of rows){const at=this.offset(row),length=this.clipLength(row);
      if(time<at-.001){this.showGap(time,at,play);return;}
      if(time<at+length-.001){this.selectRow(row,this.bounds(row).start+(time-at)*this.rate(row),play);return;}
    }
    if(rows.length){const row=rows.at(-1);this.selectRow(row,this.bounds(row).end-.01,play);}
  }
  showGap(time,until,play) {
    this.stageTarget.pause();this.selectedRow=null;this.gapState={time,until,clock:performance.now()};this.canvasTarget.classList.add('is-gap');this.playing=play;
    this.playheadTarget.value=time;this.paint();this.currentTarget.textContent=`${time.toFixed(1)} s`;this.previewTarget.textContent=play?'Pause ■':'Lire l’assemblage ▶';
    if(play){const tick=now=>{if(!this.playing||!this.gapState)return;const next=this.gapState.time+(now-this.gapState.clock)/1000;if(next>=until){this.jump(until,true);return;}this.playheadTarget.value=next;this.paintText(next);this.syncMusic(next);this.currentTarget.textContent=`${next.toFixed(1)} s`;if(this.hasCursorTarget)this.cursorTarget.style.left=`${next/Number(this.playheadTarget.max)*100}%`;this.gapFrame=requestAnimationFrame(tick);};this.gapFrame=requestAnimationFrame(tick);}
  }
  paint() {
    const ratio=this.formatTarget.value.replace(':',' / '); this.canvasTarget.style.aspectRatio=ratio;
    const [w,h]=this.formatTarget.value.split(':').map(Number);this.canvasTarget.style.setProperty('--editor-ratio',String(w/h));
    this.stageTarget.style.objectFit=this.fitTarget.value;
    if(this.hasFormTarget){const bg=this.formTarget.querySelector('[name="video_edit[background]"]');if(bg)this.canvasTarget.style.backgroundColor=bg.value;}
    if(this.selectedRow)this.applyClipAppearance(this.selectedRow);
    if(this.hasSourceVolumeTarget&&!this.selectedRow)this.stageTarget.volume=0;
    this.captionTextTarget.dataset.style=this.captionStyleTarget.value;
    this.paintText(Number(this.playheadTarget.value));
    this.syncMusic(Number(this.playheadTarget.value));
    this.drawCaptionTrack();
  }
  syncMusic(position) {
    if(this.hasAudioRowsTarget){this.syncAudio(position);return;}
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
  audioChange() { this.paint(); this.scheduleSave(); this.noticeTarget.textContent='Audio modifié · enregistrement à venir.'; }
  openMusic(event) {
    event.preventDefault(); this.stop();
    const section=this.element.querySelector('.edit-music');
    this.musicDialogTarget.showModal();
    section.open=true;section.scrollIntoView({block:'start',behavior:'auto'});
    section.querySelector('input[type=file]').focus();
  }
  remember() {
    if(!this.hasUndoButtonTarget)return;
    this.history.push(this.captureCurrent());
    if(this.history.length>15)this.history.shift();this.future=[];if(this.hasRedoButtonTarget)this.redoButtonTarget.disabled=true;this.undoButtonTarget.disabled=false;
  }
  undo() {
    const last=this.history.pop();if(!last)return;this.stop();
    const current=this.captureCurrent();this.future??=[];this.future.push(current);this.restoreExtras(last);
    this.listTarget.replaceChildren(...last.clips);this.captionRowsTarget.replaceChildren(...last.captions.childNodes);
    queueMicrotask(()=>{this.selectedRow=null;this.captionBasis=this.recipe();this.refresh();this.scheduleSave();this.undoButtonTarget.disabled=!this.history.length;this.noticeTarget.textContent='Dernière modification annulée.';if(this.hasRedoButtonTarget)this.redoButtonTarget.disabled=false;this.selectedLayer=last.layer||null;this.jump(last.time||0,false);});
  }
  removeSelected() {
    if(this.selectedLayer)return this.removeLayer();
    if(!this.selectedRow)return;
    if(this.active().length<=1){this.noticeTarget.textContent='Garde au moins un clip dans le montage.';return;}
    this.remember();const selected=this.clipSelection?.size?this.active().filter(row=>this.clipSelection.has(row.dataset.key)):[this.selectedRow];if(selected.length>=this.active().length){this.noticeTarget.textContent='Garde au moins un clip.';return;}selected.forEach(row=>row.querySelector('[data-edit-omit]').checked=true);this.clipSelection?.clear();this.change(null,true);
  }
  rowFor(key) {return this.clipTargets.find(row=>(row.dataset.key??row.dataset.id)===key);}
  beginTrim(event) {
    event.preventDefault();event.stopPropagation();event.currentTarget.focus();this.stop();this.endTrim?.();
    const row=this.rowFor(event.currentTarget.dataset.clipId);if(!row)return;
    this.remember();this.selectRow(row);this.showClipPanel();
    if(this.hasCaptionRowsTarget)this.trimCaptions={before:this.recipe(),rows:this.readCaptions()};
    const edge=event.currentTarget.dataset.edge;const original=this.bounds(row)[edge];
    const seconds=Number(this.playheadTarget.max)/this.timelineTarget.getBoundingClientRect().width;const x=event.clientX;
    const move=next=>{this.resizeClip(row,edge,original+(next.clientX-x)*seconds);};
    const end=()=>{window.removeEventListener('pointermove',move);window.removeEventListener('pointerup',end);window.removeEventListener('pointercancel',end);this.trimCaptions=null;this.endTrim=null;};
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
    if(edge==='start'&&row.querySelector('[data-edit-at]'))row.querySelector('[data-edit-at]').value=Math.max(0,this.offset(row)+(next-start)/this.rate(row));
    row.querySelector(`[data-edit-${edge}]`).value=String(Math.round(next*1000)/1000);
    this.change(null,true);this.selectRow(row,edge==='start'?this.bounds(row).start:Math.max(this.bounds(row).start,this.bounds(row).end-0.03));
  }
  addSource(event) {
    event.preventDefault();this.stop();
    if(this.active().length>=32){this.noticeTarget.textContent='32 fragments maximum : retire un clip pour ajouter celui-ci.';return;}
    this.remember();this.clipTargets.filter(row=>row.querySelector('[data-edit-omit]').checked).forEach(row=>row.remove());
    const row=event.currentTarget.querySelector('[data-clip-template]').content.firstElementChild.cloneNode(true);row.dataset.key=String(this.nextClipKey++);row.querySelector('[data-edit-origin]').value=crypto.randomUUID();
    row.querySelectorAll('[data-edit-control]').forEach(control=>control.hidden=false);
    if(row.querySelector('[data-edit-at]'))row.querySelector('[data-edit-at]').value=timelineDuration(this.recipe());
    this.listTarget.append(row);
    queueMicrotask(()=>{this.change(null,true);this.selectRow(row);this.showClipPanel();this.noticeTarget.textContent='Clip ajouté. Tu peux le déplacer dans la timeline.';});
  }
  recipe() {
    return this.active().map(row=>({id:Number(row.dataset?.id),...this.bounds(row),...(row.querySelector('[data-edit-at]')?{at:Number(row.querySelector('[data-edit-at]').value)}:{}),rate:this.rate(row),key:row.dataset?.key,origin:row.querySelector('[data-edit-origin]')?.value}));
  }
  packClips() {let at=0;this.active().forEach(row=>{const field=row.querySelector('[data-edit-at]');if(field)field.value=at;at+=this.clipLength(row);});}
  packClipsAction() {this.remember();let at=0;[...this.active()].sort((a,b)=>this.offset(a)-this.offset(b)).forEach(row=>{row.querySelector('[data-edit-at]').value=at;at+=this.clipLength(row);});this.change(null,true);}
  rippleRemove() {
    if(this.selectedLayer)return this.removeLayer();const removed=this.active().filter(row=>this.clipSelection?.size?this.clipSelection.has(row.dataset.key):row===this.selectedRow).map(row=>({at:this.offset(row),length:this.clipLength(row)}));
    if(removed.length>=this.active().length)return;this.removeSelected();this.active().forEach(row=>{const at=this.offset(row),shift=removed.filter(clip=>clip.at<at).reduce((sum,clip)=>sum+clip.length,0);const field=row.querySelector('[data-edit-at]');if(field)field.value=Math.max(0,at-shift);});this.change(null,true);
  }
  duplicateClip() {
    if(!this.selectedRow||this.active().length>=32)return;this.stop();this.remember();const copy=this.cloneEditable(this.selectedRow);copy.dataset.key=String(this.nextClipKey++);copy.querySelector('[data-edit-origin]').value=crypto.randomUUID();copy.querySelector('[data-edit-at]').value=timelineDuration(this.recipe());this.listTarget.append(copy);queueMicrotask(()=>{this.change(null,true);this.selectRow(copy);});
  }
  zoomIn() {this.setZoom(Math.min(8,(this.zoom||1)*1.5));}
  zoomOut() {this.setZoom(Math.max(1,(this.zoom||1)/1.5));}
  zoomFit() {this.setZoom(1);}
  setZoom(value) {this.zoom=value;if(this.hasTimeContentTarget)this.timeContentTarget.style.width=`${value*100}%`;}
  snapAt(value,length,ignore=[]) {
    if(!this.hasSnapTarget||!this.snapTarget.checked)return value;const targets=[0,Number(this.playheadTarget.value)];
    this.active().filter(row=>!ignore.includes(row.dataset.key)).forEach(row=>targets.push(this.offset(row),this.offset(row)+this.clipLength(row)));
    for(const kind of ['text','caption','audio'])if((kind==='text'&&this.hasTextRowsTarget)||(kind==='caption'&&this.hasCaptionRowsTarget)||(kind==='audio'&&this.hasAudioRowsTarget))this.layerRows(kind).forEach(row=>{const key=kind==='caption'?String(this.layerRows(kind).indexOf(row)):this.layerValue(row,kind,'id').value;if(this.selectedLayer?.kind===kind&&this.selectedLayer.key===key)return;targets.push(Number(this.layerValue(row,kind,'start').value),Number(this.layerValue(row,kind,'end').value));});
    const threshold=8*(Number(this.playheadTarget.max)||1)/this.timelineTarget.getBoundingClientRect().width;let best=value,distance=threshold;
    for(const target of targets)for(const at of [target,target-length])if(Math.abs(at-value)<distance){distance=Math.abs(at-value);best=at;}return Math.max(0,best);
  }
  beginClipMove(event) {
    if(this.transcribing||event.button>0)return;const row=this.rowFor(event.currentTarget.dataset.clipId);if(!row)return;this.stop();
    const key=row.dataset.key;this.clipSelection??=new Set();if(event.shiftKey){if(this.clipSelection.has(key))this.clipSelection.delete(key);else this.clipSelection.add(key);}else if(!this.clipSelection.has(key)){this.clipSelection.clear();this.clipSelection.add(key);}
    this.selectedLayer=null;this.selectRow(row);this.showClipPanel();const selected=this.active().filter(clip=>this.clipSelection.has(clip.dataset.key)),before=this.recipe(),original=new Map(selected.map(row=>[row,this.offset(row)])),startX=event.clientX;
    const ratio=Number(this.playheadTarget.max)/this.timelineTarget.getBoundingClientRect().width;let remembered=false;
    const move=next=>{if(Math.abs(next.clientX-startX)<4&&!remembered)return;if(!remembered){this.remember();remembered=true;}this.movedClip=true;
      let delta=(next.clientX-startX)*ratio;const at=this.snapAt(original.get(row)+delta,this.clipLength(row),selected.map(row=>row.dataset.key));delta=at-original.get(row);
      if(!canPlace(before,selected.map(row=>row.dataset.key),delta)){this.noticeTarget.textContent='Choisis un espace libre : les clips ne se superposent pas.';return;}
      selected.forEach(row=>row.querySelector('[data-edit-at]').value=Math.round((original.get(row)+delta)*1000)/1000);this.change(null,true);this.jump(this.offset(row),false);
    };
    const end=()=>{window.removeEventListener('pointermove',move);window.removeEventListener('pointerup',end);window.removeEventListener('pointercancel',end);this.endClipMove=null;};this.endClipMove=end;window.addEventListener('pointermove',move);window.addEventListener('pointerup',end,{once:true});window.addEventListener('pointercancel',end,{once:true});
  }
  seekTimeline(event) {if(event.target.closest('button,input,.editor-track-label'))return;const rect=this.timelineTarget.getBoundingClientRect(),time=Math.max(0,Math.min(Number(this.playheadTarget.max),(event.clientX-rect.left)/rect.width*Number(this.playheadTarget.max)));this.stop();this.jump(time,false);}
  clipKey(event) {
    if(!['ArrowLeft','ArrowRight'].includes(event.key))return;event.preventDefault();const row=this.rowFor(event.currentTarget.dataset.clipId);if(!row)return;this.remember();const delta=(event.key==='ArrowLeft'?-1:1)*(event.shiftKey?.5:.1);
    const selected=this.clipSelection?.has(row.dataset.key)?this.active().filter(clip=>this.clipSelection.has(clip.dataset.key)):[row],keys=selected.map(clip=>clip.dataset.key);
    if(canPlace(this.recipe(),keys,delta)){this.selectedLayer=null;this.clipSelection=new Set(keys);selected.forEach(clip=>clip.querySelector('[data-edit-at]').value=this.offset(clip)+delta);this.selectedRow=row;this.change(null,true);this.jump(this.offset(row),false);this.showClipPanel();}
  }
  shortcut(event) {
    if(event.target.closest('input,textarea,select,[contenteditable=true]')||this.transcribing)return;
    if((event.ctrlKey||event.metaKey)&&event.key.toLowerCase()==='z'){event.preventDefault();event.shiftKey?this.redo():this.undo();}
    else if(event.code==='Space'){event.preventDefault();this.preview();}
    else if(event.key==='Delete'||event.key==='Backspace'){event.preventDefault();this.removeSelected();}
  }
  readLook(row) {
    const defaults={zoom:1,x:.5,y:.5,rotation:0,flip:0,filter:'none',intensity:100,brightness:1,contrast:1,saturation:1,fadeIn:0,fadeOut:0};
    return Object.fromEntries(Object.entries(defaults).map(([key,value])=>[key,row.querySelector(`[data-look-${key}]`)?(key==='filter'?row.querySelector(`[data-look-${key}]`).value:Number(row.querySelector(`[data-look-${key}]`).value)):value]));
  }
  lookChange(event) {this.selectedRow=event.currentTarget.closest('[data-edit-target=clip]');this.paint();this.scheduleSave();}
  applyClipAppearance(row) {
    if(!row.querySelector('[data-look-zoom]'))return;
    const look=this.readLook(row),video=this.stageTarget,cw=this.canvasTarget.clientWidth,ch=this.canvasTarget.clientHeight,sw=video.videoWidth,sh=video.videoHeight;
    if(!sw||!sh||!cw||!ch)return;
    const turned=look.rotation%180!==0,rw=turned?sh:sw,rh=turned?sw:sh,scale=(this.fitTarget.value==='cover'?Math.max(cw/rw,ch/rh):Math.min(cw/rw,ch/rh))*look.zoom;
    const w=rw*scale,h=rh*scale,cx=(cw-w)*look.x+w/2,cy=(ch-h)*look.y+h/2;
    Object.assign(video.style,{position:'absolute',width:`${sw*scale}px`,height:`${sh*scale}px`,maxWidth:'none',maxHeight:'none',left:`${cx}px`,top:`${cy}px`,objectFit:'fill',transform:`translate(-50%,-50%) scaleX(${look.flip?-1:1}) rotate(${look.rotation}deg)`});
    const presets={none:[1,1,1,1,1],warm:[1.03,1.03,1.05,1.08,.91],cool:[1.01,1.04,.95,.93,1.08],vivid:[1,1.12,1.3,1,1],soft:[1.05,.9,.85,1,1],bw:[1,1.05,0,1,1]},amount=look.intensity/100;
    const [b,c,sat,red,blue]=presets[look.filter].map(n=>1+(n-1)*amount),s=sat*look.saturation,brightness=b*look.brightness,contrast=c*look.contrast;
    if(!this.gradeFilter){const ns='http://www.w3.org/2000/svg',svg=document.createElementNS(ns,'svg');svg.setAttribute('width','0');svg.setAttribute('height','0');svg.setAttribute('aria-hidden','true');svg.setAttribute('focusable','false');svg.style.position='absolute';const filter=document.createElementNS(ns,'filter');filter.id='vf-editor-grade';filter.setAttribute('color-interpolation-filters','sRGB');const transfer=document.createElementNS(ns,'feComponentTransfer');const funcs=['R','G','B'].map(channel=>{const node=document.createElementNS(ns,`feFunc${channel}`);node.setAttribute('type','linear');transfer.append(node);return node;});const matrix=document.createElementNS(ns,'feColorMatrix');filter.append(transfer,matrix);svg.append(filter);this.canvasTarget.append(svg);this.gradeFilter={funcs,matrix};}
    this.gradeFilter.funcs.forEach(node=>{node.setAttribute('slope',String(brightness*contrast));node.setAttribute('intercept',String(.5*(1-contrast)));});
    const m=[(s*.787+.213)*red,(.715-.715*s)*red,(.072-.072*s)*red,0,0,.213-.213*s,.285*s+.715,.072-.072*s,0,0,(.213-.213*s)*blue,(.715-.715*s)*blue,(.928*s+.072)*blue,0,0,0,0,0,1,0];this.gradeFilter.matrix.setAttribute('values',m.map(n=>Math.max(-2,Math.min(2,n))).join(' '));video.style.filter='url(#vf-editor-grade)';
    const at=(video.currentTime-this.bounds(row).start)/this.rate(row),length=this.clipLength(row),fadeIn=Math.min(look.fadeIn,length/2),fadeOut=Math.min(look.fadeOut,length/2);
    const fade=Math.max(0,Math.min(1,fadeIn?at/fadeIn:1,fadeOut?(length-at)/fadeOut:1));video.style.opacity=String(fade);if(this.hasSourceVolumeTarget)video.volume=Math.max(0,Math.min(1,Number(this.sourceVolumeTarget.value)/100))*fade;
  }
  panVideo(event) {
    if(event.target!==this.stageTarget||!this.selectedRow||this.transcribing)return;event.preventDefault();this.stop();this.remember();const row=this.selectedRow,look=this.readLook(row),x=event.clientX,y=event.clientY;
    const move=next=>{row.querySelector('[data-look-x]').value=Math.max(0,Math.min(1,look.x-(next.clientX-x)/this.canvasTarget.clientWidth));row.querySelector('[data-look-y]').value=Math.max(0,Math.min(1,look.y-(next.clientY-y)/this.canvasTarget.clientHeight));this.paint();this.scheduleSave();};
    const end=()=>{window.removeEventListener('pointermove',move);window.removeEventListener('pointerup',end);this.endPan=null;};this.endPan=end;window.addEventListener('pointermove',move);window.addEventListener('pointerup',end,{once:true});
  }
  readCaptions() {
    return [...this.captionRowsTarget.querySelectorAll('[data-caption-row]')].map(row=>({start:Number(row.querySelector('[data-caption-start]').value),end:Number(row.querySelector('[data-caption-end]').value),text:row.querySelector('[data-caption-text]').value,...(row.querySelector('[data-caption-x]')?.value?{x:Number(row.querySelector('[data-caption-x]').value)}:{}),...(row.querySelector('[data-caption-y]')?.value?{y:Number(row.querySelector('[data-caption-y]').value)}:{})}));
  }
  retimeCaptions() {
    const next=this.recipe(),rows=this.trimCaptions?.rows||this.readCaptions();
    if(next.some(c=>!Number.isFinite(c.start)||!Number.isFinite(c.end)||c.end<=c.start)||rows.some(c=>!Number.isFinite(c.start)||!Number.isFinite(c.end)||c.end<=c.start))return;
    const active=document.activeElement;
    // Replace only after geometry changes; ordinary typing must retain its focus.
    if(this.captionBasis&&JSON.stringify(this.captionBasis)!==JSON.stringify(next)){
      const detached=this.hasAudioRowsTarget&&this.layerRows('audio').some(row=>this.readAudio(row).kind==='video');
      const duration=timelineDuration(next);
      this.writeCaptions(detached?rows.filter(cue=>cue.start<duration).map(cue=>({...cue,end:Math.min(cue.end,duration)})):remapCaptions(rows,this.trimCaptions?.before||this.captionBasis,next));
    }
    this.captionBasis=next;
    this.clampTextTimes?.();this.clampAudioTimes?.();
    if(this.hasCaptionsCurrentTarget)this.captionsCurrentTarget.value='1';
    if(active?.isConnected&&active!==document.activeElement)active.focus();
  }
  writeCaptions(rows) {
    const elements=rows.map((cue,index)=>{
      const row=document.createElement('div');row.className='editor-caption-row';row.setAttribute('data-caption-row','');
      for(const axis of ['x','y']){const input=document.createElement('input');input.type='hidden';input.name=`captions[${index}][${axis}]`;input.value=cue[axis]??'';input.setAttribute(`data-caption-${axis}`,'');row.append(input);}
      for(const field of ['start','end','text']) {
        const label=document.createElement('label');label.textContent={start:'Début',end:'Fin',text:'Texte du sous-titre'}[field];
        const input=document.createElement(field==='text'?'textarea':'input');input.name=`captions[${index}][${field}]`;input.value=cue[field];
        input.setAttribute(`data-caption-${field}`,'');input.setAttribute('data-action',`focus->edit#remember ${field==='text'?'input':'change'}->edit#captionChange`);
        if(field==='text'){input.maxLength=160;input.rows=2;}else{input.type='number';input.min=0;input.step='0.001';}
        label.append(input);row.append(label);
      }
      return row;
    });
    this.captionRowsTarget.replaceChildren(...elements);
  }
  scheduleSave(event) {
    if(event?.target?.closest('form')!==this.formTarget&&event)return;
    if(event?.target?.matches('[data-edit-ui],[name="confirm"],[name="autoCaptions"],[type="range"][aria-label="Position dans le montage"]'))return;
    if(this.transcribing||this.disconnected||!this.hasRevisionTarget)return;
    this.dirty=true;this.changeSerial=(this.changeSerial||0)+1;
    clearTimeout(this.saveTimer);
    this.saveTimer=setTimeout(()=>this.persist({quiet:true}),1000);
  }
  async persist({intent='save',quiet=false}={}) {
    clearTimeout(this.saveTimer);
    if(this.savePromise){const success=await this.savePromise;if(!success)return false;return this.persist({intent,quiet});}
    if(quiet&&!this.dirty)return true;
    if(this.transcribing)return false;
    const serial=this.changeSerial||0;if(!quiet)this.stop();this.saving=true;
    if(!quiet)this.element.inert=true;
    this.noticeTarget.textContent=intent==='captions'?'Préparation des sous-titres…':'Enregistrement…';
    this.savePromise=(async()=>{
      const abort=new AbortController();const timeout=setTimeout(()=>abort.abort(),15000);
      try {
        const data=new FormData(this.formTarget);data.set('intent',intent);
        const response=await fetch(this.formTarget.action,{method:'POST',body:data,credentials:'same-origin',headers:{Accept:'application/json'},signal:abort.signal});
        if(new URL(response.url).pathname!==new URL(this.formTarget.action).pathname||!response.headers.get('content-type')?.includes('application/json'))throw new Error('Enregistrement impossible. Vérifie ta connexion puis réessaie.');
        const state=await response.json();if(!response.ok)throw new Error(state.message||'Vérifie les champs du montage.');
        if(!Number.isInteger(state.revision))throw new Error('Réponse d’enregistrement invalide. Recharge pour vérifier le montage.');
        if(this.disconnected)return false;
        if(this.hasRevisionTarget)this.revisionTarget.value=String(state.revision);
        this.dirty=(this.changeSerial||0)!==serial;
        this.noticeTarget.textContent=this.dirty?'Modifications en attente…':'Montage enregistré';
        if(state.status==='transcribing'){this.setTranscribing(true);this.pollCaptions();}
        else if(['queued','rendering'].includes(state.status))window.location.assign(this.formTarget.action);
        return true;
      } catch(error) {
        this.noticeTarget.textContent=error.name==='AbortError'?'Le serveur ne répond pas. Vérifie le montage avant de réessayer.':error.message;
        this.saveFailed=true;return false;
      } finally {clearTimeout(timeout);this.saving=false;this.element.inert=false;}
    })();
    const success=await this.savePromise;this.savePromise=null;
    if(success&&this.dirty&&!this.transcribing)this.saveTimer=setTimeout(()=>this.persist({quiet:true}),500);
    return success;
  }
  setTranscribing(active) {
    this.transcribing=active;this.element.classList.toggle('is-transcribing',active);
    this.element.querySelectorAll('[data-edit-lock]').forEach(node=>node.inert=active);
    const title=this.formTarget.querySelector('[name="video_edit[title]"]');if(title)title.readOnly=active;
    if(active){clearTimeout(this.saveTimer);this.noticeTarget.textContent='Création des sous-titres… Tu peux lire le montage.';}
  }
  async pollCaptions() {
    clearTimeout(this.captionTimer);
    if(this.disconnected||!this.transcribing||document.hidden||this.captionBusy)return;
    this.captionBusy=true;this.captionAbort=new AbortController();const timeout=setTimeout(()=>this.captionAbort.abort(),8000);
    try {
      const response=await fetch(this.stateUrlValue,{headers:{Accept:'application/json'},credentials:'same-origin',cache:'no-store',signal:this.captionAbort.signal});
      if(!response.ok||!response.headers.get('content-type')?.includes('application/json'))throw new Error('state');
      const state=await response.json();if(this.disconnected)return;
      if(!Number.isInteger(state.revision)||!Array.isArray(state.captions))throw new Error('state');
      if(state.status==='transcribing')return;
      if(!['draft','failed'].includes(state.status)){this.noticeTarget.textContent='Le projet a changé. Recharge pour vérifier son état.';this.transcribing=false;return;}
      this.writeCaptions(state.captions);
      this.revisionTarget.value=String(state.revision);this.captionStyleTarget.value=state.captionStyle;
      this.captionBasis=this.recipe();this.setTranscribing(false);this.paint();
      this.noticeTarget.textContent=state.issue||'Sous-titres prêts · relis le texte avant l’export.';
      this.captionErrors=0;
    } catch {
      this.captionErrors=(this.captionErrors||0)+1;
      this.noticeTarget.textContent='Suivi momentanément indisponible. Le montage et tes sous-titres restent conservés.';
    } finally {
      clearTimeout(timeout);this.captionBusy=false;
      if(this.transcribing&&!this.disconnected)this.captionTimer=setTimeout(()=>this.pollCaptions(),Math.min(15000,3000*(1+(this.captionErrors||0))));
    }
  }
  async saveBeforeLink(event) {event.preventDefault();const url=event.currentTarget.href;if(this.transcribing||await this.persist())window.location.assign(url);}
  async leave(event) {
    if(event.defaultPrevented||event.button!==0||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey||!this.dirty||this.transcribing)return;
    const link=event.target.closest('a[href]');
    if(!link||link.hasAttribute('download')||link.target==='_blank'||link.getAttribute('href').startsWith('#'))return;
    const url=new URL(link.href);if(url.origin!==window.location.origin)return;
    event.preventDefault();if(await this.persist())window.location.assign(url.href);
  }
  async searchMedia(event) {event.preventDefault();const url=new URL(window.location.href);url.searchParams.set('q',new FormData(event.currentTarget).get('q')||'');url.searchParams.delete('page');if(await this.persist())window.location.assign(url);}
  async saveBeforeMusic(event) {if(event.currentTarget.dataset.saved==='yes')return;event.preventDefault();const form=event.currentTarget;if(await this.persist()){form.dataset.saved='yes';form.requestSubmit();}}
  openExport() {this.stop();this.exportDialogTarget.showModal();}
  closeExport() {this.exportDialogTarget.close();}
  closeMusic() {this.musicDialogTarget.close();}
  openProject() {this.stop();this.projectDialogTarget.showModal();}
  closeProject() {this.projectDialogTarget.close();}
  drawCaptionTrack() {
    if(!this.hasCaptionTrackTarget)return;
    this.drawLayerTrack('caption',this.captionTrackTarget);
    if(this.hasTextTrackTarget)this.drawLayerTrack('text',this.textTrackTarget);
    if(this.hasAudioTrackTarget)this.drawAudioTrack();
    if(this.hasTextRowsTarget)this.updateElementChoices();
  }
  paintText(position) {
    const rows=this.captionRowsTarget.querySelectorAll('[data-caption-row]'); let text='';
    if (this.captionStyleTarget.value !== 'off') for(const row of rows) {
      const start=Number(row.querySelector('[data-caption-start]').value), end=Number(row.querySelector('[data-caption-end]').value);
      if(position>=start && position<end) {text=row.querySelector('[data-caption-text]').value;this.currentCaption=row;break;}
    }
    if(!text)this.currentCaption=null;
    this.captionTextTarget.textContent=text; this.captionTextTarget.hidden=!text;
    if(this.hasCaptionXTarget&&this.hasCanvasTarget){this.positionOverlay(this.captionTextTarget,Number(this.currentCaption?.querySelector('[data-caption-x]')?.value||this.captionXTarget.value),Number(this.currentCaption?.querySelector('[data-caption-y]')?.value||this.captionYTarget.value),Number(this.captionSizeTarget.value));}
    const type=this.element?.querySelector('[data-type-kind=caption]');if(type)this.styleOverlay(this.captionTextTarget,this.readTypography(type));
    if(this.hasTextOverlaysTarget)this.paintTextLayers(position);
    this.hookTarget.textContent=this.formTarget.querySelector('[name="video_edit[title]"]').value.slice(0,60);
    this.hookTarget.hidden=!this.introTarget.checked || position>=2.5;
  }
  captionChange() {
    if (this.captionStyleTarget.value === 'off') this.captionStyleTarget.value='clean';
    this.noticeTarget.textContent='Sous-titres modifiés : pense à enregistrer.'; this.paint();
  }
  addCaption() {
    const rows=this.readCaptions();if(rows.length>=300)return;
    const start=Math.max(rows.at(-1)?.end||0,Number(this.playheadTarget.value)),end=Math.min(Number(this.playheadTarget.max),start+2);
    if(end<=start){this.noticeTarget.textContent='Reviens plus tôt dans la vidéo pour ajouter un sous-titre.';return;}
    this.remember();rows.push({start,end,text:''});this.writeCaptions(rows);this.selectLayer('caption',String(rows.length-1));
    this.captionRowsTarget.querySelectorAll('textarea')[rows.length-1]?.focus();
  }
  layerRows(kind) {return [...(kind==='audio'?this.audioRowsTarget:kind==='text'?this.textRowsTarget:this.captionRowsTarget).querySelectorAll(`[data-${kind}-row]`)];}
  layerRow(kind,key) {return kind==='caption'?this.layerRows(kind)[Number(key)]:this.layerRows(kind).find(row=>this.layerValue(row,kind,'id').value===key);}
  layerValue(row,kind,field) {return row.querySelector(`[data-${kind}-${field}]`);}
  selectLayer(kind,key) {
    this.stop();this.selectedLayer={kind,key};const row=this.layerRow(kind,key);if(!row)return;
    const start=Number(this.layerValue(row,kind,'start').value),end=Number(this.layerValue(row,kind,'end').value),now=Number(this.playheadTarget.value);
    if(now<start||now>=end)this.jump(start,false);
    this.panel({currentTarget:this.element.querySelector(`[data-panel="${kind==='audio'?'audio':kind==='text'?'text':'captions'}"]`)});if(this.hasCaptionTextTarget)this.paintText(Number(this.playheadTarget.value));this.drawCaptionTrack();
  }
  selectTextRow(event) {this.selectLayer('text',event.currentTarget.dataset.textId);}
  readText(row) {return {...Object.fromEntries(['id','start','end','text','x','y','size'].map(key=>[key,['id','text'].includes(key)?this.layerValue(row,'text',key).value:Number(this.layerValue(row,'text',key).value)])),...this.readTypography(row.querySelector('[data-type-controls]'))};}
  addText() {
    if(this.layerRows('text').length>=30){this.noticeTarget.textContent='30 textes maximum.';return;}
    const start=Number(this.playheadTarget.value),end=Math.min(Number(this.playheadTarget.max),start+2);
    if(end-start<0.1){this.noticeTarget.textContent='Place le curseur avant la fin de la vidéo.';return;}
    this.remember();const row=this.textTemplateTarget.content.firstElementChild.cloneNode(true),id=crypto.randomUUID();
    row.querySelector('[data-text-id]').value=id;row.querySelector('[data-text-start]').value=start;row.querySelector('[data-text-end]').value=end;
    row.querySelector('button[data-text-id]').dataset.textId=id;this.textRowsTarget.append(row);this.reindexTexts();this.selectLayer('text',id);this.textChange();row.querySelector('textarea').focus();
  }
  reindexTexts() {this.layerRows('text').forEach((row,index)=>row.querySelectorAll('[name^="texts["]').forEach(input=>input.name=input.name.replace(/^texts\[[^\]]+\]/,`texts[${index}]`)));}
  clampTextTimes() {
    if(!this.hasTextRowsTarget)return;const duration=timelineDuration(this.recipe());
    this.layerRows('text').forEach(row=>{const start=Number(this.layerValue(row,'text','start').value);if(start>=duration-0.1)row.remove();else this.layerValue(row,'text','end').value=Math.min(duration,Number(this.layerValue(row,'text','end').value));});this.reindexTexts();
  }
  textChange() {this.syncMusic(Number(this.playheadTarget.value));this.paintText(Number(this.playheadTarget.value));this.drawCaptionTrack();this.scheduleSave();this.noticeTarget.textContent='Texte modifié · enregistrement à venir.';}
  positionOverlay(node,x,y,size) {
    Object.assign(node.style,{left:`${x*100}%`,top:`${y*100}%`,fontSize:`${this.canvasTarget.clientHeight*size/100}px`});
  }
  paintTextLayers(position) {
    const ids=new Set();
    this.layerRows('text').forEach(row=>{
      const text=this.readText(row);ids.add(text.id);let button=[...this.textOverlaysTarget.children].find(node=>node.dataset.textId===text.id);
      if(!button){button=document.createElement('div');button.setAttribute('role','button');button.tabIndex=0;button.className='editor-overlay editor-free-text';button.dataset.textId=text.id;const content=document.createElement('span');content.className='editor-text-content';const handle=document.createElement('button');handle.type='button';handle.className='editor-text-resize';handle.dataset.resizeText='1';handle.setAttribute('aria-label','Redimensionner le texte');handle.setAttribute('data-action','keydown->edit#resizeTextKey');handle.textContent='↘';button.append(content,handle);button.setAttribute('data-action','pointerdown->edit#beginOverlayDrag keydown->edit#overlayKey dblclick->edit#focusText');this.textOverlaysTarget.append(button);}
      button.querySelector('.editor-text-content').textContent=text.text;this.styleOverlay(button,text);button.setAttribute('aria-label',`Déplacer le texte : ${text.text}`);button.style.color=text.color;button.hidden=position<text.start||position>=text.end;
      this.positionOverlay(button,text.x,text.y,text.size);button.classList.toggle('is-selected',this.selectedLayer?.kind==='text'&&this.selectedLayer.key===text.id);
    });
    [...this.textOverlaysTarget.children].forEach(node=>{if(!ids.has(node.dataset.textId))node.remove();});
  }
  updateElementChoices() {
    for(const kind of ['text','audio']){
      const select=this.element.querySelector(`[data-element-choice="${kind}"]`);if(!select)continue;
      const rows=this.layerRows(kind),choices=rows.map((row,index)=>({key:this.layerValue(row,kind,'id').value,title:kind==='text'?this.layerValue(row,kind,'text').value:this.layerValue(row,kind,'text').value,index}));
      const signature=JSON.stringify(choices);if(select.dataset.signature!==signature){select.replaceChildren();for(const choice of choices){const option=document.createElement('option');option.value=choice.key;option.textContent=`${choice.index+1}. ${choice.title||'Texte vide'}`;select.append(option);}select.dataset.signature=signature;}
      const key=this.selectedLayer?.kind===kind?this.selectedLayer.key:choices[0]?.key;select.value=key||'';rows.forEach(row=>row.hidden=this.layerValue(row,kind,'id').value!==key);
    }
  }
  selectElementChoice(event) {this.selectLayer(event.currentTarget.dataset.elementChoice,event.currentTarget.value);}
  mergeCaption() {
    if(this.selectedLayer?.kind!=='caption'){this.noticeTarget.textContent='Sélectionne le premier sous-titre à fusionner.';return;}const rows=this.readCaptions(),index=Number(this.selectedLayer.key),first=rows[index],next=rows[index+1];if(!next)return;
    const text=`${first.text.trim()} ${next.text.trim()}`;if(text.length>160){this.noticeTarget.textContent='Raccourcis le texte avant de fusionner (160 caractères maximum).';return;}this.remember();rows.splice(index,2,{...first,text,end:next.end});this.writeCaptions(rows);this.captionChange();this.scheduleSave();
  }
  convertLegacyIntro() {
    if(!this.introTarget.checked)return;if(this.layerRows('text').length>=30){this.noticeTarget.textContent='Retire un texte avant de convertir ce titre (30 maximum).';return;}this.addText();const row=this.layerRows('text').at(-1);row.querySelector('[data-text-text]').value=this.hookTarget.textContent;row.querySelector('[data-text-start]').value=0;row.querySelector('[data-text-end]').value=Math.min(2.5,Number(this.playheadTarget.max));row.querySelector('[data-text-y]').value=.15;this.introTarget.checked=false;this.textChange();
  }
  readTypography(container) {
    const defaults={font:'sans-bold',bold:0,italic:0,align:'center',outline:2,shadow:1,box:0,background:'#000000',opacity:100,width:80,color:'#ffffff'};
    return Object.fromEntries(Object.entries(defaults).map(([key,value])=>{const field=container?.querySelector(`[data-type-${key}]`);return [key,field?(['font','align','background','color'].includes(key)?field.value:Number(field.value)):value];}));
  }
  styleOverlay(node,style) {
    const scale=this.canvasTarget.clientHeight/1080,family=({anton:'VF Anton',bebas:'VF Bebas',oswald:'VF Oswald'})[style.font]||(style.font.startsWith('serif')?'VF Serif':style.font.startsWith('mono')?'VF Mono':'VF Sans');
    Object.assign(node.style,{fontFamily:`"${family}"`,fontWeight:style.bold||style.font.endsWith('-bold')?'700':'400',fontStyle:style.italic?'italic':'normal',textAlign:style.align,color:style.color,opacity:String(style.opacity/100),width:`${style.width}%`,textShadow:style.shadow?`${style.shadow*scale}px ${style.shadow*scale}px ${style.shadow*scale}px black`:'none',webkitTextStroke:style.box?'0':`${style.outline*scale}px black`,paintOrder:'stroke fill',padding:style.box?`${6*scale}px`:'0'});
    node.style.setProperty('background-color',style.box?`${style.background}9f`:'transparent','important');
  }
  typographyChange(event) {const container=event.currentTarget.closest('[data-type-controls]');if(container?.dataset.typeKind==='caption')this.captionChange();else this.textChange();this.scheduleSave();}
  beginTextResize(event,layer) {
    const row=this.layerRow('text',layer.key);if(!row)return;event.preventDefault();this.remember();this.selectLayer('text',layer.key);
    const width=row.querySelector('[data-type-width]'),size=row.querySelector('[data-text-size]'),before=Number(width.value),font=Number(size.value),x=event.clientX,y=event.clientY,rect=this.canvasTarget.getBoundingClientRect();
    const move=next=>{width.value=Math.round(Math.max(10,Math.min(95,before+(next.clientX-x)*200/rect.width)));size.value=Math.round(Math.max(2,Math.min(10,font+(next.clientY-y)*50/rect.height))*10)/10;this.textChange();};
    const end=()=>{window.removeEventListener('pointermove',move);window.removeEventListener('pointerup',end);this.endOverlayDrag=null;};this.endOverlayDrag=end;window.addEventListener('pointermove',move);window.addEventListener('pointerup',end,{once:true});
  }
  resizeTextKey(event) {
    if(!['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(event.key))return;event.preventDefault();event.stopPropagation();const key=event.currentTarget.closest('[data-text-id]').dataset.textId,row=this.layerRow('text',key);if(!row)return;this.remember();
    const horizontal=['ArrowLeft','ArrowRight'].includes(event.key),field=horizontal?row.querySelector('[data-type-width]'):row.querySelector('[data-text-size]'),delta=(['ArrowLeft','ArrowUp'].includes(event.key)?-1:1)*(horizontal?(event.shiftKey?5:1):(event.shiftKey?.5:.1));field.value=Math.max(horizontal?10:2,Math.min(horizontal?95:10,Number(field.value)+delta));this.textChange();
  }
  applyTypePreset(event) {
    const container=event.currentTarget.closest('[data-type-controls]'),key=container.querySelector('[data-type-preset]').value;
    const presets={clean:{font:'sans-bold',color:'#ffffff',outline:2,box:0,width:80,opacity:100},hook:{font:'sans-bold',color:'#ffe26b',outline:3,box:0,width:85,opacity:100},boxed:{font:'sans',color:'#ffffff',outline:0,box:1,background:'#17121f',width:75,opacity:100}};
    const style=this.stylesValue?.[key]?.style||presets[key];if(!style)return;this.remember();for(const [name,value] of Object.entries(style)){const field=container.querySelector(`[data-type-${name}]`);if(field)field.value=String(value);}this.typographyChange({currentTarget:container});
  }
  async saveTypePreset(event) {
    const name=window.prompt('Nom de ce style (30 caractères maximum)','Mon style');if(!name?.trim())return;
    const style=this.readTypography(event.currentTarget.closest('[data-type-controls]')),data=new URLSearchParams({_token:this.stylesTokenValue,name:name.trim()});for(const [key,value] of Object.entries(style))data.set(`style[${key}]`,String(value));
    try{const response=await fetch(this.stylesUrlValue,{method:'POST',body:data,headers:{Accept:'application/json'},credentials:'same-origin'});const state=await response.json();if(!response.ok)throw new Error(state.message||'Style non enregistré.');this.stylesValue=state.styles;this.refreshTypePresets();this.noticeTarget.textContent='Style enregistré dans ton compte.';}catch(error){this.noticeTarget.textContent=error.message;}
  }
  refreshTypePresets() {this.element.querySelectorAll('[data-type-preset]').forEach(select=>{select.querySelectorAll('[data-saved-style]').forEach(option=>option.remove());for(const [key,preset] of Object.entries(this.stylesValue||{})){const option=document.createElement('option');option.value=key;option.textContent=preset.name;option.dataset.savedStyle='1';select.append(option);}});}
  focusText(event) {const row=this.layerRow('text',event.currentTarget.dataset.textId);row?.querySelector('textarea').focus();}
  overlayContext(node) {return node.dataset.textId?{kind:'text',key:node.dataset.textId}:{kind:'caption',key:String(this.layerRows('caption').indexOf(this.currentCaption))};}
  beginOverlayDrag(event) {
    if(this.transcribing)return;event.preventDefault();event.stopPropagation();this.endOverlayDrag?.();const node=event.currentTarget,layer=this.overlayContext(node);
    if(layer.key==='-1')return;if(event.target.closest('[data-resize-text]'))return this.beginTextResize(event,layer);this.remember();this.selectLayer(layer.kind,layer.key);node.focus();
    const rect=this.canvasTarget.getBoundingClientRect(),box=node.getBoundingClientRect();const minX=Math.min(.45,Math.max(.05,box.width/rect.width/2)),minY=Math.min(.45,Math.max(.05,box.height/rect.height/2));
    const move=next=>this.placeLayer(layer,Math.max(minX,Math.min(1-minX,(next.clientX-rect.left)/rect.width)),Math.max(minY,Math.min(1-minY,(next.clientY-rect.top)/rect.height)));
    const end=()=>{window.removeEventListener('pointermove',move);window.removeEventListener('pointerup',end);window.removeEventListener('pointercancel',end);this.endOverlayDrag=null;};
    this.endOverlayDrag=end;window.addEventListener('pointermove',move);window.addEventListener('pointerup',end,{once:true});window.addEventListener('pointercancel',end,{once:true});
  }
  placeLayer(layer,x,y) {
    const row=this.layerRow(layer.kind,layer.key);if(!row)return;
    x=Math.round(x*1000)/1000;y=Math.round(y*1000)/1000;
    if(layer.kind==='caption'&&this.captionScopeTarget.value==='all') {
      this.captionXTarget.value=x;this.captionYTarget.value=y;this.layerRows('caption').forEach(cue=>{cue.querySelector('[data-caption-x]').value='';cue.querySelector('[data-caption-y]').value='';});
    }else{this.layerValue(row,layer.kind,'x').value=x;this.layerValue(row,layer.kind,'y').value=y;}
    this.paintText(Number(this.playheadTarget.value));this.scheduleSave();
  }
  overlayKey(event) {
    const vector={ArrowLeft:[-.01,0],ArrowRight:[.01,0],ArrowUp:[0,-.01],ArrowDown:[0,.01]}[event.key];if(!vector||this.transcribing)return;
    event.preventDefault();this.remember();const layer=this.overlayContext(event.currentTarget);this.selectLayer(layer.kind,layer.key);const row=this.layerRow(layer.kind,layer.key);if(!row)return;
    const x=Number(this.layerValue(row,layer.kind,'x').value||this.captionXTarget.value),y=Number(this.layerValue(row,layer.kind,'y').value||this.captionYTarget.value),scale=event.shiftKey?5:1;
    this.placeLayer(layer,Math.max(.05,Math.min(.95,x+vector[0]*scale)),Math.max(.05,Math.min(.95,y+vector[1]*scale)));
  }
  captionPreset(event) {
    this.remember();const index=this.selectedLayer?.kind==='caption'?this.selectedLayer.key:String(this.layerRows('caption').indexOf(this.currentCaption));
    if(index!=='-1')this.selectedLayer={kind:'caption',key:index};
    if(this.captionScopeTarget.value==='all'){this.captionXTarget.value=.5;this.captionYTarget.value=event.currentTarget.dataset.y;this.layerRows('caption').forEach(row=>{row.querySelector('[data-caption-x]').value='';row.querySelector('[data-caption-y]').value='';});this.captionChange();this.scheduleSave();}
    else if(index!=='-1')this.placeLayer({kind:'caption',key:index},.5,Number(event.currentTarget.dataset.y));
    else this.noticeTarget.textContent='Sélectionne un sous-titre dans la timeline.';
  }
  drawLayerTrack(kind,track,rows=null) {
    track.replaceChildren();if(kind==='caption'&&this.captionStyleTarget.value==='off'){track.textContent='Sous-titres désactivés';return;}
    const duration=Number(this.playheadTarget.max)||1;
    (rows||this.layerRows(kind)).forEach((row,index)=>{
      const start=Number(this.layerValue(row,kind,'start').value),end=Number(this.layerValue(row,kind,'end').value),key=kind==='caption'?String(index):this.layerValue(row,kind,'id').value;
      if(!Number.isFinite(start)||!Number.isFinite(end)||end<=start)return;
      const block=document.createElement('div');block.className='editor-layer-block';block.style.left=`${start/duration*100}%`;block.style.width=`${(end-start)/duration*100}%`;
      for(const edge of ['move','start','end']){
        const button=document.createElement('button');button.type='button';button.dataset.layerKind=kind;button.dataset.layerKey=key;button.dataset.layerEdge=edge;
        button.className=edge==='move'?'editor-layer-label':`editor-layer-edge edge-${edge}`;button.textContent=edge==='move'?this.layerValue(row,kind,'text').value:'⋮';
        button.setAttribute('aria-label',`${edge==='move'?'Déplacer':edge==='start'?'Début':'Fin'} ${kind==='audio'?`du fragment audio ${this.layerValue(row,kind,'text').value} à ${start.toFixed(1)} s`:kind==='text'?'du texte':'du sous-titre'} ${kind==='audio'?'':index+1}`);button.setAttribute('data-action','pointerdown->edit#beginLayerDrag keydown->edit#layerKey');block.append(button);
      }
      if(kind==='audio')this.renderWaveform(row,block);
      block.classList.toggle('is-selected',this.selectedLayer?.kind===kind&&this.selectedLayer.key===key);track.append(block);
    });
  }
  beginLayerDrag(event) {
    if(this.transcribing)return;event.preventDefault();event.stopPropagation();this.endLayerDrag?.();const {layerKind:kind,layerKey:key,layerEdge:edge}=event.currentTarget.dataset;this.remember();this.selectLayer(kind,key);
    const row=this.layerRow(kind,key),start=Number(this.layerValue(row,kind,'start').value),end=Number(this.layerValue(row,kind,'end').value),x=event.clientX;
    const offset=kind==='audio'?Number(this.layerValue(row,kind,'offset').value):0;
    const track=kind==='audio'?this.audioTrackTarget:kind==='text'?this.textTrackTarget:this.captionTrackTarget,ratio=Number(this.playheadTarget.max)/track.getBoundingClientRect().width;
    const move=next=>this.shiftLayer(kind,key,edge,start,end,(next.clientX-x)*ratio,offset);
    const finish=()=>{window.removeEventListener('pointermove',move);window.removeEventListener('pointerup',finish);window.removeEventListener('pointercancel',finish);this.endLayerDrag=null;};this.endLayerDrag=finish;
    window.addEventListener('pointermove',move);window.addEventListener('pointerup',finish,{once:true});window.addEventListener('pointercancel',finish,{once:true});
  }
  shiftLayer(kind,key,edge,start,end,delta,offset=null) {
    if(kind==='audio')return this.shiftAudio(key,edge,start,end,delta,offset);
    const row=this.layerRow(kind,key);if(!row)return;const rows=this.layerRows(kind),index=rows.indexOf(row);
    const min=kind==='caption'&&index>0?Number(this.layerValue(rows[index-1],kind,'end').value):0,max=kind==='caption'&&rows[index+1]?Number(this.layerValue(rows[index+1],kind,'start').value):Number(this.playheadTarget.max);
    let first=start,last=end;
    if(edge==='start')first=Math.max(min,Math.min(end-.1,start+delta));
    else if(edge==='end')last=Math.min(max,Math.max(start+.1,end+delta));
    else{first=Math.max(min,Math.min(max-(end-start),this.snapAt(start+delta,end-start)));last=first+end-start;}
    this.layerValue(row,kind,'start').value=Math.round(first*1000)/1000;this.layerValue(row,kind,'end').value=Math.round(last*1000)/1000;this.textChange();
  }
  layerKey(event) {
    if(!['ArrowLeft','ArrowRight'].includes(event.key))return;event.preventDefault();const {layerKind:kind,layerKey:key,layerEdge:edge}=event.currentTarget.dataset;this.remember();this.selectLayer(kind,key);const row=this.layerRow(kind,key);
    this.shiftLayer(kind,key,edge,Number(this.layerValue(row,kind,'start').value),Number(this.layerValue(row,kind,'end').value),(event.key==='ArrowLeft'?-1:1)*(event.shiftKey ? .5 : .1));
    const track=kind==='audio'?this.audioTrackTarget:kind==='text'?this.textTrackTarget:this.captionTrackTarget;[...track.querySelectorAll('button')].find(node=>node.dataset.layerKey===key&&node.dataset.layerEdge===edge)?.focus();
  }
  splitLayer() {
    if(this.selectedLayer.kind==='audio')return this.splitAudio();
    this.stop();
    const {kind,key}=this.selectedLayer,row=this.layerRow(kind,key);if(!row)return;const start=Number(this.layerValue(row,kind,'start').value),end=Number(this.layerValue(row,kind,'end').value),time=Number(this.playheadTarget.value);
    if(time-start<.1||end-time<.1){this.noticeTarget.textContent='Place le curseur à l’intérieur du bloc sélectionné.';return;}
    if((kind==='text'&&this.layerRows(kind).length>=30)||(kind==='caption'&&this.layerRows(kind).length>=300))return;
    if(kind==='caption'){
      const rows=this.readCaptions(),words=rows[Number(key)].text.trim().split(/\s+/);if(words.length<2){this.noticeTarget.textContent='Ajoute au moins deux mots pour séparer ce sous-titre.';return;}
      this.remember();const cut=Math.max(1,Math.min(words.length-1,Math.round(words.length*(time-start)/(end-start)))),old=rows[Number(key)];rows.splice(Number(key),1,{...old,end:time,text:words.slice(0,cut).join(' ')},{...old,start:time,text:words.slice(cut).join(' ')});this.writeCaptions(rows);
    }else{this.remember();const copy=this.cloneEditable(row);copy.querySelector('[data-text-id]').value=crypto.randomUUID();copy.querySelector('button[data-text-id]').dataset.textId=copy.querySelector('[data-text-id]').value;this.layerValue(row,kind,'end').value=time;copy.querySelector('[data-text-start]').value=time;row.after(copy);this.reindexTexts();}
    this.textChange();
  }
  removeLayer() {
    this.stop();
    const {kind,key}=this.selectedLayer,row=this.layerRow(kind,key);if(!row)return;this.remember();row.remove();
    if(kind==='audio')this.reindexAudio();else if(kind==='text')this.reindexTexts();else this.writeCaptions(this.readCaptions());this.selectedLayer=null;this.textChange();
  }
  duplicateLayer() {
    this.stop();
    if(!this.selectedLayer)return this.duplicateClip();
    const {kind,key}=this.selectedLayer,row=this.layerRow(kind,key);if(!row)return;
    if(kind==='audio')return this.duplicateAudio(row);
    if(kind==='text'){
      if(this.layerRows(kind).length>=30)return;this.remember();const copy=this.cloneEditable(row),id=crypto.randomUUID();copy.querySelector('[data-text-id]').value=id;copy.querySelector('button[data-text-id]').dataset.textId=id;copy.querySelector('[data-text-y]').value=Math.min(.95,Number(copy.querySelector('[data-text-y]').value)+.05);row.after(copy);this.reindexTexts();this.selectLayer(kind,id);
    }else{
      const rows=this.readCaptions(),cue=rows[Number(key)],limit=rows[Number(key)+1]?.start??Number(this.playheadTarget.max),end=Math.min(limit,cue.end+cue.end-cue.start);
      if(end-cue.end<.1||rows.length>=300){this.noticeTarget.textContent='Il faut un espace après ce sous-titre pour le dupliquer.';return;}this.remember();rows.splice(Number(key)+1,0,{...cue,start:cue.end,end});this.writeCaptions(rows);
    }
    this.textChange();
  }
  renderWaveform(row,block) {
    if(!row.dataset.waveformUrl)return;const audio=this.readAudio(row),url=`${row.dataset.waveformUrl}?${audio.kind==='video'?`video=${audio.source}`:`track=${encodeURIComponent(audio.musicId)}`}`;
    const state=this.waveforms.get(url);
    if(state?.status==='ready'){
      const canvas=document.createElement('canvas');canvas.width=512;canvas.height=30;canvas.className='editor-waveform';canvas.setAttribute('aria-hidden','true');const context=canvas.getContext('2d');context.fillStyle='#6ce0b0';
      for(let x=0;x<512;x++){let time=audio.offset+x/512*(audio.end-audio.start)*audio.rate;if(audio.loop)time%=state.duration;const amplitude=time<state.duration?Number(state.peaks[Math.min(255,Math.floor(time/state.duration*256))]||0):0;const envelope=Math.min(1,audio.fadeIn?(x/512*(audio.end-audio.start))/audio.fadeIn:1,audio.fadeOut?((1-x/512)*(audio.end-audio.start))/audio.fadeOut:1);const height=Math.max(1,amplitude*28*audio.volume/100*envelope);context.fillRect(x,(30-height)/2,1,height);}block.prepend(canvas);return;
    }
    if(state?.status==='unavailable'){block.title='Pas de forme d’onde disponible pour cette source.';return;}
    block.title='Préparation de la forme d’onde…';if(this.waveformRequests.has(url))return;const task={abort:new AbortController(),attempts:0};this.waveformRequests.set(url,task);
    const load=async()=>{try{const response=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json'},signal:task.abort.signal});if(!response.ok)throw new Error('waveform');const state=await response.json();if(this.disconnected)return;this.waveforms.set(url,state);if(state.status==='pending'&&++task.attempts<45){task.timer=setTimeout(load,4000);return;}this.waveformRequests.delete(url);this.drawCaptionTrack();}catch{this.waveformRequests.delete(url);this.waveforms.set(url,{status:'unavailable'});}};load();
  }
  readAudio(row) {
    return Object.fromEntries(['id','kind','source','start','end','offset','volume','loop','rate','musicId','fadeIn','fadeOut'].map(key=>[key,['id','kind','musicId'].includes(key)?this.layerValue(row,'audio',key)?.value??'original':Number(this.layerValue(row,'audio',key)?.value??(key==='rate'?1:0))]));
  }
  selectAudioRow(event) {this.selectLayer('audio',event.currentTarget.dataset.audioId);}
  reindexAudio() {this.layerRows('audio').forEach((row,index)=>row.querySelectorAll('[name^="audio["]').forEach(input=>input.name=input.name.replace(/^audio\[[^\]]+\]/,`audio[${index}]`)));}
  appendAudio(values,url,title,duration=0) {
    const row=this.audioTemplateTarget.content.firstElementChild.cloneNode(true);values={...values,id:crypto.randomUUID()};
    for(const [key,value] of Object.entries(values))if(this.layerValue(row,'audio',key))this.layerValue(row,'audio',key).value=String(value);
    row.querySelector('[data-audio-text]').value=title;row.querySelector('button[data-audio-id]').textContent=`♫ ${title}`;row.querySelector('button[data-audio-id]').dataset.audioId=values.id;
    row.querySelector('[data-audio-loop]').closest('label').hidden=values.kind==='video';row.dataset.audioUrl=url;row.dataset.audioDuration=String(duration);this.audioRowsTarget.append(row);this.reindexAudio();return row;
  }
  detachAudio() {
    this.stop();
    if(this.layerRows('audio').some(row=>this.readAudio(row).kind==='video')){this.noticeTarget.textContent='Le son des clips est déjà détaché. Modifie ses fragments dans la piste Audio.';return;}
    if(this.layerRows('audio').length+this.active().length>32){this.noticeTarget.textContent='32 fragments audio maximum.';return;}
    this.remember();let time=0;
    for(const clip of this.active()){
      const {start,end}=this.bounds(clip),player=clip.querySelector('video');
      if(player)this.appendAudio({kind:'video',source:clip.dataset.id,start:this.offset(clip),end:this.offset(clip)+this.clipLength(clip),offset:start,rate:this.rate(clip),fadeIn:this.readLook(clip).fadeIn,fadeOut:this.readLook(clip).fadeOut,volume:Number(this.sourceVolumeTarget.value),loop:0},player.getAttribute('src'),clip.querySelector('strong').textContent,Number(clip.querySelector('[data-edit-end]').max));
      time+=end-start;
    }
    this.sourceVolumeTarget.value=0;this.selectedLayer=null;this.audioChange();this.panel({currentTarget:this.element.querySelector('[data-panel="audio"]')});this.noticeTarget.textContent='Son détaché · le son intégré aux clips est coupé pour éviter de le doubler.';
  }
  addMusicSegment() {
    if(this.layerRows('audio').length>=32)return;
    const start=Number(this.playheadTarget.value),end=Number(this.playheadTarget.max);if(end-start<.1)return;
    this.remember();const sample=this.audioTemplateTarget.content.firstElementChild;
    const row=this.appendAudio({kind:'music',musicId:this.hasMusicChoiceTarget?this.musicChoiceTarget.value:'original',source:0,start,end,offset:0,volume:20,loop:1},this.hasMusicChoiceTarget?`${sample.dataset.audioUrl.split('?')[0]}?track=${encodeURIComponent(this.musicChoiceTarget.value)}`:sample.dataset.audioUrl,this.hasMusicChoiceTarget?this.musicChoiceTarget.selectedOptions[0].textContent:sample.querySelector('[data-audio-text]').value,this.hasMusicChoiceTarget?Number(this.musicChoiceTarget.selectedOptions[0].dataset.duration):0);
    this.selectLayer('audio',this.readAudio(row).id);this.audioChange();
  }
  drawAudioTrack() {
    this.audioTrackTarget.replaceChildren();
    const rows=this.layerRows('audio');if(!rows.length){this.audioTrackTarget.textContent='Musique ou son détaché';return;}
    const lanes=[];
    [...rows].sort((a,b)=>this.readAudio(a).start-this.readAudio(b).start).forEach(row=>{
      const audio=this.readAudio(row);let lane=lanes.find(lane=>lane.end<=audio.start+.001);
      if(!lane){lane={rows:[],end:0};lanes.push(lane);}lane.rows.push(row);lane.end=audio.end;
    });
    lanes.forEach(({rows})=>{const lane=document.createElement('div');lane.className='editor-caption-track editor-audio-lane';this.audioTrackTarget.append(lane);this.drawLayerTrack('audio',lane,rows);});
  }
  shiftAudio(key,edge,start,end,delta,baseOffset=null) {
    const row=this.layerRow('audio',key);if(!row)return;const value=this.readAudio(row),offset=baseOffset??value.offset,max=Number(this.playheadTarget.max),sourceDuration=Number(row.dataset.audioDuration)||600;
    let first=start,last=end,nextOffset=offset;
    if(edge==='start'){first=Math.max(0,value.loop?0:start-offset/value.rate,Math.min(end-.1,start+delta));nextOffset=offset+(first-start)*value.rate;if(value.loop)nextOffset=((nextOffset%sourceDuration)+sourceDuration)%sourceDuration;}
    else if(edge==='end')last=Math.min(max,value.loop?max:start+(sourceDuration-offset)/value.rate,Math.max(start+.1,end+delta));
    else{first=Math.max(0,Math.min(max-(end-start),this.snapAt(start+delta,end-start)));last=first+end-start;}
    for(const [field,value] of Object.entries({start:first,end:last,offset:nextOffset}))this.layerValue(row,'audio',field).value=Math.round(value*1000)/1000;
    this.audioChange();
  }
  splitAudio() {
    this.stop();const row=this.layerRow('audio',this.selectedLayer.key);if(!row)return;const audio=this.readAudio(row),time=Number(this.playheadTarget.value);
    if(time-audio.start<.1||audio.end-time<.1){this.noticeTarget.textContent='Place le curseur à l’intérieur du fragment audio.';return;}
    if(this.layerRows('audio').length>=32)return;this.remember();const offset=audio.offset+(time-audio.start)*audio.rate,duration=Number(row.dataset.audioDuration);
    this.layerValue(row,'audio','end').value=time;if(this.layerValue(row,'audio','fadeOut'))this.layerValue(row,'audio','fadeOut').value=0;
    this.appendAudio({...audio,start:time,fadeIn:0,offset:audio.loop&&duration?offset%duration:offset},row.dataset.audioUrl,row.querySelector('[data-audio-text]').value,duration);this.audioChange();
  }
  duplicateAudio(row) {
    if(this.layerRows('audio').length>=32)return;const audio=this.readAudio(row),duration=audio.end-audio.start,start=audio.end,end=Math.min(Number(this.playheadTarget.max),start+duration);
    if(end-start<.1){this.noticeTarget.textContent='Il faut de la place après ce fragment pour le dupliquer.';return;}this.remember();
    this.appendAudio({...audio,start,end},row.dataset.audioUrl,row.querySelector('[data-audio-text]').value,Number(row.dataset.audioDuration));this.audioChange();
  }
  clampAudioTimes() {
    if(!this.hasAudioRowsTarget)return;const duration=timelineDuration(this.recipe());
    this.layerRows('audio').forEach(row=>{const audio=this.readAudio(row);if(audio.start>=duration-.1)row.remove();else this.layerValue(row,'audio','end').value=Math.min(duration,audio.end);});this.reindexAudio();
  }
  releaseAudio() {this.audioPlayers?.forEach(player=>{player.pause();player.removeAttribute('src');player.load();});this.audioPlayers?.clear();}
  syncAudio(position) {
    this.audioPlayers??=new Map();const ids=new Set();
    this.layerRows('audio').forEach(row=>{
      const value=this.readAudio(row);ids.add(value.id);let player=this.audioPlayers.get(value.id);
      if(position<value.start||position>=value.end||!row.dataset.audioUrl){player?.pause();return;}
      if(!player){player=document.createElement('audio');player.preload='metadata';player.src=row.dataset.audioUrl;this.audioPlayers.set(value.id,player);
        player.addEventListener('loadedmetadata',()=>{if(this.disconnected||!row.isConnected)return;row.dataset.audioDuration=String(player.duration);this.syncAudio(Number(this.playheadTarget.value));});
        player.addEventListener('error',()=>{this.noticeTarget.textContent='Un fragment audio ne peut pas être préécouté. Vérifie sa source avant l’export.';});
      }
      const fadeIn=Math.min(value.fadeIn||0,(value.end-value.start)/2),fadeOut=Math.min(value.fadeOut||0,(value.end-value.start)/2),envelope=Math.max(0,Math.min(1,fadeIn?(position-value.start)/fadeIn:1,fadeOut?(value.end-position)/fadeOut:1));player.volume=Math.max(0,Math.min(1,value.volume/100*envelope));const wanted=value.offset+(position-value.start)*value.rate,duration=Number(player.duration);
      if(!Number.isFinite(duration)||!duration)return;
      if(!value.loop&&wanted>=duration){player.pause();return;}
      player.playbackRate=value.rate;const time=value.loop?wanted%duration:wanted;if(Math.abs(player.currentTime-time)>.12)player.currentTime=time;
      if(this.playing&&player.paused)player.play().catch(()=>{this.noticeTarget.textContent='Clique sur Lire pour autoriser l’aperçu audio.';});else if(!this.playing)player.pause();
    });
    this.audioPlayers.forEach((player,id)=>{if(!ids.has(id)){player.pause();player.removeAttribute('src');player.load();this.audioPlayers.delete(id);}});
  }
  cloneEditable(node) {const copy=node.cloneNode(true),fields=node.querySelectorAll('input,textarea,select');copy.querySelectorAll('input,textarea,select').forEach((field,index)=>{field.value=fields[index].value;field.checked=fields[index].checked;});return copy;}
  captureCurrent() {
    const clone=node=>{const copy=node.cloneNode(true);const fields=node.querySelectorAll('input,textarea,select');copy.querySelectorAll('input,textarea,select').forEach((field,i)=>{field.value=fields[i].value;field.checked=fields[i].checked;});return copy;};
    return {selection:[...(this.clipSelection||[])],time:Number(this.playheadTarget?.value||0),layer:this.selectedLayer?{...this.selectedLayer}:null,clips:this.clipTargets.map(clone),captions:clone(this.captionRowsTarget),texts:this.hasTextRowsTarget?clone(this.textRowsTarget):null,audio:this.hasAudioRowsTarget?clone(this.audioRowsTarget):null,settings:this.formTarget?[...this.formTarget.querySelectorAll('[name^="video_edit["],[name^="caption_appearance["]')].map(field=>({name:field.name,value:field.value,checked:field.checked})):[]};
  }
  restoreExtras(snapshot) {
    this.clipSelection=new Set(snapshot.selection||[]);
    if(snapshot.audio&&this.hasAudioRowsTarget){this.releaseAudio();this.audioRowsTarget.replaceChildren(...snapshot.audio.childNodes);}
    if(snapshot.texts&&this.hasTextRowsTarget)this.textRowsTarget.replaceChildren(...snapshot.texts.childNodes);
    for(const saved of snapshot.settings||[]){const field=this.formTarget.querySelector(`[name="${saved.name}"]`);if(field){field.value=saved.value;if(field.type==='checkbox')field.checked=saved.checked;}}
  }
  redo() {
    const next=this.future?.pop();if(!next)return;this.stop();this.history.push(this.captureCurrent());this.restoreExtras(next);this.listTarget.replaceChildren(...next.clips);this.captionRowsTarget.replaceChildren(...next.captions.childNodes);this.selectedLayer=null;
    queueMicrotask(()=>{this.selectedRow=null;this.captionBasis=this.recipe();this.refresh();this.scheduleSave();this.undoButtonTarget.disabled=false;this.redoButtonTarget.disabled=!this.future.length;this.noticeTarget.textContent='Modification rétablie.';this.selectedLayer=next.layer||null;this.jump(next.time||0,false);});
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
  async submit(event) { event.preventDefault();this.stop();await this.persist({intent:event.submitter?.value||'save'}); }
}
