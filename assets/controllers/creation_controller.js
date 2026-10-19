import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['model', 'duration', 'ratio', 'cost', 'description', 'connect', 'filters', 'search', 'provider', 'audio', 'minimum', 'onlyConnected', 'card', 'count', 'empty', 'choose'];
  static values = { models: Object, connected: Object };

  connect() {
    this.filtersTarget.hidden = false;
    this.chooseTargets.forEach(button => { button.hidden = false; });
    this.changeModel();
    this.filter();
  }

  changeModel() {
    const model = this.modelsValue[this.modelTarget.value];
    this.restrict(this.durationTarget, model?.seconds);
    this.restrict(this.ratioTarget, model?.ratios);
    this.estimate();
  }

  restrict(select, allowed) {
    Array.from(select.options).forEach(option => {
      option.disabled = Boolean(option.value && allowed && !allowed.map(String).includes(option.value));
      option.hidden = option.disabled;
    });
    if (allowed && !allowed.map(String).includes(select.value)) select.value = '';
  }

  choose(event) {
    const id = event.currentTarget.dataset.modelId;
    if (!this.modelsValue[id]) return;
    this.modelTarget.value = id;
    this.modelTarget.dispatchEvent(new Event('change', { bubbles: true }));
    this.durationTarget.focus();
  }

  filter() {
    const query = this.searchTarget.value.trim().toLocaleLowerCase('fr');
    let count = 0;
    this.cardTargets.forEach(card => {
      const model = this.modelsValue[card.dataset.modelId];
      const visible = Boolean(model) && `${model.name} ${model.maker} ${model.provider}`.toLocaleLowerCase('fr').includes(query)
        && (!this.providerTarget.value || model.provider === this.providerTarget.value)
        && (!this.audioTarget.value || model.audio === (this.audioTarget.value === 'yes'))
        && (!this.minimumTarget.value || Math.max(...model.seconds) >= Number(this.minimumTarget.value))
        && (!this.onlyConnectedTarget.checked || this.connectedValue[model.provider]);
      card.hidden = !visible;
      if (visible) count += 1;
    });
    this.countTarget.textContent = `${count} choix modèle / fournisseur`;
    this.emptyTarget.hidden = count !== 0;
  }

  estimate() {
    const model = this.modelsValue[this.modelTarget.value];
    const seconds = Number(this.durationTarget.value);
    this.connectTarget.hidden = Boolean(model && this.connectedValue[model.provider]);
    if (!model || !Number.isSafeInteger(model.rateMicros) || model.rateMicros < 0 || !model.seconds.includes(seconds)) {
      this.costTarget.textContent = model ? 'Choisis une durée compatible pour voir l’estimation' : 'Choisis un modèle pour voir l’estimation';
      this.descriptionTarget.textContent = 'Le coût sera vérifié au récapitulatif.';
      return;
    }
    const amount = new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Math.ceil(model.rateMicros * seconds / 10000) / 100);
    this.costTarget.textContent = `Estimation : ${amount} $ US`;
    this.descriptionTarget.textContent = `${seconds} s · ${model.audio ? 'Audio généré' : 'Sans audio'} · ${model.quality} · Facturé par ${model.provider === 'fal' ? 'Fal' : 'Replicate'}`;
  }
}
