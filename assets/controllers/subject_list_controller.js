import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['list','prototype','add','notice'];
  connect() {
    this.form = this.element.closest('form');
    this.refresh = () => this.update();
    this.form.addEventListener('change',this.refresh);
    const indices = Array.from(this.listTarget.querySelectorAll('input[type="hidden"]')).map(input=>Number(input.name.match(/\[subjects\]\[(\d+)\]/)?.[1] ?? -1));
    this.next = Math.max(-1,...indices)+1;
    this.update();
  }
  add() {
    if (this.addTarget.disabled) return;
    this.listTarget.insertAdjacentHTML('beforeend',this.prototypeTarget.innerHTML.replaceAll('__name__',String(this.next++)));
    this.update();
    this.listTarget.lastElementChild?.querySelector('input[type="file"]')?.focus();
  }
  remove(event) {
    event.currentTarget.closest('[data-subject-row]')?.remove();
    this.form.dispatchEvent(new Event('change',{bubbles:true}));
    this.update();
  }
  update() {
    const model = this.form.querySelector('[data-creation-target="model"]')?.selectedOptions[0];
    const limit = Math.max(0,Number(model?.dataset.subjectLimit || 0) - (model?.dataset.referenceMode === 'subject' ? 1 : 0));
    const count = this.listTarget.querySelectorAll('[data-subject-row]').length;
    this.element.hidden = limit === 0 && count === 0;
    this.addTarget.disabled = count >= limit;
    this.noticeTarget.textContent = `${count} / ${limit} référence(s) supplémentaire(s) pour ce modèle. ${count > limit ? 'Retire les références incompatibles avant le devis.' : 'La référence principale de personnage, si présente, compte aussi dans la limite du modèle.'}`;
  }
  disconnect() { this.form.removeEventListener('change',this.refresh); }
}
