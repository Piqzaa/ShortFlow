import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['message'];
    static values = { url: String, revision: String };

    connect() {
        this.failures = 0;
        this.stopped = false;
        this.timer = setTimeout(() => this.poll(), 10000);
    }
    async poll() {
        if (this.stopped) return;
        try {
            if (!document.hidden) {
                this.abort = new AbortController();
                const response = await fetch(this.urlValue, { headers: { Accept: 'application/json' }, cache: 'no-store', signal: this.abort.signal });
                if (!response.ok) throw new Error('state');
                const state = await response.json();
                if (typeof state.revision !== 'string') throw new Error('state');
                if (this.stopped) return;
                if (state.revision !== this.revisionValue) { window.location.reload(); return; }
                this.failures = 0;
            }
        } catch (error) {
            if (error.name === 'AbortError' || this.stopped) return;
            if (++this.failures >= 3) { this.messageTarget.textContent = 'Le suivi automatique est indisponible. Actualise cette page pour retrouver tes résultats.'; return; }
        }
        if (!this.stopped) this.timer = setTimeout(() => this.poll(), 10000);
    }
    disconnect() {
        this.stopped = true;
        clearTimeout(this.timer);
        this.abort?.abort();
    }
}
