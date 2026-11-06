import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['form', 'choices', 'list', 'order', 'prompt', 'empty', 'search', 'noResults', 'upload', 'uploadButton', 'uploadPanel', 'status'];

    connect() { this.ids = this.orderTarget.value.split(',').filter(Boolean); this.selection(); }
    selected() { return [...this.choicesTarget.querySelectorAll('input:checked')].map(input => input.value); }
    selection() {
        const selected = this.selected();
        this.ids = [...this.ids.filter(id => selected.includes(id)), ...selected.filter(id => !this.ids.includes(id))];
        this.render();
        this.search();
    }
    render() {
        this.orderTarget.value = this.ids.join(',');
        this.promptTarget.hidden = this.ids.length > 0;
        this.emptyTarget.hidden = this.choicesTarget.querySelectorAll('input').length > 0;
        this.listTarget.replaceChildren();
        this.ids.forEach((id, index) => {
            const input = [...this.choicesTarget.querySelectorAll('input')].find(field => field.value === id);
            const row = document.createElement('li'), title = document.createElement('strong'), actions = document.createElement('div');
            title.textContent = `${index + 1}. ${input.closest('label').querySelector('span').textContent}`;
            actions.className = 'series-video-order-actions';
            for (const [label, direction, disabled] of [['Monter', -1, index === 0], ['Descendre', 1, index === this.ids.length - 1], ['Retirer', 0, false]]) {
                const button = document.createElement('button'); button.type = 'button'; button.className = 'account-secondary'; button.textContent = label; button.disabled = disabled;
                button.setAttribute('aria-label', `${label} l’épisode ${index + 1}`);
                button.addEventListener('click', () => {
                    if (!direction) { input.checked = false; this.ids.splice(index, 1); } else { [this.ids[index], this.ids[index + direction]] = [this.ids[index + direction], this.ids[index]]; }
                    this.render(); this.changed();
                    this.listTarget.children[Math.min(Math.max(index + direction, 0), this.ids.length - 1)]?.querySelector('button:not(:disabled)')?.focus();
                });
                actions.append(button);
            }
            row.append(title, actions); this.listTarget.append(row);
        });
    }
    changed() { this.formTarget.dispatchEvent(new Event('change', {bubbles: true})); }
    preventSubmit(event) { event.preventDefault(); }
    openImport(event) { event.preventDefault(); this.uploadPanelTarget.open = true; this.uploadTarget.querySelector('input[type="file"]').focus(); }
    search() {
        const query = this.searchTarget.value.trim().toLocaleLowerCase('fr'); let visible = 0;
        for (const row of this.choicesTarget.querySelectorAll('label')) { row.hidden = !row.textContent.toLocaleLowerCase('fr').includes(query); if (!row.hidden) visible++; }
        this.noResultsTarget.hidden = visible > 0 || !query;
    }
    async import(event) {
        event.preventDefault();
        if (this.busy) return;
        this.busy = true; this.uploadButtonTarget.disabled = true;
        this.statusTarget.textContent = 'Import en cours…';
        const submit = this.formTarget.querySelector('button[type="submit"]'); submit.disabled = true;
        try {
            const response = await fetch(this.uploadTarget.action, {method: 'POST', body: new FormData(this.uploadTarget), headers: {'X-Requested-With': 'XMLHttpRequest'}, credentials: 'same-origin'});
            const result = await response.json().catch(() => { throw new Error('La session ou la réponse d’import est indisponible. Vérifie ta connexion et réessaie.'); });
            if (!response.ok || !result.video?.id) throw new Error(result.message || 'L’import n’a pas abouti. Vérifie le fichier et réessaie.');
            const label = document.createElement('label'), input = document.createElement('input'), text = document.createElement('span');
            label.dataset.seriesVideoChoice = ''; input.type = 'checkbox'; input.name = 'video_series[videos][]'; input.value = String(result.video.id); input.checked = true; text.textContent = result.video.title;
            label.append(input, text); this.choicesTarget.append(label); this.searchTarget.value = '';
            this.selection(); this.changed(); this.uploadTarget.reset();
            this.statusTarget.textContent = 'Vidéo importée et sélectionnée. Enregistre ta série pour conserver les épisodes.';
        } catch (error) { this.statusTarget.textContent = error.message || 'Import indisponible. Réessaie.'; }
        finally { this.busy = false; submit.disabled = false; this.uploadButtonTarget.disabled = false; }
    }
}
