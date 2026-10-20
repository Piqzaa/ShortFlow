import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['message', 'title', 'detail', 'checked', 'step', 'indicator'];
  static values = {url:String, status:String, revision:String};

  connect() {
    this.failures = 0;
    this.delay = 4000;
    this.disposed = false;
    this.visibility = () => {
      clearTimeout(this.timer);
      if (document.hidden) this.request?.abort();
      else if (this.failures < 3) this.poll();
    };
    document.addEventListener('visibilitychange', this.visibility);
    this.schedule();
  }

  schedule() {
    if (this.disposed || document.hidden || this.failures >= 3 || !['queued','submitting','processing'].includes(this.statusValue)) return;
    this.timer = setTimeout(() => this.poll(), this.delay);
  }

  async poll() {
    if (this.disposed || document.hidden || this.request) return;
    const request = this.request = new AbortController();
    const timeout = setTimeout(() => request.abort('timeout'), 10000);
    try {
      const response = await fetch(this.urlValue, {method:'GET', headers:{Accept:'application/json'}, credentials:'same-origin', cache:'no-store', redirect:'error', signal:request.signal});
      if (!response.ok) throw new Error('status');
      const state = await response.json();
      if (!['prepared','queued','submitting','processing','waiting_key','review','available'].includes(state.status) || typeof state.updatedAt !== 'string') throw new Error('status');
      if (this.disposed || document.hidden) return;
      if (state.stage != null && !['queued','running','importing'].includes(state.stage)) throw new Error('stage');
      if (state.checkedAt != null && !Number.isFinite(Date.parse(state.checkedAt))) throw new Error('checkedAt');
      if (!['queued','submitting','processing'].includes(state.status)) {
        this.disposed = true;
        window.location.reload();
        return;
      }
      this.failures = 0;
      this.delay = 4000;
      this.render(state);
      const message = 'Suivi automatique actif · tu peux quitter cette page.';
      if (this.messageTarget.textContent !== message) this.messageTarget.textContent = message;
      this.indicatorTarget.classList.remove('is-paused');
    } catch {
      if (request.signal.aborted && request.signal.reason !== 'timeout') return;
      if (this.disposed) return;
      this.failures++;
      this.delay = 20000;
      if (this.failures >= 3) this.indicatorTarget.classList.add('is-paused');
      this.messageTarget.textContent = this.failures >= 3 ? 'Le suivi automatique est indisponible. Utilise « Actualiser le suivi » ; la génération peut continuer en arrière-plan.' : 'Suivi momentanément indisponible. Nouvelle vérification automatique dans quelques instants.';
    } finally {
      clearTimeout(timeout);
      this.request = null;
      this.schedule();
    }
  }

  render(state) {
    this.statusValue = state.status;
    this.revisionValue = state.updatedAt;
    let step = 0;
    let title = 'Préparation de ta génération';
    let detail = 'La demande attend son envoi au fournisseur.';
    if (state.status === 'submitting') {
      title = 'Envoi au fournisseur';
      detail = 'Les réglages et les images sont transmis une seule fois.';
    } else if (state.status === 'processing') {
      step = state.stage === 'importing' ? 2 : 1;
      const phases = {
        queued:['Dans la file du fournisseur', 'Ta demande est acceptée. Le fournisseur attend une place disponible.'],
        running:['La vidéo prend forme', 'Le fournisseur confirme que la génération est en cours.'],
        importing:['Ta vidéo arrive', 'La génération est terminée. VydFlow récupère et vérifie le fichier dans ta bibliothèque.'],
      };
      [title, detail] = phases[state.stage] || ['Demande acceptée', 'En attente du prochain retour du fournisseur.'];
    }
    if (this.titleTarget.textContent !== title) this.titleTarget.textContent = title;
    if (this.detailTarget.textContent !== detail) this.detailTarget.textContent = detail;
    this.indicatorTarget.setAttribute('aria-valuetext', title);
    this.stepTargets.forEach((item, index) => {
      item.classList.toggle('is-current', index === step);
      item.classList.toggle('is-done', index < step);
      if (index === step) item.setAttribute('aria-current', 'step');
      else item.removeAttribute('aria-current');
    });
    this.checkedTarget.textContent = state.checkedAt
      ? `Dernier retour fournisseur à ${new Date(state.checkedAt).toLocaleTimeString('fr-FR', {hour:'2-digit',minute:'2-digit',second:'2-digit'})}.`
      : 'En attente du premier retour fournisseur.';
  }

  disconnect() {
    this.disposed = true;
    clearTimeout(this.timer);
    this.request?.abort();
    document.removeEventListener('visibilitychange', this.visibility);
  }
}
