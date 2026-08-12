<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import api, { getApiErrorMessage } from '@/api/client'
import AnalyticsPeriodFilter from '@/components/admin/AnalyticsPeriodFilter.vue'
import KpiCard from '@/components/admin/KpiCard.vue'
import {
  DEFAULT_ANALYTICS_PERIOD,
  type AnalyticsPeriod,
} from '@/constants/analyticsPeriods'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { money as formatMoneyAmount } from '@/utils/moneyFormat'

interface DayPoint {
  day: string
  orders: number
  revenue?: number
}

interface HourPoint {
  hour: number
  orders: number
  revenue: number
}

interface TableRow {
  id: number
  name: string
  zone?: string
  type?: string
  orders: number
  revenue: number
}

interface SalesData {
  filters: {
    period: string
    from: string
    to: string
    location_id: number | null
  }
  total_orders: number
  total_revenue: number
  average_order_value: number
  orders_by_day: DayPoint[]
  revenue_by_day: DayPoint[]
  best_selling_products: Array<{ product_name: string; qty: number; revenue: number }>
  orders_by_hour: HourPoint[]
  orders_by_table: TableRow[]
}

const { t, locale } = useI18n()
const ui = useUiStore()
const auth = useAuthStore()
const router = useRouter()

const loading = ref(false)
const data = ref<SalesData | null>(null)
const period = ref<AnalyticsPeriod>(DEFAULT_ANALYTICS_PERIOD)
const from = ref('')
const to = ref('')

const currency = auth.user?.tenant?.currency || 'EUR'

const maxDayOrders = computed(() =>
  Math.max(1, ...(data.value?.orders_by_day || []).map((d) => Number(d.orders || 0))),
)

const maxDayRevenue = computed(() =>
  Math.max(1, ...(data.value?.revenue_by_day || []).map((d) => Number(d.revenue || 0))),
)

const maxHourOrders = computed(() =>
  Math.max(1, ...(data.value?.orders_by_hour || []).map((d) => Number(d.orders || 0))),
)

const revenueChartPoints = computed(() => {
  const daysData = data.value?.revenue_by_day || []
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
  const hours = data.value?.orders_by_hour || []
  if (!hours.length) return null
  return [...hours].sort((a, b) => b.orders - a.orders)[0]
})

async function load() {
  loading.value = true
  try {
    const params: Record<string, string> = {}
    if (period.value === 'custom') {
      if (!from.value || !to.value) return
      params.from = from.value
      params.to = to.value
    } else {
      params.period = period.value
    }

    const { data: response } = await api.get<SalesData>('/admin/reports/sales', { params })
    data.value = response
  } catch (e: unknown) {
    ui.error(getApiErrorMessage(e, t('common.error')))
  } finally {
    loading.value = false
  }
}

watch([period, from, to], () => {
  if (period.value === 'custom' && (!from.value || !to.value)) return
  load()
})

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

function shortDay(day: string) {
  try {
    return new Date(day).toLocaleDateString(locale.value, { weekday: 'short', day: 'numeric' })
  } catch {
    return day
  }
}

function tableLabel(row: TableRow) {
  const typeLabel = row.type ? t(`admin.locationTypes.${row.type}`, row.type) : ''
  const zone = row.zone ? ` · ${row.zone}` : ''
  return typeLabel ? `${row.name} (${typeLabel})${zone}` : `${row.name}${zone}`
}
</script>

<template>
  <div class="analytics">
    <div class="analytics__head mb-4">
      <div>
        <h1 class="display-font analytics__title" style="color: var(--bo-teal-deep)">
          {{ t('admin.analytics.title') }}
        </h1>
        <p class="text-medium-emphasis mb-0">{{ t('admin.analytics.lead') }}</p>
      </div>
      <v-btn icon="mdi-refresh" variant="tonal" :loading="loading" :aria-label="t('common.refresh')" @click="load" />
    </div>

    <AnalyticsPeriodFilter v-model:period="period" v-model:from="from" v-model:to="to" class="mb-5" />

    <v-progress-linear v-if="loading && !data" indeterminate color="primary" class="mb-4" />

    <template v-if="data">
      <p class="text-caption text-medium-emphasis mb-3">
        {{ t('admin.periodRange', { from: data.filters.from, to: data.filters.to }) }}
      </p>

      <v-row dense class="mb-4">
        <v-col cols="12" sm="4">
          <KpiCard
            :label="t('admin.analytics.totalOrders')"
            :value="data.total_orders"
            :hint="t('admin.analytics.totalOrdersHint')"
          />
        </v-col>
        <v-col cols="12" sm="4">
          <KpiCard
            :label="t('admin.analytics.totalRevenue')"
            :value="money(data.total_revenue)"
            :hint="t('admin.analytics.totalRevenueHint')"
          />
        </v-col>
        <v-col cols="12" sm="4">
          <KpiCard
            :label="t('admin.analytics.avgOrderValue')"
            :value="money(data.average_order_value)"
            :hint="t('admin.analytics.avgOrderValueHint')"
          />
        </v-col>
      </v-row>

      <v-row>
        <v-col cols="12" lg="6">
          <section class="panel">
            <h2 class="panel__title">{{ t('admin.analytics.ordersByDay') }}</h2>
            <p class="panel__lead">{{ t('admin.analytics.ordersByDayLead') }}</p>
            <div v-if="!(data.orders_by_day || []).length" class="text-medium-emphasis">{{ t('admin.noData') }}</div>
            <div v-else class="chart__bars">
              <div v-for="day in data.orders_by_day" :key="day.day" class="chart__col">
                <div
                  class="chart__bar chart__bar--orders"
                  :style="{ height: `${(Number(day.orders) / maxDayOrders) * 100}%` }"
                  :title="`${shortDay(day.day)}: ${day.orders}`"
                />
                <span class="chart__label">{{ shortDay(day.day) }}</span>
              </div>
            </div>
          </section>
        </v-col>

        <v-col cols="12" lg="6">
          <section class="panel">
            <h2 class="panel__title">{{ t('admin.analytics.revenueByDay') }}</h2>
            <p class="panel__lead">{{ t('admin.analytics.revenueByDayLead') }}</p>
            <div v-if="!(data.revenue_by_day || []).length" class="text-medium-emphasis">{{ t('admin.noData') }}</div>
            <div v-else class="chart">
              <svg viewBox="0 0 100 48" class="chart__svg" preserveAspectRatio="none" aria-hidden="true">
                <defs>
                  <linearGradient id="analyticsRevFill" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="rgba(11,110,107,0.35)" />
                    <stop offset="100%" stop-color="rgba(11,110,107,0.02)" />
                  </linearGradient>
                </defs>
                <polyline
                  v-if="revenueChartPoints"
                  :points="`0,46 ${revenueChartPoints} 100,46`"
                  fill="url(#analyticsRevFill)"
                  stroke="none"
                />
                <polyline
                  v-if="revenueChartPoints"
                  :points="revenueChartPoints"
                  fill="none"
                  stroke="#0B6E6B"
                  stroke-width="1.2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  vector-effect="non-scaling-stroke"
                />
              </svg>
              <div class="chart__bars">
                <div v-for="day in data.revenue_by_day" :key="day.day" class="chart__col">
                  <div
                    class="chart__bar"
                    :style="{ height: `${(Number(day.revenue) / maxDayRevenue) * 100}%` }"
                    :title="`${shortDay(day.day)}: ${money(day.revenue || 0)}`"
                  />
                  <span class="chart__label">{{ shortDay(day.day) }}</span>
                </div>
              </div>
            </div>
          </section>
        </v-col>
      </v-row>

      <v-row class="mt-2">
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
            <div v-if="!(data.best_selling_products || []).length" class="text-medium-emphasis">{{ t('admin.noData') }}</div>
            <div v-for="(p, i) in data.best_selling_products" :key="i" class="rank-row">
              <span class="rank-row__idx">{{ i + 1 }}</span>
              <div class="min-w-0 flex-grow-1">
                <div class="text-truncate font-weight-medium">{{ p.product_name }}</div>
                <div class="text-caption text-medium-emphasis">{{ p.qty }} {{ t('admin.units') }}</div>
              </div>
              <strong>{{ money(p.revenue) }}</strong>
            </div>
          </section>
        </v-col>

        <v-col cols="12" md="6">
          <section class="panel">
            <h2 class="panel__title">{{ t('admin.charts.hourlyTitle') }}</h2>
            <p class="panel__lead">{{ t('admin.charts.hourlyLead') }}</p>
            <div class="hour-grid">
              <div
                v-for="h in data.orders_by_hour"
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
            <p v-if="peakHour && peakHour.orders > 0" class="text-caption text-medium-emphasis mt-2 mb-0">
              {{ t('admin.charts.peakHour', { hour: String(peakHour.hour).padStart(2, '0') }) }}
            </p>
          </section>
        </v-col>
      </v-row>

      <v-row class="mt-2">
        <v-col cols="12">
          <section class="panel">
            <h2 class="panel__title">{{ t('admin.analytics.ordersByTable') }}</h2>
            <p class="panel__lead">{{ t('admin.analytics.ordersByTableLead') }}</p>
            <div v-if="!(data.orders_by_table || []).length" class="text-medium-emphasis">{{ t('admin.noData') }}</div>
            <div v-for="row in data.orders_by_table" :key="row.id" class="row-line">
              <span>{{ tableLabel(row) }}</span>
              <span>{{ row.orders }} · {{ money(row.revenue) }}</span>
            </div>
          </section>
        </v-col>
      </v-row>
    </template>
  </div>
</template>

<style scoped>
.analytics__head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
}

.analytics__title {
  font-size: clamp(1.5rem, 4vw, 2rem);
  margin: 0 0 0.25rem;
  line-height: 1.15;
}

.panel {
  background: rgba(255, 255, 255, 0.72);
  border: 1px solid rgba(11, 110, 107, 0.14);
  border-radius: 18px;
  padding: 1.1rem 1.15rem 1.25rem;
  height: 100%;
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

.chart__bar--orders {
  background: linear-gradient(180deg, #e07a5f, #c96a52);
}

.chart__label {
  font-size: 0.65rem;
  color: rgba(20, 54, 66, 0.55);
  white-space: nowrap;
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

.row-line {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.55rem 0;
  border-bottom: 1px solid rgba(11, 110, 107, 0.1);
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
</style>
