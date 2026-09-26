import { onUnmounted, ref } from 'vue'
import { getEcho } from '@/plugins/echo'
import type { Order } from '@/types'

type PusherConnection = {
  bind: (event: string, callback: (payload: { current?: string }) => void) => void
}

type PusherConnector = {
  pusher?: {
    connection: PusherConnection
  }
}

type EchoChannel = {
  listen: (event: string, callback: (payload: unknown) => void) => EchoChannel
  error: (callback: (error: unknown) => void) => EchoChannel
}

export function useStaffRealtime(options: {
  tenantId: () => number | null | undefined
  onEvent: () => void
  onOrderUpdated?: (order: Order) => void
  channels: Array<'orders' | 'waiter-calls'>
  pollMs?: number
}) {
  const pollId = ref<number | null>(null)
  const live = ref(false)
  let connectionBound = false

  function stopPolling() {
    if (pollId.value != null) {
      window.clearInterval(pollId.value)
      pollId.value = null
    }
  }

  function startPolling() {
    if (pollId.value != null) return
    pollId.value = window.setInterval(options.onEvent, options.pollMs ?? 8000)
    live.value = false
  }

  function bindConnectionMonitor(echo: ReturnType<typeof getEcho>) {
    if (connectionBound) return
    connectionBound = true

    const pusher = (echo.connector as PusherConnector).pusher
    if (!pusher) return

    pusher.connection.bind('state_change', ({ current }) => {
      if (current === 'connected') {
        stopPolling()
        live.value = true
      } else if (current === 'connecting' || current === 'initialized') {
        live.value = false
      } else if (current === 'unavailable' || current === 'failed' || current === 'disconnected') {
        startPolling()
      }
    })
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
      bindConnectionMonitor(echo)

      for (const name of options.channels) {
        const channel = echo.private(`tenant.${tenantId}.${name}`) as EchoChannel
        channel.error(() => startPolling())

        if (name === 'orders') {
          channel.listen('.order.updated', (payload: unknown) => {
            const order = (payload as { order?: Order } | null)?.order
            if (order && options.onOrderUpdated) {
              options.onOrderUpdated(order)
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

      // Wait for actual socket connection before showing "Live".
      const state = (echo.connector as PusherConnector).pusher?.connection
      if (!state) {
        live.value = true
      }
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
