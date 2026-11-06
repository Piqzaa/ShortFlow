import { Controller } from '@hotwired/stimulus';
import { selectedCharacters, setCharacters } from '../character-choices.js';

export default class extends Controller {
  static values = { models: Object, url: String, mode: String };

  connect() {
    this.workspace = this.element.closest('[data-controller~="creation"]') || this.element;
    this.onChange = event => {
      if (event.target.matches?.('input[name$="[characterSelection][]"]') && !event.target.checked) {
        const id = event.target.value.split(':')[0];
        for (const group of this.sceneGroups()) setCharacters(group, selectedCharacters(group).map(input => input.value).filter(value => value !== id));
      }
      queueMicrotask(() => this.update());
    };
    this.workspace.addEventListener('change', this.onChange);
    this.onFocus = () => { if (this.returning) { this.returning = false; this.refresh(); } };
    window.addEventListener('focus', this.onFocus);
    this.observer = new MutationObserver(() => this.update());
    this.observer.observe(this.element, {childList: true, subtree: true});
    this.update();
  }

  disconnect() {
    this.workspace.removeEventListener('change', this.onChange);
    window.removeEventListener('focus', this.onFocus);
    this.observer.disconnect();
    this.abort?.abort();
  }

  sceneGroups() { return [...this.element.querySelectorAll('[data-character-field="characterIds"]')]; }
  get pool() { return this.element.querySelector('[data-character-pool]'); }
  get notice() { return this.element.querySelector('[data-character-notice]'); }
  get model() { return this.modelsValue[this.element.querySelector('[name$="[model]"]')?.value]; }

  update() {
    const source = this.workspace.querySelector('[name$="[source]"]')?.value || this.modeValue;
    const origin = this.workspace.querySelector('[name$="[referenceOrigin]"]');
    const supported = source === 'subject' && (!origin || origin.value === 'library');
    const chosen = selectedCharacters(this.element, 'characterSelection');
    const ids = new Set(chosen.map(input => input.value.split(':')[0]));
    if (this.pool) this.pool.hidden = !supported;
    if (this.notice) this.notice.hidden = source === 'subject' || !chosen.length;
    if (this.notice && !this.notice.hidden) { const text = `${chosen.length} personnage(s) conservé(s). Choisis « Garder un personnage de référence » pour les utiliser ici, ou retire cette sélection.`; const span = this.notice.querySelector('span'); if (span.textContent !== text) span.textContent = text; }
    for (const group of this.sceneGroups()) {
      group.parentElement.hidden = !supported || !group.querySelector('input[type="checkbox"]');
      for (const input of group.querySelectorAll('input[type="checkbox"]')) {
        input.closest('label').hidden = Boolean(this.pool && !ids.has(input.value) && !input.checked);
      }
    }
    for (const group of this.element.querySelectorAll('[data-character-field]')) {
      const field = group.dataset.characterField;
      const count = selectedCharacters(group, field).length;
      const summary = `${count} sélectionné(s)`;
      if (group.querySelector('[data-character-summary]').textContent !== summary) group.querySelector('[data-character-summary]').textContent = summary;
      const clear = group.querySelector('[data-character-clear]');
      clear.hidden = false; clear.disabled = count === 0;
    }
    const refresh = this.element.querySelector('[data-character-refresh]'); if (refresh) refresh.hidden = false;
    // Keep older isolated imports recoverable; new subject preparations use saved fiches.
    const legacy = Boolean(this.element.querySelector('[name$="[reference]"]')?.value || this.element.querySelector('[name$="[image]"]')?.files?.length || this.element.querySelector('[data-series-reference-original-value]:not([data-series-reference-original-value=""])') || [...this.element.querySelectorAll('[name*="[subjects]"]')].some(input => input.value));
    const panel = this.workspace.querySelector('[data-creation-target="referencePanel"], [data-series-settings-target="images"]');
    if (panel && source === 'subject') panel.hidden = panel.dataset.referenceInvalid === 'true' ? false : origin ? origin.value !== 'upload' : !legacy;
    const loose = this.element.querySelector('[data-controller~="subject-list"]');
    if (loose) loose.hidden = (origin ? source !== 'subject' || origin.value !== 'upload' : !legacy) || loose.dataset.subjectListAvailable === 'false';
  }

  clear(event) {
    event.preventDefault();
    const group = event.currentTarget.closest('[data-character-field]');
    if (group?.dataset.characterField === 'characterIds') setCharacters(group, []);
    else {
      setCharacters(this.element, [], 'characterSelection');
      for (const scene of this.sceneGroups()) setCharacters(scene, []);
    }
    this.update(); this.changed();
  }

  changed() { this.element.dispatchEvent(new Event('change', {bubbles: true})); }
  creating() { this.returning = true; }

  async refresh() {
    if (this.pending) return;
    this.pending = true;
    const status = this.element.querySelector('[data-character-refresh-status]');
    this.abort = new AbortController();
    const timeout = setTimeout(() => this.abort.abort(), 10000);
    try {
      const response = await fetch(this.urlValue, {headers: {'Accept': 'application/json'}, signal: this.abort.signal});
      if (!response.ok) throw new Error('refresh');
      const data = await response.json();
      if (!Array.isArray(data.choices)) throw new Error('choices');
      const groups = [this.pool.querySelector('[data-character-field]'), ...this.sceneGroups()];
      const template = this.element.querySelector('[data-shot-editor-target="prototype"]');
      if (template) groups.push(...template.content.querySelectorAll('[data-character-field="characterIds"]'));
      for (const group of groups) {
        for (const choice of data.choices) {
          const value = group.dataset.characterField === 'characterSelection' ? choice.value : choice.value.split(':')[0];
          if ([...group.querySelectorAll('input')].some(input => input.value === value)) continue;
          const label = document.createElement('label'); label.className = 'character-choice';
          const input = document.createElement('input'); input.type = 'checkbox'; input.name = group.dataset.characterName; input.value = value;
          input.id = `${group.id}_${choice.value.replace(/[^a-z0-9_-]/gi, '_')}`;
          const text = document.createElement('span'); text.textContent = choice.label;
          label.append(input, text); group.querySelector('[data-character-options]').append(label);
        }
      }
      if (data.choices.length) this.pool.querySelector('[data-character-empty]')?.remove();
      this.pool.querySelector('[data-action="character-picker#creating"]').textContent = data.choices.length ? 'Ajouter un personnage ↗' : 'Créer mon premier personnage ↗';
      status.textContent = 'Liste actualisée. Coche le personnage à utiliser ; tes scènes sont conservées.';
      this.update();
    } catch {
      status.textContent = 'Impossible d’actualiser la liste. Réessaie ; ta sélection et tes scènes sont conservées.';
    } finally { clearTimeout(timeout); this.pending = false; }
  }
}
