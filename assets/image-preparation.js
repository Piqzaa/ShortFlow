export function buildImagePrompt({ idea = '', scene = '', ratio = '9:16', style = '', characters = '', subjects = [] } = {}) {
  const clean = value => typeof value === 'string' ? value.trim() : '';
  if (!clean(scene)) throw new Error('Décris la scène de ce plan avant de préparer son image.');
  const formats = { '9:16': 'vertical 9:16', '16:9': 'paysage 16:9', '1:1': 'carré 1:1', auto: 'identique à la référence jointe' };
  const lines = [`Crée une seule image de début de plan, au format ${formats[ratio] || 'vertical 9:16'}.`];
  if (clean(idea) && clean(idea) !== clean(scene)) lines.push(`Contexte de l’histoire : ${clean(idea)}`);
  if (clean(characters)) lines.push(`Personnages à conserver : ${clean(characters)}`);
  const names = subjects.map(clean).filter(Boolean);
  if (names.length) lines.push(`Sujets de référence : ${names.join(', ')}.`);
  if (clean(style)) lines.push(`Style visuel commun : ${clean(style)}`);
  lines.push(`Scène à préparer : ${clean(scene)}`);
  lines.push('Représente l’instant juste AVANT l’action, sans dérouler toute la scène. Les éventuelles répliques donnent le contexte : ne les écris pas dans l’image.');
  lines.push('Si je joins des références, conserve leurs personnages, vêtements, accessoires et style. Garde les visages lisibles et les membres utiles à l’action dans le cadre. Pas de collage, de sous-titres, de logo ni de texte ajouté.');
  const prompt = lines.join('\n\n');
  if (Array.from(prompt).length > 3000) throw new Error('Ces consignes dépassent 3 000 caractères. Raccourcis l’idée ou la scène, ou rédige tes consignes directement. Aucun texte n’a été remplacé.');
  return prompt;
}
