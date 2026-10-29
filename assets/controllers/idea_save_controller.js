import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['status', 'retry'];
  static values = { url: String, revision: String };

  connect() {
    this.editor = crypto.randomUUID().replaceAll('-', '');
    this.sequence = 0;
    this.saved = this.snapshot();
    this.latest = this.saved;
    this.stopped = false;
    this.statusTarget.hidden = true;
  }

  snapshot() {
    const body = new FormData(this.element);
    // Search and display-only selectors have no name and never enter the saved idea.
    return new URLSearchParams([...body].filter(([key]) => key.startsWith('ai_idea['))).toString();
  }

  change(event) {
    if (this.stopped || this.snapshot() === this.saved && this.latest === this.saved && !this.failed) return;
    clearTimeout(this.timer);
    this.statusTarget.textContent = 'Enregistrement…';
    this.statusTarget.hidden = false;
    this.timer = setTimeout(() => this.flush(), event?.type === 'change' ? 0 : 500);
  }

  async flush() {
    clearTimeout(this.timer);
    if (this.stopped) return;
    const snapshot = this.snapshot();
    if (snapshot === this.saved && this.latest === this.saved && !this.failed) return;
    this.latest = snapshot;
    const sequence = ++this.sequence;
    const body = new URLSearchParams(snapshot);
    body.set('editor', this.editor);
    body.set('sequence', String(sequence));
    body.set('revision', this.revisionValue);
    this.retryTarget.hidden = true;
    try {
      const response = await fetch(this.urlValue, {
        method: 'POST', body, credentials: 'same-origin', redirect: 'error',
        headers: { Accept: 'application/json' }, keepalive: true,
      });
      if (!response.ok || (await response.json()).saved !== true) throw new Error('save');
      if (sequence !== this.sequence || this.stopped) return;
      this.failed = false;
      this.saved = snapshot;
      if (snapshot === this.snapshot()) {
        this.statusTarget.textContent = '';
        this.statusTarget.hidden = true;
      } else {
        this.change();
      }
    } catch {
      if (sequence !== this.sequence || this.stopped) return;
      this.failed = true;
      this.statusTarget.hidden = false;
      this.statusTarget.textContent = 'Enregistrement impossible. Garde cet onglet ouvert et réessaie avant de partir.';
      this.retryTarget.hidden = false;
    }
  }

  submit() {
    this.stopped = true;
    clearTimeout(this.timer);
  }

  disconnect() { clearTimeout(this.timer); }
}
