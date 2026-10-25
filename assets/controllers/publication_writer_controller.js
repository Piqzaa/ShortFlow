import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['status'];
  static values = { url: String };

  connect() {
    this.active = true;
    this.dirty = false;
    this.form = this.element.closest('form');
    this.changed = () => { this.dirty = true; };
    this.form?.addEventListener('input', this.changed);
    this.form?.addEventListener('change', this.changed);
    if (this.urlValue) this.poll();
  }

  async poll() {
    if (!this.active) return;
    this.request = new AbortController();
    const timeout = setTimeout(() => this.request?.abort(), 10000);
    try {
      const response = await fetch(this.urlValue, { credentials:'same-origin',headers:{Accept:'application/json'},signal:this.request.signal });
      if (!response.ok) throw new Error();
      const job = await response.json();
      if (!this.active) return;
      if (job.status === 'ready') {
        if (this.dirty) this.statusTarget.textContent = 'La proposition est prête. Enregistre le brouillon pour afficher l’aperçu en conservant tes modifications.';
        else location.reload();
        return;
      }
      if (job.status === 'failed') { this.statusTarget.textContent = job.error || 'La rédaction a échoué. Tes textes restent conservés.'; return; }
      if (job.status === 'stale') { this.statusTarget.textContent = 'Cette proposition ne correspond plus à la version ou aux réseaux. Tes textes restent conservés. Enregistre puis demande une nouvelle proposition.'; return; }
      if (!['queued','editorial'].includes(job.status)) throw new Error();
      this.statusTarget.textContent = job.status === 'queued' ? '✦ Demande enregistrée, en attente du rédacteur local…' : '✦ Le rédacteur local prépare tes textes…';
      this.timer = setTimeout(() => this.poll(), 2500);
    } catch {
      if (this.active) this.statusTarget.textContent = 'Le suivi est indisponible. Tes textes restent conservés. Enregistre puis rouvre le brouillon pour retrouver la demande.';
    } finally { clearTimeout(timeout); }
  }

  disconnect() {
    this.active = false;
    this.request?.abort();
    clearTimeout(this.timer);
    this.form?.removeEventListener('input', this.changed);
    this.form?.removeEventListener('change', this.changed);
  }
}
