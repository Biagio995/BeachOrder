<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api, { getApiErrorMessage, tenantPath } from '@/api/client'
import { useMenuStore } from '@/stores/menu'
import { useActiveOrderStore } from '@/stores/activeOrder'
import { formatMoney } from '@/utils/money'
import OrderLineDetails from '@/components/shared/OrderLineDetails.vue'
import OrderStatusJourney from '@/components/customer/OrderStatusJourney.vue'
import ServiceUnavailable from '@/components/customer/ServiceUnavailable.vue'
import type { Order } from '@/types'

const route = useRoute()
const { t, locale } = useI18n()
const menu = useMenuStore()
const activeOrder = useActiveOrderStore()
const order = ref<Order | null>(null)
const loading = ref(true)
const error = ref('')
const reveal = ref(false)
const steps = ['received', 'accepted', 'preparing', 'ready', 'delivering', 'delivered']

const currency = computed(() => menu.tenant?.currency || 'EUR')
const currentIdx = computed(() => (order.value ? stepIndex(order.value.status) : -1))
const progress = computed(() => {
  if (currentIdx.value < 0) return 0
  return ((currentIdx.value + 1) / steps.length) * 100
})

const statusCopy = computed(() => {
  if (!order.value) return ''
  if (order.value.status === 'cancelled') return t('order.copy.cancelled')
  return t(`order.copy.${order.value.status}`, t(`order.statuses.${order.value.status}`))
})

async function load() {
  const tenant = String(route.params.tenant || menu.tenantSlug)
  menu.setTenant(tenant)
  loading.value = true
  error.value = ''
  const active = await menu.checkTenantStatus()
  if (!active) {
    loading.value = false
    return
  }
  try {
    const { data } = await api.get(tenantPath(tenant, `/orders/${route.params.id}`), {
      params: { session: route.query.session || menu.session },
    })
    order.value = data
    const session = String(route.query.session || menu.session)
    activeOrder.track(data, tenant, session)
  } catch (e: unknown) {
    error.value = getApiErrorMessage(e, t('common.error'))
  } finally {
    loading.value = false
    requestAnimationFrame(() => {
      reveal.value = true
    })
  }
}

onMounted(async () => {
  await load()
  void activeOrder.startWatching()
})

watch(
  () => activeOrder.current?.status,
  (status) => {
    if (!status || !order.value) return
    if (String(activeOrder.current?.id) !== String(order.value.id)) return
    if (status === order.value.status) return
    void load()
  },
)

watch(
  () => activeOrder.current?.paymentStatus,
  (paymentStatus) => {
    if (!paymentStatus || !order.value) return
    if (String(activeOrder.current?.id) !== String(order.value.id)) return
    if (paymentStatus === order.value.payment_status) return
    void load()
  },
)

watch(
  () => order.value?.status,
  () => {
    reveal.value = false
    requestAnimationFrame(() => {
      reveal.value = true
    })
  },
)

function stepIndex(status: string) {
  if (status === 'cancelled') return -1
  return steps.indexOf(status)
}

function isOnlinePayment(method?: string) {
  return method === 'card_online' || method === 'apple_pay' || method === 'google_pay'
}

function paymentLabel(method?: string) {
  if (!method) return ''
  return t(`cart.payment.${method}`, method)
}

const showPaymentBanner = computed(() => {
  if (!order.value) return false
  return isOnlinePayment(order.value.payment_method) && order.value.payment_status !== 'paid'
})

const showReceipt = computed(() => order.value?.payment_receipt && order.value.payment_status === 'paid')

const paymentRoute = computed(() => ({
  name: 'order-payment' as const,
  params: { tenant: route.params.tenant, id: route.params.id },
  query: { session: route.query.session || menu.session },
}))
</script>

<template>
  <ServiceUnavailable
    v-if="menu.tenantInactive"
    :tenant-name="menu.tenant?.name"
    :tagline="menu.tenant?.branding?.tagline"
  />
  <div v-else class="page-shell order-page" :class="{ 'order-page--ready': reveal }">
    <v-progress-linear v-if="loading" indeterminate color="primary" />
    <v-alert v-else-if="error" type="error">{{ error }}</v-alert>
    <template v-else-if="order">
      <div class="order-hero mb-5">
        <div class="order-hero__orb" aria-hidden="true" />
        <p class="text-medium-emphasis mb-1">{{ t('order.tracking') }}</p>
        <h1 class="display-font order-hero__title mb-2">{{ t('order.placed') }}</h1>
        <p class="mb-3">
          {{ t('order.number') }}
          <strong>{{ order.order_number }}</strong>
          · {{ order.location?.name }}
        </p>
        <p class="order-hero__copy">{{ statusCopy }}</p>
      </div>

      <div class="d-flex flex-wrap ga-2 mb-5">
        <v-chip class="status-chip" :color="order.status === 'cancelled' ? 'error' : 'primary'" size="large">
          {{ t(`order.statuses.${order.status}`) }}
        </v-chip>
        <v-chip variant="tonal" :color="order.payment_status === 'paid' ? 'success' : 'secondary'">
          {{ paymentLabel(order.payment_method) }} · {{ t(`order.paymentStatuses.${order.payment_status}`) }}
        </v-chip>
      </div>

      <v-alert
        v-if="showPaymentBanner && order.payment_status === 'pending'"
        type="warning"
        class="mb-4"
      >
        {{ t('payment.pendingKitchen') }}
        <v-btn class="mt-3" color="accent" block :to="paymentRoute">
          {{ t('payment.completePayment') }}
        </v-btn>
      </v-alert>

      <v-alert
        v-else-if="showPaymentBanner && order.payment_status === 'failed'"
        type="error"
        class="mb-4"
      >
        <div>{{ t('payment.failedMessage') }}</div>
        <div v-if="order.payment_error" class="text-caption mt-1">{{ order.payment_error }}</div>
        <v-btn class="mt-3" color="accent" block :to="paymentRoute">
          {{ t('payment.retry') }}
        </v-btn>
      </v-alert>

      <v-card v-if="showReceipt" class="mb-4 receipt-card" variant="tonal" color="success">
        <v-card-title>{{ t('payment.receiptTitle') }}</v-card-title>
        <v-card-text>
          <div class="receipt-row">
            <span>{{ t('payment.receiptReference') }}</span>
            <strong>{{ order.payment_receipt?.reference }}</strong>
          </div>
          <div class="receipt-row">
            <span>{{ t('payment.receiptAmount') }}</span>
            <strong>
              {{ formatMoney(order.payment_receipt?.amount || order.total, order.payment_receipt?.currency || currency, locale) }}
            </strong>
          </div>
          <div v-if="order.payment_receipt?.paid_at" class="receipt-row">
            <span>{{ t('payment.receiptPaidAt') }}</span>
            <strong>{{ new Date(order.payment_receipt.paid_at).toLocaleString(locale) }}</strong>
          </div>
        </v-card-text>
      </v-card>

      <OrderStatusJourney
        v-if="order.status !== 'cancelled'"
        :steps="steps"
        :current-idx="currentIdx"
        :progress="progress"
      />

      <div class="order-lines">
        <div v-for="item in order.items" :key="item.id" class="order-line">
          <OrderLineDetails :item="item" :currency="currency" />
        </div>
        <div class="order-line order-line--total">
          <span>{{ t('cart.total') }}</span>
          <span>{{ formatMoney(order.total, currency, locale) }}</span>
        </div>
      </div>

      <v-btn
        v-if="menu.locationCode"
        class="mt-6"
        variant="tonal"
        color="primary"
        block
        :to="`/t/${route.params.tenant}/q/${menu.locationCode}`"
      >
        {{ t('order.rescanToOrder') }}
      </v-btn>
    </template>
  </div>
</template>

<style scoped>
.order-page {
  opacity: 0;
  transform: translateY(8px);
  transition: opacity 0.4s ease, transform 0.4s ease;
}
.order-page--ready {
  opacity: 1;
  transform: none;
}

.order-hero {
  position: relative;
  overflow: hidden;
  border-radius: 20px;
  padding: 1.25rem 1.15rem;
  background:
    linear-gradient(135deg, rgba(255, 255, 255, 0.72), rgba(232, 244, 243, 0.55)),
    radial-gradient(420px 180px at 100% 0%, rgba(224, 122, 95, 0.18), transparent 60%);
  border: 1px solid rgba(11, 110, 107, 0.12);
}

.order-hero__orb {
  position: absolute;
  width: 140px;
  height: 140px;
  right: -24px;
  top: -36px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(244, 201, 95, 0.45), transparent 70%);
  animation: drift 6s ease-in-out infinite alternate;
}

.order-hero__title {
  font-size: clamp(1.55rem, 5vw, 2.1rem);
  color: var(--bo-teal-deep);
  line-height: 1.15;
  margin: 0;
}

.order-hero__copy {
  margin: 0;
  font-size: 1.05rem;
  color: var(--bo-ink);
  max-width: 34rem;
}

.order-lines {
  border-top: 1px solid rgba(11, 110, 107, 0.12);
  padding-top: 0.5rem;
}

.order-line {
  padding: 0.55rem 0;
}

.order-line--total {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  margin-top: 0.35rem;
  padding-top: 0.85rem;
  border-top: 1px solid rgba(11, 110, 107, 0.15);
  font-size: 1.15rem;
  font-weight: 700;
}

.receipt-card {
  border-radius: 16px;
}

.receipt-row {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.25rem 0;
}

@keyframes drift {
  from {
    transform: translateY(0);
  }
  to {
    transform: translateY(10px) scale(1.05);
  }
}
</style>
