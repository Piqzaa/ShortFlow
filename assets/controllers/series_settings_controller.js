import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['model', 'seconds', 'ratio', 'hint', 'images', 'source'];
    static values = { models: Object };

    connect() { this.update(false); }
    change() { this.update(true); }

    changeSource() {
        const image = this.sourceTarget.value === 'image';
        for (const option of this.modelTarget.options) {
            const model = this.modelsValue[option.value];
            option.disabled = !!model && !!model.image !== image;
            option.hidden = option.disabled;
        }
        const model = this.modelsValue[this.modelTarget.value];
        if (model && !!model.image !== image) this.modelTarget.value = '';
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
        if (this.hasSourceTarget) this.sourceTarget.value = model.image ? 'image' : 'text';
        for (const [select, values] of [[this.secondsTarget, model.seconds], [this.ratioTarget, model.ratios]]) {
            for (const option of select.options) option.disabled = !values.map(String).includes(option.value);
            if (reset && !values.map(String).includes(select.value)) select.value = String(values[0]);
        }
        this.hintTarget.textContent = model.image
            ? `Image de départ obligatoire. ${model.requiresEnd ? 'Image de fin obligatoire.' : model.end ? 'Image de fin possible.' : 'Image de fin non prise en charge.'}`
            : 'Ce modèle crée la vidéo à partir du texte. Il n’utilise pas d’image de référence.';
    }
}
