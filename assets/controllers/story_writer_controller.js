import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['tone', 'generate', 'status', 'preview', 'title', 'summary', 'scenes', 'loading', 'progress', 'elapsed', 'retry', 'warnings'];
  static values = { url: String, latest: String, token: String, episode: Number };

  get form() { return this.element.querySelector('#ai-idea-form'); }
  get editor() { return this.application.getControllerForElementAndIdentifier(this.form, 'shot-editor'); }
  payload() {
    const field = name => this.form.querySelector(`[name="ai_idea[${name}]"]`).value;
    const parts = this.editor.rows().map(row => Number(row.querySelector('select').value));
    const layout = parts.length && parts.reduce((sum, seconds) => sum + seconds, 0) === Number(field('target')) && parts.every(seconds => this.editor.modelValue.seconds?.includes(seconds)) ? parts.join('+') : '';
    return { idea: field('prompt'), model: field('model'), target: field('target'), layout, continuity: field('continuity'), tone: this.toneTarget.value, episode: String(this.episodeValue) };
  }
  fingerprint() {
    return JSON.stringify({ ...this.payload(), title: this.form.querySelector('[name="ai_idea[title]"]').value, framing: ['reference', 'endReference', 'source', 'ratio', 'imageFit', 'useEnd'].map(name => { const input = this.element.querySelector(`[name="ai_idea[${name}]"]`); return input?.type === 'checkbox' ? input.checked : input?.value; }), shots: this.editor.rows().map(row => [row.querySelector('textarea').value, row.querySelector('select').value]) });
  }
  connect() { this.active = true; this.restore(); }

  busy(value) {
    this.pending = value;
    this.generateTarget.disabled = value;
    this.generateTarget.textContent = value ? '✨ Rédaction en cours…' : '✨ Proposer mon scénario';
    if (this.hasLoadingTarget) this.loadingTarget.hidden = !value;
    if (this.hasRetryTarget) this.retryTarget.hidden = true;
    clearInterval(this.elapsedTimer);
    if (value && this.hasElapsedTarget) {
      this.startedAt ||= Date.now();
      const tick = () => { this.elapsedTarget.textContent = `${Math.floor((Date.now() - this.startedAt) / 1000)} s écoulées · tu peux changer de page et revenir.`; };
      tick(); this.elapsedTimer = setInterval(tick, 1000);
    }
  }

  async request(url, options = {}) {
    const abort = new AbortController();
    this.abort = abort;
    const timeout = setTimeout(() => abort.abort(), 10000);
    try {
      const response = await fetch(url, { credentials: 'same-origin', ...options, headers: { Accept: 'application/json', ...options.headers }, signal: abort.signal });
      let data;
      try { data = await response.json(); }
      catch { throw new Error('La connexion ou la session a expiré. Recharge la page pour retrouver ta demande.'); }
      if (!response.ok) throw new Error(data?.error || 'La proposition est indisponible. Réessaie dans un instant.');
      return data;
    } finally { clearTimeout(timeout); }
  }

  async restore() {
    if (!this.latestValue || this.pending) return;
    try {
      const job = await this.request(this.latestValue);
      if (!this.active || this.pending || !job || job.status === 'none') return;
      if (['ready', 'failed'].includes(job.status) && job.fingerprint !== this.fingerprint()) return;
      this.requestFingerprint = job.fingerprint;
      await this.watch(job);
    } catch (error) { if (this.active) this.failed(error); }
  }

  async propose() {
    if (this.pending) return;
    this.active = true;
    try {
      const payload = this.payload();
      if (!payload.idea.trim() || !payload.model || !payload.target) {
        this.statusTarget.textContent = 'Ajoute une idée, une durée et un modèle vidéo pour proposer des scènes compatibles.';
        return;
      }
      this.startedAt = Date.now();
      this.busy(true);
      this.statusTarget.textContent = 'Demande enregistrée…';
      this.previewTarget.hidden = true;
      this.proposal = null;
      this.requestFingerprint = this.fingerprint();
      const job = await this.request(this.urlValue, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' }, body: new URLSearchParams({ ...payload, fingerprint: this.requestFingerprint, _token: this.tokenValue }) });
      if (!this.active) return;
      // Keep previews returned by an already-open older page compatible.
      if (job.shots) { this.busy(false); this.render(job); return; }
      this.requestFingerprint = job.fingerprint;
      await this.watch(job);
    } catch (error) { if (this.active) this.failed(error); }
  }

  async watch(job) {
    if (!this.active) return;
    clearTimeout(this.pollTimer);
    if (job.status === 'failed') throw new Error(job.error);
    if (job.status === 'ready') {
      this.busy(false);
      this.render(job.result);
      return;
    }
    if (job.createdAt) this.startedAt = Date.parse(job.createdAt);
    this.busy(true);
    const phases = {queued: [1, 'En attente du rédacteur…'], story: [2, 'L’IA invente l’histoire…'], shots: [3, 'L’IA prépare les prompts des clips…']};
    const phase = phases[job.status] || phases.queued;
    this.statusTarget.textContent = phase[1];
    if (this.hasProgressTarget) this.progressTarget.value = phase[0];
    this.pollTimer = setTimeout(async () => {
      try { await this.watch(await this.request(job.url)); }
      catch (error) { if (this.active) this.failed(error); }
    }, 1500);
  }

  failed(error) {
    this.busy(false);
    this.statusTarget.textContent = error.name === 'AbortError' ? 'La connexion a pris trop de temps. La rédaction peut continuer en arrière-plan ; retrouve son état ou reviens sur cette page.' : error.name === 'TypeError' ? 'La préparation n’a pas pu être lue ou transmise. Recharge la page puis réessaie ; vérifie aussi ta connexion.' : error.message;
    if (this.hasRetryTarget) this.retryTarget.hidden = false;
  }

  render(data) {
    const stale = this.requestFingerprint !== this.fingerprint();
    if (stale) {
      this.proposal = null;
      this.statusTarget.textContent = 'Ta préparation a changé pendant ou après la rédaction. Demande une nouvelle proposition pour tes réglages actuels.';
      return;
    }
    this.proposal = data;
    this.titleTarget.textContent = data.title;
    this.summaryTarget.textContent = data.summary;
    const warnings = Array.isArray(data.warnings) ? data.warnings : [];
    if (this.hasWarningsTarget) {
      this.warningsTarget.replaceChildren();
      warnings.forEach(warning => {
        const item = document.createElement('li');
        item.textContent = warning;
        this.warningsTarget.append(item);
      });
      this.warningsTarget.hidden = warnings.length === 0;
    }
    this.scenesTarget.replaceChildren();
    data.shots.forEach((shot, index) => {
      const scene = document.createElement('li');
      const heading = document.createElement('strong');
      heading.textContent = `Scène ${index + 1} · ${shot.seconds} s`;
      const description = document.createElement('p');
      description.textContent = shot.description;
      const technical = document.createElement('details');
      const label = document.createElement('summary');
      label.textContent = 'Voir le prompt vidéo';
      const prompt = document.createElement('p');
      prompt.textContent = shot.prompt;
      technical.append(label, prompt);
      scene.append(heading, description, technical);
      this.scenesTarget.append(scene);
    });
    this.previewTarget.hidden = false;
    this.statusTarget.textContent = warnings.length ? 'Proposition à vérifier : des points demandent ta relecture avant d’utiliser ces scènes.' : 'Proposition prête. Relis-la puis utilise ces scènes si elles te conviennent. L’absence d’alerte ne garantit pas sa fidélité.';
  }
  apply() {
    if (!this.proposal) return;
    if (this.requestFingerprint !== this.fingerprint()) {
      this.statusTarget.textContent = 'Ta préparation a changé. Demande une nouvelle proposition avant de remplacer tes scènes.';
      return;
    }
    this.editor.listTarget.replaceChildren();
    this.proposal.shots.forEach((shot, index) => {
      this.editor.listTarget.insertAdjacentHTML('beforeend', this.editor.prototypeTarget.innerHTML.replaceAll('__name__', String(index)));
      const row = this.editor.rows()[index];
      row.querySelector('textarea').value = shot.prompt;
      row.querySelector('select').value = String(shot.seconds);
    });
    this.form.querySelector('[name="ai_idea[title]"]').value = this.proposal.title;
    this.editor.update();
    this.form.dispatchEvent(new Event('input', { bubbles: true }));
    const details = this.form.querySelector('[data-story-scenes]');
    if (details) details.open = true;
    this.editor.rows()[0]?.querySelector('textarea').focus();
    this.previewTarget.hidden = true;
    this.proposal = null;
    this.statusTarget.textContent = 'Scènes appliquées. Tu peux les personnaliser puis vérifier le coût au récapitulatif.';
  }
  disconnect() { this.active = false; this.pending = false; this.abort?.abort(); clearTimeout(this.pollTimer); clearInterval(this.elapsedTimer); }
}
