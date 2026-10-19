import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['model', 'duration', 'ratio', 'cost', 'description', 'connect', 'filters', 'search', 'provider', 'audio', 'minimum', 'onlyConnected', 'card', 'count', 'empty', 'choose', 'grid', 'quality', 'sort', 'comparison', 'access'];
  static values = { models: Object, connected: Object, providers: Object };

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
    if (this.hasAccessTarget) this.restrict(this.accessTarget, !model || model.provider === 'fal' ? ['api', 'mcp'] : ['api']);
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
        && (!this.hasQualityTarget || !this.qualityTarget.value || model.quality === this.qualityTarget.value)
        && (!this.onlyConnectedTarget.checked || this.connectedValue[model.provider]);
      card.hidden = !visible;
      if (visible) count += 1;
    });
    this.countTarget.textContent = `${count} choix modèle / fournisseur`;
    this.emptyTarget.hidden = count !== 0;
    if (this.hasSortTarget && this.hasGridTarget) {
      const seconds = Number(this.comparisonTarget.value);
      const ids = Object.keys(this.modelsValue);
      const price = card => this.price(this.modelsValue[card.dataset.modelId], seconds);
      this.cardTargets.slice().sort((a,b) => {
        if (!this.sortTarget.value) return ids.indexOf(a.dataset.modelId) - ids.indexOf(b.dataset.modelId);
        const left = price(a), right = price(b);
        if (left === null || right === null) return left === right ? 0 : left === null ? 1 : -1;
        return this.sortTarget.value === 'price-desc' ? right - left : left - right;
      }).forEach(card => this.gridTarget.append(card));
      this.cardTargets.forEach(card => {
        const note = card.querySelector('[data-comparison-price]');
        if (!note) return;
        const cents = price(card);
        note.textContent = cents === null ? `Indisponible pour ${seconds} s` : `${seconds} s : ${this.money(cents)} $ US estimés`;
      });
    }
  }

  price(model, seconds) {
    if (!model || !model.seconds.includes(seconds)) return null;
    const cents = model.pricesCents ? model.pricesCents[seconds] : Math.ceil(model.rateMicros * seconds / 10000);
    return Number.isSafeInteger(cents) && cents > 0 ? cents : null;
  }

  money(cents) {
    return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(cents / 100);
  }

  estimate() {
    const model = this.modelsValue[this.modelTarget.value];
    const seconds = Number(this.durationTarget.value);
    const mcp = this.hasAccessTarget && this.accessTarget.value === 'mcp';
    const connected = model?.provider === 'fal' ? (mcp ? this.connectedValue['fal-mcp'] : this.connectedValue['fal-api'] ?? this.connectedValue.fal) : this.connectedValue[model?.provider];
    this.connectTarget.hidden = Boolean(model && connected);
    this.connectTarget.textContent = mcp ? 'Connecter Fal en gardant l’idée' : 'Ajouter ma clé en gardant l’idée';
    if (!model || !Number.isSafeInteger(model.rateMicros) || model.rateMicros < 0 || !model.seconds.includes(seconds)) {
      this.costTarget.textContent = model ? 'Choisis une durée compatible pour voir l’estimation' : 'Choisis un modèle pour voir l’estimation';
      this.descriptionTarget.textContent = 'Le coût sera vérifié au récapitulatif.';
      return;
    }
    const cents = this.price(model, seconds);
    if (cents === null) {
      this.costTarget.textContent = 'Le prix de cette durée doit être vérifié';
      this.descriptionTarget.textContent = 'Aucune génération sans estimation disponible.';
      return;
    }
    const amount = this.money(cents);
    this.costTarget.textContent = `Estimation : ${amount} $ US`;
    this.descriptionTarget.textContent = `${seconds} s · ${model.audio ? 'Audio généré' : 'Sans audio'} · ${model.quality} · Facturé par ${this.providersValue?.[model.provider] || model.provider}`;
  }
}
