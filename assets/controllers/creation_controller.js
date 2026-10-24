import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['model', 'duration', 'ratio', 'cost', 'description', 'connect', 'filters', 'search', 'provider', 'audio', 'minimum', 'onlyConnected', 'card', 'count', 'empty', 'choose', 'grid', 'quality', 'sort', 'comparison', 'access', 'connection', 'connectionMessage', 'connectionButton', 'onlyFavorites', 'catalog', 'fourK', 'picker', 'native', 'group', 'resolution', 'pickerSearch', 'pickerCount', 'billing', 'accessDetails', 'pickerResults', 'pickerResult', 'source', 'reference', 'referencePanel', 'useEnd', 'endReference', 'endPanel', 'imageFit', 'imageSettings', 'startFrame', 'endFrame', 'startPreview', 'endPreview', 'framingNotice', 'recommendations', 'recommendation', 'modelDetails', 'selectedModel'];
  static values = { models: Object, connected: Object, providers: Object };

  connect() {
    this.filtersTarget.hidden = false;
    if (this.hasFourKTarget) this.fourKTarget.hidden = false;
    this.chooseTargets.forEach(button => { button.hidden = false; });
    this.cardTargets.forEach(card => { const select = card.querySelector?.('.catalog-variant'); if (select) select.disabled = select.options.length < 2; });
    if (this.hasPickerTarget) {
      this.pickerTarget.hidden = false;
      this.nativeTarget.hidden = true;
      this.modelTarget.required = false;
      this.groupTarget.disabled = false;
      if (this.hasRecommendationsTarget) this.groupTarget.required = false;
      this.pickerSearchTarget.disabled = false;
    }
    for (const option of this.modelTarget.options || []) {
      option.dataset.subjectLimit = String(this.modelsValue[option.value]?.subjectLimit || 0);
      option.dataset.referenceMode = this.modelMode(this.modelsValue[option.value]);
      option.dataset.acceptsEnd = String(Boolean(this.modelsValue[option.value]?.endImageField));
    }
    this.changeSource();
    this.changeModel();
    this.filter();
    if (this.hasModelDetailsTarget) this.modelDetailsTarget.open = Boolean(this.modelTarget.value && !this.recommendationTargets.some(button => button.dataset.modelId === this.modelTarget.value));
  }

  modelMode(model) { return model?.subjectReference ? 'subject' : model?.image ? 'image' : 'text'; }

  sourceMode() { return this.hasSourceTarget ? this.sourceTarget.value || 'text' : 'text'; }

  changeSource() {
    if (!this.hasSourceTarget) return;
    const image = this.sourceMode() !== 'text';
    this.referencePanelTarget.hidden = !image;
    if (this.hasImageSettingsTarget) this.imageSettingsTarget.hidden = !image;
    const allowed = Object.keys(this.modelsValue).filter(id => this.modelMode(this.modelsValue[id]) === this.sourceMode() && (!image || !this.hasUseEndTarget || !this.useEndTarget.checked || this.modelsValue[id].endImageField));
    if (!allowed.includes(this.modelTarget.value)) {
      this.modelTarget.value = '';
    }
    if (this.hasPickerTarget) Array.from(this.groupTarget.options).forEach(option => {
      option.hidden = option.disabled = Boolean(option.value && !this.cardTargets.some(card => card.dataset.groupId === option.value && this.variantIds(card).some(id => allowed.includes(id))));
    });
    if (!image && this.ratioTarget.value === 'auto') this.ratioTarget.value = '9:16';
    this.modelTarget.dispatchEvent(new Event('change', {bubbles:true}));
    this.filter();
    if (this.hasPickerTarget) this.searchPicker();
  }

  referenceChanged(event) {
    const slot = event.detail.slot || 'start';
    const target = slot === 'end' && this.hasEndReferenceTarget ? this.endReferenceTarget : this.referenceTarget;
    target.value = event.detail.reference || '';
    this.pendingImages ??= new Set();
    if (event.detail.pending) this.pendingImages.add(slot); else this.pendingImages.delete(slot);
    if (this.sourceMode() === 'text') this.sourceTarget.value = 'image';
    this.changeSource();
    target.dispatchEvent(new Event('change', {bubbles:true}));
    this.element.querySelectorAll('#ai-idea-form button[type="submit"]').forEach(button => { button.disabled = this.pendingImages.size > 0; });
  }

  changeEnd() {
    if (!this.useEndTarget.checked && this.modelsValue[this.modelTarget.value]?.requiresEnd) this.modelTarget.value = '';
    this.changeSource();
    this.modelTarget.dispatchEvent(new Event('change', {bubbles:true}));
  }

  frameChanged(event) {
    if (!this.hasStartPreviewTarget) return;
    const subject = this.modelsValue[this.modelTarget.value]?.subjectReference;
    const ratio = subject ? 'auto' : this.ratioTarget.value;
    const start = this.startPreviewTarget;
    const nativeRatio = start.naturalWidth && start.naturalHeight ? `${start.naturalWidth} / ${start.naturalHeight}` : '9 / 16';
    const aspect = ratio && ratio !== 'auto' ? ratio.replace(':', ' / ') : nativeRatio;
    const [a,b] = aspect.split('/').map(Number);
    if (this.hasReferencePanelTarget) this.referencePanelTarget.dataset.end = String(this.hasUseEndTarget && this.useEndTarget.checked);
    const fit = this.hasImageFitTarget && this.imageFitTarget.value === 'contain' ? 'contain' : 'cover';
    for (const [frame, preview] of [[this.startFrameTarget, start], [this.endFrameTarget, this.endPreviewTarget]]) {
      frame.hidden = preview.hidden;
      frame.style.aspectRatio = aspect;
      frame.style.maxWidth = `calc(var(--reference-height, 300px) * ${a / b})`;
      preview.style.objectFit = fit;
    }
    if (this.hasFramingNoticeTarget) this.framingNoticeTarget.textContent = subject ? 'Référence de personnage : image entière envoyée pour guider son apparence. Le modèle choisit ses plans ; cette image ne fixe ni le début ni la fin.' : ratio === 'auto'
      ? 'Format d’origine conservé. L’image de fin est ajustée au même cadre.'
      : `${ratio || 'Choisis un format'} · ${fit === 'contain' ? 'image entière, bandes noires si nécessaire' : 'recadrage centré'}. Cet aperçu sera repris au récapitulatif.`;
    if (event?.type === 'change') this.referenceTarget.dispatchEvent(new Event('change', {bubbles:true}));
  }

  changeModel() {
    if (this.hasPickerTarget) this.syncPicker();
    const model = this.modelsValue[this.modelTarget.value];
    if (this.hasImageSettingsTarget) this.imageSettingsTarget.hidden = this.sourceMode() === 'text' || Boolean(model?.subjectReference);
    if (model?.requiresEnd && this.hasUseEndTarget && !this.useEndTarget.checked) {
      this.useEndTarget.checked = true;
      this.changeSource();
      return;
    }
    if (this.hasEndPanelTarget) this.endPanelTarget.hidden = this.sourceMode() === 'text' || !this.useEndTarget.checked;
    if (this.hasReferencePanelTarget) {
      const label = this.referencePanelTarget.querySelector('label');
      if (label) label.textContent = model?.subjectReference ? 'Image du personnage' : 'Image de début';
      if (this.hasUseEndTarget) this.useEndTarget.disabled = Boolean(model?.subjectReference);
    }
    this.restrict(this.durationTarget, model?.seconds);
    this.restrict(this.ratioTarget, model?.ratios);
    if (model?.image && !this.ratioTarget.value) this.ratioTarget.value = model.ratios[0];
    if (this.hasAccessTarget) {
      this.restrict(this.accessTarget, !model || model.provider === 'fal' ? ['api', 'mcp'] : ['api']);
      if (model && model.provider !== 'fal') this.accessTarget.value = 'api';
    }
    this.frameChanged();
    this.updateRecommendations();
    this.estimate();
    this.cardTargets.forEach(card => {
      const selected = this.variantIds(card).includes(this.modelTarget.value);
      if (selected) this.renderVariant(card, this.modelTarget.value);
      card.classList?.toggle('is-selected', selected);
    });
  }

  updateRecommendations() {
    if (!this.hasRecommendationsTarget) return;
    this.recommendationsTarget.hidden = false;
    const image = this.sourceMode() !== 'text';
    this.recommendationTargets.forEach(button => {
      const id = this.sourceMode() === 'subject' ? button.dataset.subjectModel : image ? (this.useEndTarget.checked ? button.dataset.framesModel : button.dataset.imageModel) : button.dataset.textModel;
      const model = this.modelsValue[id];
      button.hidden = !model;
      button.dataset.modelId = id;
      button.setAttribute('aria-pressed', String(this.modelTarget.value === id));
      const name = button.querySelector('[data-recommendation-name]');
      if (name && model) name.textContent = model.name;
    });
    const selected = this.modelsValue[this.modelTarget.value];
    this.selectedModelTarget.textContent = selected ? `Sélection : ${selected.name} · ${selected.quality === '4k' ? '4K' : selected.quality}` : 'Choisis une option ou explore les autres modèles.';
  }

  syncPicker() {
    const card = this.cardTargets.find(card => this.variantIds(card).includes(this.modelTarget.value));
    this.groupTarget.value = card?.dataset.groupId || '';
    this.restrict(this.resolutionTarget, card ? this.variantIds(card) : []);
    this.resolutionTarget.disabled = !card;
    this.resolutionTarget.value = this.modelTarget.value;
  }

  selectGroup() {
    const card = this.cardTargets.find(card => card.dataset.groupId === this.groupTarget.value);
    this.modelTarget.value = card ? this.variantIds(card)[0] : '';
    this.modelTarget.dispatchEvent(new Event('change', {bubbles:true}));
  }

  selectResolution() {
    const card = this.cardTargets.find(card => card.dataset.groupId === this.groupTarget.value);
    if (!card || !this.variantIds(card).includes(this.resolutionTarget.value)) return;
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
        const matches = Boolean(option?.textContent.toLocaleLowerCase('fr').includes(query)) && (!this.hasSourceTarget || button.dataset.source === this.sourceMode()) && (!this.hasUseEndTarget || !this.useEndTarget.checked || this.sourceMode() === 'text' || this.cardTargets.some(card => card.dataset.groupId === button.dataset.groupId && this.variantIds(card).some(id => this.modelsValue[id]?.endImageField)));
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

  renderVariant(card, id) {
    if (card.dataset.modelId === id) return;
    card.dataset.modelId = id;
    const model = this.modelsValue[id];
    const set = (selector, text) => { const node = card.querySelector?.(selector); if (node) node.textContent = text; };
    const quality = model.quality === '4k' ? '4K' : model.quality;
    set('[data-card-quality]', quality);
    set('[data-card-description]', model.description);
    set('[data-card-capabilities]', `${model.seconds.length > 4 ? `${model.seconds[0]}–${model.seconds.at(-1)}` : model.seconds.join(' / ')} s · ${model.audio ? 'Audio généré' : 'Sans audio'} · ${model.ratios.map(ratio => ratio === 'auto' ? 'Image d’origine' : ratio).join(' · ')}`);
    set('[data-card-price]', model.pricesCents ? Object.entries(model.pricesCents).map(([s,c])=>`${s} s : ${this.money(c)} $ US`).join(' · ') : `${new Intl.NumberFormat('fr-FR',{minimumFractionDigits:3,maximumFractionDigits:3}).format(model.rateMicros / 1000000)} $ US / s${model.fixedMicros ? ` + ${new Intl.NumberFormat('fr-FR',{maximumFractionDigits:3}).format(model.fixedMicros / 1000000)} $ US d’image` : ''}`);
    const select = card.querySelector?.('.catalog-variant'); if (select) select.value = id;
    const button = card.querySelector?.('[data-creation-target="choose"]'); if (button) button.dataset.modelId = id;
  }

  variant(event) {
    const card = event.currentTarget.closest('[data-group-id]');
    const id = event.currentTarget.value;
    if (!card || !this.variantIds(card).includes(id)) return;
    const selected = this.variantIds(card).includes(this.modelTarget.value);
    card.dataset.preferredId = id;
    if (this.hasQualityTarget && this.qualityTarget.value && this.qualityTarget.value !== this.modelsValue[id].quality) this.qualityTarget.value = '';
    this.renderVariant(card, id);
    if (selected) {
      this.modelTarget.value = id;
      this.modelTarget.dispatchEvent(new Event('change',{bubbles:true}));
    }
    this.filter();
  }

  showFourK() {
    this.catalogTarget.open = true;
    this.qualityTarget.value = '4k'; this.searchTarget.value = ''; this.providerTarget.value = '';
    this.audioTarget.value = ''; this.minimumTarget.value = ''; this.onlyConnectedTarget.checked = false;
    this.onlyFavoritesTarget.checked = false; this.comparisonTarget.value = '6';
    this.filter(); this.qualityTarget.focus();
  }

  async favorite(event) {
    event.preventDefault();
    const form = event.currentTarget, button = form.querySelector('button'), status = form.querySelector('[role="status"]');
    button.disabled = true; status.textContent = '';
    try {
      const response = await fetch(form.action,{method:'POST',body:new FormData(form),headers:{Accept:'application/json'},credentials:'same-origin',redirect:'error'});
      if (!response.ok) throw new Error('favorite');
      const data = await response.json();
      if (typeof data.favorite !== 'boolean') throw new Error('favorite');
      form.closest('[data-group-id]').dataset.favorite = String(data.favorite);
      this.markFavoriteOptions(form.closest('[data-group-id]'), data.favorite);
      form.querySelector('[name="favorite"]').value = data.favorite ? '0' : '1';
      button.setAttribute('aria-pressed', String(data.favorite)); button.textContent = data.favorite ? '★ En favori' : '☆ Favori';
      this.filter();
    } catch { status.textContent = 'Enregistrement impossible. Réessaie.'; }
    finally { button.disabled = false; }
  }

  restrict(select, allowed) {
    Array.from(select.options).forEach(option => {
      option.disabled = Boolean(option.value && allowed && !allowed.map(String).includes(option.value));
      option.hidden = option.disabled;
    });
    if (allowed && !allowed.map(String).includes(select.value)) select.value = '';
  }

  markFavoriteOptions(card, favorite) {
    const ids = this.variantIds(card);
    if (this.hasPickerTarget) {
      const option = Array.from(this.groupTarget.options).find(option => option.value === card.dataset.groupId);
      if (option) option.textContent = `${favorite ? '★ ' : ''}${option.textContent.replace(/^★\s*/u, '')}`;
      if (this.hasPickerResultsTarget) {
        const label = this.pickerResultTargets.find(button => button.dataset.groupId === card.dataset.groupId)?.querySelector('[data-result-label]');
        if (label) label.textContent = `${favorite ? '★ ' : ''}${label.textContent.replace(/^★\s*/u, '')}`;
        this.searchPicker();
      }
    }
    Array.from(this.modelTarget.options).forEach(option => {
      if (ids.includes(option.value)) option.textContent = `${favorite ? '★ ' : ''}${option.textContent.replace(/^★\s*/u, '')}`;
    });
  }

  choose(event) {
    const id = event.currentTarget.dataset.modelId;
    if (!this.modelsValue[id]) return;
    this.modelTarget.value = id;
    this.modelTarget.dispatchEvent(new Event('change', { bubbles: true }));
    if (this.hasModelDetailsTarget && event.currentTarget.classList.contains('creation-recommendation')) this.modelDetailsTarget.open = false;
    if (this.hasConnectionTarget && !this.connectionTarget.hidden) {
      this.connectionTarget.focus();
    } else {
      this.durationTarget.focus();
    }
  }

  filter() {
    const query = this.searchTarget.value.trim().toLocaleLowerCase('fr');
    let count = 0;
    this.cardTargets.forEach(card => {
      const matches = this.variantIds(card).filter(id => {
      const model = this.modelsValue[id];
      return Boolean(model) && this.modelMode(model) === this.sourceMode() && (!model.image || !this.hasUseEndTarget || !this.useEndTarget.checked || model.endImageField) && `${model.name} ${model.maker} ${model.provider} ${model.quality}`.toLocaleLowerCase('fr').includes(query)
        && (!this.providerTarget.value || model.provider === this.providerTarget.value)
        && (!this.audioTarget.value || model.audio === (this.audioTarget.value === 'yes'))
        && (!this.minimumTarget.value || Math.max(...model.seconds) >= Number(this.minimumTarget.value))
        && (!this.hasQualityTarget || !this.qualityTarget.value || model.quality === this.qualityTarget.value)
        && (!this.onlyConnectedTarget.checked || this.connectedValue[model.provider])
        && (!this.hasOnlyFavoritesTarget || !this.onlyFavoritesTarget.checked || card.dataset.favorite === 'true');
      });
      const visible = matches.length > 0;
      if (visible) this.renderVariant(card, matches.includes(card.dataset.preferredId) ? card.dataset.preferredId : matches.includes(card.dataset.modelId) ? card.dataset.modelId : matches[0]);
      card.hidden = !visible;
      if (visible) count += 1;
    });
    this.countTarget.textContent = `${count} ${count === 1 ? 'modèle' : 'modèles'} / fournisseur`;
    this.emptyTarget.hidden = count !== 0;
    this.emptyTarget.textContent = this.hasOnlyFavoritesTarget && this.onlyFavoritesTarget.checked ? 'Aucun favori ne correspond. Ajoute un favori avec ☆ ou modifie les filtres.' : 'Aucun modèle ne correspond. Modifie les filtres ou affiche les fournisseurs non connectés.';
    const seconds = this.hasComparisonTarget ? Number(this.comparisonTarget.value) : 5;
    const visiblePrices = this.cardTargets.filter(card => !card.hidden).map(card => this.price(this.modelsValue[card.dataset.modelId], seconds)).filter(price => price !== null);
    const minimumPrice = visiblePrices.length ? Math.min(...visiblePrices) : null;
    this.cardTargets.forEach(card => {
      const badge = card.querySelector?.('[data-economy-badge]');
      if (!badge) return;
      badge.hidden = card.hidden || minimumPrice === null || this.price(this.modelsValue[card.dataset.modelId], seconds) !== minimumPrice;
      badge.textContent = `Le moins cher ici · ${seconds} s`;
    });
    if (this.hasSortTarget && this.hasGridTarget) {
      const seconds = Number(this.comparisonTarget.value);
      const ids = Object.keys(this.modelsValue);
      const price = card => this.price(this.modelsValue[card.dataset.modelId], seconds);
      this.cardTargets.slice().sort((a,b) => {
        if (!this.sortTarget.value) return ids.indexOf(a.dataset.groupId || a.dataset.modelId) - ids.indexOf(b.dataset.groupId || b.dataset.modelId);
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
    const cents = model.pricesCents ? model.pricesCents[seconds] : Math.ceil((model.rateMicros * seconds + (model.fixedMicros || 0)) / 10000);
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
    if (this.hasAccessDetailsTarget && !this.accessTarget.value) this.accessDetailsTarget.open = true;
    if (this.hasBillingTarget) {
      const name = this.providersValue?.[model?.provider] || model?.provider || '';
      this.billingTarget.textContent = model ? `${name} · ${mcp ? 'connexion navigateur' : 'clé API'} ${connected ? 'enregistrée' : 'à connecter'} · facturation chez ${name}` : 'Connexion et facturation';
    }
    this.connectTarget.hidden = Boolean(this.hasConnectionTarget || (model && connected));
    this.connectTarget.textContent = mcp ? 'Connecter Fal en gardant l’idée' : 'Ajouter ma clé en gardant l’idée';
    if (this.hasConnectionTarget) {
      this.connectionTarget.hidden = !model || Boolean(connected);
      const name = this.providersValue?.[model?.provider] || model?.provider || '';
      this.connectionMessageTarget.textContent = mcp ? 'Connecte ton compte Fal par navigateur pour utiliser ce modèle avec cet accès.' : `Ajoute ta clé ${name} pour utiliser ce modèle. Ton idée sera conservée.`;
      this.connectionButtonTarget.textContent = mcp ? 'Connecter mon compte Fal' : `Connecter ${name}`;
    }
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
    this.descriptionTarget.textContent = `${seconds} s · ${model.audio ? 'Audio généré' : 'Sans audio'} · ${model.quality === '4k' ? '4K' : model.quality} · Facturé par ${this.providersValue?.[model.provider] || model.provider}`;
  }
}
