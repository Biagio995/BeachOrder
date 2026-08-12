<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'
import api from '@/api/client'
import KpiCard from '@/components/admin/KpiCard.vue'
import PeriodPills from '@/components/admin/PeriodPills.vue'
import { useUiStore } from '@/stores/ui'
import { money as formatMoneyAmount } from '@/utils/moneyFormat'


interface TenantRow {
  id: number
  name: string
  slug: string
  default_locale: string
  currency: string
  is_active: boolean
  admin_suspended?: boolean
  subscription_status?: string
  created_at?: string
  orders: number
  orders_total: number
  revenue: number
  paid_revenue: number
  last_order_at: string | null
  locations: number
  locations_active: number
  users: number
}

interface OverviewData {
  filters: { days: number; from: string; to: string }
  kpis: {
    tenants_total: number
    tenants_active: number
    tenants_inactive: number
    orders: number
    revenue: number
    paid_revenue: number
    locations: number
    users: number
  }
  revenue_by_day: Array<{ day: string; orders: number; revenue: number }>
  tenants: TenantRow[]
}

const { t, locale } = useI18n()
const { smAndDown } = useDisplay()
const ui = useUiStore()

const overview = ref<OverviewData | null>(null)
const loading = ref(false)
const days = ref(7)
const dialogOpen = ref(false)
const saving = ref(false)
const q = ref('')

const emptyForm = () => ({
  id: null as number | null,
  name: '',
  slug: '',
  default_locale: 'it',
  currency: 'EUR',
  is_active: true,
})

const form = ref(emptyForm())

const dialogTitle = computed(() =>
  form.value.id ? t('admin.editTenant') : t('admin.createTenant'),
)

const canSave = computed(() => form.value.name.trim().length > 0)

const filteredTenants = computed(() => {
  const term = q.value.trim().toLowerCase()
  const rows = overview.value?.tenants || []
  if (!term) return rows
  return rows.filter(
    (tenant) =>
      tenant.name.toLowerCase().includes(term) ||
      tenant.slug.toLowerCase().includes(term),
  )
})

const maxDayRevenue = computed(() =>
  Math.max(1, ...(overview.value?.revenue_by_day || []).map((d) => Number(d.revenue || 0))),
)

const peakDay = computed(() => {
  const daysData = overview.value?.revenue_by_day || []
  if (!daysData.length) return null
  return [...daysData].sort((a, b) => Number(b.revenue) - Number(a.revenue))[0]
})

const hasChartData = computed(() =>
  (overview.value?.revenue_by_day || []).some((d) => Number(d.revenue) > 0 || Number(d.orders) > 0),
)

function money(n: number, currency = 'EUR') {
  return formatMoneyAmount(n, currency, locale.value)
}

function shortDay(day: string) {
  try {
    return new Date(day).toLocaleDateString(locale.value, { weekday: 'short', day: 'numeric' })
  } catch {
    return day
  }
}

function barHeight(revenue: number) {
  const pct = (Number(revenue) / maxDayRevenue.value) * 100
  return `${Math.max(pct, revenue > 0 ? 6 : 2)}%`
}

function showDayLabel(index: number, total: number) {
  if (total <= 10) return true
  if (total <= 16) return index % 2 === 0 || index === total - 1
  return index === 0 || index === total - 1 || index % 3 === 0
}

function formatWhen(iso: string | null) {
  if (!iso) return t('platform.never')
  try {
    return new Intl.DateTimeFormat(locale.value, {
      dateStyle: 'short',
      timeStyle: 'short',
    }).format(new Date(iso))
  } catch {
    return iso
  }
}

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/platform/overview', { params: { days: days.value } })
    overview.value = data
  } catch {
    ui.error(t('common.error'))
  } finally {
    loading.value = false
  }
}

function openCreate() {
  form.value = emptyForm()
  dialogOpen.value = true
}

function openEdit(tenant: TenantRow) {
  form.value = {
    id: tenant.id,
    name: tenant.name || '',
    slug: tenant.slug || '',
    default_locale: tenant.default_locale || 'it',
    currency: tenant.currency || 'EUR',
    is_active: tenant.is_active ?? true,
  }
  dialogOpen.value = true
}

function closeDialog() {
  dialogOpen.value = false
  form.value = emptyForm()
}

async function save() {
  if (!canSave.value || saving.value) return
  saving.value = true
  try {
    const payload = {
      name: form.value.name,
      slug: form.value.slug || undefined,
      default_locale: form.value.default_locale,
      currency: form.value.currency,
      is_active: form.value.is_active,
    }
    if (form.value.id) {
      await api.put(`/platform/tenants/${form.value.id}`, payload)
    } else {
      await api.post('/platform/tenants', {
        ...payload,
        branding: { tagline: form.value.name },
      })
    }
    closeDialog()
    ui.success(t('admin.saved'))
    await load()
  } catch {
    ui.error(t('common.error'))
  } finally {
    saving.value = false
  }
}

async function toggle(tenant: TenantRow) {
  const suspending = !tenant.admin_suspended
  if (suspending) {
    const ok = await ui.askConfirm({
      title: t('platform.confirmDeactivateTitle'),
      message: t('platform.confirmDeactivateMessage', { name: tenant.name }),
      confirmLabel: t('platform.deactivate'),
      cancelLabel: t('common.cancel'),
      danger: true,
    })
    if (!ok) return
  }
  try {
    await api.put(`/platform/tenants/${tenant.id}`, { is_active: !suspending })
    ui.success(suspending ? t('platform.tenantSuspended') : t('platform.tenantActivated'))
    await load()
  } catch {
    ui.error(t('common.error'))
  }
}

function subscriptionStatus(tenant: TenantRow) {
  if (tenant.admin_suspended) {
    return t('platform.subscriptionStatus.suspended')
  }
  const status = tenant.subscription_status || (tenant.is_active ? 'active' : 'inactive')
  const key = `platform.subscriptionStatus.${status}` as const
  return t(key, status)
}

function useTenant(slug: string) {
  localStorage.setItem('bo_tenant_slug', slug)
  window.location.href = '/admin'
}

watch(days, load)
onMounted(load)
</script>

<template>
  <div class="platform">
    <div class="d-flex justify-space-between align-start flex-wrap ga-3 mb-4">
      <div>
        <h1 class="display-font text-h4 mb-1" style="color: var(--bo-teal-deep)">
          {{ t('platform.title') }}
        </h1>
        <p class="text-body-2 text-medium-emphasis mb-0">{{ t('platform.lead') }}</p>
      </div>
      <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreate">{{ t('admin.createTenant') }}</v-btn>
    </div>

    <div class="d-flex flex-wrap ga-3 mb-4 align-center">
      <PeriodPills v-model="days" />
      <v-spacer />
      <v-text-field
        v-model="q"
        :placeholder="t('platform.search')"
        prepend-inner-icon="mdi-magnify"
        hide-details
        density="compact"
        style="max-width: 260px"
        clearable
      />
    </div>

    <div v-if="loading && !overview" class="text-medium-emphasis py-8">{{ t('common.loading') }}</div>

    <template v-else-if="overview">
      <div class="kpi-grid mb-5">
        <KpiCard
          :label="t('platform.kpi.tenants')"
          :value="overview.kpis.tenants_total"
          display
          :hint="`${overview.kpis.tenants_active} ${t('admin.active').toLowerCase()} · ${overview.kpis.tenants_inactive} ${t('admin.inactive').toLowerCase()}`"
        />
        <KpiCard
          :label="t('platform.kpi.orders')"
          :value="overview.kpis.orders"
          display
          :hint="t('platform.periodHint')"
        />
        <KpiCard
          :label="t('platform.kpi.revenue')"
          :value="money(overview.kpis.revenue)"
          display
          :hint="`${t('admin.paidRevenue')}: ${money(overview.kpis.paid_revenue)}`"
        />
        <KpiCard
          :label="t('platform.kpi.locations')"
          :value="overview.kpis.locations"
          display
          :hint="`${overview.kpis.users} ${t('platform.staffUsers')}`"
        />
      </div>

      <div class="chart-panel mb-5">
        <div class="chart-panel__head">
          <div>
            <h2 class="chart-panel__title">{{ t('admin.charts.revenueTitle') }}</h2>
            <p class="chart-panel__lead">{{ t('platform.chartLead') }}</p>
          </div>
          <span class="text-caption text-medium-emphasis">{{ t('platform.allTenants') }}</span>
        </div>

        <div v-if="!hasChartData" class="chart-empty">
          {{ t('platform.chartEmpty') }}
        </div>

        <template v-else>
          <div class="bars" :class="{ 'bars--dense': (overview.revenue_by_day?.length || 0) > 14 }">
            <div
              v-for="(day, index) in overview.revenue_by_day"
              :key="day.day"
              class="bars__col"
              :title="`${shortDay(day.day)}: ${money(day.revenue)} · ${day.orders} ${t('platform.kpi.orders').toLowerCase()}`"
            >
              <span
                v-if="showDayLabel(index, overview.revenue_by_day.length) && Number(day.revenue) > 0"
                class="bars__amount"
              >
                {{ money(day.revenue) }}
              </span>
              <div
                class="bars__bar"
                :class="{ 'bars__bar--peak': peakDay?.day === day.day && Number(day.revenue) > 0 }"
                :style="{ height: barHeight(day.revenue) }"
              />
              <span
                class="bars__label"
                :class="{ 'bars__label--hidden': !showDayLabel(index, overview.revenue_by_day.length) }"
              >
                {{ shortDay(day.day) }}
              </span>
            </div>
          </div>

          <div class="chart-legend">
            <span>
              {{ t('admin.charts.totalRevenue') }}:
              <strong>{{ money(overview.kpis.revenue) }}</strong>
            </span>
            <span v-if="peakDay && Number(peakDay.revenue) > 0">
              {{ t('platform.peakDay') }}:
              <strong>{{ shortDay(peakDay.day) }}</strong>
              ({{ money(peakDay.revenue) }})
            </span>
          </div>
        </template>
      </div>

      <div v-if="!filteredTenants.length" class="text-medium-emphasis py-6">
        {{ t('platform.empty') }}
      </div>

      <div
        v-for="tenant in filteredTenants"
        :key="tenant.id"
        class="tenant-card"
      >
        <div class="tenant-card__head">
          <div class="min-w-0">
            <div class="d-flex align-center ga-2 flex-wrap">
              <strong class="text-h6">{{ tenant.name }}</strong>
              <v-chip
                size="x-small"
                :color="tenant.admin_suspended ? 'warning' : tenant.is_active ? 'success' : 'default'"
                variant="tonal"
              >
                {{ subscriptionStatus(tenant) }}
              </v-chip>
            </div>
            <div class="text-medium-emphasis text-body-2">
              {{ tenant.slug }} · {{ tenant.default_locale }} · {{ tenant.currency }}
              · {{ t('platform.subscriptionStatus.label') }}: {{ subscriptionStatus(tenant) }}
            </div>
          </div>
          <div class="stack-actions">
            <v-btn size="small" color="primary" variant="flat" @click="useTenant(tenant.slug)">
              {{ t('platform.manage') }}
            </v-btn>
            <v-btn size="small" variant="text" color="primary" @click="openEdit(tenant)">{{ t('admin.edit') }}</v-btn>
            <v-btn size="small" variant="text" color="primary" @click="toggle(tenant)">
              {{ tenant.admin_suspended ? t('platform.activate') : t('platform.deactivate') }}
            </v-btn>
          </div>
        </div>

        <div class="tenant-card__metrics">
          <div>
            <div class="metric__label">{{ t('platform.kpi.orders') }}</div>
            <div class="metric__value">{{ tenant.orders }}</div>
          </div>
          <div>
            <div class="metric__label">{{ t('platform.kpi.revenue') }}</div>
            <div class="metric__value">{{ money(tenant.revenue, tenant.currency) }}</div>
          </div>
          <div>
            <div class="metric__label">{{ t('admin.locations') }}</div>
            <div class="metric__value">{{ tenant.locations_active }}/{{ tenant.locations }}</div>
          </div>
          <div>
            <div class="metric__label">{{ t('admin.users') }}</div>
            <div class="metric__value">{{ tenant.users }}</div>
          </div>
          <div>
            <div class="metric__label">{{ t('platform.lastOrder') }}</div>
            <div class="metric__value metric__value--sm">{{ formatWhen(tenant.last_order_at) }}</div>
          </div>
        </div>
      </div>
    </template>

    <v-dialog v-model="dialogOpen" :fullscreen="smAndDown" :max-width="smAndDown ? undefined : 560" persistent>
      <v-card>
        <v-card-title class="d-flex justify-space-between align-center">
          <span class="display-font text-h6" style="color: var(--bo-teal-deep)">{{ dialogTitle }}</span>
          <v-btn icon="mdi-close" variant="text" density="comfortable" @click="closeDialog" />
        </v-card-title>
        <v-card-text>
          <v-row dense>
            <v-col cols="12" md="6">
              <v-text-field v-model="form.name" :label="t('auth.companyName')" />
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field v-model="form.slug" :label="t('auth.slug')" :hint="t('auth.slugHint')" persistent-hint />
            </v-col>
            <v-col cols="12" md="4">
              <v-select v-model="form.default_locale" :items="['it', 'en', 'el', 'de']" label="Locale" />
            </v-col>
            <v-col cols="12" md="4">
              <v-text-field v-model="form.currency" label="Currency" />
            </v-col>
            <v-col cols="12" md="4" class="d-flex align-center">
              <v-switch v-model="form.is_active" :label="t('admin.active')" color="primary" hide-details />
            </v-col>
          </v-row>
        </v-card-text>
        <v-card-actions class="px-4 pb-4">
          <v-spacer />
          <v-btn variant="text" @click="closeDialog">{{ t('common.close') }}</v-btn>
          <v-btn color="primary" :loading="saving" :disabled="!canSave" @click="save">
            {{ form.id ? t('admin.save') : t('admin.create') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 12px;
}

.kpi {
  padding: 14px 16px;
  border-radius: 14px;
  background: color-mix(in srgb, var(--bo-teal) 8%, transparent);
  border: 1px solid color-mix(in srgb, var(--bo-teal) 18%, transparent);
}

.kpi__label {
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: rgba(0, 0, 0, 0.55);
}

.kpi__value {
  font-size: 1.55rem;
  line-height: 1.2;
  margin-top: 4px;
  color: var(--bo-teal-deep);
}

.kpi__meta {
  margin-top: 4px;
  font-size: 0.8rem;
  color: rgba(0, 0, 0, 0.5);
}

.chart-panel {
  padding: 16px 18px 14px;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.72);
  border: 1px solid rgba(0, 0, 0, 0.07);
}

.chart-panel__head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  margin-bottom: 14px;
}

.chart-panel__title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--bo-teal-deep);
}

.chart-panel__lead {
  margin: 0.2rem 0 0;
  font-size: 0.85rem;
  color: rgba(20, 54, 66, 0.55);
}

.chart-empty {
  padding: 2rem 0.5rem;
  text-align: center;
  color: rgba(20, 54, 66, 0.5);
  font-size: 0.9rem;
}

.bars {
  display: grid;
  grid-auto-flow: column;
  grid-auto-columns: minmax(0, 1fr);
  gap: 0.45rem;
  align-items: end;
  height: 200px;
}

.bars--dense {
  gap: 0.2rem;
}

.bars__col {
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  align-items: center;
  height: 100%;
  gap: 0.3rem;
  min-width: 0;
}

.bars__amount {
  font-size: 0.62rem;
  font-weight: 650;
  color: var(--bo-teal-deep);
  white-space: nowrap;
  line-height: 1;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
}

.bars--dense .bars__amount {
  display: none;
}

.bars__bar {
  width: 100%;
  max-width: 42px;
  min-height: 3px;
  border-radius: 8px 8px 3px 3px;
  background: linear-gradient(180deg, #2aa8a3 0%, #0b6e6b 100%);
  opacity: 0.88;
  transition: height 0.25s ease, opacity 0.2s ease;
}

.bars__bar--peak {
  background: linear-gradient(180deg, #e08a72 0%, #c45c26 100%);
  opacity: 1;
}

.bars__col:hover .bars__bar {
  opacity: 1;
  filter: brightness(1.05);
}

.bars__label {
  font-size: 0.65rem;
  color: rgba(20, 54, 66, 0.55);
  white-space: nowrap;
  line-height: 1.1;
}

.bars__label--hidden {
  visibility: hidden;
}

.bars--dense .bars__label {
  font-size: 0.58rem;
  transform: rotate(-35deg);
  transform-origin: top center;
  margin-top: 0.35rem;
}

.chart-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem 1.25rem;
  margin-top: 12px;
  font-size: 0.85rem;
  color: rgba(20, 54, 66, 0.7);
}

.tenant-card {
  padding: 16px;
  margin-bottom: 12px;
  border-radius: 14px;
  border: 1px solid rgba(0, 0, 0, 0.08);
  background: rgba(255, 255, 255, 0.7);
}

.tenant-card__head {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  align-items: flex-start;
  margin-bottom: 14px;
}

.tenant-card__metrics {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
  gap: 10px;
}

.metric__label {
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: rgba(0, 0, 0, 0.45);
}

.metric__value {
  font-weight: 650;
  font-size: 1.05rem;
  color: var(--bo-teal-deep);
}

.metric__value--sm {
  font-size: 0.9rem;
  font-weight: 550;
}
</style>
