import { Controller } from '@hotwired/stimulus';
export default class extends Controller {
  static targets = ['count'];
  connect() { this.change(); }
  change(event) {
    let selected = this.element.querySelectorAll('input[name="videos[]"]:checked');
    if (selected.length > 8 && event) { event.target.checked = false; this.countTarget.textContent = '8 clips maximum. Tu peux retirer un clip de la sélection.'; return; }
    this.countTarget.textContent = `${selected.length} clip${selected.length > 1 ? 's' : ''} sélectionné${selected.length > 1 ? 's' : ''} · 8 maximum`;
  }
}
