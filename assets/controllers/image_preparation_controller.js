import { Controller } from '@hotwired/stimulus';
import { buildImagePrompt } from '../image-preparation.js';

export default class extends Controller {
  static targets = ['prompt', 'propose', 'proposal', 'preview', 'status', 'notice', 'chooseMode', 'tools'];
  static values = { style: String, characters: String, idea: String, ratio: String, mode: String, continuity: String };

  connect() {
    this.form = this.element.closest('form');
    this.root = this.element.closest('[data-controller~="creation"]') || this.form;
    this.changed = event => {
      this.refresh();
      if (this.pending && event.target !== this.previewTarget) {
        this.pending = null;
        this.proposalTarget.hidden = true;
        this.statusTarget.textContent = 'La préparation a changé. Propose de nouvelles consignes avant de remplacer ton texte.';
      }
    };
    this.root.addEventListener('input', this.changed);
    this.root.addEventListener('change', this.changed);
    this.toolsTarget.hidden = false;
    this.refresh();
  }

  field(selector) { return this.root.querySelector(selector); }
  get row() { return this.element.closest('[data-shot-editor-row]'); }
  get mode() { return this.field('[data-creation-target="source"]')?.value || this.field('[data-series-settings-target="source"]')?.value || this.modeValue; }
  get linked() { return (this.field('[name$="[continuity]"]')?.value || this.continuityValue) === 'linked' && [...this.form.querySelectorAll('[data-shot-editor-row]')].indexOf(this.row) > 0; }

  refresh() {
    const subject = this.mode === 'subject';
    this.proposeTarget.disabled = subject || this.linked;
    this.chooseModeTarget.hidden = this.mode !== 'text' || !this.field('[data-creation-target="source"]');
    this.noticeTarget.textContent = subject ? 'Le mode personnage utilise des références d’identité communes, sans image de début propre à chaque plan. Prépare-les dans « Personnages et objets de référence ».'
      : this.linked ? 'En raccord d’image, le début de ce plan vient du clip précédent : aucune nouvelle image de début à préparer ici.'
        : this.mode === 'text' ? 'En mode texte, aucune image n’est envoyée. Tu peux préparer tes consignes ici, puis choisir un mode image et un modèle compatible pour importer le résultat.'
          : 'Ces consignes préparent l’image de début. Relis-les après une modification de scène ; elles ne changent pas le prompt vidéo.';
  }

  build() {
    return buildImagePrompt({
      idea: this.field('[name="ai_idea[prompt]"]')?.value || this.ideaValue,
      scene: this.row.querySelector('textarea').value,
      ratio: this.field('[data-creation-target="ratio"]')?.value || this.ratioValue,
      style: this.styleValue,
      characters: this.charactersValue,
      subjects: [...this.form.querySelectorAll('[data-subject-name]')].filter(input => !input.disabled).map(input => input.value),
    });
  }

  propose() {
    if (this.proposeTarget.disabled) return;
    try {
      const prompt = this.build();
      if (!this.promptTarget.value.trim()) { this.apply(prompt); return; }
      if (this.promptTarget.value === prompt) { this.statusTarget.textContent = 'Ces consignes sont déjà à jour. Tu peux les modifier ou les copier.'; return; }
      this.pending = prompt;
      this.previewTarget.value = prompt;
      this.proposalTarget.hidden = false;
      this.statusTarget.textContent = 'Ton texte est conservé. Compare la proposition avant de choisir « Remplacer mes consignes ».';
      this.previewTarget.focus();
    } catch (error) { this.statusTarget.textContent = error.message; }
  }

  replace() {
    if (!this.pending || this.proposeTarget.disabled) return;
    try {
      if (this.build() !== this.pending) { this.pending = null; this.proposalTarget.hidden = true; this.statusTarget.textContent = 'La scène a changé. Propose de nouvelles consignes.'; return; }
      this.apply(this.pending);
    } catch (error) { this.statusTarget.textContent = error.message; }
  }

  apply(prompt) {
    this.pending = null;
    this.proposalTarget.hidden = true;
    this.promptTarget.value = prompt;
    this.promptTarget.dispatchEvent(new Event('input', { bubbles: true }));
    this.statusTarget.textContent = 'Consignes préparées à partir de tes textes, sans appel IA ni dépense. Relis-les avant de les copier.';
    this.promptTarget.focus();
  }

  async copy() {
    if (!this.promptTarget.value.trim()) { this.statusTarget.textContent = 'Prépare ou écris tes consignes avant de les copier.'; this.promptTarget.focus(); return; }
    try {
      if (!navigator.clipboard?.writeText) throw new Error('clipboard');
      await navigator.clipboard.writeText(this.promptTarget.value);
      this.statusTarget.textContent = 'Consignes copiées. Colle-les dans ton outil d’images et joins tes références de personnages si nécessaire.';
    } catch {
      this.promptTarget.focus(); this.promptTarget.select();
      this.statusTarget.textContent = 'Copie automatique indisponible : le texte est sélectionné. Utilise Ctrl+C ou le menu Copier.';
    }
  }

  chooseMode() {
    const source = this.field('[data-creation-target="source"]');
    if (!source) return;
    source.value = 'image';
    source.dispatchEvent(new Event('change', { bubbles: true }));
    source.focus(); source.scrollIntoView({ block: 'center' });
  }

  disconnect() { this.root.removeEventListener('input', this.changed); this.root.removeEventListener('change', this.changed); }
}
