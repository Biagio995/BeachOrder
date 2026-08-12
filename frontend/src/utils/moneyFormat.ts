import { formatMoney } from '@/utils/money'

/** Map app locale codes to Intl locales. */
export function resolveMoneyLocale(locale: string): string {
  if (locale === 'en') return 'en-GB'
  if (locale.includes('-')) return locale
  return `${locale}-${locale.toUpperCase()}`
}

export function money(
  amount: number | string,
  currency = 'EUR',
  locale = 'el',
): string {
  return formatMoney(amount, currency, resolveMoneyLocale(locale))
}
