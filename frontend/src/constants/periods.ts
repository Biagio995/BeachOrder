export const REPORT_PERIOD_OPTIONS = [
  { value: 1, labelKey: 'admin.period.today' },
  { value: 7, labelKey: 'admin.period.days7' },
  { value: 14, labelKey: 'admin.period.days14' },
  { value: 30, labelKey: 'admin.period.days30' },
] as const

export type ReportPeriodDays = (typeof REPORT_PERIOD_OPTIONS)[number]['value']
