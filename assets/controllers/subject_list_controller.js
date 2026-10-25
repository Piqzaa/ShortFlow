import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['list', 'prototype', 'add', 'notice', 'detailsList', 'detailsPrototype', 'primary', 'legacy'];
  static values = { namespace: { type: String, default: 'ai_idea' }, primaryReference: String };

  connect() {
    this.form = this.element.closest('form');
    this.refresh = () => this.update();
    this.form.addEventListener('change', this.refresh);
    this.next = this.nextIndex('subjects');
    this.profileNext = this.nextIndex('subjectDetails');
    this.element.classList.add('subject-list-enhanced');
    if (this.hasLegacyTarget) this.legacyTargets.forEach(target => { target.hidden = true; });
    this.update();
  }

  nextIndex(field) {
    const pattern = new RegExp(`\\[${field}\\]\\[(\\d+)\\]`);
    return Math.max(-1, ...Array.from(this.element.querySelectorAll('input[name]')).map(input => Number(input.name.match(pattern)?.[1] ?? -1))) + 1;
  }
  modelOption() { return this.form.querySelector('[data-creation-target="model"], [data-series-settings-target="model"]')?.selectedOptions[0]; }
  rows() { return Array.from(this.listTarget.querySelectorAll('[data-subject-row]')); }
  profiles() { return Array.from(this.element.querySelectorAll('[data-subject-profile]')); }
  reference(row) { return row.querySelector('[data-subject-main] [data-reference-picker-target="value"]')?.value || ''; }

  add() {
    if (this.addTarget.disabled) return;
    this.listTarget.insertAdjacentHTML('beforeend', this.prototypeTarget.innerHTML.replaceAll('__name__', String(this.next++)));
    this.update();
    this.listTarget.lastElementChild?.querySelector('input[type="file"]')?.focus();
    this.changed();
  }
  remove(event) {
    event.currentTarget.closest('[data-subject-row]')?.remove();
    this.update(); this.changed();
    this.addTarget.focus();
  }

  primaryReference() {
    const name = this.namespaceValue;
    if (this.form.querySelector(`[name="${name}[removeImage]"]`)?.checked) return '';
    const file = this.form.querySelector(`[name="${name}[image]"]`);
    const stored = this.form.querySelector(`[name="${name}[reference]"]`)?.value || this.primaryReferenceValue || '';
    return file?.files?.length ? 'primary' : stored;
  }

  defaultName(primary) {
    const used = new Set(this.profiles().map(row => row.querySelector('[data-subject-name]')?.value.trim().toLocaleLowerCase('fr')).filter(Boolean));
    const base = primary ? 'Sujet principal' : 'Sujet';
    let index = primary ? 0 : 1;
    let name;
    do { name = index ? `${base} ${index}` : base; index++; } while (used.has(name.toLocaleLowerCase('fr')));
    return name;
  }

  ensureProfile(reference, destination, primary = false) {
    let profile = this.profiles().find(row => primary ? row.dataset.subjectPrimary === 'true' || [reference, 'primary'].includes(row.querySelector('[data-subject-profile-reference]')?.value) : row.dataset.subjectPrimary !== 'true' && row.querySelector('[data-subject-profile-reference]')?.value === reference);
    if (!profile) {
      this.detailsListTarget.insertAdjacentHTML('beforeend', this.detailsPrototypeTarget.innerHTML.replaceAll('__name__', String(this.profileNext++)));
      profile = this.detailsListTarget.lastElementChild;
    }
    profile.dataset.subjectPrimary = String(primary);
    profile.querySelector('[data-subject-profile-reference]').value = primary ? 'primary' : reference;
    const name = profile.querySelector('[data-subject-name]');
    if (!name.value.trim()) name.value = this.defaultName(primary);
    const heading = profile.querySelector('[data-subject-profile-title]');
    if (heading) heading.textContent = primary ? 'Identité du sujet principal' : 'Nom et rôle de cette référence';
    if (profile.parentElement !== destination) destination.append(profile);
    profile.hidden = false;
    profile.querySelectorAll('input,select,textarea').forEach(input => { input.disabled = false; });
    return profile;
  }

  addView(event) {
    const profile = event.currentTarget.closest('[data-subject-profile]');
    const list = profile.querySelector('[data-subject-views-list]');
    if (list.children.length >= 3) return;
    const template = profile.querySelector('[data-subject-views-prototype]');
    const indices = Array.from(list.querySelectorAll('input[type="hidden"]')).map(input => Number(input.name.match(/\[views\]\[(\d+)\]/)?.[1] ?? -1));
    list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__view__', String(Math.max(-1, ...indices) + 1)));
    this.update(); this.changed();
    list.lastElementChild?.querySelector('input[type="file"]')?.focus();
  }
  removeView(event) {
    const profile = event.currentTarget.closest('[data-subject-profile]');
    event.currentTarget.closest('[data-subject-view]')?.remove();
    this.update(); this.changed();
    profile?.querySelector('[data-subject-add-view]')?.focus();
  }

  update() {
    const model = this.modelOption();
    const identity = model?.dataset.referenceMode === 'subject';
    const limit = Math.max(0, Number(model?.dataset.subjectLimit || 0) - (identity ? 1 : 0));
    const rows = this.rows();
    const primaryReference = identity ? this.primaryReference() : '';
    this.element.hidden = limit === 0 && rows.length === 0 && !identity;
    this.addTarget.disabled = rows.length >= limit;
    this.noticeTarget.textContent = identity
      ? `Identité commune : une image principale et jusqu’à ${limit} autre(s) référence(s). Les images ne fixent pas les cadres des scènes.`
      : limit ? `${rows.length} / ${limit} sujet(s) de référence. L’image de début peut être un décor ; ces références guident séparément l’identité.`
        : 'Ce modèle ne reçoit pas de sujet séparé. Retire les anciennes références ou choisis un modèle compatible.';
    if (rows.length > limit) this.noticeTarget.textContent += ' Retire les références incompatibles avant le devis.';
    if (!this.hasDetailsListTarget || !this.hasDetailsPrototypeTarget) return;
    const active = new Set();
    if (primaryReference && this.hasPrimaryTarget) active.add(this.ensureProfile(primaryReference, this.primaryTarget, true));
    rows.forEach(row => {
      const reference = this.reference(row);
      if (/^[a-f0-9]{48}\.jpg$/.test(reference)) active.add(this.ensureProfile(reference, row));
    });
    this.profiles().forEach(profile => {
      if (!active.has(profile)) {
        if (profile.dataset.subjectPrimary === 'true') {
          this.detailsListTarget.append(profile); profile.hidden = true;
          profile.querySelectorAll('input,select,textarea').forEach(input => { input.disabled = true; });
        } else profile.remove();
        return;
      }
      const views = profile.querySelector('[data-subject-views]');
      const presence = profile.querySelector('[name$="[presence]"]');
      const visible = Array.from(presence?.options || []).find(option => option.value === 'visible');
      if (visible) visible.textContent = identity ? 'Visible dès le début de la scène' : 'Déjà visible dans l’image de début';
      const help = profile.querySelector('[data-subject-presence-help]');
      if (help) help.textContent = identity ? 'Utilise ce nom dans tes scènes. La présence guide leur action ; cette référence d’identité ne fixe aucun premier cadre.' : 'Utilise ce nom dans tes scènes. Si l’image de début montre un décor vide, choisis une entrée pendant l’action. Pour commencer avec le sujet déjà visible, fournis un premier cadre qui le contient.';
      const count = views.querySelectorAll('[data-subject-view]').length;
      const kling = model?.value === 'kling-3-image';
      views.hidden = !kling && count === 0;
      profile.querySelector('[data-subject-add-view]').disabled = !kling || count >= 3;
      profile.querySelector('[data-subject-views-notice]').textContent = kling
        ? 'Kling demande 1 à 3 vues complémentaires du même sujet, distinctes de la vue principale. Aucun coût vidéo pour ces imports.'
        : 'Ces vues ne conviennent pas au modèle choisi. Retire-les avant de continuer ; tes images principales restent conservées.';
    });
  }

  changed() { this.form.dispatchEvent(new Event('change', { bubbles: true })); }
  disconnect() { this.form.removeEventListener('change', this.refresh); }
}
