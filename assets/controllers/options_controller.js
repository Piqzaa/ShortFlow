import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  hover() { if (!window.matchMedia('(hover: hover)').matches) return; this.hoverOpened = !this.element.open; this.element.open = true; }
  leave() { if (this.hoverOpened && !this.element.contains(document.activeElement)) this.element.open = false; this.hoverOpened = false; }

  outside(event) {
    if (this.element.open && !this.element.contains(event.target)) this.element.open = false;
  }

  escape(event) {
    if (event.key !== 'Escape' || !this.element.open) return;
    this.element.open = false;
    this.element.querySelector('summary')?.focus();
  }
}
