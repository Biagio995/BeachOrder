import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import api, { tenantPath } from '@/api/client'
import { getEcho } from '@/plugins/echo'
import type { Order } from '@/types'

export interface ActiveOrderRef {
  id: number
  tenantSlug: string
  session: string
  orderNumber: string
  status: string
  paymentStatus?: string
  tenantId?: number | null
}

const STORAGE_PREFIX = 'bo_active_order:'

function isTerminal(status: string) {
  return status === 'delivered' || status === 'cancelled'
}

function storageKey(tenantSlug: string) {
  return `${STORAGE_PREFIX}${tenantSlug}`
}

function readStored(tenantSlug: string): ActiveOrderRef | null {
  if (!tenantSlug) return null
  try {
    const raw = localStorage.getItem(storageKey(tenantSlug))
    if (!raw) return null
    const parsed = JSON.parse(raw) as ActiveOrderRef
    if (!parsed?.id || isTerminal(parsed.status)) {
      localStorage.removeItem(storageKey(tenantSlug))
      return null
    }
    return parsed
  } catch {
    return null
  }
}

function writeStored(ref: ActiveOrderRef | null) {
  if (!ref?.tenantSlug) return
  if (isTerminal(ref.status)) {
    localStorage.removeItem(storageKey(ref.tenantSlug))
    return
  }
  localStorage.setItem(storageKey(ref.tenantSlug), JSON.stringify(ref))
}

export const useActiveOrderStore = defineStore('activeOrder', () => {
  const current = ref<ActiveOrderRef | null>(null)
  let echoChannel: string | null = null
  let pollTimer: ReturnType<typeof setInterval> | null = null

  const isActive = computed(() => !!current.value && !isTerminal(current.value.status))

  const statusRoute = computed(() => {
    if (!current.value) return null
    return {
      name: 'order-status' as const,
      params: { tenant: current.value.tenantSlug, id: String(current.value.id) },
      query: { session: current.value.session },
    }
  })

  function hydrate(tenantSlug: string) {
    current.value = readStored(tenantSlug)
  }

  function track(order: Order, tenantSlug: string, session: string) {
    const next: ActiveOrderRef = {
      id: order.id,
      tenantSlug,
      session,
      orderNumber: order.order_number,
      status: order.status,
      paymentStatus: order.payment_status,
      tenantId: order.tenant_id ?? null,
    }
    if (isTerminal(next.status)) {
      clear(tenantSlug)
      return
    }
    current.value = next
    writeStored(next)
  }

  function applyStatus(status: string, extras?: Partial<ActiveOrderRef>) {
    if (!current.value) return
    const next = { ...current.value, ...extras, status }
    if (isTerminal(status)) {
      clear(current.value.tenantSlug)
      return
    }
    current.value = next
    writeStored(next)
  }

  function clear(tenantSlug?: string) {
    const slug = tenantSlug || current.value?.tenantSlug
    stopWatching()
    if (slug) localStorage.removeItem(storageKey(slug))
    if (!tenantSlug || current.value?.tenantSlug === slug) {
      current.value = null
    }
  }

  async function refresh() {
    if (!current.value) return null
    const { tenantSlug, id, session } = current.value
    try {
      const { data } = await api.get<Order>(tenantPath(tenantSlug, `/orders/${id}`), {
        params: { session },
      })
      track(data, tenantSlug, session)
      return data
    } catch {
      // Order gone / session mismatch — drop banner.
      clear(tenantSlug)
      return null
    }
  }

  function stopWatching() {
    if (pollTimer) {
      clearInterval(pollTimer)
      pollTimer = null
    }
    if (echoChannel) {
      try {
        getEcho().leave(echoChannel)
      } catch {
        // ignore
      }
      echoChannel = null
    }
  }

  async function startWatching() {
    stopWatching()
    if (!current.value) return

    await refresh()
    if (!current.value) return

    const { tenantId, id } = current.value
    if (tenantId) {
      echoChannel = `tenant.${tenantId}.orders.${id}`
      try {
        getEcho()
          .channel(echoChannel)
          .listen('.order.updated', (payload: { order: Order }) => {
            if (!current.value || payload.order.id !== current.value.id) return
            track(payload.order, current.value.tenantSlug, current.value.session)
          })
      } catch {
        // realtime optional
      }
    }

    pollTimer = setInterval(() => {
      if (current.value) void refresh()
    }, 20000)
  }

  return {
    current,
    isActive,
    statusRoute,
    hydrate,
    track,
    applyStatus,
    clear,
    refresh,
    startWatching,
    stopWatching,
  }
})
