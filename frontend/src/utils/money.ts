export function formatMoney(amount: number | string, currency = 'EUR', locale = 'it-IT'): string {
  const value = typeof amount === 'string' ? Number(amount) : amount
  try {
    return new Intl.NumberFormat(locale, {
      style: 'currency',
      currency: currency || 'EUR',
    }).format(Number.isFinite(value) ? value : 0)
  } catch {
    return `€ ${(Number.isFinite(value) ? value : 0).toFixed(2)}`
  }
}
