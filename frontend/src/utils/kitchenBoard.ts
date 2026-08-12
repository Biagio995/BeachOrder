import type { Order } from '@/types'

export type KitchenStation = 'kitchen' | 'bar'

export const KDS_STATUSES = ['received', 'accepted', 'preparing', 'ready'] as const

export const KDS_COLUMNS = [
  { key: 'new', statuses: ['received', 'accepted'] as const, labelKey: 'staff.kdsNew' },
  { key: 'preparing', statuses: ['preparing'] as const, labelKey: 'staff.kdsPreparing' },
  { key: 'ready', statuses: ['ready'] as const, labelKey: 'staff.kdsReady' },
] as const

export type KdsColumnKey = (typeof KDS_COLUMNS)[number]['key']

export function stationStatusOf(order: Order, station: KitchenStation): string {
  return (
    order.station_status
    || (station === 'bar' ? order.bar_status : order.kitchen_status)
    || order.status
  )
}

export function kdsColumnForStatus(status: string): KdsColumnKey | null {
  for (const col of KDS_COLUMNS) {
    if ((col.statuses as readonly string[]).includes(status)) {
      return col.key
    }
  }
  return null
}

export function normalizeStationOrder(order: Order, station: KitchenStation): Order | null {
  if (['delivering', 'delivered', 'cancelled'].includes(order.status)) {
    return null
  }

  const status = stationStatusOf(order, station)
  if (!status || !(KDS_STATUSES as readonly string[]).includes(status)) {
    return null
  }

  const items = order.items.filter((item) => item.station === station)
  if (items.length === 0) {
    return null
  }

  return {
    ...order,
    items,
    station_status: status,
  }
}

export function mergeStationOrder(list: Order[], incoming: Order, station: KitchenStation): Order[] {
  const normalized = normalizeStationOrder(incoming, station)
  const without = list.filter((order) => order.id !== incoming.id)

  if (!normalized) {
    return without
  }

  return [normalized, ...without].sort(
    (a, b) => new Date(b.created_at || 0).getTime() - new Date(a.created_at || 0).getTime(),
  )
}

export function formatOrderTime(value: string | undefined, locale: string, timezone?: string): string {
  if (!value) return '—'
  return new Intl.DateTimeFormat(locale, {
    hour: '2-digit',
    minute: '2-digit',
    timeZone: timezone || undefined,
  }).format(new Date(value))
}

export function elapsedMinutes(value: string | undefined): number {
  if (!value) return 0
  return Math.max(0, Math.floor((Date.now() - new Date(value).getTime()) / 60000))
}
