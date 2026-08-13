import api, { tenantPath } from '@/api/client'
import type { Order } from '@/types'

function sleep(ms: number) {
  return new Promise((resolve) => setTimeout(resolve, ms))
}

/** Poll order status until Stripe payment is reflected on the backend. */
export async function waitForOrderPayment(
  tenantSlug: string,
  orderId: string | number,
  session: string,
  options?: { maxAttempts?: number; intervalMs?: number },
): Promise<Order | null> {
  const maxAttempts = options?.maxAttempts ?? 20
  const intervalMs = options?.intervalMs ?? 400

  for (let attempt = 0; attempt < maxAttempts; attempt++) {
    const { data } = await api.get<Order>(tenantPath(tenantSlug, `/orders/${orderId}`), {
      params: { session },
    })

    if (data.payment_status === 'paid') {
      return data
    }

    if (attempt < maxAttempts - 1) {
      await sleep(intervalMs)
    }
  }

  return null
}
