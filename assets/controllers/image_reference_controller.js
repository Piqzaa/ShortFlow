import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['file', 'preview', 'status', 'submit', 'remove'];

  connect() {
    this.removeTarget.hidden = this.previewTarget.hidden;
    this.submitTarget.hidden = true;
    this.serial = 0;
    this.disposed = false;
  }

  release() {
    if (this.localUrl) URL.revokeObjectURL(this.localUrl);
    this.localUrl = null;
  }

  changed(reference = '', pending = false) {
    this.dispatch('changed', { detail: { reference, pending } });
  }

  select() {
    const file = this.fileTarget.files?.[0];
    if (!file) return;
    this.request?.abort();
    this.serial++;
    this.release();
    this.changed();
    this.submitTarget.hidden = true;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 10485760) {
      this.previewTarget.hidden = true;
      this.statusTarget.textContent = 'Choisis un JPG, PNG ou WebP de 10 Mo maximum.';
      this.fileTarget.value = '';
      this.submitTarget.disabled = false;
      return;
    }
    this.localUrl = URL.createObjectURL(file);
    this.previewTarget.src = this.localUrl;
    this.previewTarget.hidden = false;
    this.removeTarget.hidden = false;
    this.statusTarget.textContent = 'Aperçu local · enregistrement de l’image…';
    this.element.requestSubmit();
  }

  async upload(event) {
    event.preventDefault();
    if (!this.fileTarget.files?.length) return;
    this.request?.abort();
    const serial = ++this.serial;
    const request = new AbortController();
    this.request = request;
    const body = new FormData(this.element);
    this.submitTarget.disabled = true;
    this.submitTarget.hidden = true;
    this.statusTarget.textContent = 'Enregistrement de l’image…';
    this.changed('', true);
    let message = 'Enregistrement impossible. Vérifie ta connexion puis réessaie.';
    const timeout = setTimeout(() => request.abort(), 25000);
    try {
      const response = await fetch(this.element.action, { method: 'POST', body,
        headers: { Accept: 'application/json' }, credentials: 'same-origin', redirect: 'error', signal: request.signal });
      const data = await response.json();
      if (serial !== this.serial || this.disposed) return;
      if (!response.ok) {
        if (typeof data.error === 'string') message = data.error;
        throw new Error('upload');
      }
      if (!/^[a-f0-9]{48}\.jpg$/.test(data.reference)) throw new Error('Réponse d’import invalide.');
      this.changed(data.reference);
      this.statusTarget.textContent = 'Image enregistrée. Décris maintenant le mouvement souhaité.';
    } catch (error) {
      if (serial !== this.serial || this.disposed) return;
      this.changed();
      this.submitTarget.hidden = false;
      this.submitTarget.textContent = 'Réessayer l’import';
      this.statusTarget.textContent = error.name === 'AbortError' ? 'L’import a expiré. Réessaie avant de continuer.' : message;
    } finally {
      clearTimeout(timeout);
      if (serial === this.serial && !this.disposed) this.submitTarget.disabled = false;
    }
  }

  remove() {
    this.request?.abort();
    this.serial++;
    this.release();
    this.fileTarget.value = '';
    this.submitTarget.hidden = true;
    this.previewTarget.removeAttribute('src');
    this.previewTarget.hidden = true;
    this.removeTarget.hidden = true;
    this.submitTarget.disabled = false;
    this.statusTarget.textContent = 'Choisis une autre image de départ.';
    this.changed();
  }

  disconnect() {
    this.disposed = true;
    this.request?.abort();
    this.release();
  }
}
