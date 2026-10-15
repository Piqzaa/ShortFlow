import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['file', 'button', 'progressPanel', 'progress', 'status', 'error'];

  submit(event) {
    if (!window.XMLHttpRequest || !window.FormData) return;
    event.preventDefault();
    if (this.request) return;
    const file = this.fileTarget.files[0];
    if (!file || file.size > 104857600) {
      this.fail(file ? 'Cette vidéo dépasse la limite de 100 Mo.' : 'Choisis une vidéo avant de continuer.');
      return;
    }
    this.errorTarget.hidden = true;
    this.buttonTarget.disabled = true;
    this.progressPanelTarget.hidden = false;
    this.progressTarget.value = 0;
    this.statusTarget.textContent = 'Transfert de ta vidéo…';
    const request = new XMLHttpRequest();
    this.request = request;
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
    request.send(new FormData(this.element));
  }

  fail(message) {
    this.errorTarget.textContent = message;
    this.errorTarget.hidden = false;
    this.progressPanelTarget.hidden = true;
    this.buttonTarget.disabled = false;
  }

  disconnect() {
    if (this.request) {
      this.request.onload = this.request.onerror = this.request.ontimeout = null;
      this.request.upload.onprogress = null;
      this.request.abort();
      this.request = null;
    }
  }
}
