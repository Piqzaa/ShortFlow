import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['account'];
    connect() { this.update(); }
    update() {
        const selected = [...this.element.querySelectorAll('input[type="checkbox"]:checked')].map(input => input.value);
        for (const row of this.accountTargets) row.hidden = !selected.includes(row.dataset.network);
    }
}
