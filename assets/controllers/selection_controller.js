import { Controller } from '@hotwired/stimulus';
export default class extends Controller {
  static targets = ['item', 'count', 'submit', 'all'];
  connect() { this.update(); }
  update() {
    const count = this.itemTargets.filter(input => input.checked).length;
    this.countTarget.textContent = `${count} vidéo${count > 1 ? 's' : ''} sélectionnée${count > 1 ? 's' : ''}`;
    this.submitTarget.disabled = count === 0;
    this.allTarget.checked = count > 0 && count === this.itemTargets.length;
    this.allTarget.indeterminate = count > 0 && count < this.itemTargets.length;
  }
  toggleAll(event) { this.itemTargets.forEach(input => { input.checked = event.target.checked; }); this.update(); }
}
