import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['refresh', 'date'];
  connect() {
    this.element.classList.add('is-enhanced');
    this.element.querySelectorAll('[data-local-datetime]').forEach(input => { input.type = 'datetime-local'; });
    this.timing();
  }
  networks() {
    if (this.refreshPending) return;
    this.refreshPending = true;
    // Let the original change finish bubbling before submit clears the unsaved guard.
    this.refreshTimer = setTimeout(() => {
      this.refreshPending = false;
      if (this.element.isConnected) this.element.requestSubmit(this.refreshTarget);
    }, 0);
  }
  disconnect() { clearTimeout(this.refreshTimer); }
  timing() {
    const selected = this.element.querySelector('input[name$="[mode]"]:checked');
    this.dateTarget.hidden = selected?.value !== 'scheduled';
  }
}
