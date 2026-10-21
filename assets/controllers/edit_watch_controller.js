import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static values = { url: String };
  static targets = ['status'];
  connect() {
    this.stopped = false; this.errors = 0;
    this.visibility = () => { if (!document.hidden) this.poll(); else this.abort?.abort(); };
    document.addEventListener('visibilitychange', this.visibility);
    this.timer = setTimeout(() => this.poll(), 4000);
  }
  disconnect() { this.stopped = true; clearTimeout(this.timer); this.abort?.abort(); document.removeEventListener('visibilitychange', this.visibility); }
  async poll() {
    clearTimeout(this.timer);
    if (document.hidden || this.stopped || this.busy) return;
    this.busy = true;
    this.abort = new AbortController();
    const timeout = setTimeout(() => this.abort.abort(), 8000);
    try {
      const response = await fetch(this.urlValue, {headers:{Accept:'application/json'}, cache:'no-store', credentials:'same-origin', signal:this.abort.signal});
      if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) throw new Error('state');
      const state = await response.json();
      if (!['queued','rendering','transcribing','draft','ready','failed'].includes(state.status)) throw new Error('state');
      if (this.stopped) return;
      if (['draft','ready','failed'].includes(state.status)) { window.location.reload(); return; }
      if (this.statusTarget.textContent !== state.label) this.statusTarget.textContent = state.label;
      this.errors = 0;
    } catch {
      if (!this.stopped && !document.hidden && ++this.errors >= 3) { this.statusTarget.textContent = 'Suivi indisponible. Actualise la page pour vérifier l’export.'; this.stopped = true; }
    } finally {
      clearTimeout(timeout); this.busy = false;
      if (!this.stopped && !document.hidden) this.timer = setTimeout(() => this.poll(), 4000);
    }
  }
}
