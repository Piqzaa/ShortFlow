import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  toggle() {
    const video = this.element.querySelector('video');
    if (!video) return;
    if (!this.element.open) {
      video.pause();
    } else if (video.preload === 'none') {
      video.preload = 'metadata';
      video.load();
    }
  }

  pause() {
    this.element.querySelector('video')?.pause();
  }

  disconnect() {
    this.pause();
  }
}
