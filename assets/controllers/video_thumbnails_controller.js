import { Controller } from '@hotwired/stimulus';

// Decorative covers: load nearby videos two at a time, retain a small still,
// then release the source so navigation does not compete with video transfers.
export default class extends Controller {
  static targets = ['video'];

  connect() {
    this.start();
    this.hidePage = () => this.stop();
    this.showPage = event => { if (event.persisted) this.start(); };
    window.addEventListener('pagehide', this.hidePage);
    window.addEventListener('pageshow', this.showPage);
  }

  start() {
    this.stopped = false;
    this.queue = [];
    this.active = new Map();
    this.seen = new Set();
    if ('IntersectionObserver' in window) {
      this.observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
          if (!entry.isIntersecting) return;
          this.observer.unobserve(entry.target);
          this.enqueue(entry.target);
        });
      }, {rootMargin:'80px'});
      this.videoTargets.forEach(video => { if (!video.poster) this.observer.observe(video); });
    } else {
      this.videoTargets.forEach(video => this.enqueue(video));
    }
  }

  enqueue(video) {
    if (this.stopped || this.seen.has(video) || video.poster) return;
    this.seen.add(video);
    this.queue.push(video);
    this.drain();
  }

  drain() {
    while (!this.stopped && this.active.size < 2 && this.queue.length) {
      const video = this.queue.shift();
      const ready = () => {
        if (video.seeking || video.readyState < 2) return;
        try {
          const scale = Math.min(1, 360 / video.videoWidth, 480 / video.videoHeight);
          const canvas = document.createElement('canvas');
          canvas.width = Math.max(1, Math.round(video.videoWidth * scale));
          canvas.height = Math.max(1, Math.round(video.videoHeight * scale));
          canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
          video.poster = canvas.toDataURL('image/jpeg', 0.75);
        } catch { /* A missing cover never blocks the video link or navigation. */ }
        this.finish(video);
      };
      const failed = () => this.finish(video);
      const timeout = setTimeout(failed, 10000);
      this.active.set(video, () => {
        clearTimeout(timeout);
        video.removeEventListener('loadeddata', ready);
        video.removeEventListener('seeked', ready);
        video.removeEventListener('error', failed);
        video.pause(); video.removeAttribute('src'); video.preload = 'none'; video.load();
      });
      video.addEventListener('loadeddata', ready);
      video.addEventListener('seeked', ready);
      video.addEventListener('error', failed);
      video.preload = 'metadata';
      video.src = video.dataset.previewSrc;
      video.load();
    }
  }

  finish(video) {
    const cleanup = this.active.get(video);
    if (!cleanup) return;
    this.active.delete(video);
    cleanup();
    this.drain();
  }

  stop() {
    this.stopped = true;
    this.observer?.disconnect();
    this.queue = [];
    this.active?.forEach(cleanup => cleanup());
    this.active?.clear();
  }

  disconnect() {
    this.stop();
    window.removeEventListener('pagehide', this.hidePage);
    window.removeEventListener('pageshow', this.showPage);
  }
}
