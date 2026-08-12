<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import api, { getApiErrorMessage } from '@/api/client'
import FilterChipGroup from '@/components/staff/FilterChipGroup.vue'
import StaffBoardHeader from '@/components/staff/StaffBoardHeader.vue'
import OrderLineDetails from '@/components/shared/OrderLineDetails.vue'
import { useStaffRealtime } from '@/composables/useStaffRealtime'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import type { Order, WaiterCall } from '@/types'

const { t } = useI18n()
const auth = useAuthStore()
const ui = useUiStore()
const orders = ref<Order[]>([])
const calls = ref<WaiterCall[]>([])
const loading = ref(false)

const orderStatuses = ref<string[]>(['ready', 'delivering'])
const callStatuses = ref<string[]>(['pending', 'acknowledged'])
const paymentFilter = ref<string | null>(null)

const orderStatusOptions = computed(() => [
  { value: 'ready', label: t('order.statuses.ready') },
  { value: 'delivering', label: t('order.statuses.delivering') },
])
const callStatusOptions = computed(() => [
  { value: 'pending', label: t('staff.callStatuses.pending') },
  { value: 'acknowledged', label: t('staff.callStatuses.acknowledged') },
])

function sortWaiterOrders(list: Order[]): Order[] {
  return [...list].sort((a, b) => {
    const rank = (o: Order) => {
      if (o.status === 'delivered' && o.payment_status !== 'paid') return 0
      if (o.payment_status !== 'paid') return 1
      return 2
    }
    return rank(a) - rank(b)
  })
}

async function load() {
  loading.value = true
  try {
    const params: Record<string, string> = {
      status: orderStatuses.value.join(',') || 'ready,delivering',
    }
    if (paymentFilter.value) {
      params.payment_status = paymentFilter.value
    }

    const [ordersRes, callsRes] = await Promise.all([
      api.get('/orders', { params }),
      api.get('/waiter-calls', {
        params: { status: callStatuses.value.join(',') || 'pending,acknowledged' },
      }),
    ])
    orders.value = sortWaiterOrders(ordersRes.data.data || ordersRes.data)
    calls.value = callsRes.data.data || callsRes.data
  } catch (e: unknown) {
    ui.error(getApiErrorMessage(e, t('common.error')))
  } finally {
    loading.value = false
  }
}

function setPaymentFilter(value: string | null) {
  paymentFilter.value = paymentFilter.value === value ? null : value
  load()
}

async function setOrderStatus(order: Order, status: string) {
  try {
    await api.patch(`/orders/${order.id}/status`, { status })
    if (status === 'delivered' && order.payment_status !== 'paid') {
      ui.success(t('staff.deliveredKeepUnpaid'))
    }
    await load()
  } catch (e: unknown) {
    ui.error(getApiErrorMessage(e, t('common.error')))
  }
}

async function markPaid(order: Order) {
  try {
    await api.patch(`/orders/${order.id}/payment`, { payment_status: 'paid' })
    await load()
  } catch (e: unknown) {
    ui.error(getApiErrorMessage(e, t('common.error')))
  }
}

async function setCallStatus(call: WaiterCall, status: string) {
  try {
    await api.patch(`/waiter-calls/${call.id}/status`, { status })
    await load()
  } catch (e: unknown) {
    ui.error(getApiErrorMessage(e, t('common.error')))
  }
}

const unpaidCount = computed(() => orders.value.filter((o) => o.payment_status !== 'paid').length)
const deliveredUnpaidCount = computed(
  () => orders.value.filter((o) => o.status === 'delivered' && o.payment_status !== 'paid').length,
)

const { subscribe, live } = useStaffRealtime({
  tenantId: () => auth.user?.tenant_id,
  onEvent: () => load(),
  channels: ['orders', 'waiter-calls'],
})

watch(orderStatuses, () => load(), { deep: true })
watch(callStatuses, () => load(), { deep: true })

onMounted(async () => {
  await load()
  subscribe()
})
</script>

<template>
  <div class="page-shell">
    <StaffBoardHeader
      :title="t('staff.waiterTitle')"
      :subtitle="auth.user?.tenant?.name"
      :live="live"
      :loading="loading"
      @refresh="load"
    />

    <h2 class="text-h6 mb-2">{{ t('nav.order') }}</h2>
    <div class="mb-3 d-flex flex-column ga-2">
      <FilterChipGroup v-model="orderStatuses" :options="orderStatusOptions" />
      <div class="chip-scroll">
        <v-chip
          :color="paymentFilter === 'unpaid' ? 'accent' : undefined"
          :variant="paymentFilter === 'unpaid' ? 'flat' : 'outlined'"
          filter
          @click="setPaymentFilter('unpaid')"
        >
          {{ t('staff.unpaid') }} ({{ unpaidCount }})
        </v-chip>
        <v-chip
          v-if="deliveredUnpaidCount > 0"
          color="warning"
          variant="tonal"
          class="pointer-events-none"
        >
          {{ t('staff.deliveredUnpaid') }} ({{ deliveredUnpaidCount }})
        </v-chip>
      </div>
    </div>

    <v-alert v-if="!loading && orders.length === 0" type="info" class="mb-6">{{ t('staff.noOrders') }}</v-alert>
    <div
      v-for="order in orders"
      :key="order.id"
      class="panel mb-3 pa-4"
      :class="{ 'panel--unpaid': order.payment_status !== 'paid' }"
    >
      <div class="touch-row">
        <div>
          <strong>{{ order.order_number }}</strong>
          <div class="text-medium-emphasis">
            {{ order.location?.name }} · {{ t(`order.statuses.${order.status}`) }}
            · {{ t(`order.paymentStatuses.${order.payment_status}`) }}
          </div>
          <v-chip
            v-if="order.status === 'delivered' && order.payment_status !== 'paid'"
            class="mt-1"
            color="warning"
            size="small"
            variant="tonal"
          >
            {{ t('staff.deliveredUnpaid') }}
          </v-chip>
          <div v-if="order.items?.length" class="mt-3">
            <div v-for="item in order.items" :key="item.id" class="mb-2">
              <OrderLineDetails :item="item" />
            </div>
          </div>
        </div>
        <div class="stack-actions">
          <v-btn v-if="order.status === 'ready'" color="primary" @click="setOrderStatus(order, 'delivering')">
            {{ t('staff.deliver') }}
          </v-btn>
          <v-btn v-if="order.status === 'delivering'" color="success" @click="setOrderStatus(order, 'delivered')">
            {{ t('staff.delivered') }}
          </v-btn>
          <v-btn v-if="order.payment_status !== 'paid'" color="warning" variant="tonal" @click="markPaid(order)">
            {{ t('staff.markPaid') }}
          </v-btn>
        </div>
      </div>
    </div>

    <h2 class="text-h6 mb-2 mt-6">{{ t('nav.callWaiter') }}</h2>
    <div class="mb-3">
      <FilterChipGroup v-model="callStatuses" :options="callStatusOptions" />
    </div>
    <v-alert v-if="!loading && calls.length === 0" type="info">{{ t('staff.noCalls') }}</v-alert>
    <div v-for="call in calls" :key="call.id" class="panel mb-3 pa-4">
      <div class="touch-row">
        <div>
          <strong>{{ t(`waiter.reasons.${call.reason}`) }}</strong>
          <div class="text-medium-emphasis">
            {{ call.location?.name }} · {{ t(`staff.callStatuses.${call.status}`, call.status) }}
          </div>
          <div v-if="call.note" class="text-body-2">{{ call.note }}</div>
        </div>
        <div class="stack-actions">
          <v-btn v-if="call.status === 'pending'" variant="tonal" @click="setCallStatus(call, 'acknowledged')">
            {{ t('staff.acknowledge') }}
          </v-btn>
          <v-btn color="primary" @click="setCallStatus(call, 'resolved')">
            {{ t('staff.resolve') }}
          </v-btn>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.staff-title {
  font-size: clamp(1.35rem, 5vw, 2rem);
  line-height: 1.2;
  margin: 0;
}
.panel {
  background: rgba(255, 255, 255, 0.72);
  border: 1px solid rgba(11, 110, 107, 0.14);
  border-radius: 16px;
}
.panel--unpaid {
  border-color: rgba(217, 119, 6, 0.45);
}
</style>
