import { onUnmounted, ref } from 'vue'
import { getEcho } from '@/plugins/echo'
import type { Order } from '@/types'

export function useStaffRealtime(options: {
  tenantId: () => number | null | undefined
  onEvent: () => void
  onOrderUpdated?: (order: Order) => void
  channels: Array<'orders' | 'waiter-calls'>
  pollMs?: number
}) {
  const pollId = ref<number | null>(null)
  const live = ref(false)

  function stopPolling() {
    if (pollId.value != null) {
      window.clearInterval(pollId.value)
      pollId.value = null
    }
  }

  function startPolling() {
    stopPolling()
    pollId.value = window.setInterval(options.onEvent, options.pollMs ?? 8000)
    live.value = false
  }

  function subscribe() {
    const tenantId = options.tenantId()
    stopPolling()

    if (!tenantId) {
      startPolling()
      return
    }

    try {
      const echo = getEcho()
      for (const name of options.channels) {
        const channel = echo.private(`tenant.${tenantId}.${name}`)
        if (name === 'orders') {
          channel.listen('.order.updated', (payload: { order?: Order }) => {
            if (payload?.order && options.onOrderUpdated) {
              options.onOrderUpdated(payload.order)
            } else {
              options.onEvent()
            }
          })
        }
        if (name === 'waiter-calls') {
          channel.listen('.waiter-call.created', options.onEvent)
          channel.listen('.waiter-call.updated', options.onEvent)
        }
      }
      live.value = true
    } catch {
      startPolling()
    }
  }

  function leave() {
    stopPolling()
    const tenantId = options.tenantId()
    if (!tenantId) return
    try {
      const echo = getEcho()
      for (const name of options.channels) {
        echo.leave(`tenant.${tenantId}.${name}`)
      }
    } catch {
      // ignore
    }
  }

  onUnmounted(leave)

  return { subscribe, leave, live }
}
