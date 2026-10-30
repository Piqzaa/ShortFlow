import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['status'];

  connect() {
    this.restore = () => { this.reset(); this.findEpisode(); };
    window.addEventListener('pageshow', this.restore);
    this.restore();
  }

  findEpisode() {
    if (this.element.querySelector?.('[data-series-navigation-blocked="true"]')) return;
    if (this.element.querySelector?.('.legacy-episode-correction')) return;
    if (!this.element.querySelector?.('[data-series-page]')) return;
    const target = /^#episode-([1-9]\d*)$/.exec(window.location.hash);
    if (!target || this.element.querySelector(`#episode-${target[1]}`)) return;
    const destination = new URL(window.location.href);
    if (destination.searchParams.get('episode') === target[1]) return;
    destination.searchParams.set('episode', target[1]);
    window.location.replace(destination.href);
  }

  navigate(event) {
    const link = event.target.closest('a');
    if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || link.hasAttribute('download') || (link.target && link.target !== '_self')) return;
    const destination = new URL(link.href, window.location.href);
    if (destination.origin !== window.location.origin || !['http:', 'https:'].includes(destination.protocol)) return;
    if (destination.pathname === window.location.pathname && destination.search === window.location.search && destination.hash) return;
    this.show('Chargement de la page…');
  }

  submit(event) {
    if (event.defaultPrevented) return;
    const form = event.target;
    if (form.dataset.studioSubmitting) {
      event.preventDefault();
      return;
    }
    form.dataset.studioSubmitting = 'true';
    form.setAttribute('aria-busy', 'true');
    if (event.submitter) {
      event.submitter.classList.add('is-pending');
      event.submitter.setAttribute('aria-disabled', 'true');
    }
    this.show('Traitement en cours…');
  }

  show(message) {
    this.statusTarget.textContent = message;
    this.statusTarget.hidden = false;
  }

  reset() {
    this.statusTarget.hidden = true;
    this.statusTarget.textContent = '';
    this.element.querySelectorAll('[data-studio-submitting]').forEach(form => {
      delete form.dataset.studioSubmitting;
      form.removeAttribute('aria-busy');
    });
    this.element.querySelectorAll('.is-pending').forEach(button => {
      button.classList.remove('is-pending');
      button.removeAttribute('aria-disabled');
    });
  }

  disconnect() {
    window.removeEventListener('pageshow', this.restore);
  }
}
