import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  outside(event) {
    if (this.element.open && !this.element.contains(event.target)) this.element.open = false;
  }

  escape(event) {
    if (event.key !== 'Escape' || !this.element.open) return;
    this.element.open = false;
    this.element.querySelector('summary')?.focus();
  }
}
