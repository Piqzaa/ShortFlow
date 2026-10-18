import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['refresh', 'date'];
  connect() {
    this.element.classList.add('is-enhanced');
    this.element.querySelectorAll('[data-local-datetime]').forEach(input => { input.type = 'datetime-local'; });
    this.timing();
  }
  networks() { this.element.requestSubmit(this.refreshTarget); }
  timing() {
    const selected = this.element.querySelector('input[name$="[mode]"]:checked');
    this.dateTarget.hidden = selected?.value !== 'scheduled';
  }
}
