import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['file', 'button', 'buttonLabel', 'progressPanel', 'progress', 'status', 'error', 'zone', 'preview', 'player', 'fileName', 'previewInfo', 'previewError', 'batchHint', 'batchResults', 'batchContinue', 'title'];
  static values = {batchPublicationUrl: String};

  connect() {
    this.batchEnabled = this.hasBatchResultsTarget && Boolean(window.fetch && window.FormData);
    if (this.batchEnabled) {
      this.fileTarget.multiple = true;
      this.batchHintTarget.hidden = false;
    }
    this.selected();
    this.hidePage = () => this.clearPreview();
    this.showPage = () => this.selected();
    window.addEventListener('pagehide', this.hidePage);
    window.addEventListener('pageshow', this.showPage);
  }

  fileIssue(file) {
    if (!file) return 'Choisis une vidéo avant de continuer.';
    if (file.size > 104857600) return 'Cette vidéo dépasse la limite de 100 Mo.';
    if (!/\.(mp4|webm)$/iu.test(file.name)) return 'Choisis une vidéo MP4 ou WebM.';
    if(this.durationFile===file&&this.previewDuration>180)return 'Cette vidéo dépasse 3 minutes. Raccourcis-la avant de l’importer (3 minutes maximum).';
    return null;
  }

  selected() {
    if (this.request || this.batch) return;
    this.clearPreview();
    this.errorTarget.hidden = true;
    const count = this.fileTarget.files.length;
    if (this.batchEnabled) {
      this.titleTarget.hidden = count > 1;
      this.buttonLabelTarget.textContent = count > 1 ? `Importer ${count} vidéos` : 'Importer ma vidéo';
      this.batchHintTarget.textContent = count > 1 ? `${count} vidéos sélectionnées · 10 par lot maximum` : 'Tu peux sélectionner jusqu’à 10 vidéos.';
    }
    if (count > 1) {
      if (count > 10) this.fail('Sélectionne 10 vidéos maximum par lot.');
      return;
    }
    const file = this.fileTarget.files[0];
    if (!file) return;
    const issue = this.fileIssue(file);
    if (issue) { this.fail(issue); return; }
    if (!window.URL?.createObjectURL) return;
    this.previewUrl = window.URL.createObjectURL(file);
    this.fileNameTarget.textContent = file.name;
    this.previewInfoTarget.textContent = `${new Intl.NumberFormat('fr-FR', {maximumFractionDigits:1}).format(file.size / 1048576)} Mo · Aperçu sur ton appareil`;
    this.playerTarget.src = this.previewUrl;
    this.previewTarget.hidden = false;
  }

  metadata() {
    const video = this.playerTarget;
    const file = this.fileTarget.files[0];
    if (!file || !Number.isFinite(video.duration)) return;
    if(video.currentSrc&&video.currentSrc!==this.previewUrl)return;
    this.durationFile=file;this.previewDuration=video.duration;
    if(video.duration>180)this.fail(this.fileIssue(file));
    const seconds = Math.floor(video.duration);
    this.previewInfoTarget.textContent = `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')} · ${video.videoWidth} × ${video.videoHeight} · Aperçu sur ton appareil`;
    if (video.duration > 0) video.currentTime = Math.min(0.1, video.duration / 2);
  }

  previewFailed() {
    if (!this.previewUrl) return;
    this.previewErrorTarget.hidden = false;
  }

  drag(event) {
    if (!Array.from(event.dataTransfer?.types || []).includes('Files')) return;
    event.preventDefault();
    if (this.request || this.batch) { event.dataTransfer.dropEffect = 'none'; return; }
    event.dataTransfer.dropEffect = 'copy';
    this.zoneTarget.classList.add('is-dragging');
  }

  leave(event) {
    if (!this.zoneTarget.contains(event.relatedTarget)) this.zoneTarget.classList.remove('is-dragging');
  }

  drop(event) {
    event.preventDefault();
    this.zoneTarget.classList.remove('is-dragging');
    if (this.request || this.batch) return;
    const files = event.dataTransfer?.files;
    if (!files?.length) return;
    if (!this.batchEnabled && files.length !== 1) { this.fail('Dépose une seule vidéo à la fois.'); return; }
    this.fileTarget.files = files;
    this.fileTarget.dispatchEvent(new Event('change', {bubbles:true}));
  }

  remove() {
    if (this.request || this.batch) return;
    this.fileTarget.value = '';
    this.selected();
    this.fileTarget.focus();
  }

  clearPreview() {
    this.playerTarget.pause();
    this.playerTarget.removeAttribute('src');
    this.playerTarget.load();
    if (this.previewUrl) window.URL.revokeObjectURL(this.previewUrl);
    this.previewUrl = null;this.durationFile=null;this.previewDuration=null;
    this.previewTarget.hidden = true;
    this.previewErrorTarget.hidden = true;
  }

  submit(event) {
    if (!window.XMLHttpRequest || !window.FormData) return;
    event.preventDefault();
    if (this.request || this.batch) return;
    if (this.batchEnabled && this.fileTarget.files.length > 1) {
      this.submitBatch(Array.from(this.fileTarget.files));
      return;
    }
    const file = this.fileTarget.files[0];
    const issue = this.fileIssue(file);
    if (issue) {
      this.fail(issue);
      return;
    }
    this.errorTarget.hidden = true;
    this.buttonTarget.disabled = true;
    this.progressPanelTarget.hidden = false;
    this.progressTarget.value = 0;
    this.statusTarget.textContent = 'Transfert de ta vidéo…';
    const request = new XMLHttpRequest();
    const data = new FormData(this.element);
    this.request = request;
    this.fileTarget.disabled = true;
    request.open('POST', this.element.action);
    request.responseType = 'text';
    request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    request.timeout = 180000;
    request.upload.onprogress = (progress) => {
      if (!progress.lengthComputable) return;
      const percent = Math.min(100, Math.round(progress.loaded / progress.total * 100));
      this.progressTarget.value = percent;
      this.statusTarget.textContent = percent === 100 ? 'Transfert terminé. Vérification de ta vidéo…' : `Transfert de ta vidéo : ${percent} %`;
    };
    request.onload = () => {
      this.request = null;
      if (request.responseURL && new URL(request.responseURL).pathname !== new URL(this.element.action).pathname) {
        window.location.assign(request.responseURL);
        return;
      }
      if (request.status === 201 && request.getResponseHeader('Content-Type')?.includes('application/json')) {
        try {
          const destination = new URL(JSON.parse(request.responseText).redirect, window.location.origin);
          if (destination.origin !== window.location.origin) throw new Error('Invalid redirect');
          window.location.assign(destination.href);
        } catch { this.fail('Ta vidéo a peut-être été importée. Consulte ta bibliothèque avant de réessayer.'); }
        return;
      }
      if(request.getResponseHeader('Content-Type')?.includes('application/json')) {
        try {this.fail(JSON.parse(request.responseText).message||'L’import n’a pas abouti.');}
        catch {this.fail('L’import n’a pas abouti. Vérifie le fichier puis réessaie.');}
        return;
      }
      const panel = new DOMParser().parseFromString(request.responseText, 'text/html').querySelector('[data-upload-panel]');
      if (panel) {
        const replacement = document.importNode(panel, true);
        this.element.closest('[data-upload-panel]').replaceWith(replacement);
        replacement.querySelector('input')?.focus();
      } else {
        this.fail('L’import n’a pas abouti. Recharge la page puis réessaie.');
      }
    };
    request.onerror = () => { this.request = null; this.fail('Connexion interrompue. Vérifie ta connexion avant de réessayer.'); };
    request.ontimeout = () => { this.request = null; this.fail('L’import prend trop de temps. Consulte ta bibliothèque avant de réessayer pour éviter un doublon.'); };
    request.send(data);
  }

  async submitBatch(files) {
    if (files.length > 10) {
      this.fail('Sélectionne 10 vidéos maximum par lot.');
      return;
    }

    const batch = new AbortController();
    const destination = this.hasBatchPublicationUrlValue ? new URL(this.batchPublicationUrlValue, window.location.origin) : null;
    const imported = [];
    const data = new FormData(this.element);
    data.delete('video_upload[title]');
    this.batch = batch;
    this.buttonTarget.disabled = this.fileTarget.disabled = true;
    this.batchResultsTarget.replaceChildren();
    this.batchResultsTarget.hidden = false;
    if (this.hasBatchContinueTarget) this.batchContinueTarget.hidden = true;
    this.progressPanelTarget.hidden = false;
    this.errorTarget.hidden = true;
    this.progressTarget.value = 0;
    let successes = 0;
    let stopped = false;
    for (const [index, file] of files.entries()) {
      this.statusTarget.textContent = `Import de la vidéo ${index + 1} sur ${files.length}…`;
      let issue = this.fileIssue(file);
      if (!issue && !stopped) {
        data.set(this.fileTarget.name, file);
        try {
          const response = await fetch(this.element.action, {method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: data, signal: batch.signal});
          if (response.status === 201) {
            if (destination) {
              const {videoId} = await response.json();
              if (!Number.isSafeInteger(videoId) || videoId < 1) throw new Error('Invalid video ID');
              imported.push(videoId);
            }
            successes++;
          } else if ([422, 429, 413].includes(response.status) && !response.redirected) {
            issue = response.status === 413 ? 'Le fichier dépasse la limite d’envoi.' : ((await response.json()).message || 'Le serveur a refusé ce fichier.');
            stopped = response.status === 429;
          } else {
            throw new Error('Unexpected upload response');
          }
        } catch {
          if (batch.signal.aborted) return;
          issue = 'État incertain. Vérifie ta bibliothèque avant de renvoyer ce fichier.';
          stopped = true;
        }
      } else if (!issue) {
        issue = 'Envoi interrompu avant ce fichier.';
      }
      const result = document.createElement('li');
      result.textContent = `${file.name} — ${issue || 'Importée'}`;
      if (issue) result.className = 'upload-result-issue';
      this.batchResultsTarget.append(result);
      this.progressTarget.value = Math.round(100 * (index + 1) / files.length);
    }

    this.batch = null;
    this.buttonTarget.disabled = this.fileTarget.disabled = false;
    this.fileTarget.value = '';
    this.selected();
    this.statusTarget.textContent = `${successes} vidéo${successes === 1 ? '' : 's'} importée${successes === 1 ? '' : 's'} sur ${files.length}.`;
    if (imported.length && destination?.origin === window.location.origin) {
      for (const videoId of imported) destination.searchParams.append('videos[]', videoId);
      if (successes === files.length) {
        window.location.assign(destination.href);
      } else if (this.hasBatchContinueTarget) {
        this.batchContinueTarget.href = destination.href;
        this.batchContinueTarget.hidden = false;
      }
    }
  }

  fail(message) {
    this.errorTarget.textContent = message;
    this.errorTarget.hidden = false;
    this.errorTarget.scrollIntoView?.({block:'nearest',behavior:'auto'});
    this.progressPanelTarget.hidden = true;
    this.buttonTarget.disabled = false;
    this.fileTarget.disabled = false;
  }

  disconnect() {
    this.batch?.abort();
    window.removeEventListener('pagehide', this.hidePage);
    window.removeEventListener('pageshow', this.showPage);
    this.clearPreview();
    if (this.request) {
      this.request.onload = this.request.onerror = this.request.ontimeout = null;
      this.request.upload.onprogress = null;
      this.request.abort();
      this.request = null;
    }
  }
}
