<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import api, { getApiErrorMessage } from '@/api/client'
import KpiCard from '@/components/admin/KpiCard.vue'
import PeriodPills from '@/components/admin/PeriodPills.vue'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { displayLocalizedName } from '@/utils/localeName'
import { money as formatMoneyAmount } from '@/utils/moneyFormat'


interface DayPoint {
  day: string
  orders: number
  revenue: number
}

interface HourPoint {
  hour: number
  orders: number
  revenue: number
}

interface LocationOpt {
  id: number
  name: string
  zone?: string
}

interface DashboardData {
  filters: { days: number; location_id: number | null; from: string; to: string }
  kpis: Record<string, number>
  revenue_by_day: DayPoint[]
  orders_by_hour: HourPoint[]
  status_breakdown: Record<string, number>
  payment_methods: Array<{ payment_method: string; count: number; revenue: number }>
  by_location: Array<{ id: number; name: string; zone?: string; orders: number; revenue: number }>
  top_products: Array<{ product_name: string; qty: number; revenue: number }>
  low_stock: Array<{
    id: number
    name: Record<string, string> | string
    stock_quantity: number
    low_stock_threshold: number
  }>
  locations: LocationOpt[]
}

const { t, locale } = useI18n()
const ui = useUiStore()
const auth = useAuthStore()
const router = useRouter()

const loading = ref(false)
const summary = ref<DashboardData | null>(null)
const days = ref(7)
const locationId = ref<number | null>(null)

const currency = auth.user?.tenant?.currency || 'EUR'

const locationItems = computed(() => [
  { title: t('admin.allLocations'), value: null as number | null },
  ...(summary.value?.locations || []).map((l) => ({
    title: `${l.name}${l.zone ? ` · ${l.zone}` : ''}`,
    value: l.id as number | null,
  })),
])

const maxDayRevenue = computed(() =>
  Math.max(1, ...(summary.value?.revenue_by_day || []).map((d) => Number(d.revenue || 0))),
)

const maxHourOrders = computed(() =>
  Math.max(1, ...(summary.value?.orders_by_hour || []).map((d) => Number(d.orders || 0))),
)

const statusEntries = computed(() => {
  const raw = summary.value?.status_breakdown || {}
  const total = Object.values(raw).reduce((s, n) => s + Number(n), 0) || 1
  return Object.entries(raw)
    .map(([status, count]) => ({
      status,
      count: Number(count),
      pct: (Number(count) / total) * 100,
    }))
    .sort((a, b) => b.count - a.count)
})

const chartPoints = computed(() => {
  const daysData = summary.value?.revenue_by_day || []
  if (!daysData.length) return ''
  const w = 100
  const h = 42
  const max = maxDayRevenue.value
  return daysData
    .map((d, i) => {
      const x = daysData.length === 1 ? w / 2 : (i / (daysData.length - 1)) * w
      const y = h - (Number(d.revenue) / max) * (h - 4) - 2
      return `${x.toFixed(2)},${y.toFixed(2)}`
    })
    .join(' ')
})

const peakHour = computed(() => {
  const hours = summary.value?.orders_by_hour || []
  if (!hours.length) return null
  return [...hours].sort((a, b) => b.orders - a.orders)[0]
})

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/admin/reports/summary', {
      params: {
        days: days.value,
        location_id: locationId.value || undefined,
      },
    })
    summary.value = data
  } catch (e: unknown) {
    ui.error(getApiErrorMessage(e, t('common.error')))
  } finally {
    loading.value = false
  }
}

watch([days, locationId], load)
onMounted(() => {
  if (auth.isSuperAdmin && !localStorage.getItem('bo_tenant_slug')) {
    router.replace('/admin/tenants')
    return
  }
  load()
})

function money(v: number | string) {
  return formatMoneyAmount(v, currency, String(locale.value))
}

function productName(name: Record<string, string> | string) {
  return displayLocalizedName(name, String(locale.value)) || '—'
}

function shortDay(day: string) {
  try {
    return new Date(day).toLocaleDateString(locale.value, { weekday: 'short', day: 'numeric' })
  } catch {
    return day
  }
}

function statusColor(status: string) {
  const map: Record<string, string> = {
    received: '#457B9D',
    accepted: '#1A8A86',
    preparing: '#E9C46A',
    ready: '#2A9D8F',
    delivering: '#E07A5F',
    delivered: '#0B6E6B',
    cancelled: '#C23B22',
  }
  return map[status] || '#0B6E6B'
}
</script>

<template>
  <div class="dash">
    <div class="dash__head mb-4">
      <div>
        <h1 class="display-font dash__title" style="color: var(--bo-teal-deep)">{{ t('admin.dashboard') }}</h1>
        <p class="text-medium-emphasis mb-0">{{ t('admin.dashboardLead') }}</p>
      </div>
      <v-btn icon="mdi-refresh" variant="tonal" :loading="loading" :aria-label="t('common.refresh')" @click="load" />
    </div>

    <div class="filters mb-5">
      <PeriodPills v-model="days" />
      <v-select
        v-model="locationId"
        :items="locationItems"
        item-title="title"
        item-value="value"
        :label="t('admin.locationFilter')"
        density="compact"
        hide-details
        clearable
        class="filters__location"
      />
    </div>

    <v-progress-linear v-if="loading && !summary" indeterminate color="primary" class="mb-4" />

    <template v-if="summary">
      <p class="text-caption text-medium-emphasis mb-3">
        {{ t('admin.periodRange', { from: summary.filters.from, to: summary.filters.to }) }}
      </p>

      <v-row dense class="mb-2">
        <v-col cols="6" md="3">
          <KpiCard
            :label="t('admin.kpi.ordersPeriod')"
            :value="summary.kpis.orders_active"
            :hint="t('admin.kpi.ordersPeriodHint', { cancelled: summary.kpis.cancelled_period })"
          />
        </v-col>
        <v-col cols="6" md="3">
          <KpiCard
            :label="t('admin.kpi.revenuePeriod')"
            :value="money(summary.kpis.revenue_period)"
            :hint="t('admin.kpi.revenuePeriodHint')"
          />
        </v-col>
        <v-col cols="6" md="3">
          <KpiCard
            :label="t('admin.kpi.paidPeriod')"
            :value="money(summary.kpis.paid_period)"
            :hint="t('admin.kpi.unpaidHint', { amount: money(summary.kpis.unpaid_period) })"
          />
        </v-col>
        <v-col cols="6" md="3">
          <KpiCard
            :label="t('admin.avgTicket')"
            :value="money(summary.kpis.avg_ticket)"
            :hint="t('admin.kpi.avgTicketHint')"
          />
        </v-col>
      </v-row>

      <v-row dense class="mb-4">
        <v-col cols="6" md="3">
          <KpiCard :label="t('admin.ordersToday')" :value="summary.kpis.orders_today" compact />
        </v-col>
        <v-col cols="6" md="3">
          <KpiCard :label="t('admin.kpi.pendingNow')" :value="summary.kpis.pending_orders" compact />
        </v-col>
        <v-col cols="6" md="3">
          <KpiCard :label="t('admin.kpi.readyNow')" :value="summary.kpis.ready_orders" compact />
        </v-col>
        <v-col cols="6" md="3">
          <KpiCard :label="t('admin.kpi.openCalls')" :value="summary.kpis.open_waiter_calls" compact />
        </v-col>
      </v-row>

      <v-row>
        <v-col cols="12" lg="7">
          <section class="panel">
            <div class="panel__head">
              <div>
                <h2 class="panel__title">{{ t('admin.charts.revenueTitle') }}</h2>
                <p class="panel__lead">{{ t('admin.charts.revenueLead') }}</p>
              </div>
            </div>

            <div class="chart">
              <svg viewBox="0 0 100 48" class="chart__svg" preserveAspectRatio="none" aria-hidden="true">
                <defs>
                  <linearGradient id="revFill" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="rgba(11,110,107,0.35)" />
                    <stop offset="100%" stop-color="rgba(11,110,107,0.02)" />
                  </linearGradient>
                </defs>
                <polyline
                  v-if="chartPoints"
                  :points="`0,46 ${chartPoints} 100,46`"
                  fill="url(#revFill)"
                  stroke="none"
                />
                <polyline
                  v-if="chartPoints"
                  :points="chartPoints"
                  fill="none"
                  stroke="#0B6E6B"
                  stroke-width="1.2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  vector-effect="non-scaling-stroke"
                />
              </svg>

              <div class="chart__bars">
                <div v-for="day in summary.revenue_by_day" :key="day.day" class="chart__col">
                  <div
                    class="chart__bar"
                    :style="{ height: `${(Number(day.revenue) / maxDayRevenue) * 100}%` }"
                    :title="`${shortDay(day.day)}: ${money(day.revenue)} · ${day.orders}`"
                  />
                  <span class="chart__label">{{ shortDay(day.day) }}</span>
                </div>
              </div>
            </div>

            <div class="legend mt-3">
              <span>{{ t('admin.charts.totalRevenue') }}: <strong>{{ money(summary.kpis.revenue_period) }}</strong></span>
              <span v-if="peakHour && peakHour.orders > 0">
                {{ t('admin.charts.peakHour', { hour: String(peakHour.hour).padStart(2, '0') }) }}
              </span>
            </div>
          </section>
        </v-col>

        <v-col cols="12" lg="5">
          <div class="panel-stack">
            <section class="panel">
              <h2 class="panel__title">{{ t('admin.charts.statusTitle') }}</h2>
              <p class="panel__lead">{{ t('admin.charts.statusLead') }}</p>
              <div v-if="!statusEntries.length" class="text-medium-emphasis">{{ t('admin.noData') }}</div>
              <div v-for="row in statusEntries" :key="row.status" class="meter mb-3">
                <div class="d-flex justify-space-between mb-1">
                  <span>{{ t(`order.statuses.${row.status}`, row.status) }}</span>
                  <strong>{{ row.count }}</strong>
                </div>
                <div class="meter__track">
                  <div class="meter__fill" :style="{ width: `${row.pct}%`, background: statusColor(row.status) }" />
                </div>
              </div>
            </section>

            <section class="panel">
              <h2 class="panel__title">{{ t('admin.charts.paymentTitle') }}</h2>
              <p class="panel__lead">{{ t('admin.charts.paymentLead') }}</p>
              <div v-if="!(summary.payment_methods || []).length" class="text-medium-emphasis">{{ t('admin.noData') }}</div>
              <div
                v-for="p in summary.payment_methods"
                :key="p.payment_method"
                class="row-line"
              >
                <span>{{ t(`cart.payment.${p.payment_method}`, p.payment_method) }}</span>
                <span>{{ p.count }} · {{ money(p.revenue) }}</span>
              </div>
            </section>
          </div>
        </v-col>
      </v-row>

      <v-row class="mt-2">
        <v-col cols="12" md="6">
          <section class="panel">
            <h2 class="panel__title">{{ t('admin.charts.hourlyTitle') }}</h2>
            <p class="panel__lead">{{ t('admin.charts.hourlyLead') }}</p>
            <div class="hour-grid">
              <div
                v-for="h in summary.orders_by_hour"
                :key="h.hour"
                class="hour-cell"
                :title="`${String(h.hour).padStart(2, '0')}:00 · ${h.orders}`"
              >
                <div
                  class="hour-cell__bar"
                  :style="{ opacity: 0.18 + (h.orders / maxHourOrders) * 0.82 }"
                />
                <span v-if="h.hour % 3 === 0" class="hour-cell__label">{{ h.hour }}</span>
              </div>
            </div>
          </section>
        </v-col>

        <v-col cols="12" md="6">
          <section class="panel">
            <div class="panel__head">
              <div>
                <h2 class="panel__title">{{ t('admin.topProducts') }}</h2>
                <p class="panel__lead mb-0">{{ t('admin.charts.topProductsLead') }}</p>
              </div>
              <v-btn
                to="/admin/analytics/products"
                size="small"
                variant="text"
                color="primary"
              >
                {{ t('admin.productAnalytics.viewAll') }}
              </v-btn>
            </div>
            <div v-if="!(summary.top_products || []).length" class="text-medium-emphasis">{{ t('admin.noData') }}</div>
            <div v-for="(p, i) in summary.top_products" :key="i" class="rank-row">
              <span class="rank-row__idx">{{ i + 1 }}</span>
              <div class="min-w-0 flex-grow-1">
                <div class="text-truncate font-weight-medium">{{ p.product_name }}</div>
                <div class="text-caption text-medium-emphasis">{{ p.qty }} {{ t('admin.units') }}</div>
              </div>
              <strong>{{ money(p.revenue) }}</strong>
            </div>
          </section>
        </v-col>
      </v-row>

      <v-row class="mt-2">
        <v-col cols="12" md="6">
          <section class="panel">
            <h2 class="panel__title">{{ t('admin.charts.locationsTitle') }}</h2>
            <p class="panel__lead">{{ t('admin.charts.locationsLead') }}</p>
            <div v-if="!(summary.by_location || []).length" class="text-medium-emphasis">{{ t('admin.noData') }}</div>
            <div v-for="loc in summary.by_location" :key="loc.id" class="row-line">
              <span>{{ loc.name }}<span v-if="loc.zone" class="text-medium-emphasis"> · {{ loc.zone }}</span></span>
              <span>{{ loc.orders }} · {{ money(loc.revenue) }}</span>
            </div>
          </section>
        </v-col>

        <v-col cols="12" md="6">
          <section class="panel">
            <h2 class="panel__title">{{ t('admin.lowStock') }}</h2>
            <p class="panel__lead">{{ t('admin.charts.lowStockLead') }}</p>
            <div v-if="!(summary.low_stock || []).length" class="text-medium-emphasis">{{ t('admin.stockOk') }}</div>
            <div v-for="p in summary.low_stock" :key="p.id" class="row-line">
              <span>{{ productName(p.name) }}</span>
              <v-chip size="small" color="warning" variant="tonal">
                {{ p.stock_quantity }} / {{ p.low_stock_threshold }}
              </v-chip>
            </div>
          </section>
        </v-col>
      </v-row>
    </template>
  </div>
</template>

<style scoped>
.dash__head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
}

.dash__title {
  font-size: clamp(1.5rem, 4vw, 2rem);
  margin: 0 0 0.25rem;
  line-height: 1.15;
}

.filters {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.filters__location {
  max-width: 280px;
}

.stat {
  background: rgba(255, 255, 255, 0.78);
  border: 1px solid rgba(11, 110, 107, 0.14);
  border-radius: 16px;
  padding: 1rem 1.1rem;
  height: 100%;
}

.stat--soft {
  background: rgba(255, 255, 255, 0.55);
}

.stat__label {
  color: rgba(20, 54, 66, 0.65);
  font-size: 0.85rem;
  margin-bottom: 0.25rem;
}

.stat__value {
  font-size: clamp(1.25rem, 3vw, 1.7rem);
  font-weight: 700;
  color: var(--bo-teal-deep);
  line-height: 1.15;
}

.stat__value--sm {
  font-size: 1.35rem;
}

.stat__hint {
  margin-top: 0.35rem;
  font-size: 0.75rem;
  color: rgba(20, 54, 66, 0.55);
}

.panel {
  background: rgba(255, 255, 255, 0.72);
  border: 1px solid rgba(11, 110, 107, 0.14);
  border-radius: 18px;
  padding: 1.1rem 1.15rem 1.25rem;
  position: relative;
  z-index: 1;
  overflow: hidden;
}

.panel-stack {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  height: 100%;
}

.panel-stack .panel:last-child {
  flex: 1;
}

.panel__title {
  margin: 0;
  font-size: 1.05rem;
  color: var(--bo-teal-deep);
}

.panel__lead {
  margin: 0.2rem 0 1rem;
  color: rgba(20, 54, 66, 0.6);
  font-size: 0.85rem;
}

.panel__head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.panel__head .panel__lead {
  margin-bottom: 0;
}

.chart {
  position: relative;
}

.chart__svg {
  position: absolute;
  inset: 0 0 1.4rem 0;
  width: 100%;
  height: calc(100% - 1.4rem);
  opacity: 0.9;
  pointer-events: none;
}

.chart__bars {
  display: grid;
  grid-auto-flow: column;
  grid-auto-columns: minmax(0, 1fr);
  gap: 0.35rem;
  align-items: end;
  height: 180px;
  position: relative;
  z-index: 1;
}

.chart__col {
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  align-items: center;
  height: 100%;
  gap: 0.35rem;
}

.chart__bar {
  width: 100%;
  min-height: 2px;
  border-radius: 8px 8px 4px 4px;
  background: linear-gradient(180deg, #1a8a86, #0b6e6b);
  opacity: 0.85;
}

.chart__label {
  font-size: 0.65rem;
  color: rgba(20, 54, 66, 0.55);
  white-space: nowrap;
}

.legend {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem 1.25rem;
  font-size: 0.85rem;
  color: rgba(20, 54, 66, 0.7);
}

.meter__track {
  height: 8px;
  border-radius: 999px;
  background: rgba(11, 110, 107, 0.1);
  overflow: hidden;
}

.meter__fill {
  height: 100%;
  border-radius: inherit;
}

.row-line {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.55rem 0;
  border-bottom: 1px solid rgba(11, 110, 107, 0.1);
}

.rank-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.55rem 0;
  border-bottom: 1px solid rgba(11, 110, 107, 0.1);
}

.rank-row__idx {
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 999px;
  display: grid;
  place-items: center;
  background: rgba(11, 110, 107, 0.12);
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--bo-teal-deep);
}

.hour-grid {
  display: grid;
  grid-template-columns: repeat(24, minmax(0, 1fr));
  gap: 3px;
  align-items: end;
  height: 96px;
}

.hour-cell {
  height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  align-items: center;
  gap: 0.2rem;
}

.hour-cell__bar {
  width: 100%;
  flex: 1;
  border-radius: 4px 4px 2px 2px;
  background: var(--bo-coral);
  min-height: 4px;
}

.hour-cell__label {
  font-size: 0.6rem;
  color: rgba(20, 54, 66, 0.5);
}

@media (min-width: 900px) {
  .filters {
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
  }
}
</style>
