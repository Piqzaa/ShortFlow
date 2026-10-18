import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['dialog', 'date', 'label', 'upload', 'list', 'preview', 'search', 'status', 'more', 'submit'];
  static values = { searchUrl: String };
  open(event) {
    if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0 || typeof this.dialogTarget.showModal !== 'function') return;
    event.preventDefault();
    event.stopPropagation();
    const date = event.currentTarget.dataset.date;
    if (this.hasDateTarget) this.dateTarget.value = date;
    this.labelTarget.textContent = `Le ${date.split('-').reverse().join('/')}`;
    const url = new URL(this.uploadTarget.href);
    url.searchParams.set('date', date);
    this.uploadTarget.href = url.href;
    this.dialogTarget.showModal();
    this.loadPreviews();
    if (this.hasSearchTarget && this.submitTarget.disabled) this.fetchVideos(1);
  }
  loadPreviews() {
    this.stopPreviews();
    if (!this.hasListTarget) return;
    if (typeof IntersectionObserver === 'undefined') {
      this.previewTargets.slice(0, 6).forEach(video => { video.preload = 'metadata'; });
      return;
    }
    this.previewObserver = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        entry.target.preload = 'metadata';
        this.previewObserver.unobserve(entry.target);
      });
    }, { root: this.listTarget, rootMargin: '80px' });
    this.previewTargets.forEach(video => this.previewObserver.observe(video));
  }
  stopPreviews() { this.previewObserver?.disconnect(); }
  preventSubmit(event) { event.preventDefault(); }
  search() {
    this.stopSearch();
    this.submitTarget.disabled = true;
    this.moreTarget.hidden = true;
    this.moreTarget.disabled = true;
    this.statusTarget.textContent = 'Recherche en cours…';
    this.listTarget.setAttribute('aria-busy', 'true');
    this.listTarget.querySelectorAll('input:checked').forEach(input => { input.checked = false; });
    this.searchTimer = setTimeout(() => this.fetchVideos(1), 250);
  }
  more() { this.fetchVideos(Number(this.moreTarget.dataset.page)); }
  async fetchVideos(page) {
    this.stopSearch();
    const version = this.requestVersion;
    this.request = new AbortController();
    this.listTarget.setAttribute('aria-busy', 'true');
    this.statusTarget.textContent = 'Recherche en cours…';
    this.moreTarget.disabled = true;
    this.submitTarget.disabled = true;
    try {
      const url = new URL(this.searchUrlValue, window.location.href);
      url.searchParams.set('q', this.searchTarget.value.trim());
      url.searchParams.set('page', String(page));
      const response = await fetch(url, { signal: this.request.signal, credentials: 'same-origin', headers: { Accept: 'application/json' } });
      if (!response.ok || response.redirected) throw new Error('Search failed');
      const result = await response.json();
      if (version !== this.requestVersion || !this.dialogTarget.open) return;
      if (page === 1) {
        this.listTarget.innerHTML = result.html;
        this.listTarget.scrollTop = 0;
      } else {
        this.listTarget.insertAdjacentHTML('beforeend', result.html);
      }
      this.statusTarget.textContent = `${result.total} vidéo${result.total > 1 ? 's' : ''} · Les plus récentes d’abord`;
      this.moreTarget.hidden = result.nextPage === null;
      this.moreTarget.dataset.page = result.nextPage ?? 1;
      this.moreTarget.textContent = 'Afficher plus';
      this.submitTarget.disabled = result.total === 0;
      this.loadPreviews();
    } catch (error) {
      if (version !== this.requestVersion || error.name === 'AbortError') return;
      this.statusTarget.textContent = 'Impossible de charger les vidéos. Réessaie.';
      this.moreTarget.hidden = false;
      this.moreTarget.dataset.page = page;
      this.moreTarget.textContent = 'Réessayer';
    } finally {
      if (version === this.requestVersion) {
        this.listTarget.removeAttribute('aria-busy');
        this.moreTarget.disabled = false;
      }
    }
  }
  stopSearch() {
    clearTimeout(this.searchTimer);
    this.request?.abort();
    this.requestVersion = (this.requestVersion ?? 0) + 1;
  }
  closed() { this.stopSearch(); this.stopPreviews(); }
  disconnect() { this.closed(); }
  close() { this.closed(); this.dialogTarget.close(); }
}
