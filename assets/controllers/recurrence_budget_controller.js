import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['count', 'total'];
  static values = { cents: Number };
  connect() { this.update(); }
  update() {
    const count = Number(this.countTarget.value);
    this.totalTarget.textContent = Number.isInteger(count) && count >= 1 && count <= 12
      ? `${new Intl.NumberFormat('fr-FR', {style:'currency',currency:'USD'}).format(this.centsValue * count / 100)} estimés pour ${count} épisode(s)`
      : 'Choisis entre 1 et 12 épisodes.';
  }
}
