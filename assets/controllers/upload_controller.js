import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['file', 'button', 'progressPanel', 'progress', 'status', 'error', 'zone', 'preview', 'player', 'fileName', 'previewInfo', 'previewError'];

  connect() {
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
    return null;
  }

  selected() {
    if (this.request) return;
    this.clearPreview();
    this.errorTarget.hidden = true;
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
    if (this.request) { event.dataTransfer.dropEffect = 'none'; return; }
    event.dataTransfer.dropEffect = 'copy';
    this.zoneTarget.classList.add('is-dragging');
  }

  leave(event) {
    if (!this.zoneTarget.contains(event.relatedTarget)) this.zoneTarget.classList.remove('is-dragging');
  }

  drop(event) {
    event.preventDefault();
    this.zoneTarget.classList.remove('is-dragging');
    if (this.request) return;
    const files = event.dataTransfer?.files;
    if (!files?.length) return;
    if (files.length !== 1) { this.fail('Dépose une seule vidéo à la fois.'); return; }
    this.fileTarget.files = files;
    this.fileTarget.dispatchEvent(new Event('change', {bubbles:true}));
  }

  remove() {
    if (this.request) return;
    this.fileTarget.value = '';
    this.selected();
    this.fileTarget.focus();
  }

  clearPreview() {
    this.playerTarget.pause();
    this.playerTarget.removeAttribute('src');
    this.playerTarget.load();
    if (this.previewUrl) window.URL.revokeObjectURL(this.previewUrl);
    this.previewUrl = null;
    this.previewTarget.hidden = true;
    this.previewErrorTarget.hidden = true;
  }

  submit(event) {
    if (!window.XMLHttpRequest || !window.FormData) return;
    event.preventDefault();
    if (this.request) return;
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

  fail(message) {
    this.errorTarget.textContent = message;
    this.errorTarget.hidden = false;
    this.progressPanelTarget.hidden = true;
    this.buttonTarget.disabled = false;
    this.fileTarget.disabled = false;
  }

  disconnect() {
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
