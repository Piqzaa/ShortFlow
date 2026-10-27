import { Controller } from '@hotwired/stimulus';
import { writingProgress } from '../writing-progress.js';

export default class extends Controller {
  static targets = ['status', 'loading', 'progress', 'elapsed', 'retry', 'generate'];
  static values = { url: String, available: Boolean };

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
    if (!this.active || this.polling) return;
    this.polling = true;
    clearTimeout(this.timer);
    this.request = new AbortController();
    const timeout = setTimeout(() => this.request?.abort(), 10000);
    try {
      const response = await fetch(this.urlValue, { credentials:'same-origin',headers:{Accept:'application/json'},signal:this.request.signal });
      if (!response.ok) throw new Error();
      const job = await response.json();
      if (!this.active) return;
      if (job.status === 'ready') {
        this.finished();
        if (this.dirty) this.statusTarget.textContent = 'La proposition est prête. Enregistre le brouillon pour afficher l’aperçu en conservant tes modifications.';
        else location.reload();
        return;
      }
      if (job.status === 'failed') { this.finished(); this.statusTarget.textContent = job.error || 'La rédaction a échoué. Tes textes restent conservés ; tu peux demander une nouvelle proposition.'; return; }
      if (job.status === 'stale') { this.finished(); this.statusTarget.textContent = 'Cette proposition ne correspond plus à la version ou aux réseaux. Tes textes restent conservés. Enregistre puis demande une nouvelle proposition.'; return; }
      if (!['queued','editorial'].includes(job.status)) throw new Error();
      const phase = writingProgress({ ...job, kind: 'editorial' });
      if (this.statusTarget.textContent !== phase.label) this.statusTarget.textContent = phase.label;
      if (this.hasGenerateTarget) this.generateTarget.disabled = true;
      if (this.hasLoadingTarget) this.loadingTarget.hidden = false;
      if (this.hasRetryTarget) this.retryTarget.hidden = true;
      if (this.hasProgressTarget) this.progressTarget.textContent = phase.stepLabel;
      clearInterval(this.elapsedTimer);
      if (this.hasElapsedTarget) {
        const receivedAt = Date.now();
        const tick = () => { this.elapsedTarget.textContent = writingProgress({ ...job, kind: 'editorial' }, (Date.now() - receivedAt) / 1000).detail; };
        tick(); this.elapsedTimer = setInterval(tick, 1000);
      }
      this.timer = setTimeout(() => this.poll(), 2500);
    } catch {
      if (this.active) {
        this.stopProgress();
        this.statusTarget.textContent = 'Le suivi est momentanément indisponible. La rédaction peut continuer ; tes textes restent conservés. Reprends le suivi ou enregistre puis rouvre le brouillon.';
        if (this.hasRetryTarget) this.retryTarget.hidden = false;
      }
    } finally { clearTimeout(timeout); this.polling = false; }
  }

  stopProgress() {
    clearInterval(this.elapsedTimer);
    if (this.hasLoadingTarget) this.loadingTarget.hidden = true;
  }

  finished() {
    this.stopProgress();
    if (this.hasRetryTarget) this.retryTarget.hidden = true;
    if (this.hasGenerateTarget) this.generateTarget.disabled = !this.availableValue;
  }

  disconnect() {
    this.active = false;
    this.request?.abort();
    clearTimeout(this.timer);
    this.stopProgress();
    this.form?.removeEventListener('input', this.changed);
    this.form?.removeEventListener('change', this.changed);
  }
}
