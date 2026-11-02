import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  connect() {
    // invalid does not bubble: reveal closed disclosures before native focus.
    this.onInvalid = event => this.reveal(event.target);
    this.element.addEventListener('invalid', this.onInvalid, true);
    this.element.querySelectorAll('[aria-invalid="true"], .form-errors').forEach(field => this.reveal(field));
  }

  reveal(field) {
    for (let parent = field.parentElement; parent && parent !== this.element; parent = parent.parentElement) {
      if (parent.tagName === 'DETAILS') parent.open = true;
    }
  }

  disconnect() {
    this.element.removeEventListener('invalid', this.onInvalid, true);
  }
}
