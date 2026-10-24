import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['message'];
    static values = { url: String, revision: String };

    connect() {
        this.failures = 0;
        this.stopped = false;
        this.timer = setTimeout(() => this.poll(), 3000);
    }
    async poll() {
        if (this.stopped) return;
        try {
            if (!document.hidden) {
                this.abort = new AbortController();
                this.requestTimer = setTimeout(() => this.abort.abort(), 8000);
                const response = await fetch(this.urlValue, { headers: { Accept: 'application/json' }, cache: 'no-store', signal: this.abort.signal });
                if (!response.ok) throw new Error('state');
                const state = await response.json();
                if (typeof state.revision !== 'string') throw new Error('state');
                if (this.stopped) return;
                if (state.revision !== this.revisionValue) { window.location.reload(); return; }
                this.failures = 0;
            }
        } catch (error) {
            if (this.stopped) return;
            if (++this.failures >= 3) { this.messageTarget.textContent = 'Le suivi automatique est indisponible. Actualise cette page pour retrouver tes résultats.'; return; }
        } finally { clearTimeout(this.requestTimer); }
        if (!this.stopped) this.timer = setTimeout(() => this.poll(), 3000);
    }
    disconnect() {
        this.stopped = true;
        clearTimeout(this.timer);
        clearTimeout(this.requestTimer);
        this.abort?.abort();
    }
}
