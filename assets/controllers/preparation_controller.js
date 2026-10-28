import { Controller } from '@hotwired/stimulus';
import { proposePlan } from '../preparation-plan.js';

export default class extends Controller {
  static targets = ['total', 'notice', 'continuity', 'continuityHelp', 'continuityPanel', 'single', 'singleAction', 'singleStatus', 'singleButton', 'useTotal', 'recap', 'fixContinuity', 'audioHelp'];
  static values = { models: Object };

  connect() { queueMicrotask(() => { if (this.element.isConnected) this.refresh(); }); }
  get editor() { return this.application.getControllerForElementAndIdentifier(this.element.querySelector('#ai-idea-form'), 'shot-editor'); }
  get model() { return this.modelsValue[this.element.querySelector('[data-creation-target="model"]').value]; }

  guardPending(event) {
    if (this.element.querySelector('[data-plan-reference-busy="true"]')) { event.preventDefault(); this.noticeTarget.textContent = 'Attends la fin de l’import des images avant de préparer la vidéo.'; }
  }

  change(event) {
    if (this.continuityTarget.value === 'single' && this.editor?.rows().length === 1 && event.target.matches('[name*="[shots]"][name$="[seconds]"]') && this.model?.seconds.includes(Number(event.target.value))) this.totalTarget.value = event.target.value;
    this.refresh();
    if (this.continuityTarget.value !== 'single' && event.target.matches('[data-creation-target="model"]') && this.editor?.rows().every(row => !row.querySelector('textarea').value.trim())) this.redistribute();
  }
  input() { this.refresh(); }
  duration() {
    if (this.continuityTarget.value === 'single') {
      const row = this.editor?.rows().length === 1 ? this.editor.rows()[0] : null;
      if (row && this.model?.seconds.includes(Number(this.totalTarget.value))) row.querySelector('select').value = this.totalTarget.value;
      this.refresh();
      return;
    }
    if (this.editor?.rows().every(row => !row.querySelector('textarea').value.trim())) this.redistribute();
    this.refresh();
  }

  refresh() {
    const model = this.model;
    if (this.hasAudioHelpTarget) this.audioHelpTarget.textContent = model?.audio ? 'Ce modèle génère son propre audio pour chaque clip. Le raccord par image ne transmet pas le son précédent. « Couper le son » agit au montage, sans réduire le coût de génération.' : model ? 'Ce modèle produit des clips sans audio. Tu peux ajouter une bande sonore dans le montage. Le réglage ci-dessous concerne uniquement le son conservé à l’export.' : 'Choisis un modèle pour connaître son audio. Couper le son au montage ne réduit pas le coût de génération.';
    const reference = this.element.querySelector('[data-creation-target="reference"]').value;
    const firstFrame = this.editor?.rows()[0]?.querySelector('[name$="[startReference]"]')?.value;
    const possible = Boolean(model?.image && (reference || firstFrame) && !model.subjectReference && !model.requiresEnd);
    const single = !this.editor || this.editor.rows().length <= 1;
    this.continuityPanelTarget.hidden = false;
    this.singleTarget.hidden = !single;
    this.singleTarget.textContent = this.editor?.rows().length === 1 ? 'Un seul clip : aucun enchaînement à régler.' : 'Le découpage sera proposé avec ton modèle.';
    this.fixContinuityTarget.hidden = single || possible || this.continuityTarget.value !== 'linked';
    this.continuityTarget.querySelector('[value="linked"]').disabled = false;
    this.continuityHelpTarget.textContent = this.continuityTarget.value === 'linked' ? (possible ? 'Chaque clip repart de la dernière image du précédent. Cela aide les raccords, sans garantir un plan continu ni un personnage identique.' : 'Le raccord depuis une image exige un premier cadre et un modèle compatible. Il ne transmet ni le mouvement ni le son du clip précédent.') : 'Clips indépendants, assemblés avec des coupes visibles. Sans référence visuelle commune, le personnage peut changer entre les scènes. Pour prolonger une action, choisis un modèle image compatible et une référence.';
    if (this.continuityTarget.value === 'single') this.continuityHelpTarget.textContent = single ? 'Une prise continue demandée en un seul appel. Le modèle peut introduire une coupe ; aucun raccord entre générations.' : 'Le mode est choisi, mais tes scènes ne sont pas encore réunies. Utilise « Préparer un plan unique » ci-dessous ou conserve plusieurs scènes.';
    if (this.hasSingleActionTarget) this.singleActionTarget.hidden = this.continuityTarget.value !== 'single';
    if (!model || !this.editor) {
      if (!model) {
        for (const option of this.totalTarget.options) { option.hidden = option.disabled = false; if (option.value) option.textContent = `${option.value} s`; }
        if (this.hasSingleActionTarget) {
          this.singleStatusTarget.textContent = 'Choisis un modèle pour connaître les durées possibles en un seul clip. Tes scènes restent conservées.';
          this.singleButtonTarget.disabled = true;
          this.useTotalTarget.hidden = true;
          if (this.hasRecapTarget) this.recapTarget.disabled = this.continuityTarget.value === 'single';
        }
      }
      return;
    }
    const singleMode = this.continuityTarget.value === 'single';
    const desired = Number(this.totalTarget.value);
    for (const option of this.totalTarget.options) {
      if (!option.value) continue;
      const valid = !singleMode || model.seconds.includes(Number(option.value));
      option.hidden = !valid && !option.selected;
      option.disabled = !valid && !option.selected;
      option.textContent = `${option.value} s${!valid && option.selected ? ' · à ajuster pour ce modèle' : ''}`;
    }
    this.editor.modelValue = model;
    for (const row of this.editor.rows()) for (const option of row.querySelector('select').options) {
      const valid = model.seconds.includes(Number(option.value));
      option.disabled = !valid && !option.selected;
      option.hidden = !valid && !option.selected;
    }
    this.editor.update();
    const total = this.editor.rows().reduce((sum, row) => sum + Number(row.querySelector('select').value), 0);
    const allowed = model.seconds.every((seconds, i) => i === 0 || seconds === model.seconds[i - 1] + 1) && model.seconds.length > 2 ? `${Math.min(...model.seconds)} à ${Math.max(...model.seconds)} s` : `${model.seconds.join(' ou ')} s`;
    if (this.hasSingleActionTarget) {
      const native = model.seconds.includes(desired);
      const mismatch = this.editor.rows().length > 1 && total !== desired;
      this.singleButtonTarget.disabled = !native || mismatch;
      this.useTotalTarget.hidden = !singleMode || total === desired || !model.seconds.includes(total);
      this.useTotalTarget.textContent = `Utiliser les ${total} s de mes plans`;
      this.singleStatusTarget.textContent = !native ? `La durée actuelle (${desired} s) ne convient pas à ce modèle en un seul clip. Choisis ${allowed} dans « Durée finale souhaitée », ou conserve plusieurs scènes. Tes plans totalisent ${total} s et restent conservés.`
        : mismatch ? `${this.editor.rows().length} plans actuellement : ${total} s au total, pour ${desired} s souhaitées. ${model.seconds.includes(total) ? 'Utilise le bouton ci-dessous pour reprendre leur total sans modifier les scènes.' : `Ce total n’est pas accepté en un seul clip (${allowed}, maximum ${Math.max(...model.seconds)} s). Choisis un modèle acceptant ${total} s ou conserve plusieurs scènes.`}`
          : `${this.editor.rows().length} plan(s) actuellement · ${total} s. ${single ? total === desired ? 'Durée compatible : tu peux ouvrir le récapitulatif.' : `Utilise « Préparer un plan unique » pour appliquer les ${desired} s choisies à ce clip, ou reprends son total ci-dessous.` : 'Le bouton réunit les prompts et résumés dans l’ordre ; la durée totale est conservée.'} Les détails incompatibles restent protégés ; relis ensuite le récapitulatif.`;
      if (this.hasRecapTarget) this.recapTarget.disabled = singleMode && (!native || this.editor.rows().length !== 1 || total !== desired);
    }
    const invalid = this.editor.rows().some(row => !model.seconds.includes(Number(row.querySelector('select').value)));
    const proposal = proposePlan(Number(this.totalTarget.value), model.seconds);
    this.noticeTarget.textContent = !proposal.parts ? `Ce modèle ne produit pas ce total exact en huit plans maximum. Totaux proches : ${proposal.nearby.join(' ou ')} s. Choisis explicitement la durée souhaitée.` : invalid ? 'Certaines durées ne sont pas compatibles avec ce modèle. Utilise « Répartir la durée » ou ajuste les plans ; tes textes sont conservés.' : total !== Number(this.totalTarget.value) ? `Tes plans totalisent ${total} s pour ${this.totalTarget.value} s souhaitées. Ajuste les plans ou utilise « Répartir la durée ».` : `${total} s réparties en ${this.editor.rows().length} plan(s). Le modèle et les références restent modifiables avant confirmation.`;
    if (singleMode) this.noticeTarget.textContent = `${model.name || 'Ce modèle'} · un seul clip : ${allowed} (maximum ${Math.max(...model.seconds)} s). Seules ces durées sont proposées. Tes scènes actuelles totalisent ${total} s.`;
  }

  usePlanTotal(event) {
    event.preventDefault();
    const total = this.editor?.rows().reduce((sum, row) => sum + Number(row.querySelector('select').value), 0);
    if (!this.model?.seconds.includes(total)) return;
    this.totalTarget.value = String(total);
    this.totalTarget.dispatchEvent(new Event('change', { bubbles: true }));
    this.refresh();
    this.singleButtonTarget.focus();
  }

  imageMode() {
    const source = this.element.querySelector('[data-creation-target="source"]');
    source.value = 'image';
    source.dispatchEvent(new Event('change', { bubbles: true }));
    source.focus();
    source.scrollIntoView({block:'center'});
    this.refresh();
  }

  redistribute(event) {
    event?.preventDefault();
    if (!this.editor || !this.model) return;
    const proposal = proposePlan(Number(this.totalTarget.value), this.model.seconds);
    if (!proposal.parts) { this.refresh(); return; }
    const inputs = this.editor.rows().map(row => Object.fromEntries(['startReference','endReference','referenceFit','imagePrompt','sceneDescription','translatedIdentity','translationSource'].map(name=>[name,row.querySelector(`[name$="[${name}]"]`)?.value || ''])));
    if (inputs.slice(proposal.parts.length).some(row => row.sceneDescription.trim())) { this.noticeTarget.textContent = 'Ce découpage retirerait un résumé de scène. Conserve tes plans ou reporte ce texte avant de changer leur nombre.'; return; }
    if (inputs.length !== proposal.parts.length && inputs.some(row=>row.startReference || row.endReference || row.imagePrompt?.trim())) { this.noticeTarget.textContent = 'Déplace ou retire les cadres et les consignes d’images avant de changer le nombre de plans. Tes textes restent conservés.'; return; }
    const prompts = this.editor.rows().map(row => row.querySelector('textarea').value);
    this.editor.listTarget.replaceChildren();
    proposal.parts.forEach((seconds, index) => {
      this.editor.listTarget.insertAdjacentHTML('beforeend', this.editor.prototypeTarget.innerHTML.replaceAll('__name__', String(index)));
      const row = this.editor.rows()[index];
      row.querySelector('textarea').value = prompts[index] || '';
      row.querySelector('select').value = String(seconds);
      for (const [name,value] of Object.entries(inputs[index] || {})) { const input=row.querySelector(`[name$="[${name}]"]`); if (input) input.value=value; }
    });
    if (prompts.length > proposal.parts.length) this.editor.rows().at(-1).querySelector('textarea').value += '\n' + prompts.slice(proposal.parts.length).join('\n');
    this.refresh();
    if (event) this.editor.element.dispatchEvent(new Event('input', { bubbles: true }));
  }
}
