<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'
import api, { getApiErrorMessage } from '@/api/client'
import { useUiStore } from '@/stores/ui'
import { displayLocalizedName } from '@/utils/localeName'
import { resolvePublicAssetUrl } from '@/utils/publicAssetUrl'
import { buildNamePayload, hasPrimaryName, pickNameFields } from '@/utils/namePayload'
import { useDeleteConfirm } from '@/composables/useDeleteConfirm'
import BilingualNameFields from '@/components/admin/BilingualNameFields.vue'
import ProductCustomizationsForm from '@/components/admin/ProductCustomizationsForm.vue'
import {
  customizationsValid,
  type AddonGroupFormRow,
  type VariantGroupFormRow,
} from '@/utils/productCustomizations'

interface AdminCategory {
  id: number
  name?: Record<string, string> | string
}

interface AdminTag {
  id: number
  name?: Record<string, string> | string
  is_active?: boolean
}

interface AdminNamed {
  id?: number | null
  name?: Record<string, string> | string
  price?: number
  is_active?: boolean
  is_required?: boolean
  min_quantity?: number
  max_quantity?: number
  min_selections?: number
  max_selections?: number | null
  options?: AdminNamed[]
  addons?: AdminNamed[]
}

interface AdminProduct {
  id: number
  category_id?: number
  station?: 'kitchen' | 'bar'
  name?: Record<string, string> | string
  price: number
  is_available: boolean
  is_active?: boolean
  track_inventory?: boolean
  stock_quantity?: number | null
  low_stock_threshold?: number | null
  image_path?: string | null
  image_url?: string | null
  category?: AdminCategory
  tags?: AdminTag[]
  variant_groups?: AdminNamed[]
  addon_groups?: AdminNamed[]
  addons?: AdminNamed[]
}

const MAX_TAGS_PER_PRODUCT = 3

const { t, locale } = useI18n()
const { smAndDown } = useDisplay()
const ui = useUiStore()
const { confirmDelete } = useDeleteConfirm()

function displayName(value?: Record<string, string> | string): string {
  if (!value) return ''
  if (typeof value === 'string') return value
  return displayLocalizedName(value, String(locale.value))
}

function newKey(prefix: string) {
  return `${prefix}-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

function mapVariantGroups(groups?: AdminNamed[]): VariantGroupFormRow[] {
  return (groups || []).map((group) => {
    const names = pickNameFields(typeof group.name === 'object' ? group.name : group.name)
    return {
      key: newKey('vg'),
      id: group.id ?? null,
      name_el: names.name_el,
      name_en: names.name_en,
      is_required: group.is_required !== false,
      is_active: group.is_active !== false,
      options: (group.options || []).map((option) => {
        const optionNames = pickNameFields(typeof option.name === 'object' ? option.name : option.name)
        return {
          key: newKey('vo'),
          id: option.id ?? null,
          name_el: optionNames.name_el,
          name_en: optionNames.name_en,
          price: Number(option.price) || 0,
          is_active: option.is_active !== false,
        }
      }),
    }
  })
}

function mapAddonGroups(product: AdminProduct): AddonGroupFormRow[] {
  if (product.addon_groups?.length) {
    return product.addon_groups.map((group) => {
      const names = pickNameFields(typeof group.name === 'object' ? group.name : group.name)
      return {
        key: newKey('ag'),
        id: group.id ?? null,
        name_el: names.name_el,
        name_en: names.name_en,
        min_selections: Number(group.min_selections) || 0,
        max_selections: group.max_selections == null ? null : Number(group.max_selections),
        is_active: group.is_active !== false,
        addons: (group.addons || []).map((addon) => {
          const addonNames = pickNameFields(typeof addon.name === 'object' ? addon.name : addon.name)
          return {
            key: newKey('ad'),
            id: addon.id ?? null,
            name_el: addonNames.name_el,
            name_en: addonNames.name_en,
            price: Number(addon.price) || 0,
            is_active: addon.is_active !== false,
            min_quantity: Number(addon.min_quantity) || 0,
            max_quantity: Number(addon.max_quantity) || 1,
          }
        }),
      }
    })
  }

  // Legacy flat addons → one editable group in the form (saved as addon_groups).
  if (product.addons?.length) {
    return [
      {
        key: newKey('ag'),
        id: null,
        name_el: '',
        name_en: 'Extras',
        min_selections: 0,
        max_selections: null,
        is_active: true,
        addons: product.addons.map((addon) => {
          const addonNames = pickNameFields(typeof addon.name === 'object' ? addon.name : addon.name)
          return {
            key: newKey('ad'),
            id: addon.id ?? null,
            name_el: addonNames.name_el,
            name_en: addonNames.name_en,
            price: Number(addon.price) || 0,
            is_active: addon.is_active !== false,
            min_quantity: 0,
            max_quantity: 1,
          }
        }),
      },
    ]
  }

  return []
}

const products = ref<AdminProduct[]>([])
const categories = ref<AdminCategory[]>([])
const tags = ref<AdminTag[]>([])
const filterCategoryId = ref<number | null>(null)
const filterAvailable = ref<string>('all')
const filterLowStock = ref(false)
const search = ref('')
const dialogOpen = ref(false)
const saving = ref(false)
const apiBase = (import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api').replace(/\/api\/?$/, '')

const emptyForm = () => ({
  id: null as number | null,
  category_id: null as number | null,
  station: 'kitchen' as 'kitchen' | 'bar',
  name_el: '',
  name_en: '',
  price: 0,
  is_available: true,
  is_active: true,
  track_inventory: false,
  stock_quantity: 0,
  low_stock_threshold: 5,
  tag_ids: [] as number[],
  variant_groups: [] as VariantGroupFormRow[],
  addon_groups: [] as AddonGroupFormRow[],
})

const form = ref(emptyForm())

const categoryItems = computed(() =>
  categories.value.map((c) => ({
    title: displayName(c.name) || `#${c.id}`,
    value: c.id,
  })),
)

const tagItems = computed(() =>
  tags.value
    .filter((tag) => tag.is_active !== false)
    .map((tag) => ({
      title: displayName(tag.name) || `#${tag.id}`,
      value: tag.id,
    })),
)

const dialogTitle = computed(() =>
  form.value.id ? t('admin.editProduct') : t('admin.createProduct'),
)

const canSave = computed(() => {
  if (!form.value.category_id || !hasPrimaryName(form.value.name_el, form.value.name_en) || form.value.price < 0) {
    return false
  }
  return customizationsValid(form.value.variant_groups, form.value.addon_groups)
})

function customizationSummary(product: AdminProduct) {
  const variants = (product.variant_groups || []).reduce((sum, g) => sum + (g.options?.length || 0), 0)
  const addons = (product.addon_groups || []).reduce((sum, g) => sum + (g.addons?.length || 0), 0)
    || (product.addons?.length || 0)
  return { variants, addons }
}

async function load() {
  const params: Record<string, string | number | boolean> = {}
  if (filterCategoryId.value) params.category_id = filterCategoryId.value
  if (filterAvailable.value === 'yes') params.is_available = true
  if (filterAvailable.value === 'no') params.is_available = false
  if (filterLowStock.value) params.low_stock = true
  if (search.value.trim()) params.q = search.value.trim()

  const [p, c, tg] = await Promise.all([
    api.get('/admin/products', { params }),
    api.get('/admin/categories'),
    api.get('/admin/tags'),
  ])
  products.value = p.data
  categories.value = c.data
  tags.value = tg.data
}

watch([filterCategoryId, filterAvailable, filterLowStock], () => load())
watch(locale, () => load())

let searchTimer: ReturnType<typeof setTimeout> | null = null
watch(search, () => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => load(), 300)
})

function openCreate() {
  form.value = {
    ...emptyForm(),
    category_id: categories.value[0]?.id || null,
  }
  dialogOpen.value = true
}

function openEdit(product: AdminProduct) {
  const names = pickNameFields(typeof product.name === 'object' ? product.name : product.name)
  form.value = {
    id: product.id,
    category_id: product.category_id || null,
    station: product.station === 'bar' ? 'bar' : 'kitchen',
    name_el: names.name_el,
    name_en: names.name_en,
    price: Number(product.price),
    is_available: product.is_available,
    is_active: product.is_active ?? true,
    track_inventory: !!product.track_inventory,
    stock_quantity: product.stock_quantity ?? 0,
    low_stock_threshold: product.low_stock_threshold ?? 5,
    tag_ids: (product.tags || []).map((tag) => tag.id).slice(0, MAX_TAGS_PER_PRODUCT),
    variant_groups: mapVariantGroups(product.variant_groups),
    addon_groups: mapAddonGroups(product),
  }
  dialogOpen.value = true
}

function onTagsChange(value: unknown) {
  const ids = Array.isArray(value) ? (value as number[]) : []
  form.value.tag_ids = ids.slice(0, MAX_TAGS_PER_PRODUCT)
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
      category_id: form.value.category_id,
      station: form.value.station,
      name: buildNamePayload(form.value.name_el, form.value.name_en),
      price: form.value.price,
      is_available: form.value.is_available,
      is_active: form.value.is_active,
      track_inventory: form.value.track_inventory,
      stock_quantity: form.value.track_inventory ? form.value.stock_quantity : null,
      low_stock_threshold: form.value.track_inventory ? form.value.low_stock_threshold : null,
      tag_ids: form.value.tag_ids.slice(0, MAX_TAGS_PER_PRODUCT),
      variant_groups: form.value.variant_groups.map((group, gIndex) => ({
        id: group.id || undefined,
        name: buildNamePayload(group.name_el, group.name_en),
        is_required: group.is_required,
        is_active: group.is_active,
        sort_order: gIndex,
        options: group.options.map((option, oIndex) => ({
          id: option.id || undefined,
          name: buildNamePayload(option.name_el, option.name_en),
          price: Number(option.price) || 0,
          is_active: option.is_active,
          sort_order: oIndex,
        })),
      })),
      addon_groups: form.value.addon_groups.map((group, gIndex) => ({
        id: group.id || undefined,
        name: buildNamePayload(group.name_el, group.name_en),
        min_selections: Number(group.min_selections) || 0,
        max_selections: group.max_selections == null || group.max_selections === ('' as unknown) ? null : Number(group.max_selections),
        is_active: group.is_active,
        sort_order: gIndex,
        addons: group.addons.map((addon, aIndex) => ({
          id: addon.id || undefined,
          name: buildNamePayload(addon.name_el, addon.name_en),
          price: Number(addon.price) || 0,
          is_active: addon.is_active,
          min_quantity: Number(addon.min_quantity) || 0,
          max_quantity: Number(addon.max_quantity) || 1,
          sort_order: aIndex,
        })),
      })),
    }
    if (form.value.id) {
      await api.put(`/admin/products/${form.value.id}`, payload)
    } else {
      await api.post('/admin/products', payload)
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

async function remove(product: AdminProduct) {
  const ok = await confirmDelete(displayName(product.name))
  if (!ok) return
  try {
    await api.delete(`/admin/products/${product.id}`)
    ui.success(t('admin.deleted'))
    await load()
  } catch (e: unknown) {
    ui.error(getApiErrorMessage(e, t('common.error')))
  }
}

async function toggleAvailable(product: AdminProduct) {
  try {
    await api.put(`/admin/products/${product.id}`, { is_available: !product.is_available })
    await load()
  } catch (e: unknown) {
    ui.error(getApiErrorMessage(e, t('common.error')))
  }
}

async function onImage(product: AdminProduct, event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return
  const body = new FormData()
  body.append('image', file)
  try {
    await api.post(`/admin/products/${product.id}/image`, body, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    ui.success(t('admin.saved'))
    await load()
  } catch (e: unknown) {
    ui.error(getApiErrorMessage(e, t('common.error')))
  }
}

async function removeImage(product: AdminProduct) {
  const ok = await ui.askConfirm({
    title: t('admin.removeImage'),
    message: displayName(product.name),
    confirmLabel: t('admin.removeImage'),
    cancelLabel: t('common.cancel'),
    danger: true,
  })
  if (!ok) return
  try {
    await api.delete(`/admin/products/${product.id}/image`)
    ui.success(t('admin.saved'))
    await load()
  } catch (e: unknown) {
    ui.error(getApiErrorMessage(e, t('common.error')))
  }
}

function pickImage(productId: number) {
  document.getElementById(`img-${productId}`)?.click()
}

onMounted(load)
</script>

<template>
  <div>
    <div class="d-flex justify-space-between align-center flex-wrap ga-2 mb-4">
      <h1 class="display-font text-h4 mb-0" style="color: var(--bo-teal-deep)">{{ t('admin.products') }}</h1>
      <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreate">
        {{ t('admin.create') }}
      </v-btn>
    </div>

    <v-row class="mb-4">
      <v-col cols="12" md="3">
        <v-text-field v-model="search" label="Cerca" prepend-inner-icon="mdi-magnify" clearable hide-details density="compact" />
      </v-col>
      <v-col cols="12" md="3">
        <v-select
          v-model="filterCategoryId"
          :items="[{ title: 'Tutte le categorie', value: null }, ...categoryItems]"
          label="Filtra categoria"
          hide-details
          density="compact"
          clearable
        />
      </v-col>
      <v-col cols="6" md="2">
        <v-select
          v-model="filterAvailable"
          :items="[
            { title: 'Tutti', value: 'all' },
            { title: 'Disponibili', value: 'yes' },
            { title: 'Non disponibili', value: 'no' },
          ]"
          label="Disponibilità"
          hide-details
          density="compact"
        />
      </v-col>
      <v-col cols="6" md="2" class="d-flex align-center">
        <v-switch v-model="filterLowStock" label="Scorte basse" color="warning" hide-details density="compact" />
      </v-col>
    </v-row>

    <div
      v-for="product in products"
      :key="product.id"
      class="product-admin-row"
    >
      <div class="d-flex ga-3 align-center min-w-0">
        <img
          v-if="product.image_url || product.image_path"
          :src="
            resolvePublicAssetUrl(product.image_url) ||
            (product.image_path ? `${apiBase}/storage/${product.image_path}` : '')
          "
          alt=""
          width="48"
          height="48"
          style="object-fit: cover; border-radius: 8px; flex-shrink: 0"
        />
          <div class="min-w-0">
          <strong class="d-block text-truncate">{{ displayName(product.name) }}</strong>
          <div class="text-medium-emphasis text-body-2">
            € {{ Number(product.price).toFixed(2) }} · {{ displayName(product.category?.name) }}
            · {{ product.station === 'bar' ? t('admin.stationBar') : t('admin.stationKitchen') }}
            <span v-if="product.track_inventory"> · stock {{ product.stock_quantity }}</span>
          </div>
          <div v-if="product.tags?.length" class="d-flex flex-wrap ga-1 mt-1">
            <v-chip
              v-for="tag in product.tags"
              :key="tag.id"
              size="x-small"
              variant="tonal"
              color="primary"
            >
              {{ displayName(tag.name) }}
            </v-chip>
          </div>
          <div
            v-if="customizationSummary(product).variants || customizationSummary(product).addons"
            class="text-caption text-medium-emphasis mt-1"
          >
            <span v-if="customizationSummary(product).variants">
              {{ t('admin.variantsCount', { n: customizationSummary(product).variants }) }}
            </span>
            <span v-if="customizationSummary(product).variants && customizationSummary(product).addons"> · </span>
            <span v-if="customizationSummary(product).addons">
              {{ t('admin.addonsCount', { n: customizationSummary(product).addons }) }}
            </span>
          </div>
        </div>
      </div>
      <div class="stack-actions">
        <input
          :id="`img-${product.id}`"
          type="file"
          accept="image/*"
          hidden
          @change="onImage(product, $event)"
        />
        <v-btn size="small" variant="text" color="primary" @click="pickImage(product.id)">
          {{ product.image_url || product.image_path ? t('admin.replaceImage') : t('admin.photo') }}
        </v-btn>
        <v-btn
          v-if="product.image_url || product.image_path"
          size="small"
          variant="text"
          color="error"
          @click="removeImage(product)"
        >
          {{ t('admin.removeImage') }}
        </v-btn>
        <v-chip
          size="small"
          class="admin-status-chip"
          :color="product.is_available ? 'success' : undefined"
          :variant="product.is_available ? 'flat' : 'outlined'"
          @click="toggleAvailable(product)"
        >
          {{ product.is_available ? t('admin.available') : t('menu.unavailable') }}
        </v-chip>
        <v-btn size="small" variant="text" color="primary" @click="openEdit(product)">
          {{ t('admin.edit') }}
        </v-btn>
        <v-btn size="small" color="error" variant="text" @click="remove(product)">
          {{ t('admin.delete') }}
        </v-btn>
      </div>
    </div>

    <v-dialog v-model="dialogOpen" :fullscreen="smAndDown" :max-width="smAndDown ? undefined : 860" persistent>
      <v-card>
        <v-card-title class="d-flex justify-space-between align-center">
          <span class="display-font text-h6" style="color: var(--bo-teal-deep)">{{ dialogTitle }}</span>
          <v-btn icon="mdi-close" variant="text" density="comfortable" @click="closeDialog" />
        </v-card-title>
        <v-card-text>
          <v-row dense>
            <v-col cols="12">
              <v-select
                v-model="form.category_id"
                :items="categoryItems"
                :label="t('admin.categories')"
                :rules="[(v) => !!v || 'Required']"
              />
            </v-col>
            <v-col cols="12">
              <v-select
                v-model="form.station"
                :items="[
                  { title: t('admin.stationKitchen'), value: 'kitchen' },
                  { title: t('admin.stationBar'), value: 'bar' },
                ]"
                :label="t('admin.station')"
              />
            </v-col>
            <v-col cols="12">
              <BilingualNameFields v-model:name-el="form.name_el" v-model:name-en="form.name_en" />
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field v-model.number="form.price" :label="t('admin.price')" type="number" step="0.01" min="0" />
            </v-col>
            <v-col cols="12">
              <v-select
                :model-value="form.tag_ids"
                :items="tagItems"
                :label="t('admin.tags')"
                :hint="t('admin.tagsProductHint', { max: MAX_TAGS_PER_PRODUCT })"
                persistent-hint
                multiple
                chips
                closable-chips
                :menu-props="{ maxHeight: 280 }"
                @update:model-value="onTagsChange"
              />
            </v-col>
            <v-col cols="6" md="3" class="d-flex align-center">
              <v-switch v-model="form.is_available" :label="t('admin.available')" color="primary" hide-details />
            </v-col>
            <v-col cols="6" md="3" class="d-flex align-center">
              <v-switch v-model="form.is_active" :label="t('admin.active')" color="primary" hide-details />
            </v-col>
            <v-col cols="12">
              <v-switch v-model="form.track_inventory" label="Traccia scorte" color="primary" hide-details />
            </v-col>
            <v-col cols="6">
              <v-text-field
                v-model.number="form.stock_quantity"
                type="number"
                label="Stock"
                :disabled="!form.track_inventory"
              />
            </v-col>
            <v-col cols="6">
              <v-text-field
                v-model.number="form.low_stock_threshold"
                type="number"
                label="Soglia bassa"
                :disabled="!form.track_inventory"
              />
            </v-col>

            <v-col cols="12">
              <ProductCustomizationsForm
                v-model:variant-groups="form.variant_groups"
                v-model:addon-groups="form.addon_groups"
              />
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
.product-admin-row {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding: 1rem 0;
  border-bottom: 1px solid rgba(11, 110, 107, 0.12);
}
@media (min-width: 600px) {
  .product-admin-row {
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
  }
}
</style>
