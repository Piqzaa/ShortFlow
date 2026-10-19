import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['model', 'duration', 'cost', 'description'];
  static values = { models: Object };

  connect() { this.estimate(); }

  estimate() {
    const model = this.modelsValue[this.modelTarget.value];
    const seconds = Number(this.durationTarget.value);
    if (!model || !Number.isSafeInteger(model.rate) || model.rate < 0 || ![5, 10].includes(seconds)) {
      this.costTarget.textContent = 'Choisis un modèle pour voir l’estimation';
      this.descriptionTarget.textContent = 'Le coût sera vérifié au récapitulatif.';
      return;
    }
    const amount = new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(model.rate * seconds / 100);
    this.costTarget.textContent = `Estimation : ${amount} $ US`;
    this.descriptionTarget.textContent = `${seconds} s · ${model.audio ? 'Audio généré · 720p' : 'Sans audio'} · Facturé par Fal`;
  }
}
