const phases = {
  queued: { step: 1, label: 'Demande en attente du rédacteur local…', button: 'Demande en attente…' },
  story: { step: 2, label: 'Rédaction du scénario…', button: 'Rédaction du scénario…' },
  shots: { step: 3, label: 'Préparation des scènes et des consignes vidéo…', button: 'Préparation des scènes…' },
  editorial: { step: 2, label: 'Préparation des titres, légendes et hashtags…', button: 'Préparation des textes…' },
};

function timestamp(value) {
  const parsed = typeof value === 'string' ? Date.parse(value) : NaN;
  return Number.isFinite(parsed) ? parsed : null;
}

function duration(seconds) {
  const minutes = Math.floor(seconds / 60), rest = seconds % 60;
  return minutes ? `${minutes} min${rest ? ` ${rest} s` : ''}` : `${rest} s`;
}

// Count time from the server observation, never from the device's absolute clock.
// A stage number describes the workflow, not a percentage or a completion estimate.
export function writingProgress(job, secondsSinceObservation = 0) {
  if (!job || !Object.hasOwn(phases, job.status)) return null;
  const phase = phases[job.status];
  const observed = timestamp(job.observedAt), created = timestamp(job.createdAt), started = timestamp(job.startedAt);
  const delta = Number.isFinite(secondsSinceObservation) ? Math.max(0, Math.floor(secondsSinceObservation)) : 0;
  const age = origin => Math.max(0, Math.floor((observed - origin) / 1000));
  let detail;
  if (job.status === 'queued') {
    detail = observed !== null && created !== null ? `Attente : ${duration(age(created) + delta)}. Le traitement n’a pas encore démarré.` : 'Le traitement n’a pas encore démarré.';
  } else if (observed !== null && started !== null) {
    detail = `Traitement : ${duration(age(started) + delta)}.`;
    if (created !== null) detail += ` Attente avant démarrage : ${duration(Math.max(0, Math.floor((started - created) / 1000)))}.`;
  } else {
    detail = 'Le traitement a démarré. Sa durée dépend du moteur local.';
  }
  return { ...phase, detail, stepLabel: `Étape ${phase.step} sur ${job.status === 'editorial' || job.kind === 'editorial' ? 2 : 3}` };
}
