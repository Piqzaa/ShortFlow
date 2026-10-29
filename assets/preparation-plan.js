export function proposePlan(target, durations, planCount = null) {
  if (!Number.isInteger(target) || target < 1 || target > 60 || !durations?.length || planCount !== null && (!Number.isInteger(planCount) || planCount < 1 || planCount > 8)) return { parts: null, nearby: [] };
  const states = [new Map([[0, []]])];
  const choices = new Map();
  const spread = parts => Math.max(...parts) - Math.min(...parts);
  for (let count = 1; count <= 8; count++) {
    const next = new Map();
    for (const [sum, parts] of states[count - 1]) for (const seconds of durations) {
      const total = sum + seconds;
      if (!Number.isInteger(seconds) || seconds < 1 || total > 60) continue;
      const candidate = [...parts, seconds];
      if (!next.has(total) || spread(candidate) < spread(next.get(total))) next.set(total, candidate);
    }
    states.push(next);
    for (const [total, parts] of next) if ((planCount === null || planCount === count) && !choices.has(total)) choices.set(total, parts.sort((a, b) => b - a));
  }
  return { parts: choices.get(target) || null, nearby: [...choices.keys()].sort((a, b) => Math.abs(a - target) - Math.abs(b - target) || a - b).slice(0, 2) };
}
