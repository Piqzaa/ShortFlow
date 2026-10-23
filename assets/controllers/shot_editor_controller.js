import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['list', 'prototype', 'summary', 'add'];
  static values = { model: Object };

  connect() { this.update(); }

  rows() { return Array.from(this.listTarget.querySelectorAll('[data-shot-editor-row]')); }

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
    if (action === 'add') this.rows().at(-1)?.querySelector('textarea')?.focus();
    if (action === 'up' || action === 'down') event.currentTarget.closest('[data-shot-editor-row]')?.querySelector('textarea')?.focus();
    if (action === 'remove') this.rows()[Math.min(index, this.rows().length - 1)]?.querySelector('textarea')?.focus();
    this.element.dispatchEvent(new Event('input', { bubbles: true }));
  }

  update() {
    const rows = this.rows();
    let seconds = 0, cents = 0;
    rows.forEach((row, index) => {
      row.querySelector('[data-shot-heading]').textContent = `Plan ${index + 1}`;
      row.querySelectorAll('[name], [id], label[for]').forEach(element => {
        for (const attr of ['name', 'id', 'for']) {
          const value = element.getAttribute(attr);
          if (value) element.setAttribute(attr, value.replace(/\[shots\]\[\d+\]/g, `[shots][${index}]`).replace(/_shots_\d+(?=_|$)/g, `_shots_${index}`));
        }
      });
      row.querySelectorAll('[name="plan_action"]').forEach(button => {
        const action = button.value.split(':')[0];
        button.value = `${action}:${index}`;
        button.disabled = action === 'up' ? index === 0 : action === 'down' ? index === rows.length - 1 : rows.length === 1;
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
