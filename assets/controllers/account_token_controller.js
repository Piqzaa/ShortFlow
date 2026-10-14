import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['input'];

  connect() {
    if (!this.hasInputTarget) return;
    const token = new URLSearchParams(window.location.hash.slice(1)).get('token');
    if (token && /^[a-f0-9]{64}$/.test(token)) {
      this.inputTarget.value = token;
      window.history.replaceState(null, '', window.location.pathname + window.location.search);
    }
    if (/^[a-f0-9]{64}$/.test(this.inputTarget.value)) {
      this.inputTarget.type = 'hidden';
      this.inputTarget.closest('div').hidden = true;
    }
  }
}
