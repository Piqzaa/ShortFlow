import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  connect() {
    this.dirty = this.element?.dataset.unsavedInitial === 'true';
    this.warn = event => {
      if (!this.dirty) return;
      event.preventDefault();
      event.returnValue = '';
    };
    window.addEventListener('beforeunload', this.warn);
  }

  change() { this.dirty = true; }
  submit() { this.dirty = false; }
  disconnect() { window.removeEventListener('beforeunload', this.warn); }
}
