// Six original keyframes, not a live generation feature.
export const FRAME_NAMES = Array.from({ length: 6 }, (_, i) => 'horizon-' + i);
export function loadFrames() {
  return Promise.all(FRAME_NAMES.map(name => new Promise(resolve => {
    const image = new Image();
    image.onload = () => resolve(image);
    image.onerror = () => resolve(null);
    image.src = '/media/horizon/' + name + '.webp';
  })));
}
export function drawSequence(c, frames, progress, x = 0, y = 0, w = 540, h = 960) {
  const position = Math.max(0, Math.min(1, progress)) * 5;
  const index = Math.floor(position), blend = position - index;
  const first = frames[index] || frames.find(Boolean), next = frames[Math.min(5, index + 1)];
  if (!first) return false;
  c.save();
  c.fillStyle = '#041322'; c.fillRect(x, y, w, h);
  c.drawImage(first, x, y, w, h);
  if (next && blend > 0) { c.globalAlpha = blend; c.drawImage(next, x, y, w, h); }
  c.restore();
  return true;
}
