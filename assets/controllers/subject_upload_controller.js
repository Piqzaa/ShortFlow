import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['input', 'preview', 'status'];
  connect() { this.urls = []; this.render(); }
  render() {
    this.release(); this.previewTarget.replaceChildren();
    const files = [...(this.inputTarget.files || [])];
    this.statusTarget.textContent = files.length ? `${files.length} nouvelle(s) référence(s). Images entières, sans cadre imposé.` : '';
    if (files.length > 7 || files.some(file => !['image/jpeg','image/png','image/webp'].includes(file.type) || file.size > 10485760)) {
      this.statusTarget.textContent = 'Choisis au maximum sept images JPG, PNG ou WebP de 10 Mo maximum. La limite exacte dépend du modèle et des références conservées.';
      return;
    }
    files.forEach((file,index) => {
      const item = document.createElement('div'); item.className = 'story-context';
      const image = document.createElement('img'); image.alt = `Nouvelle référence ${index+1}`; image.className = 'series-reference';
      const url = URL.createObjectURL(file); this.urls.push(url); image.src = url;
      const button = document.createElement('button'); button.type = 'button'; button.className = 'account-secondary'; button.textContent = typeof DataTransfer === 'undefined' ? 'Effacer les nouvelles références' : `Retirer la nouvelle référence ${index+1}`;
      button.addEventListener('click', () => {
        if (typeof DataTransfer === 'undefined') this.inputTarget.value = '';
        else {
          const transfer = new DataTransfer(); files.forEach((candidate,n) => { if (n !== index) transfer.items.add(candidate); });
          this.inputTarget.files = transfer.files;
        }
        this.inputTarget.dispatchEvent(new Event('change',{bubbles:true}));
      });
      item.append(image,button); this.previewTarget.append(item);
    });
  }
  release() { (this.urls || []).forEach(url => URL.revokeObjectURL(url)); this.urls = []; }
  disconnect() { this.release(); }
}
