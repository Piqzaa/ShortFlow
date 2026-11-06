export function GuidedModelPicker(Base) {
  return class extends Base {
  setupPicker() {
    if (!this.hasPickerTarget) return;
    this.pickerTarget.hidden = false;
    this.nativeTarget.hidden = true;
    this.modelTarget.required = false;
    this.groupTarget.disabled = false;
    this.pickerSearchTarget.disabled = false;
    this.syncPicker();
    this.refreshPickerAvailability();
  }

  pickerVariants(card) {
    return this.variantIds(card).filter(id => {
      const model = this.modelsValue[id];
      return Boolean(model) && (!this.hasSourceTarget || this.modelMode(model) === this.sourceMode()) && (!this.hasUseEndTarget || !this.useEndTarget.checked || this.sourceMode() !== 'image' || model.endImageField || model.end);
    });
  }

  refreshPickerAvailability() {
    if (!this.hasPickerTarget) return;
    for (const option of this.groupTarget.options || []) {
      const card = this.cardTargets.find(card => card.dataset.groupId === option.value);
      if (option.value) option.hidden = option.disabled = !card || !this.pickerVariants(card).length;
    }
    this.syncPicker();
    this.searchPicker();
    this.refreshPickerConnections();
  }

  pickerConnected(model) {
    if (!model) return false;
    if (this.hasAccessTarget && this.accessTarget.value === 'mcp' && model.provider !== 'fal') return false;
    if (model.provider === 'fal') return this.hasAccessTarget && this.accessTarget.value === 'mcp' ? Boolean(this.connectedValue?.['fal-mcp']) : Boolean(this.connectedValue?.['fal-api'] ?? this.connectedValue?.fal);
    return Boolean(this.connectedValue?.[model.provider]);
  }

  connectionCaption() {
    const model = this.modelsValue[this.modelTarget.value];
    if (!model) return 'Connexion du fournisseur';
    const name = this.providersValue?.[model.provider] || model.provider;
    return `${name} · ${this.hasAccessTarget && this.accessTarget.value === 'mcp' ? 'connexion navigateur' : 'clé API'} ${this.pickerConnected(model) ? 'enregistrée' : 'à connecter'}`;
  }

  refreshPickerConnections() {
    if (!this.hasPickerTarget) return;
    for (const option of this.groupTarget.options || []) {
      if (!option.value) continue;
      const card = this.cardTargets.find(card => card.dataset.groupId === option.value);
      const model = card ? this.modelsValue[this.variantIds(card)[0]] : null;
      const connected = this.pickerConnected(model);
      option.textContent = `${(option.textContent || '').replace(/ · à connecter$/u, '')}${connected ? '' : ' · à connecter'}`;
      option.classList?.toggle('catalog-option-disconnected', !connected);
    }
    for (const button of this.pickerResultTargets || []) {
      const card = this.cardTargets.find(card => card.dataset.groupId === button.dataset.groupId);
      const label = button.querySelector?.('[data-result-access]');
      if (label && card) label.textContent = this.pickerConnected(this.modelsValue[this.variantIds(card)[0]]) ? 'Connexion enregistrée' : 'À connecter';
    }
  }

  speechCaption(model, id = '') {
    if (!model?.audio) return 'Sans audio : ce modèle ne génère ni voix ni dialogues.';
    if (id === 'kling-3' || id === 'kling-3-image') return 'Voix annoncées en chinois ou anglais. Ce modèle ne convient pas à des dialogues français.';
    if (id.startsWith('veo-31-subject-')) return 'Retrait de cet accès Fal annoncé le 17/11/2026.';
    return '';
  }

  changeRecommendationResolution(event) {
    const id = event.currentTarget.value;
    const card = (this.cardTargets || []).find(card => this.variantIds(card).includes(this.modelTarget.value));
    if (!card || !this.pickerVariants(card).includes(id)) return;
    this.modelTarget.value = id;
    this.modelTarget.dispatchEvent(new Event('change', {bubbles:true}));
  }

  updateRecommendationResolution() {
    const panel = this.element?.querySelector('[data-recommendation-resolution]');
    if (!panel) return;
    const card = (this.cardTargets || []).find(card => this.variantIds(card).includes(this.modelTarget.value));
    const ids = card ? this.pickerVariants(card) : [];
    panel.hidden = !ids.length;
    const select = panel.querySelector('select');
    select.replaceChildren(...ids.map(id => {
      const option = document.createElement('option');
      option.value = id;
      option.textContent = this.recommendationEstimate(this.modelsValue[id]);
      return option;
    }));
    select.value = this.modelTarget.value;
    select.disabled = ids.length < 2;
    const price = panel.querySelector('[data-selected-model-price]');
    if (price) price.textContent = this.modelsValue[this.modelTarget.value] ? `Exemple de prix pour ta sélection : ${this.recommendationEstimate(this.modelsValue[this.modelTarget.value])}. Le récapitulatif calcule le total de tes plans.` : '';
  }

  recommendationEstimate(model) {
    const current = Number(this.element?.querySelector('[data-shot-editor-row] select[name$="[seconds]"]')?.value);
    const seconds = model.seconds.includes(current) ? current : model.seconds.find(value => value >= 5) ?? model.seconds[0];
    const cents = model.pricesCents?.[seconds] ?? Math.ceil(((model.rateMicros || 0) * seconds + (model.fixedMicros || 0) + (model.subjectReference ? model.referenceMicros || 0 : 0)) / 10000);
    return `${model.quality === '4k' ? '4K' : model.quality} · ${seconds} s · ${Number.isFinite(cents) ? (cents / 100).toLocaleString('fr-FR', {minimumFractionDigits:2,maximumFractionDigits:2}) + ' $ US estimés' : 'coût au récapitulatif'}${model.subjectReference && model.referenceMicros ? ' · avec 1 référence' : ''}`;
  }

  updateRecommendations() {
    if (!this.hasRecommendationsTarget) return;
    this.recommendationsTarget.hidden = false;
    const image = this.sourceMode() !== 'text';
    this.recommendationTargets.forEach(button => {
      const baseId = this.sourceMode() === 'subject' ? button.dataset.subjectModel : image ? (this.hasUseEndTarget && this.useEndTarget.checked ? button.dataset.framesModel : button.dataset.imageModel) : button.dataset.textModel;
      const family = (this.cardTargets || []).find(card => this.variantIds(card).includes(baseId));
      const id = family && this.pickerVariants(family).includes(this.modelTarget.value) ? this.modelTarget.value : baseId;
      const model = this.modelsValue[id];
      button.hidden = !model;
      button.dataset.modelId = id;
      button.setAttribute('aria-pressed', String(this.modelTarget.value === id));
      const name = button.querySelector('[data-recommendation-name]');
      if (name && model) name.textContent = model.name;
      const audio = button.querySelector('[data-recommendation-audio]');
      if (audio && model) audio.textContent = model.audio ? 'Avec audio' : 'Sans audio · aucune voix';
      const access = button.querySelector('[data-recommendation-access]');
      if (access && model) access.textContent = `${this.providersValue?.[model.provider] || model.provider} · ${this.pickerConnected(model) ? 'connexion enregistrée' : 'à connecter'}`;
      const example = button.querySelector('[data-recommendation-example]');
      if (example && model) example.textContent = this.recommendationEstimate(model);
    });
    const selected = this.modelsValue[this.modelTarget.value];
    this.selectedModelTarget.hidden = !selected;
    const quality = selected?.quality === '4k' ? '4K' : selected?.quality;
    this.selectedModelTarget.textContent = selected ? `Sélection : ${selected.name}${selected.name.toLowerCase().includes(quality.toLowerCase()) ? '' : ` · ${quality}`}` : '';
    const speech = this.element?.querySelector('[data-selected-model-speech]');
    if (speech) { speech.textContent = selected ? this.speechCaption(selected, this.modelTarget.value) : ''; speech.hidden = !speech.textContent; }
    this.updateRecommendationResolution();
  }

  syncPicker() {
    const card = this.cardTargets.find(card => this.variantIds(card).includes(this.modelTarget.value));
    this.groupTarget.value = card?.dataset.groupId || '';
    this.restrict(this.resolutionTarget, card ? this.pickerVariants(card) : []);
    this.resolutionTarget.disabled = !card;
    this.resolutionTarget.value = this.modelTarget.value;
  }

  selectGroup() {
    const card = this.cardTargets.find(card => card.dataset.groupId === this.groupTarget.value);
    this.modelTarget.value = card ? this.pickerVariants(card)[0] || '' : '';
    this.modelTarget.dispatchEvent(new Event('change', {bubbles:true}));
  }

  selectResolution() {
    const card = this.cardTargets.find(card => card.dataset.groupId === this.groupTarget.value);
    if (!card || !this.pickerVariants(card).includes(this.resolutionTarget.value)) return;
    this.modelTarget.value = this.resolutionTarget.value;
    this.modelTarget.dispatchEvent(new Event('change', {bubbles:true}));
  }

  searchPicker(event) {
    event?.stopPropagation();
    const query = this.pickerSearchTarget.value.trim().toLocaleLowerCase('fr');
    let count = 0;
    if (this.hasPickerResultsTarget) {
      this.pickerResultsTarget.hidden = !query;
      this.pickerResultTargets.forEach(button => {
        const option = Array.from(this.groupTarget.options).find(option => option.value === button.dataset.groupId);
        const card = this.cardTargets.find(card => card.dataset.groupId === button.dataset.groupId);
        const matches = Boolean(option?.textContent.toLocaleLowerCase('fr').includes(query)) && Boolean(card && this.pickerVariants(card).length);
        button.hidden = !matches;
        if (matches) count++;
      });
    }
    this.pickerCountTarget.textContent = !query ? 'Tes favoris sont marqués d’une étoile ★.' : count ? `${count} modèle${count > 1 ? 's' : ''} : clique sur un résultat pour le sélectionner.` : 'Aucun modèle trouvé. Modifie la recherche ; ta sélection est conservée.';
  }

  pickResult(event) {
    const id = event.currentTarget.dataset.groupId;
    if (!this.cardTargets.some(card => card.dataset.groupId === id)) return;
    this.groupTarget.value = id;
    this.selectGroup();
    this.pickerSearchTarget.value = '';
    this.searchPicker();
    this.resolutionTarget.focus();
  }

  preventPickerSubmit(event) { event.preventDefault(); }

  variantIds(card) {
    try { return card.dataset.variants ? JSON.parse(card.dataset.variants) : [card.dataset.modelId]; }
    catch { return [card.dataset.modelId]; }
  }

  restrict(select, allowed) {
    Array.from(select.options).forEach(option => {
      option.disabled = Boolean(option.value && allowed && !allowed.map(String).includes(option.value));
      option.hidden = option.disabled;
    });
    if (allowed && !allowed.map(String).includes(select.value)) select.value = '';
  }

  };
}
