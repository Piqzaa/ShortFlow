import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['model', 'seconds', 'ratio', 'hint', 'images', 'source', 'endPanel', 'fitPanel', 'role'];
    static values = { models: Object };

    connect() {
        for (const option of this.modelTarget.options || []) {
            const model = this.modelsValue[option.value];
            option.dataset.subject = String(Boolean(model?.subject));
            if (this.hasSourceTarget && model) option.disabled = option.hidden = (model.subject ? 'subject' : model.image ? 'image' : 'text') !== this.sourceTarget.value;
        }
        this.update(false);
    }
    change() { this.update(true); }

    changeSource() {
        const mode = this.sourceTarget.value;
        for (const option of this.modelTarget.options) {
            const model = this.modelsValue[option.value];
            option.disabled = !!model && (model.subject ? 'subject' : model.image ? 'image' : 'text') !== mode;
            option.hidden = option.disabled;
        }
        const model = this.modelsValue[this.modelTarget.value];
        if (model && (model.subject ? 'subject' : model.image ? 'image' : 'text') !== mode) this.modelTarget.value = '';
        this.update(false);
        this.modelTarget.focus();
    }

    update(reset) {
        const model = this.modelsValue[this.modelTarget.value];
        if (this.hasImagesTarget) {
            this.imagesTarget.hidden = false;
            for (const input of this.imagesTarget.querySelectorAll('input[type="file"]')) input.disabled = !model?.image || (input.name?.endsWith('[endImage]') && !model.end && !model.requiresEnd);
        }
        if (!model) { this.hintTarget.textContent = 'Choisis explicitement un modèle compatible ci-dessus. Aucun fournisseur ni tarif n’est sélectionné automatiquement.'; return; }
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
