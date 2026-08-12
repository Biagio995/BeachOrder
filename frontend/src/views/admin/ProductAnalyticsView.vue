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

interface LocationOpt {
  id: number
  name: string
  zone?: string
}

interface ProductRow {
  rank: number
  product_id: number
  product_name: string
  qty: number
  revenue: number
  qty_pct: number
  revenue_pct: number
}

interface AnalyticsData {
  filters: {
    period: string
    days: number
    location_id: number | null
    from: string
    to: string
  }
  totals: { qty: number; revenue: number }
  products: ProductRow[]
  locations: LocationOpt[]
}

const { t, locale } = useI18n()
const ui = useUiStore()
const auth = useAuthStore()
const router = useRouter()

const loading = ref(false)
const data = ref<AnalyticsData | null>(null)
const period = ref<AnalyticsPeriod>(DEFAULT_ANALYTICS_PERIOD)
const from = ref('')
const to = ref('')
const locationId = ref<number | null>(null)

const currency = auth.user?.tenant?.currency || 'EUR'

const locationItems = computed(() => [
  { title: t('admin.allLocations'), value: null as number | null },
  ...(data.value?.locations || []).map((l) => ({
    title: `${l.name}${l.zone ? ` · ${l.zone}` : ''}`,
    value: l.id as number | null,
  })),
])

async function load() {
  loading.value = true
  try {
    const params: Record<string, string | number> = {}
    if (period.value === 'custom') {
      if (!from.value || !to.value) return
      params.from = from.value
      params.to = to.value
    } else {
      params.period = period.value
    }
    if (locationId.value) params.location_id = locationId.value

    const { data: response } = await api.get<AnalyticsData>('/admin/reports/products', { params })
    data.value = response
  } catch (e: unknown) {
    ui.error(getApiErrorMessage(e, t('common.error')))
  } finally {
    loading.value = false
  }
}

watch([period, from, to, locationId], () => {
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

function pct(v: number) {
  return `${v.toLocaleString(locale.value, { maximumFractionDigits: 1 })}%`
}
</script>

<template>
  <div class="product-analytics">
    <div class="product-analytics__head mb-4">
      <div>
        <h1 class="display-font product-analytics__title" style="color: var(--bo-teal-deep)">
          {{ t('admin.productAnalytics.title') }}
        </h1>
        <p class="text-medium-emphasis mb-0">{{ t('admin.productAnalytics.lead') }}</p>
      </div>
      <div class="d-flex ga-2">
        <v-btn to="/admin/analytics" size="small" variant="text" color="primary" prepend-icon="mdi-chart-line">
          {{ t('admin.analytics.title') }}
        </v-btn>
        <v-btn icon="mdi-refresh" variant="tonal" :loading="loading" :aria-label="t('common.refresh')" @click="load" />
      </div>
    </div>

    <AnalyticsPeriodFilter v-model:period="period" v-model:from="from" v-model:to="to" class="mb-4" />

    <v-select
      v-model="locationId"
      :items="locationItems"
      item-title="title"
      item-value="value"
      :label="t('admin.locationFilter')"
      density="compact"
      hide-details
      clearable
      class="filters__location mb-5"
    />

    <v-progress-linear v-if="loading && !data" indeterminate color="primary" class="mb-4" />

    <template v-if="data">
      <p class="text-caption text-medium-emphasis mb-3">
        {{ t('admin.periodRange', { from: data.filters.from, to: data.filters.to }) }}
      </p>

      <v-row dense class="mb-4">
        <v-col cols="12" sm="4">
          <KpiCard
            :label="t('admin.productAnalytics.totalQty')"
            :value="data.totals.qty"
            :hint="t('admin.productAnalytics.totalQtyHint')"
          />
        </v-col>
        <v-col cols="12" sm="4">
          <KpiCard
            :label="t('admin.productAnalytics.totalRevenue')"
            :value="money(data.totals.revenue)"
            :hint="t('admin.productAnalytics.totalRevenueHint')"
          />
        </v-col>
        <v-col cols="12" sm="4">
          <KpiCard
            :label="t('admin.productAnalytics.productsRanked')"
            :value="data.products.length"
            :hint="t('admin.productAnalytics.productsRankedHint')"
          />
        </v-col>
      </v-row>

      <section class="panel">
        <h2 class="panel__title">{{ t('admin.productAnalytics.rankingTitle') }}</h2>
        <p class="panel__lead">{{ t('admin.productAnalytics.rankingLead') }}</p>

        <div v-if="!data.products.length" class="text-medium-emphasis">
          {{ t('admin.noData') }}
        </div>

        <div v-else class="table-wrap">
          <v-table density="comfortable" class="ranking-table">
            <thead>
              <tr>
                <th class="col-rank">{{ t('admin.productAnalytics.rank') }}</th>
                <th>{{ t('admin.productAnalytics.product') }}</th>
                <th class="col-num text-end">{{ t('admin.productAnalytics.qtySold') }}</th>
                <th class="col-num text-end">{{ t('admin.productAnalytics.qtyPct') }}</th>
                <th class="col-num text-end">{{ t('admin.productAnalytics.revenue') }}</th>
                <th class="col-num text-end">{{ t('admin.productAnalytics.revenuePct') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in data.products" :key="row.product_id">
                <td class="col-rank">
                  <span class="rank-badge">{{ row.rank }}</span>
                </td>
                <td>
                  <div class="font-weight-medium">{{ row.product_name }}</div>
                </td>
                <td class="text-end">{{ row.qty }}</td>
                <td class="text-end text-medium-emphasis">{{ pct(row.qty_pct) }}</td>
                <td class="text-end font-weight-medium">{{ money(row.revenue) }}</td>
                <td class="text-end text-medium-emphasis">{{ pct(row.revenue_pct) }}</td>
              </tr>
            </tbody>
          </v-table>
        </div>
      </section>
    </template>
  </div>
</template>

<style scoped>
.product-analytics__head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  flex-wrap: wrap;
}

.product-analytics__title {
  font-size: clamp(1.5rem, 4vw, 2rem);
  margin: 0 0 0.25rem;
  line-height: 1.15;
}

.filters__location {
  max-width: 280px;
}

.panel {
  background: rgba(255, 255, 255, 0.72);
  border: 1px solid rgba(11, 110, 107, 0.14);
  border-radius: 18px;
  padding: 1.1rem 1.15rem 1.25rem;
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

.table-wrap {
  overflow-x: auto;
}

.ranking-table :deep(th) {
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: rgba(20, 54, 66, 0.55) !important;
  white-space: nowrap;
}

.ranking-table :deep(td) {
  border-bottom: 1px solid rgba(11, 110, 107, 0.1) !important;
}

.col-rank {
  width: 3rem;
}

.col-num {
  white-space: nowrap;
}

.rank-badge {
  display: inline-grid;
  place-items: center;
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 999px;
  background: rgba(11, 110, 107, 0.12);
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--bo-teal-deep);
}
</style>
