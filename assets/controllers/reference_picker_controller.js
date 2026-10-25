import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['file', 'value', 'preview', 'frame', 'remove', 'status', 'fit'];
  static values = { upload: String, token: String, kind: String, end: Boolean, ratio: String, image: String, allowRemove: { type: Boolean, default: true } };

  connect() {
    this.form = this.element.closest('form');
    this.active = true;
    this.serial = 0;
    this.refresh = () => this.render();
    this.form.addEventListener('change', this.refresh);
    this.render();
  }

  async select() {
    const file = this.fileTarget.files?.[0];
    if (!file) return;
    this.request?.abort();
    const serial = ++this.serial;
    if (!['image/jpeg','image/png','image/webp'].includes(file.type) || file.size > 10485760) {
      this.element.dataset.planReferenceBusy = 'false';
      this.release(); this.render();
      this.fileTarget.value = '';
      this.statusTarget.textContent = 'Choisis un JPG, PNG ou WebP de 10 Mo maximum.';
      return;
    }
    this.release();
    this.localUrl = URL.createObjectURL(file);
    this.element.dataset.planReferenceBusy = 'true';
    this.render();
    this.statusTarget.textContent = 'Enregistrement privé de cette image…';
    const request = this.request = new AbortController();
    const timeout = setTimeout(() => request.abort(), 25000);
    const body = new FormData();
    body.set('ai_reference[_token]', this.tokenValue);
    body.set('ai_reference[image]', file);
    try {
      const response = await fetch(this.uploadValue, { method:'POST',body,credentials:'same-origin',headers:{Accept:'application/json'},redirect:'error',signal:request.signal });
      const data = await response.json();
      if (!this.active || serial !== this.serial) return;
      if (!response.ok || !/^[a-f0-9]{48}\.jpg$/.test(data.reference)) throw new Error(data.error || 'L’image n’a pas pu être enregistrée.');
      const url = new URL(data.url, location.href);
      if (url.origin !== location.origin || !url.pathname.endsWith('/'+data.reference)) throw new Error('Aperçu indisponible.');
      this.valueTarget.value = data.reference;
      this.imageValue = url.href;
      this.release();
      this.render();
      this.changed();
    } catch (error) {
      if (this.active && serial === this.serial) {
        this.release(); this.render();
        this.statusTarget.textContent = error.name === 'AbortError' ? 'L’import a expiré. Sélectionne à nouveau l’image pour réessayer.' : error.message;
      }
    } finally {
      clearTimeout(timeout);
      if (this.active && serial === this.serial) this.element.dataset.planReferenceBusy = 'false';
    }
  }

  remove() {
    this.request?.abort(); this.serial++;
    this.element.dataset.planReferenceBusy = 'false';
    this.release(); this.fileTarget.value = ''; this.valueTarget.value = ''; this.imageValue = '';
    this.render(); this.changed();
  }

  fit() {
    const input = this.element.closest('[data-shot-editor-row]')?.querySelector('[name$="[referenceFit]"]');
    if (input) input.value = this.fitTarget.value;
    this.changed(); this.render();
  }

  render() {
    const name = this.valueTarget.value;
    if (/^[a-f0-9]{48}\.jpg$/.test(name) && (!this.imageValue || this.renderName !== name) && !this.localUrl) {
      const url = new URL(this.uploadValue, location.href);
      url.search = ''; url.pathname += '/'+name;
      this.imageValue = url.href;
      this.renderName = name;
    }
    const select = this.form.querySelector('[data-creation-target="model"], [data-series-settings-target="model"]');
    const option = select?.selectedOptions[0];
    const identity = this.kindValue === 'subject' || this.kindValue === 'view';
    const allowed = !select || (this.kindValue === 'view' ? select.value === 'kling-3-image' : this.kindValue === 'subject' ? Number(option?.dataset.subjectLimit) > 0 : option?.dataset.referenceMode === 'image' && (!this.endValue || option?.dataset.acceptsEnd === 'true'));
    const src = this.localUrl || (this.valueTarget.value ? this.imageValue : '');
    this.element.hidden = !allowed && !src;
    this.fileTarget.disabled = !allowed;
    this.removeTarget.hidden = !src || this.allowRemoveValue === false;
    this.frameTarget.hidden = !src;
    if (src && this.previewTarget.getAttribute('src') !== src) this.previewTarget.src = src;
    if (!src) this.previewTarget.removeAttribute('src');
    const ratio = identity ? 'auto' : this.form.querySelector('[data-creation-target="ratio"], [data-series-settings-target="ratio"]')?.value || this.ratioValue || '9:16';
    const fit = this.element.closest('[data-shot-editor-row]')?.querySelector('[name$="[referenceFit]"]')?.value || 'crop';
    const native = `${this.previewTarget.naturalWidth || 1} / ${this.previewTarget.naturalHeight || 1}`;
    this.frameTarget.style.aspectRatio = ratio === 'auto' ? native : ratio.replace(':',' / ');
    const [a,b] = this.frameTarget.style.aspectRatio.split('/').map(Number);
    this.frameTarget.style.maxWidth = `${Math.min(280, 330*a/b)}px`;
    this.previewTarget.style.objectFit = identity || fit === 'contain' ? 'contain' : 'cover';
    if (this.hasFitTarget) { this.fitTarget.hidden = identity; this.fitTarget.value = fit; }
    this.statusTarget.textContent = !allowed && src ? 'Cette référence ne convient pas au modèle choisi : retire-la ou choisis un modèle compatible.' : src ? (identity ? 'Référence d’identité : image entière.' : `${ratio} · ${fit === 'contain' ? 'image entière avec bandes si nécessaire' : 'recadrage centré'} · ce plan uniquement.`) : 'Aucune image affectée ici.';
  }

  changed() { this.valueTarget.dispatchEvent(new Event('change',{bubbles:true})); }
  release() { if (this.localUrl) URL.revokeObjectURL(this.localUrl); this.localUrl = null; }
  disconnect() {
    this.active = false; this.serial++; this.request?.abort(); this.release();
    this.element.dataset.planReferenceBusy = 'false';
    this.form.removeEventListener('change',this.refresh);
  }
}
