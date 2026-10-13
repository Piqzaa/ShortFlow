// Presentation only: one clock selects the phone image and both timelines.
export const EDIT_NAMES = ['edit-0', 'edit-1', 'edit-2', 'edit-3'];
export function loadEditFrames() {
  return Promise.all(EDIT_NAMES.map(name => new Promise(resolve => {
    const image = new Image();
    image.onload = () => resolve(image); image.onerror = () => resolve(null);
    image.src = '/media/edit/' + name + '.webp';
  })));
}
export function editState(progress) {
  const value = Math.max(0, Math.min(1, progress));
  const index = Math.min(3, Math.floor(value * 4));
  return { progress: value, index, local: value === 1 ? 1 : value * 4 - index,
    timecode: '00:0' + Math.min(8, Math.floor(value * 8)) };
}
function crop(c, image, x, y, w, h, zoom = 1) {
  if (!image?.naturalWidth) return;
  const scale = Math.max(w / image.naturalWidth, h / image.naturalHeight) * zoom;
  c.save(); c.beginPath(); c.rect(x, y, w, h); c.clip();
  c.drawImage(image, x + (w - image.naturalWidth * scale) / 2, y + (h - image.naturalHeight * scale) / 2, image.naturalWidth * scale, image.naturalHeight * scale);
  c.restore();
}
export function drawEditScreen(c, frames, progress = 0, fallback = null) {
  const ready = frames.length === 4 && frames.every(Boolean);
  const state = editState(ready ? progress : 0), image = ready ? frames[state.index] : fallback || frames.find(Boolean);
  if (!image) return false;
  c.clearRect(0, 0, 540, 960); c.fillStyle = '#101118'; c.fillRect(0, 0, 540, 960);
  const label = (value, x, y, size, color = '#eeeaf5', weight = 400) => { c.fillStyle = color; c.font = weight + ' ' + size + 'px Arial'; c.fillText(value, x, y); };
  label('vydflow /', 30, 82, 28, '#f0ebfa', 700);
  label('APERÇU DU MONTAGE · 4 PLANS', 30, 112, 12, '#aaa0b9');
  crop(c, image, 26, 145, 488, 560, 1 + state.local * .035);
  c.strokeStyle = '#d9c7ff'; c.lineWidth = 2; c.strokeRect(58, 180, 424, 480);
  c.fillStyle = '#08090cdd'; c.fillRect(60, 610, 420, 48);
  label(['Tout part d’une idée.', 'Trouve le geste.', 'Donne-lui forme.', 'Laisse-la prendre son envol.'][state.index], 76, 641, 23, '#fff', 700);
  label('TON HISTOIRE / 9:16', 30, 747, 16, '#cab5ef');
  for (let i = 0; i < 4; i++) {
    crop(c, frames[i] || fallback, 28 + i * 121, 776, 116, 85);
    if (i === state.index) { c.strokeStyle = '#d4bbff'; c.lineWidth = 3; c.strokeRect(28 + i * 121, 776, 116, 85); }
  }
  c.strokeStyle = '#f1e4ff'; c.lineWidth = 2;
  c.beginPath(); c.moveTo(28 + state.progress * 484, 766); c.lineTo(28 + state.progress * 484, 872); c.stroke();
  label('Sous-titres', 30, 914, 16); label('Cadrage', 240, 914, 16); label(state.timecode, 440, 914, 16);
  return true;
}
