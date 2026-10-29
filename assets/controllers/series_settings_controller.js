import { Controller } from '@hotwired/stimulus';
import { GuidedModelPicker } from '../guided-model-picker.js';

export default class extends GuidedModelPicker(Controller) {
    static targets = ['model', 'seconds', 'ratio', 'hint', 'images', 'source', 'endPanel', 'fitPanel', 'role', 'card', 'picker', 'native', 'group', 'resolution', 'pickerSearch', 'pickerCount', 'pickerResults', 'pickerResult', 'access', 'billing', 'recommendations', 'recommendation', 'selectedModel', 'modelDetails'];
    static values = { models: Object, connected: Object, providers: Object };

    connect() {
        for (const option of this.modelTarget.options || []) {
            const model = this.modelsValue[option.value];
            option.dataset.subject = String(Boolean(model?.subject));
            option.dataset.referenceMode = model?.subject ? 'subject' : model?.image ? 'image' : 'text';
            option.dataset.subjectLimit = String(model?.subjectLimit || 0);
            option.dataset.acceptsEnd = String(Boolean(model?.end));
            if (this.hasSourceTarget && model) option.disabled = option.hidden = (model.subject ? 'subject' : model.image ? 'image' : 'text') !== this.sourceTarget.value;
        }
        this.setupPicker();
        this.update(false);
        if (this.hasModelDetailsTarget) this.modelDetailsTarget.open = this.modelDetailsTarget.dataset.pickerInvalid === 'true' || !this.recommendationTargets.some(button => button.dataset.modelId === this.modelTarget.value);
    }
    change() { this.update(true); }
    modelMode(model) { return model?.subject || model?.subjectReference ? 'subject' : model?.image ? 'image' : 'text'; }
    sourceMode() { return this.hasSourceTarget ? this.sourceTarget.value : this.modelMode(this.modelsValue[this.modelTarget.value]); }
    choose(event) {
        const id = event.currentTarget.dataset.modelId;
        if (!this.modelsValue[id]) return;
        this.modelTarget.value = id;
        this.modelTarget.dispatchEvent(new Event('change', {bubbles:true}));
        this.ratioTarget.focus();
    }
    accessChanged() {
        this.refreshPickerConnections();
        this.updateRecommendations();
        if (this.hasBillingTarget) this.billingTarget.textContent = this.connectionCaption();
    }
    guardPending(event) {
        if (!this.element.querySelector('[data-plan-reference-busy="true"]')) return;
        event.preventDefault();
        event.stopImmediatePropagation?.();
        this.hintTarget.textContent = 'Attends la fin de l’import des références avant d’enregistrer la série.';
        this.hintTarget.tabIndex = -1;
        this.hintTarget.focus();
    }

    changeSource() {
        const mode = this.sourceTarget.value;
        for (const option of this.modelTarget.options) {
            const model = this.modelsValue[option.value];
            option.disabled = !!model && (model.subject ? 'subject' : model.image ? 'image' : 'text') !== mode;
            option.hidden = option.disabled;
        }
        const model = this.modelsValue[this.modelTarget.value];
        if (model && (model.subject ? 'subject' : model.image ? 'image' : 'text') !== mode) this.modelTarget.value = '';
        this.setupPicker();
        this.update(false);
        if (this.hasPickerTarget) this.groupTarget.focus(); else this.modelTarget.focus();
    }

    update(reset) {
        this.refreshPickerAvailability();
        this.updateRecommendations();
        if (this.hasBillingTarget) this.billingTarget.textContent = this.connectionCaption();
        const model = this.modelsValue[this.modelTarget.value];
        if (this.hasImagesTarget) {
            this.imagesTarget.hidden = false;
            for (const input of this.imagesTarget.querySelectorAll('input[type="file"][name]')) input.disabled = input.name?.endsWith('[subjectImages][]') || input.name?.endsWith('[subjectImages]') ? !(model?.subjectLimit > 0) : !model?.image || (input.name?.endsWith('[endImage]') && !model.end && !model.requiresEnd);
        }
        if (!model) { this.hintTarget.textContent = 'Choisis explicitement un modèle compatible ci-dessus. Aucun fournisseur ni tarif n’est sélectionné automatiquement.'; return; }
        if (this.hasAccessTarget) {
            for (const option of this.accessTarget.options) option.disabled = option.value === 'mcp' && model.provider !== 'fal';
            if (reset && model.provider !== 'fal') this.accessTarget.value = 'api';
            this.accessChanged();
        }
        if (this.hasSourceTarget) this.sourceTarget.value = model.subject ? 'subject' : model.image ? 'image' : 'text';
        if (this.hasEndPanelTarget) this.endPanelTarget.hidden = Boolean(model.subject);
        if (this.hasFitPanelTarget) this.fitPanelTarget.hidden = Boolean(model.subject);
        if (this.hasImagesTarget) {
            const label = this.imagesTarget.querySelector?.('label');
            if (label) label.textContent = model.subject ? 'Image du personnage' : 'Début du premier plan';
        }
        for (const [select, values] of [[this.secondsTarget, model.seconds], [this.ratioTarget, model.ratios]]) {
            for (const option of select.options) option.disabled = !values.map(String).includes(option.value);
            if (reset && !values.map(String).includes(select.value)) select.value = String(values[0]);
        }
        this.hintTarget.textContent = model.subject ? 'Référence de personnage obligatoire. Le modèle reçoit son apparence, sans première ni dernière image imposée. Identité non garantie ; scènes indépendantes.' : model.image
            ? `Image de départ obligatoire. ${model.requiresEnd ? 'Image de fin obligatoire.' : model.end ? 'Image de fin possible.' : 'Image de fin non prise en charge.'}`
            : 'Ce modèle crée la vidéo à partir du texte. Il n’utilise pas d’image de référence.';
    }
}
