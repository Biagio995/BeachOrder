import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

declare global {
  interface Window {
    Pusher: typeof Pusher
    Echo: Echo<'reverb'>
  }
}

window.Pusher = Pusher

let echoInstance: Echo<'reverb'> | null = null

function reverbTransport() {
  // Route WebSocket through Vite (/app → Reverb) so LAN and DevTunnel work.
  const useProxy = import.meta.env.VITE_REVERB_USE_PROXY !== 'false'
  if (useProxy && typeof window !== 'undefined') {
    const port = window.location.port
      ? Number(window.location.port)
      : window.location.protocol === 'https:' ? 443 : 80

    return {
      wsHost: window.location.hostname,
      wsPort: port,
      wssPort: port,
      forceTLS: window.location.protocol === 'https:',
    }
  }

  return {
    wsHost: import.meta.env.VITE_REVERB_HOST || 'localhost',
    wsPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https',
  }
}

export function getEcho(): Echo<'reverb'> {
  if (!echoInstance) {
    echoInstance = new Echo({
      broadcaster: 'reverb',
      key: import.meta.env.VITE_REVERB_APP_KEY,
      ...reverbTransport(),
      enabledTransports: ['ws', 'wss'],
      // Auto-reconnect after Render Free sleep / brief network blips.
      // Pusher-js retries with backoff; activityTimeout must exceed nginx
      // proxy idle cuts so heartbeats keep the board sockets alive.
      disableStats: true,
      enableLogging: false,
      activityTimeout: 30000,
      pongTimeout: 15000,
      unavailableTimeout: 10000,
      authEndpoint: `${import.meta.env.VITE_API_URL || '/api'}/broadcasting/auth`,
      auth: {
        headers: {
          Authorization: `Bearer ${localStorage.getItem('bo_token') || ''}`,
          Accept: 'application/json',
          'X-Tenant': localStorage.getItem('bo_tenant_slug') || '',
        },
      },
    })
    window.Echo = echoInstance
  } else {
    // Refresh auth headers for private channels after login.
    const connector = echoInstance.connector as { options?: { auth?: { headers?: Record<string, string> } } }
    if (connector.options?.auth?.headers) {
      connector.options.auth.headers.Authorization = `Bearer ${localStorage.getItem('bo_token') || ''}`
      connector.options.auth.headers['X-Tenant'] = localStorage.getItem('bo_tenant_slug') || ''
    }
  }
  return echoInstance
}

export function resetEcho() {
  if (echoInstance) {
    try {
      echoInstance.disconnect()
    } catch {
      // ignore
    }
    echoInstance = null
  }
}
