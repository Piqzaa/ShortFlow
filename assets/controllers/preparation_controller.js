import { Controller } from '@hotwired/stimulus';
import { proposePlan } from '../preparation-plan.js';

export default class extends Controller {
  static targets = ['total', 'notice', 'continuity', 'continuityHelp', 'continuityPanel', 'single', 'singleAction', 'singleStatus', 'singleButton', 'useTotal', 'recap', 'fixContinuity', 'audioHelp', 'countPanel', 'countInfo', 'planCount', 'countStatus', 'countApply'];
  static values = { models: Object };

  connect() { queueMicrotask(() => { if (this.element.isConnected) { if (this.hasPlanCountTarget && !this.planCountTarget.value && this.editor?.rows().length > 1) this.planCountTarget.value = String(this.editor.rows().length); this.refresh(); } }); }
  get editor() { return this.application.getControllerForElementAndIdentifier(this.element.querySelector('#ai-idea-form'), 'shot-editor'); }
  get model() { return this.modelsValue[this.element.querySelector('[data-creation-target="model"]').value]; }
  get desiredCount() { return this.continuityTarget.value === 'single' ? 1 : this.hasPlanCountTarget ? Number(this.planCountTarget.value) || null : null; }

  guardPending(event) {
    if (this.element.querySelector('[data-plan-reference-busy="true"]')) { event.preventDefault(); event.stopImmediatePropagation(); this.showNotice('Attends la fin de l’import des images avant de préparer la vidéo.'); }
  }

  showNotice(message) {
    this.noticeTarget.hidden = false;
    this.noticeTarget.textContent = message;
    if (this.hasCountInfoTarget) this.countInfoTarget.hidden = true;
  }

  change(event) {
    if (this.hasPlanCountTarget && event.target.matches('[data-preparation-target="continuity"]') && this.continuityTarget.value !== 'single' && this.editor?.rows().length === 1) this.planCountTarget.value = '';
    if (this.continuityTarget.value === 'single' && this.editor?.rows().length === 1 && event.target.matches('[name*="[shots]"][name$="[seconds]"]') && this.model?.seconds.includes(Number(event.target.value))) this.totalTarget.value = event.target.value;
    this.refresh();
    if (this.continuityTarget.value === 'single' && event.target.matches('[data-preparation-target="continuity"]')) this.applySingle();
    if (this.continuityTarget.value !== 'single' && event.target.matches('[data-creation-target="model"]') && this.editor?.rows().every(row => !row.querySelector('textarea').value.trim())) this.redistribute();
  }
  input() { this.refresh(); }
  duration(event) {
    if (this.continuityTarget.value === 'single') {
      const row = this.editor?.rows().length === 1 ? this.editor.rows()[0] : null;
      if (row && this.model?.seconds.includes(Number(this.totalTarget.value))) row.querySelector('select').value = this.totalTarget.value;
      this.refresh();
      this.applySingle();
      return;
    }
    if (this.hasPlanCountTarget && this.desiredCount || this.editor?.rows().every(row => !row.querySelector('textarea').value.trim())) { this.redistribute(event); return; }
    this.refresh();
  }

  applySingle() {
    if (!this.hasSingleActionTarget || !this.model || !this.editor || this.editor.rows().length === 1 || this.singleButtonTarget.disabled) return;
    if (this.element.querySelector('[data-plan-reference-busy="true"]')) { this.showNotice('Attends la fin de l’import des images avant de passer au plan unique. Tes plans restent conservés.'); return; }
    // Use the existing server conversion so references, contexts and text limits
    // are checked before any card is removed. This intent cannot generate video.
    const form = this.singleButtonTarget.form;
    if (!form) return;
    form.requestSubmit(this.singleButtonTarget);
  }

  refresh() {
    this.noticeTarget.hidden = false;
    const model = this.model;
    if (this.hasCountPanelTarget) this.countPanelTarget.hidden = this.continuityTarget.value === 'single';
    if (this.hasCountInfoTarget) this.countInfoTarget.hidden = this.continuityTarget.value === 'single' || !model;
    if (this.hasAudioHelpTarget) {
      this.audioHelpTarget.hidden = !model;
      this.audioHelpTarget.textContent = model?.audio ? 'Audio généré par clip ; le raccord ne transmet pas le son précédent.' : 'Ce modèle génère des clips sans audio.';
    }
    const reference = this.element.querySelector('[data-creation-target="reference"]').value;
    const firstFrame = this.editor?.rows()[0]?.querySelector('[name$="[startReference]"]')?.value;
    const possible = Boolean(model?.image && (reference || firstFrame) && !model.subjectReference && !model.requiresEnd);
    const single = !this.editor || this.editor.rows().length <= 1;
    this.continuityPanelTarget.hidden = false;
    this.singleTarget.hidden = true;
    this.singleTarget.textContent = this.editor?.rows().length === 1 ? 'Un seul clip : aucun enchaînement à régler.' : 'Le découpage sera proposé avec ton modèle.';
    this.fixContinuityTarget.hidden = single || possible || this.continuityTarget.value !== 'linked';
    this.continuityTarget.querySelector('[value="linked"]').disabled = false;
    this.continuityHelpTarget.hidden = this.continuityTarget.value !== 'linked';
    this.continuityHelpTarget.textContent = this.continuityTarget.value === 'linked' ? (possible ? 'Chaque clip repart de la dernière image du précédent. Clips séparés, sans transmission du mouvement ni du son ; continuité parfaite non garantie.' : 'Le raccord par image exige un modèle image et un premier cadre. Les modes texte et personnage ne transmettent pas ce cadre dans ce parcours.') : 'Clips indépendants, assemblés avec des coupes visibles. Sans référence visuelle commune, le personnage peut changer entre les scènes. Pour prolonger une action, choisis un modèle image compatible et une référence.';
    if (this.continuityTarget.value === 'single') this.continuityHelpTarget.textContent = single ? 'Une prise continue demandée en un seul appel. Le modèle peut introduire une coupe ; aucun raccord entre générations.' : 'Passage au plan unique à terminer. Les cartes restent conservées si une durée, une référence ou un texte empêche leur réunion.';
    if (this.hasSingleActionTarget) this.singleActionTarget.hidden = this.continuityTarget.value !== 'single' || !model;
    if (!model || !this.editor) {
      if (!model) {
        this.showNotice('Choisis un modèle pour préparer tes plans.');
        for (const option of this.totalTarget.options) { option.hidden = option.disabled = false; if (option.value) option.textContent = `${option.value} s`; }
        if (this.hasSingleActionTarget) {
          this.singleStatusTarget.textContent = 'Choisis un modèle pour connaître les durées possibles en un seul clip. Tes scènes restent conservées.';
          this.singleButtonTarget.disabled = true;
          this.useTotalTarget.hidden = true;
          if (this.hasRecapTarget) this.recapTarget.disabled = this.continuityTarget.value === 'single';
        }
        if (this.hasPlanCountTarget) {
          this.countStatusTarget.textContent = 'Choisis un modèle avant de répartir la durée et le nombre de plans.';
          for (const option of this.planCountTarget.options) option.hidden = option.disabled = false;
          if (this.hasCountApplyTarget) this.countApplyTarget.disabled = true;
          if (this.hasCountApplyTarget) this.countApplyTarget.hidden = true;
          if (this.hasRecapTarget) this.recapTarget.disabled = true;
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
      this.singleButtonTarget.hidden = single && total === desired && native;
      this.useTotalTarget.hidden = !singleMode || total === desired || !model.seconds.includes(total);
      this.useTotalTarget.textContent = `Utiliser les ${total} s de mes plans`;
      this.singleStatusTarget.textContent = !native ? `La durée actuelle (${desired} s) ne convient pas à ce modèle en un seul clip. Choisis ${allowed} dans « Durée finale souhaitée », ou conserve plusieurs scènes. Tes plans totalisent ${total} s et restent conservés.`
        : mismatch ? `${this.editor.rows().length} plans actuellement : ${total} s au total, pour ${desired} s souhaitées. ${model.seconds.includes(total) ? 'Utilise le bouton ci-dessous pour reprendre leur total sans modifier les scènes.' : `Ce total n’est pas accepté en un seul clip (${allowed}, maximum ${Math.max(...model.seconds)} s). Choisis un modèle acceptant ${total} s ou conserve plusieurs scènes.`}`
          : single && total === desired ? `Plan unique · ${total} s · un seul clip. Tu peux ouvrir le récapitulatif.`
            : `Passage au plan unique à terminer : ${this.editor.rows().length} plan(s) conservés · ${total} s. Corrige les champs signalés puis utilise « Préparer un plan unique ». Aucun texte ni référence n’est supprimé.`;
      if (this.hasRecapTarget) this.recapTarget.disabled = singleMode && (!native || this.editor.rows().length !== 1 || total !== desired);
    }
    const invalid = this.editor.rows().some(row => !model.seconds.includes(Number(row.querySelector('select').value)));
    const proposal = proposePlan(Number(this.totalTarget.value), model.seconds, this.desiredCount);
    this.noticeTarget.textContent = !proposal.parts ? `Ce modèle ne produit pas ce total exact en huit plans maximum. Totaux proches : ${proposal.nearby.join(' ou ')} s. Choisis explicitement la durée souhaitée.` : invalid ? 'Certaines durées ne sont pas compatibles avec ce modèle. Utilise « Répartir la durée » ou ajuste les plans ; tes textes sont conservés.' : total !== Number(this.totalTarget.value) ? `Tes plans totalisent ${total} s pour ${this.totalTarget.value} s souhaitées. Ajuste les plans ou utilise « Répartir la durée ».` : `${total} s réparties en ${this.editor.rows().length} plan(s). Le modèle et les références restent modifiables avant confirmation.`;
    if (singleMode) this.noticeTarget.textContent = `${model.name || 'Ce modèle'} · un seul clip : ${allowed} (maximum ${Math.max(...model.seconds)} s). Seules ces durées sont proposées. Tes scènes actuelles totalisent ${total} s.`;
    if (this.hasPlanCountTarget && !singleMode) {
      const possibleCounts = [];
      for (const option of this.planCountTarget.options) {
        if (!option.value) continue;
        const valid = Boolean(proposePlan(desired, model.seconds, Number(option.value)).parts);
        if (valid) possibleCounts.push(Number(option.value));
        option.hidden = option.disabled = !valid && !option.selected;
        option.textContent = `${option.value} plans${!valid && option.selected ? ' · à ajuster' : ''}`;
      }
      const ready = this.desiredCount && this.editor.rows().length === this.desiredCount && total === desired && !invalid;
      const loss = proposal.parts && this.editor.rows().slice(proposal.parts.length).some(row => this.rowHasContent(row));
      this.countStatusTarget.textContent = !this.desiredCount ? possibleCounts.length ? 'Choisis combien de plans préparer. La durée sera répartie avant la proposition de scénario ; tes scènes actuelles restent conservées.' : `Aucun découpage de ${desired} s en 2 à 8 plans n’est compatible avec ce modèle. Augmente la durée totale ou choisis un plan unique ; tes scènes restent conservées.`
        : !proposal.parts ? `Ce modèle ne répartit pas ${desired} s en ${this.desiredCount} plans. Choisis un autre nombre ou un total proche : ${proposal.nearby.join(' ou ')} s.`
          : loss ? 'Ce nombre retirerait des plans déjà renseignés. Conserve le nombre actuel ou reporte leurs textes et images dans les plans conservés avant de réduire. Rien n’a été effacé.'
          : ready ? `${this.desiredCount} plans · ${this.editor.rows().map(row => row.querySelector('select').value).join(' + ')} = ${desired} s.`
            : `${this.desiredCount} plans proposés : ${proposal.parts.join(' + ')} = ${desired} s. Applique ce découpage pour préparer les cartes.`;
      if (this.hasRecapTarget) this.recapTarget.disabled = !ready;
      if (this.hasCountApplyTarget) {
        this.countApplyTarget.disabled = !this.desiredCount || !proposal.parts || Boolean(loss);
        this.countApplyTarget.hidden = Boolean(ready);
      }
      if (ready) this.noticeTarget.hidden = true;
    }
    if (this.continuityTarget.value === 'linked' && !possible) this.showNotice('Le raccord demande un modèle image compatible et une image de début.');
  }

  planCountChanged(event) { this.redistribute(event); }

  rowHasContent(row) {
    return Boolean(row.querySelector('textarea').value.trim() || row.querySelector('[name$="[referenceFit]"]')?.value === 'contain' || ['startReference', 'endReference', 'imagePrompt', 'sceneDescription', 'translatedIdentity', 'translationSource'].some(name => row.querySelector(`[name$="[${name}]"]`)?.value.trim()));
  }

  countFromCards() {
    if (this.hasPlanCountTarget) this.planCountTarget.value = this.editor.rows().length > 1 ? String(this.editor.rows().length) : '';
    this.refresh();
  }

  prepareStory() {
    if (!this.model || !this.editor) return false;
    const rows = this.editor.rows();
    const total = rows.reduce((sum, row) => sum + Number(row.querySelector('select').value), 0);
    const ready = rows.length === this.desiredCount && total === Number(this.totalTarget.value) && rows.every(row => this.model.seconds.includes(Number(row.querySelector('select').value)));
    if (ready) return true;
    if (this.continuityTarget.value === 'single') { this.singleButtonTarget.focus(); return false; }
    if (!this.desiredCount) { this.refresh(); this.planCountTarget.focus(); return false; }
    return this.redistribute();
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
    if (!this.editor || !this.model) return false;
    if (this.element.querySelector('[data-plan-reference-busy="true"]')) { this.showNotice('Attends la fin de l’import des images avant de modifier le découpage.'); return false; }
    if (this.hasPlanCountTarget && this.continuityTarget.value !== 'single' && !this.desiredCount) { this.refresh(); this.planCountTarget.focus(); return false; }
    const proposal = proposePlan(Number(this.totalTarget.value), this.model.seconds, this.desiredCount);
    if (!proposal.parts) { this.refresh(); return false; }
    const inputs = this.editor.rows().map(row => Object.fromEntries(['startReference','endReference','referenceFit','imagePrompt','sceneDescription','translatedIdentity','translationSource','promptOnly'].map(name=>[name,row.querySelector(`[name$="[${name}]"]`)?.value || ''])));
    if (inputs.slice(proposal.parts.length).some(row => row.sceneDescription.trim())) { this.showNotice('Ce découpage retirerait un résumé de scène. Conserve tes plans ou reporte ce texte avant de changer leur nombre.'); return false; }
    const prompts = this.editor.rows().map(row => row.querySelector('textarea').value);
    if (this.editor.rows().slice(proposal.parts.length).some(row => this.rowHasContent(row))) { this.showNotice('Ce découpage retirerait un plan déjà renseigné. Conserve le nombre actuel ou réaffecte ses textes et images avant de le réduire. Rien n’a été effacé.'); return false; }
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
    this.editor.element.dispatchEvent(new Event('input', { bubbles: true }));
    return true;
  }
}
