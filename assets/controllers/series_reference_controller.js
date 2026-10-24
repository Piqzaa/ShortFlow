import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['file', 'preview', 'frame', 'remove', 'clear', 'status', 'removeRow'];
    static values = { original: String };

    connect() {
        this.form = this.element.closest('form');
        if (this.hasRemoveRowTarget) this.removeRowTarget.hidden = true;
        this.refresh = () => this.render();
        this.form.addEventListener('change', this.refresh);
        this.render();
    }

    select() {
        this.release();
        const file = this.fileTarget.files?.[0];
        if (!file) { this.render(); return; }
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 10485760) {
            this.fileTarget.value = '';
            this.render();
            this.statusTarget.textContent = 'Choisis un JPG, PNG ou WebP de 10 Mo maximum.';
            return;
        }
        this.localUrl = URL.createObjectURL(file);
        this.removeTarget.checked = false;
        this.render();
    }

    remove() {
        this.release();
        this.fileTarget.value = '';
        this.removeTarget.checked = true;
        this.render();
        this.removeTarget.dispatchEvent(new Event('change', { bubbles: true }));
    }

    render() {
        const modelSelect = this.form.querySelector('[data-series-settings-target="model"]');
        const subject = modelSelect?.selectedOptions[0]?.dataset.subject === 'true';
        const ratio = subject ? 'auto' : this.form.querySelector('[data-series-settings-target="ratio"]').value;
        const fit = this.form.querySelector('[name="ai_series[imageFit]"]').value;
        const src = this.localUrl || (!this.removeTarget.checked ? this.originalValue : '');
        if (src && this.previewTarget.getAttribute('src') !== src) this.previewTarget.src = src;
        if (!src) this.previewTarget.removeAttribute('src');
        this.frameTarget.hidden = !src;
        this.clearTarget.hidden = !src;
        const start = this.form.querySelector('[data-series-reference-slot="start"] img');
        const nativeRatio = start?.naturalWidth && start?.naturalHeight ? `${start.naturalWidth} / ${start.naturalHeight}` : '1 / 1';
        this.frameTarget.style.aspectRatio = ratio === 'auto' ? (subject ? `${this.previewTarget.naturalWidth || 1} / ${this.previewTarget.naturalHeight || 1}` : nativeRatio) : ratio.replace(':', ' / ');
        const [a, b] = this.frameTarget.style.aspectRatio.split('/').map(Number);
        this.frameTarget.style.maxWidth = `${Math.min(340, 460 * a / b)}px`;
        this.previewTarget.style.objectFit = subject || fit === 'contain' ? 'contain' : 'cover';
        this.statusTarget.textContent = !src ? (this.removeTarget.checked ? 'Image retirée de cette configuration après enregistrement. Ajoute une autre image avant une prochaine génération avec ce modèle. Les préparations existantes sont conservées.' : 'Choisis une image pour afficher son aperçu.') : subject ? 'Personnage : image entière envoyée, sans imposer le début ni la fin du clip.' : `Aperçu ${ratio === 'auto' ? 'au format de l’image de début' : ratio} · ${fit === 'contain' ? 'image entière avec bandes noires si nécessaire' : 'recadrage centré'}.`;
    }

    release() {
        if (this.localUrl) URL.revokeObjectURL(this.localUrl);
        this.localUrl = null;
    }

    disconnect() {
        this.form.removeEventListener('change', this.refresh);
        this.release();
    }
}
