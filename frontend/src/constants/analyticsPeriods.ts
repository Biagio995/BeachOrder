export const ANALYTICS_PERIOD_OPTIONS = [
  { value: 'today', labelKey: 'admin.analytics.period.today' },
  { value: 'yesterday', labelKey: 'admin.analytics.period.yesterday' },
  { value: '7d', labelKey: 'admin.analytics.period.days7' },
  { value: '30d', labelKey: 'admin.analytics.period.days30' },
  { value: 'custom', labelKey: 'admin.analytics.period.custom' },
] as const

export type AnalyticsPeriod = (typeof ANALYTICS_PERIOD_OPTIONS)[number]['value']

export const DEFAULT_ANALYTICS_PERIOD: AnalyticsPeriod = '7d'
