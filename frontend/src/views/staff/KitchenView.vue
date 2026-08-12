<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useDisplay } from 'vuetify'
import api, { getApiErrorMessage } from '@/api/client'
import FilterChipGroup from '@/components/staff/FilterChipGroup.vue'
import StaffBoardHeader from '@/components/staff/StaffBoardHeader.vue'
import OrderLineDetails from '@/components/shared/OrderLineDetails.vue'
import { useStaffRealtime } from '@/composables/useStaffRealtime'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import type { Order } from '@/types'
import {
  KDS_COLUMNS,
  type KitchenStation,
  elapsedMinutes,
  formatOrderTime,
  kdsColumnForStatus,
  mergeStationOrder,
  stationStatusOf,
} from '@/utils/kitchenBoard'

const { t, locale } = useI18n()
const route = useRoute()
const { mdAndUp } = useDisplay()
const auth = useAuthStore()
const ui = useUiStore()
const orders = ref<Order[]>([])
const loading = ref(false)
const printingOrderId = ref<number | null>(null)
const knownOrderIds = ref(new Set<number>())
const newOrderIds = ref(new Set<number>())
let newOrderTimer: ReturnType<typeof setTimeout> | null = null
let elapsedTimer: ReturnType<typeof setInterval> | null = null
const nowTick = ref(Date.now())

const station = computed(() => (route.meta.station as KitchenStation) || 'kitchen')
const titleKey = computed(() =>
  station.value === 'bar' ? 'staff.barTitle' : 'staff.kitchenTitle',
)
const timezone = computed(() => auth.user?.tenant?.timezone)

const columns = KDS_COLUMNS

const selectedColumns = ref<string[]>(columns.map((col) => col.key))
const statusParam = computed(() => 'received,accepted,preparing,ready')

const columnFilterOptions = computed(() =>
  columns.map((col) => ({ value: col.key, label: t(col.labelKey) })),
)

function statusForOrder(order: Order): string {
  return stationStatusOf(order, station.value)
}

function orderInSelectedColumn(order: Order): boolean {
  const column = kdsColumnForStatus(statusForOrder(order))
  return !!column && selectedColumns.value.includes(column)
}

const visibleOrders = computed(() => orders.value.filter(orderInSelectedColumn))

const columnOrders = computed(() =>
  Object.fromEntries(
    columns.map((col) => [
      col.key,
      visibleOrders.value.filter((order) => kdsColumnForStatus(statusForOrder(order)) === col.key),
    ]),
  ) as Record<string, Order[]>,
)

function playNewOrderAlert() {
  try {
    const ctx = new AudioContext()
    const osc = ctx.createOscillator()
    const gain = ctx.createGain()
    osc.connect(gain)
    gain.connect(ctx.destination)
    osc.frequency.value = 880
    gain.gain.value = 0.08
    osc.start()
    osc.stop(ctx.currentTime + 0.15)
    setTimeout(() => {
      const osc2 = ctx.createOscillator()
      const gain2 = ctx.createGain()
      osc2.connect(gain2)
      gain2.connect(ctx.destination)
      osc2.frequency.value = 1175
      gain2.gain.value = 0.08
      osc2.start()
      osc2.stop(ctx.currentTime + 0.2)
    }, 180)
  } catch {
    // Audio optional
  }
}

function markNewOrder(orderId: number) {
  newOrderIds.value = new Set([...newOrderIds.value, orderId])
  if (newOrderTimer) clearTimeout(newOrderTimer)
  newOrderTimer = setTimeout(() => {
    newOrderIds.value = new Set()
  }, 8000)
}

function handleIncomingOrder(order: Order) {
  const normalizedStatus = statusForOrder(order)
  const isNew = !knownOrderIds.value.has(order.id)
  const isIncomingNew = isNew && (normalizedStatus === 'received' || normalizedStatus === 'accepted')

  orders.value = mergeStationOrder(orders.value, order, station.value)
  knownOrderIds.value.add(order.id)

  if (isIncomingNew) {
    markNewOrder(order.id)
    playNewOrderAlert()
  }
}

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/orders', {
      params: {
        status: statusParam.value,
        station: station.value,
      },
    })
    const list: Order[] = data.data || data
    orders.value = list
    knownOrderIds.value = new Set(list.map((order) => order.id))
  } catch (e: unknown) {
    ui.error(getApiErrorMessage(e, t('common.error')))
  } finally {
    loading.value = false
  }
}

async function reprint(order: Order) {
  printingOrderId.value = order.id
  try {
    const { data } = await api.post(`/orders/${order.id}/print`, { station: station.value })
    const failed = data.results?.find((r: { status: string; error?: string }) => r.status === 'failed')
    if (failed?.error) {
      ui.error(failed.error)
    } else {
      ui.success(t('staff.printSent'))
    }
  } catch (e: unknown) {
    ui.error(getApiErrorMessage(e, t('staff.printFailed')))
  } finally {
    printingOrderId.value = null
  }
}

async function advanceOrder(order: Order) {
  const status = statusForOrder(order)
  try {
    if (status === 'received') {
      await api.patch(`/orders/${order.id}/status`, { status: 'accepted', station: station.value })
    }
    if (status === 'received' || status === 'accepted') {
      await api.patch(`/orders/${order.id}/status`, { status: 'preparing', station: station.value })
      return
    }
    if (status === 'preparing') {
      await api.patch(`/orders/${order.id}/status`, { status: 'ready', station: station.value })
    }
  } catch (e: unknown) {
    ui.error(getApiErrorMessage(e, t('common.error')))
    return
  }
  await load()
}

function nextAction(status: string): { label: string; handler: (order: Order) => void } | null {
  if (status === 'received' || status === 'accepted') {
    return { label: t('staff.kdsStartPreparing'), handler: advanceOrder }
  }
  if (status === 'preparing') {
    return { label: t('staff.kdsMarkReady'), handler: advanceOrder }
  }
  return null
}

function orderTime(order: Order): string {
  return formatOrderTime(order.created_at, locale.value, timezone.value)
}

function orderElapsed(order: Order): number {
  void nowTick.value
  return elapsedMinutes(order.created_at)
}

function isUrgent(order: Order): boolean {
  return orderElapsed(order) >= 15 && statusForOrder(order) !== 'ready'
}

const { subscribe, live } = useStaffRealtime({
  tenantId: () => auth.user?.tenant_id,
  onEvent: () => load(),
  onOrderUpdated: handleIncomingOrder,
  channels: ['orders'],
})

watch(station, () => load())

onMounted(async () => {
  await load()
  subscribe()
  elapsedTimer = setInterval(() => {
    nowTick.value = Date.now()
  }, 30000)
})

onUnmounted(() => {
  if (newOrderTimer) clearTimeout(newOrderTimer)
  if (elapsedTimer) clearInterval(elapsedTimer)
})
</script>

<template>
  <div class="page-shell kitchen-page">
    <StaffBoardHeader
      :title="t(titleKey)"
      :subtitle="auth.user?.tenant?.name"
      :live="live"
      :loading="loading"
      @refresh="load"
    />

    <div v-if="!mdAndUp" class="mb-4">
      <FilterChipGroup v-model="selectedColumns" :options="columnFilterOptions" />
    </div>

    <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-4" />
    <v-alert v-if="!loading && visibleOrders.length === 0" type="info">{{ t('staff.noOrders') }}</v-alert>

    <div v-if="!mdAndUp" class="mobile-list">
      <article
        v-for="order in visibleOrders"
        :key="order.id"
        class="order-panel pa-4 mb-3"
        :class="{
          'order-panel--new': newOrderIds.has(order.id),
          'order-panel--urgent': isUrgent(order),
        }"
      >
        <div class="order-panel__head mb-2">
          <div>
            <strong class="order-number">{{ order.order_number }}</strong>
            <div class="order-meta">
              <span>{{ t('staff.kdsTable') }}: {{ order.location?.name }}</span>
              <span v-if="order.location?.zone" class="order-meta__zone">{{ order.location.zone }}</span>
            </div>
          </div>
          <div class="order-panel__time">
            <span class="order-time">{{ orderTime(order) }}</span>
            <span class="order-elapsed">{{ t('staff.kdsElapsed', { minutes: orderElapsed(order) }) }}</span>
            <v-chip size="small" color="primary" variant="tonal">
              {{ t(columns.find((c) => c.key === kdsColumnForStatus(statusForOrder(order)))?.labelKey || 'staff.kdsNew') }}
            </v-chip>
          </div>
        </div>

        <div v-if="order.notes" class="order-notes mb-3">
          <span class="order-notes__label">{{ t('staff.kdsOrderNotes') }}</span>
          {{ order.notes }}
        </div>

        <div v-for="item in order.items" :key="item.id" class="mb-2">
          <OrderLineDetails :item="item" :show-price="false" />
        </div>

        <div class="mt-3 d-flex ga-2 flex-wrap">
          <v-btn
            variant="outlined"
            size="small"
            :loading="printingOrderId === order.id"
            @click="reprint(order)"
          >
            {{ t('staff.reprint') }}
          </v-btn>
          <v-btn
            v-if="nextAction(statusForOrder(order))"
            color="primary"
            class="staff-action flex-grow-1"
            @click="nextAction(statusForOrder(order))!.handler(order)"
          >
            {{ nextAction(statusForOrder(order))!.label }}
          </v-btn>
        </div>
      </article>
    </div>

    <div v-else class="kanban">
      <section v-for="col in columns" :key="col.key" class="kanban__col">
        <header class="kanban__head">
          <h2>{{ t(col.labelKey) }}</h2>
          <span class="kanban__count">{{ columnOrders[col.key]?.length || 0 }}</span>
        </header>
        <div class="kanban__list">
          <article
            v-for="order in columnOrders[col.key]"
            :key="order.id"
            class="order-panel order-panel--kanban pa-4"
            :class="{
              'order-panel--new': newOrderIds.has(order.id),
              'order-panel--urgent': isUrgent(order),
            }"
          >
            <div class="order-panel__head mb-2">
              <div>
                <strong class="order-number">{{ order.order_number }}</strong>
                <div class="order-meta">
                  <span>{{ t('staff.kdsTable') }}: {{ order.location?.name }}</span>
                  <span v-if="order.location?.zone" class="order-meta__zone">{{ order.location.zone }}</span>
                </div>
              </div>
              <div class="order-panel__time">
                <span class="order-time">{{ orderTime(order) }}</span>
                <span class="order-elapsed">{{ t('staff.kdsElapsed', { minutes: orderElapsed(order) }) }}</span>
              </div>
            </div>

            <div v-if="order.notes" class="order-notes mb-3">
              <span class="order-notes__label">{{ t('staff.kdsOrderNotes') }}</span>
              {{ order.notes }}
            </div>

            <div v-for="item in order.items" :key="item.id" class="mb-2">
              <OrderLineDetails :item="item" :show-price="false" />
            </div>

            <div class="mt-3 d-flex ga-2 flex-wrap">
              <v-btn
                variant="outlined"
                size="small"
                :loading="printingOrderId === order.id"
                @click="reprint(order)"
              >
                {{ t('staff.reprint') }}
              </v-btn>
              <v-btn
                v-if="nextAction(statusForOrder(order))"
                color="primary"
                block
                class="staff-action flex-grow-1"
                @click="nextAction(statusForOrder(order))!.handler(order)"
              >
                {{ nextAction(statusForOrder(order))!.label }}
              </v-btn>
            </div>
          </article>
          <p v-if="!(columnOrders[col.key]?.length)" class="kanban__empty">—</p>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.staff-title {
  font-size: clamp(1.35rem, 5vw, 2rem);
  line-height: 1.2;
  margin: 0;
}

.order-number {
  font-size: 1.15rem;
  letter-spacing: 0.02em;
}

.order-meta {
  font-weight: 600;
  color: var(--bo-teal-deep);
  font-size: 0.92rem;
  margin-top: 0.15rem;
}

.order-meta__zone::before {
  content: ' · ';
}

.order-panel__head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.75rem;
}

.order-panel__time {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.15rem;
  flex-shrink: 0;
}

.order-time {
  font-weight: 700;
  font-size: 1rem;
  color: var(--bo-teal-deep);
}

.order-elapsed {
  font-size: 0.75rem;
  color: rgba(20, 54, 66, 0.55);
}

.order-notes {
  padding: 0.55rem 0.65rem;
  border-radius: 10px;
  background: rgba(255, 193, 7, 0.12);
  border: 1px solid rgba(255, 193, 7, 0.35);
  font-size: 0.88rem;
}

.order-notes__label {
  display: block;
  font-size: 0.68rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: rgba(20, 54, 66, 0.55);
  margin-bottom: 0.15rem;
}

.order-panel {
  background: rgba(255, 255, 255, 0.78);
  border: 1px solid rgba(11, 110, 107, 0.14);
  border-radius: 16px;
  transition: box-shadow 0.25s ease, border-color 0.25s ease;
}

.order-panel--kanban {
  margin-bottom: 0.75rem;
}

.order-panel--new {
  border-color: rgba(11, 110, 107, 0.45);
  box-shadow: 0 0 0 2px rgba(11, 110, 107, 0.18);
  animation: kds-pulse 1.6s ease-in-out 3;
}

.order-panel--urgent {
  border-color: rgba(211, 47, 47, 0.45);
}

.staff-action {
  min-height: 44px;
}

.kanban {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1rem;
  align-items: start;
}

.kanban__col {
  min-height: 280px;
  border-radius: 18px;
  padding: 0.85rem;
  background: rgba(255, 255, 255, 0.35);
  border: 1px solid rgba(11, 110, 107, 0.1);
}

.kanban__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.85rem;
}

.kanban__head h2 {
  margin: 0;
  font-size: 0.95rem;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--bo-teal-deep);
}

.kanban__count {
  min-width: 1.6rem;
  height: 1.6rem;
  border-radius: 999px;
  display: inline-grid;
  place-items: center;
  background: rgba(11, 110, 107, 0.12);
  font-size: 0.8rem;
  font-weight: 700;
}

.kanban__empty {
  margin: 1.5rem 0;
  text-align: center;
  color: rgba(20, 54, 66, 0.35);
}

@keyframes kds-pulse {
  0%,
  100% {
    box-shadow: 0 0 0 2px rgba(11, 110, 107, 0.18);
  }
  50% {
    box-shadow: 0 0 0 4px rgba(11, 110, 107, 0.28);
  }
}
</style>
