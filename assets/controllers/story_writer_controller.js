import { Controller } from '@hotwired/stimulus';
import { writingProgress } from '../writing-progress.js';

export default class extends Controller {
  static targets = ['tone', 'generate', 'status', 'preview', 'title', 'summary', 'scenes', 'loading', 'progress', 'elapsed', 'retry', 'warnings'];
  static values = { url: String, latest: String, token: String, episode: Number, enabled: {type: Boolean, default: true} };

  get form() { return this.element.querySelector('#ai-idea-form'); }
  get editor() { return this.application.getControllerForElementAndIdentifier(this.form, 'shot-editor'); }
  payload() {
    const field = name => this.form.querySelector(`[name="ai_idea[${name}]"]`).value;
    const parts = this.editor.rows().map(row => Number(row.querySelector('select').value));
    const layout = parts.length && parts.reduce((sum, seconds) => sum + seconds, 0) === Number(field('target')) && parts.every(seconds => this.editor.modelValue.seconds?.includes(seconds)) ? parts.join('+') : '';
    const subjectHints = [...this.form.querySelectorAll('[data-subject-profile]')]
      .filter(profile => !profile.querySelector('[data-subject-profile-reference]')?.disabled)
      .map(profile => ({ name: profile.querySelector('[data-subject-name]')?.value.trim() || '', role: profile.querySelector('[name$="[role]"]')?.value || 'character', presence: profile.querySelector('[name$="[presence]"]')?.value || 'unspecified' }))
      .filter(subject => subject.name);
    return { idea: field('prompt'), model: field('model'), target: field('target'), layout, planCount: field('continuity') === 'single' ? '1' : this.form.querySelector('[name="ai_idea[planCount]"]')?.value || '', continuity: field('continuity'), tone: this.toneTarget.value, episode: String(this.episodeValue), subjectHints: JSON.stringify(subjectHints) };
  }
  fingerprint() {
    return JSON.stringify({
      ...this.payload(), title: this.form.querySelector('[name="ai_idea[title]"]').value,
      framing: ['reference', 'endReference', 'source', 'ratio', 'imageFit', 'useEnd'].map(name => {
        const input = this.element.querySelector(`[name="ai_idea[${name}]"]`);
        return input?.type === 'checkbox' ? input.checked : input?.value;
      }),
      subjects: [...this.form.querySelectorAll('[name^="ai_idea[subjects]"]')].map(input => input.value),
      subjectDetails: [...this.form.querySelectorAll('[name^="ai_idea[subjectDetails]"]')].filter(input => !input.disabled).map(input => [input.name, input.value]),
      shots: this.editor.rows().map(row => [
        row.querySelector('textarea').value, row.querySelector('select').value,
        ...['startReference', 'endReference', 'referenceFit', 'sceneDescription', 'translatedIdentity', 'translationSource', 'promptOnly'].map(name => row.querySelector(`[name$="[${name}]"]`)?.value || ''),
      ]),
    });
  }
  connect() {
    this.active = true;
    this.restoreChanged = false;
    this.connectedForm = this.form;
    this.changed = event => { if (event.isTrusted) this.restoreChanged = true; };
    this.connectedForm?.addEventListener('input', this.changed);
    this.connectedForm?.addEventListener('change', this.changed);
    this.restore();
  }

  busy(value) {
    this.pending = value;
    this.generateTarget.disabled = value || this.enabledValue === false;
    this.generateTarget.textContent = value ? 'Envoi de la demande…' : '✨ Proposer mon scénario';
    if (this.hasLoadingTarget) this.loadingTarget.hidden = !value;
    if (this.hasRetryTarget) this.retryTarget.hidden = true;
    clearInterval(this.elapsedTimer);
    if (this.hasElapsedTarget) this.elapsedTarget.textContent = '';
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
      if (!response.ok) {
        const error = new Error(data?.error || 'La proposition est indisponible. Réessaie dans un instant.');
        error.status = response.status;
        throw error;
      }
      return data;
    } finally { clearTimeout(timeout); }
  }

  async restore(event) {
    if (!this.latestValue || this.pending) return;
    try {
      const job = await this.request(this.latestValue);
      if (!this.active || this.pending || !job) return;
      if (job.status === 'none') {
        this.busy(false);
        if (event) this.statusTarget.textContent = this.enabledValue === false ? 'Aucune demande à retrouver. Tu peux écrire tes scènes directement.' : 'Aucune demande à retrouver pour cette préparation. Tu peux proposer un scénario.';
        return;
      }
      this.recoverTone(job);
      if (['ready', 'failed'].includes(job.status) && job.fingerprint !== this.fingerprint()) return;
      this.requestFingerprint = job.fingerprint;
      await this.watch(job);
    } catch (error) { if (this.active) this.failed(error); }
  }

  recoverTone(job) {
    if (!this.hasToneTarget || this.restoreChanged) return;
    try {
      const saved = JSON.parse(job.fingerprint), current = JSON.parse(this.fingerprint());
      if (!saved || !current || Array.isArray(saved) || Array.isArray(current)) return;
      const tone = saved.tone;
      if (!['auto', 'humour', 'aventure', 'calme'].includes(tone)) return;
      saved.tone = current.tone;
      if (JSON.stringify(saved) === JSON.stringify(current)) this.toneTarget.value = tone;
    } catch { /* Older opaque fingerprints cannot restore a display-only preference. */ }
  }

  async propose() {
    if (this.enabledValue === false) { this.statusTarget.textContent = 'La proposition automatique n’est pas activée sur ce poste. Tu peux écrire tes scènes directement.'; return; }
    if (this.pending) return;
    this.active = true;
    try {
      const preparation = this.application?.getControllerForElementAndIdentifier(this.element, 'preparation');
      if (preparation?.prepareStory && !preparation.prepareStory()) {
        this.statusTarget.textContent = 'Vérifie le nombre de plans et leur répartition près du mode de tournage avant de proposer le scénario.';
        return;
      }
      const payload = this.payload();
      if (!payload.idea.trim() || !payload.model || !payload.target) {
        this.statusTarget.textContent = 'Ajoute une idée, une durée et un modèle vidéo pour proposer des scènes compatibles.';
        return;
      }
      this.busy(true);
      this.statusTarget.textContent = 'Envoi de la demande…';
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
    if (job.status === 'failed') {
      this.busy(false);
      this.proposal = null;
      this.previewTarget.hidden = true;
      this.statusTarget.textContent = job.error || 'La rédaction s’est arrêtée. Tes scènes sont conservées ; tu peux demander une nouvelle proposition.';
      return;
    }
    if (job.status === 'ready') {
      this.busy(false);
      this.render(job.result);
      return;
    }
    const phase = writingProgress(job);
    if (!phase) throw new Error('L’état de la demande n’a pas pu être lu. Retrouve son suivi avant de réessayer.');
    this.busy(true);
    this.generateTarget.textContent = phase.button;
    if (this.statusTarget.textContent !== phase.label) this.statusTarget.textContent = phase.label;
    if (this.hasProgressTarget) this.progressTarget.textContent = phase.stepLabel;
    if (this.hasElapsedTarget) {
      const receivedAt = Date.now();
      const tick = () => { this.elapsedTarget.textContent = writingProgress(job, (Date.now() - receivedAt) / 1000).detail; };
      tick(); this.elapsedTimer = setInterval(tick, 1000);
    }
    this.pollTimer = setTimeout(async () => {
      try { await this.watch(await this.request(job.url)); }
      catch (error) { if (this.active) this.failed(error); }
    }, 1500);
  }

  failed(error) {
    this.busy(false);
    this.statusTarget.textContent = error.name === 'AbortError' ? 'La connexion a pris trop de temps. La rédaction peut continuer en arrière-plan ; retrouve son état ou reviens sur cette page.' : error.name === 'TypeError' ? 'La préparation n’a pas pu être lue ou transmise. Recharge la page puis réessaie ; vérifie aussi ta connexion.' : error.message;
    if (this.hasRetryTarget) this.retryTarget.hidden = Boolean(error.status && error.status < 500);
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
    this.statusTarget.textContent = data.translationReady === false ? 'Traduction à corriger. Le scénario français est conservé. Tu peux appliquer ce brouillon pour corriger les prompts ; la génération vidéo reste bloquée jusqu’à correction des points signalés.' : warnings.length ? 'Proposition à relire. Tu peux copier ces scènes dans ton brouillon malgré les alertes ; le récapitulatif contrôlera les consignes avant génération.' : 'Proposition prête. Relis-la puis utilise ces scènes si elles te conviennent. L’absence d’alerte ne garantit pas sa fidélité.';
  }
  apply() {
    if (!this.proposal) return;
    const appliedCount = this.proposal.shots.length;
    const correctionNotice = this.proposal.translationReady === false ? 'Brouillon appliqué. Corrige les points signalés avant de demander le devis. Alertes de la proposition initiale : '+(this.proposal.warnings || []).join(' ') : null;
    if (this.requestFingerprint !== this.fingerprint()) {
      this.statusTarget.textContent = 'Ta préparation a changé. Demande une nouvelle proposition avant de remplacer tes scènes.';
      return;
    }
    if (this.form.querySelector('[data-plan-reference-busy="true"]')) {
      this.statusTarget.textContent = 'Attends la fin de l’import des images avant d’utiliser ces scènes. Ta proposition reste disponible.';
      return;
    }
    const inputs = this.editor.rows().map(row => Object.fromEntries(['startReference','endReference','referenceFit','imagePrompt'].map(name=>[name,row.querySelector(`[name$="[${name}]"]`)?.value || ''])));
    if (inputs.length !== this.proposal.shots.length && inputs.some(row=>row.startReference || row.endReference || row.imagePrompt?.trim())) {
      this.statusTarget.textContent = 'La proposition change le nombre de plans. Déplace ou retire leurs cadres et consignes d’images avant de l’appliquer ; tes textes restent conservés.';
      return;
    }
    this.editor.listTarget.replaceChildren();
    this.proposal.shots.forEach((shot, index) => {
      this.editor.listTarget.insertAdjacentHTML('beforeend', this.editor.prototypeTarget.innerHTML.replaceAll('__name__', String(index)));
      const row = this.editor.rows()[index];
      row.querySelector('textarea').value = shot.prompt;
      const promptOnly = row.querySelector('[name$="[promptOnly]"]');
      if (promptOnly) promptOnly.value = '1';
      const description = row.querySelector('[name$="[sceneDescription]"]');
      if (description) description.value = shot.description || '';
      for (const name of ['translatedIdentity', 'translationSource']) {
        const field = row.querySelector(`[name$="[${name}]"]`);
        if (field) field.value = shot[name] || '';
      }
      row.querySelector('select').value = String(shot.seconds);
      for (const [name,value] of Object.entries(inputs[index] || {})) { const input=row.querySelector(`[name$="[${name}]"]`); if (input) input.value=value; }
    });
    this.form.querySelector('[name="ai_idea[title]"]').value = this.proposal.title;
    this.editor.update();
    this.form.dispatchEvent(new Event('input', { bubbles: true }));
    const details = this.form.querySelector('[data-story-scenes]');
    if (details) details.open = true;
    const firstPrompt = this.editor.rows()[0]?.querySelector('textarea');
    if (firstPrompt) {
      const panel = firstPrompt.closest?.('[data-shot-details]');
      if (panel?.tagName === 'DETAILS') panel.open = true;
      firstPrompt.focus();
    }
    this.editor.rows()[0]?.scrollIntoView?.({ block: 'start' });
    this.previewTarget.hidden = true;
    this.proposal = null;
    this.statusTarget.textContent = correctionNotice || `${appliedCount} scène(s) copiée(s) dans ton brouillon ci-dessous. Tu peux les modifier puis ouvrir le récapitulatif. Aucune vidéo n’a été lancée.`;
  }
  disconnect() {
    this.active = false; this.pending = false;
    this.abort?.abort(); clearTimeout(this.pollTimer); clearInterval(this.elapsedTimer);
    this.connectedForm?.removeEventListener('input', this.changed);
    this.connectedForm?.removeEventListener('change', this.changed);
  }
}
