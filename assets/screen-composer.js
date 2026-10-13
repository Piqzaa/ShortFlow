// Presentation assets only. This module does not implement editing or publishing.
export const MEDIA = ['dance', 'object', 'architecture', 'backstage', 'portrait', 'landscape'];
export async function loadMedia() {
  const pairs = await Promise.all([...MEDIA, 'manta', 'river'].map(name => new Promise(resolve => {
    const image = new Image(); image.onload = () => resolve([name, image]); image.onerror = () => resolve([name, null]);
    image.src = '/media/creators/' + name + '.webp';
  })));
  return Object.fromEntries(pairs);
}
export function cover(c, image, x, y, w, h, zoom = 1, offset = 0) {
  if (!image || !(image.videoWidth || image.naturalWidth)) return;
  const iw = image.videoWidth || image.naturalWidth, ih = image.videoHeight || image.naturalHeight;
  const scale = Math.max(w / iw, h / ih) * zoom;
  c.save(); c.beginPath(); c.rect(x, y, w, h); c.clip();
  c.drawImage(image, x + (w - iw * scale) / 2 + offset, y + (h - ih * scale) / 2, iw * scale, ih * scale); c.restore();
}
const text = (c, value, x, y, size = 18, color = '#eeeaf5', weight = 400) => {
  c.fillStyle = color; c.font = weight + ' ' + size + 'px Arial'; c.fillText(value, x, y);
};
function shade(c, y = 550) { const g = c.createLinearGradient(0, y, 0, 960); g.addColorStop(0, '#08090c00'); g.addColorStop(1, '#08090cf5'); c.fillStyle = g; c.fillRect(0, y, 540, 960 - y); }
export function drawFilm(c, images, seconds, variant = 'hero') {
  c.fillStyle = '#09090d'; c.fillRect(0, 0, 540, 960);
  const sequences = { hero: ['dance', 'object', 'architecture'], tiktok: ['dance', 'backstage'], reels: ['object', 'portrait'], shorts: ['architecture', 'landscape'] };
  const sequence = sequences[variant] || sequences.hero, duration = variant === 'hero' ? 8 : 5;
  const time = Math.max(0, seconds) % duration, index = Math.min(sequence.length - 1, Math.floor(time / duration * sequence.length));
  const local = time / duration * sequence.length - index;
  cover(c, images[sequence[index]], 0, 0, 540, 960, 1.02 + local * .09, Math.sin(local * Math.PI) * 7);
  shade(c);
  text(c, 'VYDFLOW / CREATOR FILMS', 34, 80, 13);
  if (variant === 'hero') {
    const titles = [['Le mouvement.', 'À ta façon.'], ['Une autre', 'perspective.'], ['Ouvre', 'le cadre.']];
    text(c, titles[index][0], 32, 765, 52, '#fff', 700); text(c, titles[index][1], 32, 825, 52, '#fff', 700);
  } else if (variant === 'tiktok') {
    text(c, 'LE RYTHME AVANT TOUT', 34, 700, 17, '#e3c7ff');
    text(c, index ? 'Les coulisses' : 'Trouve ton', 32, 764, 49, '#fff', 700);text(c, index ? 'du mouvement.' : 'propre rythme.', 32, 819, 49, '#fff', 700);
    c.fillStyle = '#b498ff'; c.fillRect(34, 854, 250 * local + 30, 4);
  } else if (variant === 'reels') {
    text(c, 'STUDIO JOURNAL / 002', 34, 700, 16, '#e2d9e5');
    text(c, index ? 'Une présence.' : 'Le détail', 32, 772, 53, '#fff', 700);text(c, index ? 'Une signature.' : 'change tout.', 32, 830, 53, '#fff', 700);
  } else {
    c.fillStyle = '#ece8de'; c.fillRect(30, 700, 480, 60);text(c, 'CHANGE DE PERSPECTIVE', 47, 739, 23, '#151316', 700);
    text(c, index ? 'Va plus loin.' : 'Lève les yeux.', 32, 829, 49, '#fff', 700);
  }
  text(c, 'CRÉATION ORIGINALE · FILM DE DÉMONSTRATION', 34, 918, 10, '#d5ccd9');
}
export function drawScreen(c, images, mode, phase = 0, video = null) {
  c.clearRect(0, 0, 540, 960); c.fillStyle = '#101118'; c.fillRect(0, 0, 540, 960);
  if (mode === 0) {
    if (video?.readyState >= 2) cover(c, video, 0, 0, 540, 960);
    else drawFilm(c, images, phase * 8, 'hero');

    return;
  }
  if (mode === 5) {
    cover(c, images.river, 0, 0, 540, 570);
    c.fillStyle = '#101118'; c.fillRect(0, 570, 540, 390);
    text(c, 'VYDFLOW / À TOI DE CRÉER', 34, 628, 15, '#bda9dd');
    text(c, 'TON PROCHAIN', 34, 703, 22, '#dbc9ff');
    text(c, 'mouvement.', 30, 774, 61, '#fff', 700);
    text(c, 'commence ici /', 34, 835, 32);
    c.fillStyle = '#bda9dd'; c.fillRect(34, 887, 62, 3);
    return;
  }
  text(c, 'vydflow /', 30, 82, 28, '#f0ebfa', 700);text(c, 'APERÇU CONCEPTUEL', 30, 112, 12, '#aaa0b9');
  if (mode === 1) {
    cover(c, images.dance, 26, 145, 488, 560, 1.03);
    c.strokeStyle = '#d9c7ff'; c.lineWidth = 2; c.strokeRect(58, 180, 424, 480);
    c.fillStyle = '#08090cbd'; c.fillRect(84, 578, 372, 45);text(c, 'Trouve ton propre rythme.', 99, 608, 23, '#fff', 700);
    text(c, 'LE MONTAGE / 9:16', 30, 747, 16, '#cab5ef');
    for (let i = 0; i < 6; i++) cover(c, images[i < 4 ? 'dance' : 'backstage'], 28 + i * 81, 776, 77, 85);
    c.strokeStyle = '#d4bbff';c.beginPath();c.moveTo(195 + phase * 150, 766);c.lineTo(195 + phase * 150, 872);c.stroke();
    text(c, 'Sous-titres', 30, 914, 16);text(c, 'Cadrage', 240, 914, 16);text(c, '00:08', 440, 914, 16);
  } else if (mode === 2) {
    text(c, 'UNE IDÉE, TROIS VERSIONS.', 30, 165, 24, '#ddd0f5', 700);
    ['dance', 'object', 'architecture'].forEach((name, i) => {
      cover(c, images[name], 28 + i * 169, 214, 147, 390);
      text(c, ['TikTok', 'Reels', 'Shorts'][i], 33 + i * 169, 637, 21, '#fff', 700);
    });
    text(c, 'Ton rythme. Ton univers.', 30, 730, 29, '#f4effb', 700);text(c, 'Ta prochaine histoire.', 30, 775, 29, '#b7a5d0');
    c.fillStyle = '#b59aff';c.fillRect(30, 839, 480, 57);text(c, 'PRÉPARER LES VERSIONS ↗', 55, 875, 20, '#15101e', 700);
  } else if (mode === 3) {
    text(c, 'Ta bibliothèque', 30, 174, 35, '#fff', 700);text(c, '6 idées. Un seul studio.', 30, 214, 18, '#b6afc2');
    MEDIA.forEach((name, i) => {const x = 29 + i % 3 * 170, y = 255 + Math.floor(i / 3) * 260;cover(c, images[name], x, y, 145, 220);text(c, ['Mouvement', 'Matière', 'Perspective', 'Coulisses', 'Portrait', 'Évasion'][i], x, y + 244, 16);});
    c.fillStyle = '#201b2e';c.fillRect(29, 813, 482, 98);text(c, 'MARDI / 18:00', 47, 850, 17, '#cbb2f8');text(c, 'Mouvement · Reel à préparer', 47, 884, 21);
  } else {
    text(c, 'STUDIO IA', 30, 172, 34, '#fff', 700);text(c, 'BIENTÔT DISPONIBLE', 30, 213, 17, '#c5a9ff');
    c.fillStyle = '#242030';c.fillRect(28, 250, 484, 112);text(c, '« Une raie de verre,', 45, 293, 24);text(c, 'dans un océan de nuit. »', 45, 331, 24);
    cover(c, images.manta, 28, 390, 484, 380, 1.12);
    text(c, 'IMAGINER → EXPLORER → CRÉER', 30, 823, 17, '#c5a9ff');
    for(let i = 0; i < 6; i++)cover(c, images.manta, 28 + i * 81, 850, 76, 51, 1 + i * .06);
    text(c, 'Illustration du futur studio', 30, 932, 14, '#9d93ac');
  }
}
