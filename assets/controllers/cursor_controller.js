import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  connect() {
    this.preference = window.matchMedia('(hover: hover) and (pointer: fine) and (prefers-reduced-motion: no-preference)');
    this.frame = null;
    this.position = null;
    this.onPreference = () => this.configure();
    this.onMove = (event) => {
      if (event.pointerType !== 'mouse' || !this.halo) return;
      this.target = { x: event.clientX, y: event.clientY };
      this.position ??= { ...this.target };
      this.halo.classList.add('is-visible');
      if (this.frame === null) this.frame = requestAnimationFrame(() => this.paint());
    };
    this.onHide = () => {
      this.halo?.classList.remove('is-visible');
      if (this.frame !== null) cancelAnimationFrame(this.frame);
      this.frame = null;
      this.position = null;
    };
    this.onKey = (event) => { if (event.key === 'Tab') this.onHide(); };
    this.preference.addEventListener('change', this.onPreference);
    this.configure();
  }

  configure() {
    this.stop();
    if (!this.preference.matches) return;
    this.halo = document.createElement('div');
    this.halo.className = 'cursor-halo';
    this.halo.setAttribute('aria-hidden', 'true');
    this.element.append(this.halo);
    window.addEventListener('pointermove', this.onMove, { passive: true });
    document.addEventListener('pointerleave', this.onHide);
    document.addEventListener('visibilitychange', this.onHide);
    window.addEventListener('blur', this.onHide);
    window.addEventListener('keydown', this.onKey);
  }

  paint() {
    this.frame = null;
    if (!this.halo || !this.position || !this.target) return;
    this.position.x += (this.target.x - this.position.x) * 0.24;
    this.position.y += (this.target.y - this.position.y) * 0.24;
    this.halo.style.transform = `translate3d(${this.position.x}px, ${this.position.y}px, 0)`;
    if (Math.abs(this.target.x - this.position.x) + Math.abs(this.target.y - this.position.y) > 0.2) {
      this.frame = requestAnimationFrame(() => this.paint());
    }
  }

  stop() {
    this.onHide();
    this.halo?.remove();
    this.halo = null;
    window.removeEventListener('pointermove', this.onMove);
    document.removeEventListener('pointerleave', this.onHide);
    document.removeEventListener('visibilitychange', this.onHide);
    window.removeEventListener('blur', this.onHide);
    window.removeEventListener('keydown', this.onKey);
  }

  disconnect() {
    this.preference.removeEventListener('change', this.onPreference);
    this.stop();
  }
}
