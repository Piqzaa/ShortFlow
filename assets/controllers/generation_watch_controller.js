import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['message'];
  static values = {url:String, status:String, revision:String};

  connect() {
    this.failures = 0;
    this.delay = 6000;
    this.disposed = false;
    this.visibility = () => {
      clearTimeout(this.timer);
      if (document.hidden) this.request?.abort();
      else this.schedule();
    };
    document.addEventListener('visibilitychange', this.visibility);
    this.schedule();
  }

  schedule() {
    if (this.disposed || document.hidden || this.failures >= 3 || !['queued','submitting','processing'].includes(this.statusValue)) return;
    this.timer = setTimeout(() => this.poll(), this.delay);
  }

  async poll() {
    if (this.disposed || document.hidden || this.request) return;
    const request = this.request = new AbortController();
    const timeout = setTimeout(() => request.abort('timeout'), 10000);
    try {
      const response = await fetch(this.urlValue, {method:'GET', headers:{Accept:'application/json'}, credentials:'same-origin', cache:'no-store', redirect:'error', signal:request.signal});
      if (!response.ok) throw new Error('status');
      const state = await response.json();
      if (!['prepared','queued','submitting','processing','waiting_key','review','available'].includes(state.status) || typeof state.updatedAt !== 'string') throw new Error('status');
      if (this.disposed || document.hidden) return;
      if (state.status !== this.statusValue || state.updatedAt !== this.revisionValue) {
        window.location.reload();
        return;
      }
      this.failures = 0;
      this.delay = Math.min(20000, this.delay + 2000);
      this.messageTarget.textContent = 'Suivi automatique actif. Tu peux quitter cette page.';
    } catch {
      if (request.signal.aborted && request.signal.reason !== 'timeout') return;
      this.failures++;
      this.delay = 20000;
      this.messageTarget.textContent = this.failures >= 3 ? 'Le suivi automatique est indisponible. Utilise « Actualiser le suivi » ; la génération peut continuer en arrière-plan.' : 'Suivi momentanément indisponible. Nouvelle vérification automatique dans quelques instants.';
    } finally {
      clearTimeout(timeout);
      this.request = null;
      this.schedule();
    }
  }

  disconnect() {
    this.disposed = true;
    clearTimeout(this.timer);
    this.request?.abort();
    document.removeEventListener('visibilitychange', this.visibility);
  }
}
