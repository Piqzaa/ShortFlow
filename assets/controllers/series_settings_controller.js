import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['model', 'seconds', 'ratio', 'hint', 'images'];
    static values = { models: Object };

    connect() { this.update(false); }
    change() { this.update(true); }

    update(reset) {
        const model = this.modelsValue[this.modelTarget.value];
        if (!model) return;
        if (this.hasImagesTarget) {
            this.imagesTarget.hidden = !model.image;
            for (const input of this.imagesTarget.querySelectorAll('input[type="file"]')) input.disabled = !model.image;
        }
        for (const [select, values] of [[this.secondsTarget, model.seconds], [this.ratioTarget, model.ratios]]) {
            for (const option of select.options) option.disabled = !values.map(String).includes(option.value);
            if (reset && !values.map(String).includes(select.value)) select.value = String(values[0]);
        }
        this.hintTarget.textContent = model.image
            ? `Image de départ obligatoire. ${model.requiresEnd ? 'Image de fin obligatoire.' : model.end ? 'Image de fin possible.' : 'Image de fin non prise en charge.'}`
            : 'Ce modèle crée la vidéo à partir du texte. Il n’utilise pas d’image de référence.';
    }
}
