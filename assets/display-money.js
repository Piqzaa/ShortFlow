export function exchangeRate() {
  try {
    const data = typeof document === 'undefined' ? null : JSON.parse(document.documentElement.dataset.aiExchange || 'null');
    return data && Number.isFinite(data.usdPerEur) && data.usdPerEur >= .01 && data.usdPerEur <= 100 ? data : null;
  } catch { return null; }
}

// The server validates freshness. This helper never changes provider prices.
export function displayMoney(usdCents, decimals = 2, rate = exchangeRate()) {
  if (!Number.isFinite(usdCents) || usdCents < 0 || ![2,3].includes(decimals)) throw new Error('Montant indicatif invalide.');
  const amount = usdCents / 100 / (rate?.usdPerEur || 1), unit = rate ? ' €' : ' $ US';
  const format = value => value.toLocaleString('fr-FR', {minimumFractionDigits:decimals,maximumFractionDigits:decimals});
  return amount > 0 && amount < 10 ** -decimals ? `< ${format(10 ** -decimals)}${unit}` : `${format(amount)}${unit}`;
}
