import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['form', 'choices', 'choiceTemplate', 'list', 'order', 'orderStatus', 'prompt', 'empty', 'search', 'noResults', 'upload', 'uploadButton', 'uploadPanel', 'status'];

    connect() { this.ids = this.orderTarget.value.split(',').filter(Boolean); this.selection(); }
    selected() { return [...this.choicesTarget.querySelectorAll('input:checked')].map(input => input.value); }
    selection() {
        const selected = this.selected();
        this.ids = [...this.ids.filter(id => selected.includes(id)), ...selected.filter(id => !this.ids.includes(id))];
        this.render();
        this.search();
    }
    render() {
        this.cancelDrag();
        this.orderTarget.value = this.ids.join(',');
        this.promptTarget.hidden = this.ids.length > 0;
        this.emptyTarget.hidden = this.choicesTarget.querySelectorAll('input').length > 0;
        this.listTarget.replaceChildren();
        const inputs = new Map([...this.choicesTarget.querySelectorAll('input')].map(input => [input.value, input]));
        this.ids.forEach((id, index) => {
            const input = inputs.get(id);
            const row = document.createElement('li'), title = document.createElement('strong'), actions = document.createElement('div');
            const heading = document.createElement('div');
            const handle = document.createElement('button');
            heading.className = 'series-video-order-heading';
            handle.type = 'button';
            handle.className = 'series-video-grip account-secondary';
            handle.textContent = '⠿';
            handle.setAttribute('aria-label', `Déplacer l’épisode ${index + 1}`);
            handle.setAttribute('aria-keyshortcuts', 'ArrowUp ArrowDown');
            handle.addEventListener('pointerdown', event => this.startDrag(event, index));
            handle.addEventListener('pointermove', event => this.dragMove(event));
            handle.addEventListener('pointerup', event => this.drop(event));
            handle.addEventListener('pointercancel', () => this.cancelDrag());
            handle.addEventListener('lostpointercapture', () => this.cancelDrag());
            handle.addEventListener('keydown', event => {
                if (event.key !== 'ArrowUp' && event.key !== 'ArrowDown') return;
                event.preventDefault();
                this.move(index, index + (event.key === 'ArrowUp' ? -1 : 1), '.series-video-grip');
            });
            title.textContent = `${index + 1}. ${input.closest('label').querySelector('[data-series-video-title]').textContent}`;
            heading.append(handle, title);
            actions.className = 'series-video-order-actions';
            for (const [label, direction, disabled] of [['Monter', -1, index === 0], ['Descendre', 1, index === this.ids.length - 1], ['Retirer', 0, false]]) {
                const button = document.createElement('button'); button.type = 'button'; button.className = 'account-secondary'; button.textContent = label; button.disabled = disabled;
                button.setAttribute('aria-label', `${label} l’épisode ${index + 1}`);
                button.addEventListener('click', () => {
                    if (direction) {
                        this.move(index, index + direction, `.series-video-order-actions button:nth-child(${direction < 0 ? 1 : 2}):not(:disabled)`);
                        return;
                    }
                    input.checked = false;
                    this.ids.splice(index, 1);
                    this.render(); this.changed();
                    this.listTarget.children[Math.min(index, this.ids.length - 1)]?.querySelector('button')?.focus();
                    if (!this.ids.length) input.focus();
                });
                actions.append(button);
            }
            row.append(heading, actions); this.listTarget.append(row);
        });
    }
    move(from, to, focus) {
        if (to < 0 || to >= this.ids.length || from === to) return;
        const [id] = this.ids.splice(from, 1);
        this.ids.splice(to, 0, id);
        this.render();
        this.changed();
        this.orderStatusTarget.textContent = `Épisode déplacé en position ${to + 1} sur ${this.ids.length}.`;
        const row = this.listTarget.children[to];
        (row.querySelector(focus) || row.querySelector('.series-video-grip')).focus();
    }

    startDrag(event, index) {
        if (!event.isPrimary || event.button !== 0 || this.ids.length < 2 || this.drag) return;
        const handle = event.currentTarget;
        handle.setPointerCapture(event.pointerId);
        this.drag = {index, id: event.pointerId, start: event.clientY, y: event.clientY, handle, target: index};
    }

    dragMove(event) {
        if (!this.drag || event.pointerId !== this.drag.id) return;
        this.drag.y = event.clientY;
        if (!this.drag.active && Math.abs(this.drag.y - this.drag.start) < 6) return;
        if (!this.drag.active) {
            this.drag.active = true;
            this.listTarget.children[this.drag.index].classList.add('is-dragged');
            this.trackDrag();
        } else this.markDrop(this.drag);
    }

    trackDrag() {
        const drag = this.drag;
        if (!drag?.active) return;
        const edge = Math.min(75, window.innerHeight / 5);
        if (drag.y < edge) window.scrollBy(0, -12);
        if (drag.y > window.innerHeight - edge) window.scrollBy(0, 12);
        this.markDrop(drag);
        drag.frame = requestAnimationFrame(() => this.trackDrag());
    }

    markDrop(drag) {
        const rows = [...this.listTarget.children];
        let insertion = rows.findIndex(row => {
            const bounds = row.getBoundingClientRect();
            return drag.y < bounds.top + bounds.height / 2;
        });
        if (insertion === -1) insertion = rows.length;
        const target = insertion > drag.index ? insertion - 1 : insertion;
        if (target === drag.target) return;
        drag.target = target;
        drag.marker?.classList.remove('drop-before', 'drop-after');
        drag.marker = null;
        if (drag.target !== drag.index) {
            drag.marker = rows[drag.target];
            drag.marker.classList.add(drag.target < drag.index ? 'drop-before' : 'drop-after');
        }
    }

    drop(event) {
        if (!this.drag || event.pointerId !== this.drag.id) return;
        this.drag.y = event.clientY;
        if (this.drag.active) this.markDrop(this.drag);
        const {index, target, active} = this.drag;
        this.cancelDrag();
        if (active) this.move(index, target, '.series-video-grip');
    }

    cancelDrag() {
        if (!this.drag) return;
        const drag = this.drag;
        this.drag = null;
        cancelAnimationFrame(drag.frame);
        this.listTarget.children[drag.index]?.classList.remove('is-dragged');
        drag.marker?.classList.remove('drop-before', 'drop-after');
        if (drag.handle.hasPointerCapture(drag.id)) drag.handle.releasePointerCapture(drag.id);
    }

    disconnect() { this.cancelDrag(); }
    changed() { this.orderTarget.dispatchEvent(new Event('input', {bubbles: true})); }
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
            const choice = this.choiceTemplateTarget.content.cloneNode(true);
            const input = choice.querySelector('input');
            input.value = String(result.video.id);
            input.checked = true;
            choice.querySelector('video').dataset.previewSrc = result.video.preview;
            choice.querySelector('[data-series-video-title]').textContent = result.video.title;
            this.choicesTarget.append(choice); this.searchTarget.value = '';
            this.selection(); this.changed(); this.uploadTarget.reset();
            this.statusTarget.textContent = 'Vidéo importée et sélectionnée. Enregistre ta série pour conserver les épisodes.';
        } catch (error) { this.statusTarget.textContent = error.message || 'Import indisponible. Réessaie.'; }
        finally { this.busy = false; submit.disabled = false; this.uploadButtonTarget.disabled = false; }
    }
}
