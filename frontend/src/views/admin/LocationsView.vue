<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'
import api, { getApiErrorMessage } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { useDeleteConfirm } from '@/composables/useDeleteConfirm'
import { qrDataUrl } from '@/utils/qr'

interface AdminLocation {
  id: number
  name: string
  type: string
  zone?: string
  code: string
  capacity?: number
  is_active?: boolean
}

const { t } = useI18n()
const { smAndDown } = useDisplay()
const auth = useAuthStore()
const ui = useUiStore()
const { confirmDelete } = useDeleteConfirm()
const locations = ref<AdminLocation[]>([])
const dialogOpen = ref(false)
const printOpen = ref(false)
const printLoc = ref<AdminLocation | null>(null)
const printQrSrc = ref('')
const printBusy = ref(false)
const qrCache = ref<Record<string, string>>({})
const saving = ref(false)
const filterType = ref<string | null>(null)
const filterZone = ref<string | null>(null)
const filterActive = ref<string>('all')
const search = ref('')

const emptyForm = () => ({
  id: null as number | null,
  name: '',
  type: 'umbrella',
  zone: 'A',
  capacity: 4,
  is_active: true,
})

const form = ref(emptyForm())

const tenantSlug = computed(() => auth.user?.tenant?.slug || localStorage.getItem('bo_tenant_slug') || 'azure-beach')
const tenantName = computed(() => auth.user?.tenant?.name || '')
const tenantTagline = computed(
  () => auth.user?.tenant?.branding?.tagline || t('app.tagline'),
)
const frontendOrigin = computed(() => window.location.origin)
const dialogTitle = computed(() =>
  form.value.id ? t('admin.editLocation') : t('admin.createLocation'),
)
const canSave = computed(() => form.value.name.trim().length > 0)

const zones = computed(() => {
  const set = new Set(locations.value.map((l) => l.zone).filter(Boolean))
  return Array.from(set) as string[]
})

function typeLabel(type: string) {
  return t(`admin.locationTypes.${type}`, type)
}

function orderUrl(code: string) {
  return `${frontendOrigin.value}/t/${tenantSlug.value}/q/${code}`
}

async function ensureQr(code: string, size = 280): Promise<string> {
  const key = `${code}:${size}`
  if (qrCache.value[key]) return qrCache.value[key]
  const src = await qrDataUrl(orderUrl(code), size)
  qrCache.value = { ...qrCache.value, [key]: src }
  return src
}

async function load() {
  const params: Record<string, string | boolean> = {}
  if (filterType.value) params.type = filterType.value
  if (filterZone.value) params.zone = filterZone.value
  if (filterActive.value === 'yes') params.is_active = true
  if (filterActive.value === 'no') params.is_active = false
  if (search.value.trim()) params.q = search.value.trim()

  try {
    const { data } = await api.get('/admin/locations', { params })
    locations.value = data
    await Promise.all(data.map((loc: AdminLocation) => ensureQr(loc.code, 200).catch(() => null)))
  } catch (e: unknown) {
    ui.error(getApiErrorMessage(e, t('common.error')))
  }
}

watch([filterType, filterZone, filterActive], () => load())
let searchTimer: ReturnType<typeof setTimeout> | null = null
watch(search, () => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => load(), 300)
})

function openCreate() {
  form.value = emptyForm()
  dialogOpen.value = true
}

function openEdit(loc: AdminLocation) {
  form.value = {
    id: loc.id,
    name: loc.name || '',
    type: loc.type || 'umbrella',
    zone: loc.zone || 'A',
    capacity: loc.capacity ?? 4,
    is_active: loc.is_active ?? true,
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
      type: form.value.type,
      zone: form.value.zone,
      capacity: form.value.capacity,
      is_active: form.value.is_active,
    }
    if (form.value.id) {
      await api.put(`/admin/locations/${form.value.id}`, payload)
    } else {
      await api.post('/admin/locations', payload)
    }
    closeDialog()
    ui.success(t('admin.saved'))
    await load()
  } catch (e: unknown) {
    ui.error(getApiErrorMessage(e, t('common.error')))
  } finally {
    saving.value = false
  }
}

async function regenerate(id: number) {
  try {
    await api.post(`/admin/locations/${id}/regenerate-qr`)
    ui.success(t('admin.saved'))
    qrCache.value = {}
    await load()
  } catch (e: unknown) {
    ui.error(getApiErrorMessage(e, t('common.error')))
  }
}

async function remove(loc: AdminLocation) {
  const ok = await confirmDelete(loc.name)
  if (!ok) return
  try {
    await api.delete(`/admin/locations/${loc.id}`)
    ui.success(t('admin.deleted'))
    await load()
  } catch (e: unknown) {
    ui.error(getApiErrorMessage(e, t('common.error')))
  }
}

async function openPrint(loc: AdminLocation) {
  printBusy.value = true
  printLoc.value = loc
  printOpen.value = true
  try {
    printQrSrc.value = await ensureQr(loc.code, 640)
  } catch {
    ui.error(t('common.error'))
    printOpen.value = false
  } finally {
    printBusy.value = false
  }
}

function doPrint() {
  window.print()
}

function closePrint() {
  printOpen.value = false
  printLoc.value = null
  printQrSrc.value = ''
}

onMounted(load)
</script>

<template>
  <div>
    <div class="d-flex justify-space-between align-center flex-wrap ga-2 mb-4 no-print">
      <h1 class="display-font text-h4 mb-0" style="color: var(--bo-teal-deep)">{{ t('admin.locations') }}</h1>
      <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreate">{{ t('admin.create') }}</v-btn>
    </div>

    <v-row class="mb-4 no-print">
      <v-col cols="12" md="3">
        <v-text-field
          v-model="search"
          :label="t('admin.search')"
          prepend-inner-icon="mdi-magnify"
          clearable
          hide-details
          density="compact"
        />
      </v-col>
      <v-col cols="6" md="2">
        <v-select
          v-model="filterType"
          :items="[
            { title: t('admin.allTypes'), value: null },
            { title: typeLabel('umbrella'), value: 'umbrella' },
            { title: typeLabel('sunbed'), value: 'sunbed' },
            { title: typeLabel('table'), value: 'table' },
          ]"
          :label="t('admin.type')"
          hide-details
          density="compact"
          clearable
        />
      </v-col>
      <v-col cols="6" md="2">
        <v-select
          v-model="filterZone"
          :items="[{ title: t('admin.allZones'), value: null }, ...zones.map((z) => ({ title: z, value: z }))]"
          :label="t('admin.zone')"
          hide-details
          density="compact"
          clearable
        />
      </v-col>
      <v-col cols="6" md="2">
        <v-select
          v-model="filterActive"
          :items="[
            { title: t('admin.all'), value: 'all' },
            { title: t('admin.active'), value: 'yes' },
            { title: t('admin.inactive'), value: 'no' },
          ]"
          :label="t('admin.status')"
          hide-details
          density="compact"
        />
      </v-col>
    </v-row>

    <v-alert v-if="locations.length === 0" type="info" class="mb-4 no-print">{{ t('admin.noLocations') }}</v-alert>

    <v-row class="no-print">
      <v-col v-for="loc in locations" :key="loc.id" cols="12" sm="6" md="4">
        <div class="loc-card pa-4">
          <div class="font-weight-bold mb-1">{{ loc.name }}</div>
          <div class="text-medium-emphasis mb-3">
            {{ typeLabel(loc.type) }} · {{ loc.zone }} · {{ loc.code }}
          </div>
          <img
            v-if="qrCache[`${loc.code}:200`]"
            :src="qrCache[`${loc.code}:200`]"
            :alt="`QR ${loc.name}`"
            width="120"
            height="120"
            class="mb-3 loc-card__qr"
          />
          <div class="text-caption mb-3 text-break">{{ orderUrl(loc.code) }}</div>
          <div class="d-flex flex-wrap ga-2 stack-actions">
            <v-btn size="small" variant="text" color="primary" @click="openEdit(loc)">{{ t('admin.edit') }}</v-btn>
            <v-btn size="small" variant="text" color="primary" prepend-icon="mdi-printer" :loading="printBusy && printLoc?.id === loc.id" @click="openPrint(loc)">
              {{ t('admin.printQr') }}
            </v-btn>
            <v-btn size="small" variant="text" color="primary" @click="regenerate(loc.id)">{{ t('admin.regenerateQr') }}</v-btn>
            <v-btn size="small" color="error" variant="text" @click="remove(loc)">{{ t('admin.delete') }}</v-btn>
          </div>
        </div>
      </v-col>
    </v-row>

    <!-- Screen preview + print placard -->
    <Teleport to="body">
      <div v-if="printOpen && printLoc" class="print-overlay no-print">
        <div class="print-overlay__panel">
          <div class="d-flex justify-space-between align-center mb-4">
            <h2 class="display-font text-h6 mb-0" style="color: var(--bo-teal-deep)">{{ t('admin.printPreview') }}</h2>
            <v-btn icon="mdi-close" variant="text" @click="closePrint" />
          </div>
          <div class="placard placard--preview">
            <div class="placard__top">
              <p class="placard__brand">{{ tenantName }}</p>
              <p class="placard__tagline">{{ tenantTagline }}</p>
            </div>
            <div class="placard__body">
              <p class="placard__eyebrow">{{ typeLabel(printLoc.type) }} · {{ printLoc.zone }}</p>
              <h1 class="placard__name">{{ printLoc.name }}</h1>
              <div class="placard__qr-frame">
                <img v-if="printQrSrc" :src="printQrSrc" :alt="`QR ${printLoc.name}`" class="placard__qr" />
              </div>
              <p class="placard__cta">{{ t('admin.scanToOrder') }}</p>
              <p class="placard__hint">{{ t('admin.scanHint') }}</p>
            </div>
            <div class="placard__foot">
              <span>{{ printLoc.code }}</span>
              <span v-if="tenantName">{{ tenantName }}</span>
            </div>
          </div>
          <div class="d-flex justify-end ga-2 mt-4">
            <v-btn variant="text" @click="closePrint">{{ t('common.close') }}</v-btn>
            <v-btn color="primary" prepend-icon="mdi-printer" :disabled="!printQrSrc" @click="doPrint">
              {{ t('admin.printQr') }}
            </v-btn>
          </div>
        </div>
      </div>

      <div v-if="printLoc && printQrSrc" class="only-print">
        <div class="placard">
          <div class="placard__top">
            <p class="placard__brand">{{ tenantName }}</p>
            <p class="placard__tagline">{{ tenantTagline }}</p>
          </div>
          <div class="placard__body">
            <p class="placard__eyebrow">{{ typeLabel(printLoc.type) }} · {{ printLoc.zone }}</p>
            <h1 class="placard__name">{{ printLoc.name }}</h1>
            <div class="placard__qr-frame">
              <img :src="printQrSrc" :alt="`QR ${printLoc.name}`" class="placard__qr" />
            </div>
            <p class="placard__cta">{{ t('admin.scanToOrder') }}</p>
            <p class="placard__hint">{{ t('admin.scanHint') }}</p>
          </div>
          <div class="placard__foot">
            <span>{{ printLoc.code }}</span>
            <span v-if="tenantName">{{ tenantName }}</span>
          </div>
        </div>
      </div>
    </Teleport>

    <v-dialog v-model="dialogOpen" :fullscreen="smAndDown" :max-width="smAndDown ? undefined : 560" persistent class="no-print">
      <v-card>
        <v-card-title class="d-flex justify-space-between align-center">
          <span class="display-font text-h6" style="color: var(--bo-teal-deep)">{{ dialogTitle }}</span>
          <v-btn icon="mdi-close" variant="text" density="comfortable" @click="closeDialog" />
        </v-card-title>
        <v-card-text>
          <v-row dense>
            <v-col cols="12">
              <v-text-field v-model="form.name" :label="t('admin.name')" />
            </v-col>
            <v-col cols="12" md="6">
              <v-select
                v-model="form.type"
                :items="[
                  { title: typeLabel('umbrella'), value: 'umbrella' },
                  { title: typeLabel('sunbed'), value: 'sunbed' },
                  { title: typeLabel('table'), value: 'table' },
                ]"
                :label="t('admin.type')"
              />
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field v-model="form.zone" :label="t('admin.zone')" />
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field v-model.number="form.capacity" type="number" :label="t('admin.capacity')" min="1" />
            </v-col>
            <v-col cols="12" md="6" class="d-flex align-center">
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
.loc-card {
  background: rgba(255, 255, 255, 0.72);
  border: 1px solid rgba(11, 110, 107, 0.14);
  border-radius: 16px;
  height: 100%;
}

.loc-card__qr {
  display: block;
  border-radius: 8px;
  background: #fff;
}
</style>

<style>
.print-overlay {
  position: fixed;
  inset: 0;
  z-index: 3000;
  background: rgba(20, 54, 66, 0.45);
  display: grid;
  place-items: center;
  padding: 1rem;
}

.print-overlay__panel {
  width: min(440px, 100%);
  max-height: 92vh;
  overflow: auto;
  background: #eef6f5;
  border-radius: 20px;
  padding: 1.1rem 1.1rem 1.25rem;
  box-shadow: 0 24px 60px rgba(8, 78, 76, 0.28);
}

.only-print {
  display: none;
}

.placard {
  width: 100%;
  max-width: 105mm;
  margin: 0 auto;
  background: #fff;
  color: #143642;
  border: 2px solid #0b6e6b;
  border-radius: 18px;
  overflow: hidden;
  box-shadow: 0 10px 30px rgba(11, 110, 107, 0.12);
}

.placard--preview {
  box-shadow: none;
}

.placard__top {
  background: linear-gradient(135deg, #084e4c, #0b6e6b 55%, #1a8a86);
  color: #fff;
  text-align: center;
  padding: 18px 16px 16px;
}

.placard__brand {
  margin: 0;
  font-family: 'Fraunces', Georgia, serif;
  font-size: 22px;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.placard__tagline {
  margin: 4px 0 0;
  font-size: 12px;
  opacity: 0.88;
}

.placard__body {
  text-align: center;
  padding: 22px 18px 18px;
}

.placard__eyebrow {
  margin: 0 0 6px;
  text-transform: uppercase;
  letter-spacing: 0.14em;
  font-size: 11px;
  color: #0b6e6b;
  font-weight: 700;
}

.placard__name {
  margin: 0 0 16px;
  font-family: 'Fraunces', Georgia, serif;
  font-size: 34px;
  line-height: 1.05;
  color: #084e4c;
}

.placard__qr-frame {
  width: 210px;
  height: 210px;
  margin: 0 auto 16px;
  padding: 12px;
  border: 1px solid rgba(11, 110, 107, 0.22);
  border-radius: 16px;
  background:
    linear-gradient(180deg, #f7fbfb, #fff),
    radial-gradient(circle at 20% 20%, rgba(244, 201, 95, 0.18), transparent 55%);
  display: grid;
  place-items: center;
}

.placard__qr {
  width: 180px;
  height: 180px;
  display: block;
}

.placard__cta {
  margin: 0 0 6px;
  font-size: 18px;
  font-weight: 700;
  color: #143642;
}

.placard__hint {
  margin: 0;
  font-size: 12px;
  color: rgba(20, 54, 66, 0.65);
  max-width: 240px;
  margin-inline: auto;
  line-height: 1.35;
}

.placard__foot {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 16px;
  border-top: 1px dashed rgba(11, 110, 107, 0.25);
  font-size: 11px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: rgba(20, 54, 66, 0.55);
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}

@media print {
  @page {
    size: A6 portrait;
    margin: 8mm;
  }

  html,
  body {
    background: #fff !important;
  }

  body * {
    visibility: hidden !important;
  }

  .only-print,
  .only-print * {
    visibility: visible !important;
  }

  .only-print {
    display: block !important;
    position: fixed !important;
    inset: 0 !important;
    background: #fff !important;
  }

  .no-print,
  .print-overlay,
  .v-application__wrap > .v-toolbar,
  .v-navigation-drawer,
  .v-bottom-navigation,
  .v-snackbar {
    display: none !important;
  }

  .placard {
    max-width: none;
    width: 100%;
    height: 100%;
    border-radius: 12px;
    box-shadow: none;
    display: flex;
    flex-direction: column;
  }

  .placard__body {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }

  .placard__qr-frame {
    width: 58mm;
    height: 58mm;
  }

  .placard__qr {
    width: 50mm;
    height: 50mm;
  }
}
</style>
