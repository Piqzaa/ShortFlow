import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['list', 'prototype', 'summary', 'add'];
  static values = { model: Object };

  connect() {
    this.revealInvalid = event => this.reveal(event.target);
    this.element.addEventListener('invalid', this.revealInvalid, true);
    this.element.querySelectorAll('[aria-invalid="true"], .form-error, .form-errors').forEach(field => this.reveal(field));
    this.update();
  }

  disconnect() { this.element.removeEventListener('invalid', this.revealInvalid, true); }

  reveal(field) {
    for (let parent = field?.parentElement; parent && parent !== this.element; parent = parent.parentElement) {
      if (parent.tagName === 'DETAILS') parent.open = true;
    }
  }

  focusRow(row) {
    const field = row?.querySelector('[name$="[sceneDescription]"]');
    if (field) { this.reveal(field); field.focus(); }
    else row?.querySelector('textarea')?.focus();
  }

  review(event) {
    const row = event.currentTarget.closest('[data-shot-editor-row]');
    row.dataset.reviewedPrompt = row.querySelector('textarea').value;
    row.dataset.reviewedDescription = row.querySelector('[name$="[sceneDescription]"]').value;
    this.update();
    this.focusRow(row);
  }

  rows() { return Array.from(this.listTarget.querySelectorAll('[data-shot-editor-row]')); }

  detachTranslation(event) {
    event.preventDefault();
    const row = event.currentTarget.closest('[data-shot-editor-row]');
    for (const name of ['translatedIdentity', 'translationSource']) row.querySelector(`[name$="[${name}]"]`).value = '';
    this.update();
    this.focusRow(row);
    this.element.dispatchEvent(new Event('input', { bubbles: true }));
  }

  act(event) {
    event.preventDefault();
    const [action, value] = event.currentTarget.value.split(':');
    const index = Number(value);
    const rows = this.rows();
    if (action === 'add' && rows.length < 8) {
      this.listTarget.insertAdjacentHTML('beforeend', this.prototypeTarget.innerHTML.replaceAll('__name__', String(rows.length)));
      if (this.modelValue.seconds?.length) this.rows().at(-1).querySelector('select').value = String(Math.min(...this.modelValue.seconds));
    } else if (action === 'remove' && rows.length > 1) {
      rows[index]?.remove();
    } else if (action === 'up' && index > 0) {
      rows[index - 1].before(rows[index]);
    } else if (action === 'down' && index < rows.length - 1) {
      rows[index + 1].after(rows[index]);
    }
    this.update();
    if (action === 'add') this.focusRow(this.rows().at(-1));
    if (action === 'up' || action === 'down') this.focusRow(event.currentTarget.closest('[data-shot-editor-row]'));
    if (action === 'remove') this.focusRow(this.rows()[Math.min(index, this.rows().length - 1)]);
    this.element.dispatchEvent(new Event('input', { bubbles: true }));
  }

  update() {
    const rows = this.rows();
    let seconds = 0, cents = 0;
    rows.forEach((row, index) => {
      const description = row.querySelector('[name$="[sceneDescription]"]')?.value || '';
      const descriptionText = row.querySelector('[data-shot-description-text]');
      if (descriptionText) descriptionText.textContent = description.trim() || 'Résumé à compléter';
      const translated = row.querySelector('[data-shot-translated-context]');
      if (translated) {
        const identity = row.querySelector('[name$="[translatedIdentity]"]').value;
        translated.hidden = !identity;
        translated.querySelector('[data-shot-translated-identity]').textContent = identity;
      }
      const prompt = row.querySelector('textarea').value;
      const status = row.querySelector('[data-shot-summary-status]');
      if (status) {
        row.dataset.originalPrompt ??= prompt;
        const reviewed = row.dataset.reviewedPrompt === prompt && row.dataset.reviewedDescription === description;
        status.textContent = !description.trim() ? 'Résumé à compléter. Le prompt vidéo reste accessible dans les détails.'
          : reviewed ? 'Résumé relu pour cette saisie. Vérifie aussi les références au récapitulatif.'
            : prompt !== row.dataset.originalPrompt || row.dataset.reviewedPrompt !== undefined ? 'Prompt ou résumé modifié : résumé à revérifier avec le prompt vidéo.' : 'Résumé à relire avec le prompt vidéo.';
        row.querySelector('[data-shot-summary-review]').hidden = !description.trim() || reviewed;
      }
      const references = row.querySelector('[data-shot-reference-summary]');
      if (references) {
        const source = this.element.querySelector('[data-creation-target="source"]')?.value || (this.modelValue.subjectReference ? 'subject' : this.modelValue.image ? 'image' : 'text');
        const linked = this.element.querySelector('[name$="[continuity]"]')?.value === 'linked' && index > 0;
        const start = Boolean(row.querySelector('[name$="[startReference]"]')?.value);
        const end = Boolean(row.querySelector('[name$="[endReference]"]')?.value);
        references.textContent = source === 'text' ? 'Mode texte · aucune image envoyée.' : source === 'subject' ? 'Personnages et objets · références communes, sans cadre propre au plan.'
          : `Début : ${linked ? 'clip précédent' : start ? 'image de ce plan' : index === 0 ? 'référence commune si renseignée' : 'aucun cadre propre'}. Fin : ${end ? 'image de ce plan' : index === rows.length - 1 ? 'référence commune si renseignée' : 'aucune image propre'}. À vérifier au récapitulatif.`;
      }
      row.querySelector('[data-shot-heading]').textContent = `Plan ${index + 1}`;
      row.querySelectorAll('[name], [id], label[for], [aria-describedby]').forEach(element => {
        for (const attr of ['name', 'id', 'for', 'aria-describedby']) {
          const value = element.getAttribute(attr);
          if (value) element.setAttribute(attr, value.replace(/\[shots\]\[\d+\]/g, `[shots][${index}]`).replace(/_shots_\d+(?=_|$)/g, `_shots_${index}`));
        }
      });
      row.querySelectorAll('[name="plan_action"]').forEach(button => {
        const action = button.value.split(':')[0];
        button.value = `${action}:${index}`;
        button.disabled = action === 'up' ? index === 0 : action === 'down' ? index === rows.length - 1 : action === 'remove' && rows.length === 1;
      });
      const duration = Number(row.querySelector('select').value);
      seconds += duration;
      cents += !this.modelValue.seconds?.includes(duration) ? NaN : this.modelValue.pricesCents?.[duration] ?? Math.ceil(((this.modelValue.rateMicros || 0) * duration + (this.modelValue.fixedMicros || 0)) / 10000);
    });
    this.addTarget.disabled = rows.length >= 8;
    this.summaryTarget.textContent = `${rows.length} plan(s) · ${seconds} s au total · ${Number.isFinite(cents) ? (cents / 100).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' $ US estimés' : 'estimation à vérifier après ajustement des durées'}. ${seconds > 60 ? 'Réduis la durée totale à 60 secondes maximum avant de continuer.' : 'Aucun lancement avant confirmation.'}`;
    this.summaryTarget.classList.toggle('error', seconds > 60);
  }
}
