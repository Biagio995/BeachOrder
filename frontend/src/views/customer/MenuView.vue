<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'
import { useMenuStore } from '@/stores/menu'
import { useCartStore, productHasCustomizations } from '@/stores/cart'
import { useDeleteConfirm } from '@/composables/useDeleteConfirm'
import { useUiStore } from '@/stores/ui'
import ServiceUnavailable from '@/components/customer/ServiceUnavailable.vue'
import StickyCartBar from '@/components/customer/StickyCartBar.vue'
import MenuProductRow from '@/components/customer/MenuProductRow.vue'
import ProductCustomizeSheet from '@/components/customer/ProductCustomizeSheet.vue'
import type { CartAddon, CartVariant, Product } from '@/types'
import { resolvePublicAssetUrl } from '@/utils/publicAssetUrl'

const route = useRoute()
const { t, locale } = useI18n()
const { smAndDown } = useDisplay()
const menu = useMenuStore()
const cart = useCartStore()
const ui = useUiStore()
const { confirmCartRemove } = useDeleteConfirm()

const selectedCategoryId = ref<number | 'all'>('all')
const search = ref('')
const sheetOpen = ref(false)
const selected = ref<Product | null>(null)
const selectedCategoryMeta = ref<{ slug?: string; name?: string } | null>(null)
const justAddedId = ref<number | null>(null)
let pulseTimer: number | undefined

const ADDED_FEEDBACK_MS = 2500

const currency = computed(() => menu.tenant?.currency || 'EUR')
const menuHeaderUrl = computed(() =>
  resolvePublicAssetUrl(
    menu.tenant?.branding?.menu_header_url || menu.tenant?.branding?.menu_cover_url || null,
  ),
)
const cartTo = computed(() => `/t/${menu.tenantSlug || route.params.tenant}/cart`)
const stickyOffset = computed(() =>
  smAndDown.value ? 'calc(72px + env(safe-area-inset-bottom, 0px) + 12px)' : '24px',
)

async function bootstrap() {
  const tenant = String(route.params.tenant || '')
  const code = String(route.params.code || '')
  if (tenant) menu.setTenant(tenant)
  if (code) menu.setLocationCode(code)
  cart.bindContext(tenant, code)
  const active = await menu.checkTenantStatus()
  if (!active) return
  if (tenant && code && !menu.canOrder) {
    try {
      await menu.claimAccess(code)
    } catch {
      menu.clearAccess()
    }
  }
  await menu.loadMenu(locale.value)
  cart.syncLabelsFromCategories(menu.categories)
  selectedCategoryId.value = 'all'
}

onMounted(bootstrap)
watch(() => [route.params.tenant, route.params.code], bootstrap)
watch(locale, async () => {
  await menu.loadMenu(locale.value)
  cart.syncLabelsFromCategories(menu.categories)
})

const filteredCategories = computed(() => {
  const q = search.value.trim().toLowerCase()
  return menu.categories
    .filter((c) => selectedCategoryId.value === 'all' || c.id === selectedCategoryId.value)
    .map((c) => ({
      ...c,
      products: c.products.filter((p) => {
        if (!q) return true
        return (
          p.name.toLowerCase().includes(q) ||
          (p.description || '').toLowerCase().includes(q)
        )
      }),
    }))
    .filter((c) => c.products.length > 0 || (!q && selectedCategoryId.value !== 'all'))
})

function openProduct(product: Product, category?: { slug?: string; name?: string }) {
  if (!product.is_available) return
  selected.value = product
  selectedCategoryMeta.value = category ? { slug: category.slug, name: category.name } : null
  sheetOpen.value = true
}

function productQty(productId: number) {
  return cart.items
    .filter((i) => i.product.id === productId)
    .reduce((sum, i) => sum + i.quantity, 0)
}

function lineForProduct(productId: number) {
  const lines = cart.items.filter((i) => i.product.id === productId)
  return (
    lines.find(
      (i) => !i.notes && (!i.addons || i.addons.length === 0) && (!i.variants || i.variants.length === 0),
    ) ||
    lines[0] ||
    null
  )
}

function quickAdd(product: Product, event: Event) {
  event.stopPropagation()
  if (!product.is_available) return
  if (productHasCustomizations(product)) {
    openProduct(product)
    return
  }
  cart.add(product)
  pulse(product.id)
}

function increaseProduct(product: Product, event: Event) {
  event.stopPropagation()
  if (!product.is_available) return
  if (productHasCustomizations(product)) {
    openProduct(product)
    return
  }
  cart.add(product)
  pulse(product.id)
}

async function decreaseProduct(product: Product, event: Event) {
  event.stopPropagation()
  const line = lineForProduct(product.id)
  if (!line) return
  if (productQty(product.id) === 1) {
    const ok = await confirmCartRemove(product.name)
    if (!ok) return
  }
  cart.updateQuantity(line.lineId, line.quantity - 1)
}

function addFromSheet(payload: {
  quantity: number
  notes: string
  variants: CartVariant[]
  addons: CartAddon[]
}) {
  if (!selected.value) return
  cart.add(
    selected.value,
    payload.quantity,
    payload.notes,
    payload.variants,
    payload.addons,
  )
  pulse(selected.value.id)
}

function pulse(id: number) {
  justAddedId.value = id
  window.clearTimeout(pulseTimer)
  pulseTimer = window.setTimeout(() => {
    if (justAddedId.value === id) justAddedId.value = null
  }, ADDED_FEEDBACK_MS)
}

async function eraseMyData() {
  const ok = window.confirm(t('legal.eraseSessionHint'))
  if (!ok) return
  try {
    await menu.eraseSessionData()
    cart.clear()
    ui.success(t('legal.eraseSessionSuccess'))
  } catch {
    ui.error(t('common.error'))
  }
}
</script>

<template>
  <ServiceUnavailable
    v-if="menu.tenantInactive"
    :tenant-name="menu.tenant?.name"
    :tagline="menu.tenant?.branding?.tagline"
  />
  <div v-else class="page-shell menu-page" :class="{ 'menu-page--cart': cart.count > 0 }">
    <header class="mb-4 mb-sm-6 menu-hero" :class="{ 'menu-hero--banner': menuHeaderUrl }">
      <div v-if="menuHeaderUrl" class="menu-hero__banner mb-3">
        <v-img :src="menuHeaderUrl" cover height="180" class="menu-hero__image" />
      </div>
      <p v-if="menu.tenant?.branding?.tagline" class="text-medium-emphasis mb-1 text-body-2">
        {{ menu.tenant.branding.tagline }}
      </p>
      <h1 v-if="menu.tenant?.name" class="display-font menu-title mb-2" style="color: var(--bo-teal-deep)">
        {{ menu.tenant.name }}
      </h1>
      <p class="text-subtitle-1 mb-3">{{ t('menu.title') }}</p>
      <v-chip v-if="menu.location" color="primary" variant="tonal" size="small">
        <v-icon start size="small">mdi-map-marker</v-icon>
        {{ menu.location.name }} · {{ menu.location.zone }}
      </v-chip>
    </header>

    <v-text-field
      v-model="search"
      prepend-inner-icon="mdi-magnify"
      :label="t('menu.search')"
      variant="outlined"
      density="comfortable"
      hide-details
      class="mb-3"
      clearable
    />

    <div class="chip-scroll chip-scroll--sticky mb-5">
      <v-chip
        :color="selectedCategoryId === 'all' ? 'primary' : undefined"
        :variant="selectedCategoryId === 'all' ? 'flat' : 'outlined'"
        filter
        @click="selectedCategoryId = 'all'"
      >
        {{ t('menu.allCategories') }}
      </v-chip>
      <v-chip
        v-for="category in menu.categories"
        :key="category.id"
        :color="selectedCategoryId === category.id ? 'primary' : undefined"
        :variant="selectedCategoryId === category.id ? 'flat' : 'outlined'"
        filter
        @click="selectedCategoryId = category.id"
      >
        {{ category.name }}
      </v-chip>
    </div>

    <v-progress-linear v-if="menu.loading" indeterminate color="primary" class="mb-4" />
    <v-alert v-else-if="menu.error" type="error" class="mb-4">{{ t('common.error') }}</v-alert>

    <section
      v-for="(category, cIdx) in filteredCategories"
      :key="category.id"
      class="mb-8 menu-section"
      :style="{ '--delay': `${cIdx * 40}ms` }"
    >
      <h2 class="display-font text-h5 mb-1">{{ category.name }}</h2>
      <p v-if="category.description" class="text-medium-emphasis mb-4">{{ category.description }}</p>

      <div class="product-grid">
        <MenuProductRow
          v-for="product in category.products"
          :key="product.id"
          :product="product"
          :category-slug="category.slug"
          :category-name="category.name"
          :currency="currency"
          :quantity="productQty(product.id)"
          :pulsing="justAddedId === product.id"
          @open="openProduct(product, category)"
          @add="quickAdd(product, $event)"
          @increase="increaseProduct(product, $event)"
          @decrease="decreaseProduct(product, $event)"
        />
      </div>
    </section>

    <v-alert v-if="!menu.loading && filteredCategories.length === 0" type="info">
      {{ t('menu.empty') }}
    </v-alert>

    <StickyCartBar
      v-if="cart.count > 0"
      :count="cart.count"
      :total="cart.total"
      :currency="currency"
      :to="cartTo"
      :bottom="stickyOffset"
    />

    <ProductCustomizeSheet
      v-model="sheetOpen"
      :product="selected"
      :category-meta="selectedCategoryMeta"
      :currency="currency"
      @add="addFromSheet"
    />

    <footer class="menu-privacy-footer">
      <router-link to="/privacy">{{ t('legal.privacyTitle') }}</router-link>
      <span aria-hidden="true">·</span>
      <button type="button" class="menu-privacy-footer__btn" @click="eraseMyData">
        {{ t('legal.eraseSession') }}
      </button>
    </footer>
  </div>
</template>

<style scoped>
.menu-title {
  font-size: clamp(1.6rem, 6vw, 2.4rem);
  line-height: 1.15;
  margin: 0;
}

.menu-hero {
  animation: rise-in 0.55s ease both;
}

.menu-hero__banner {
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 10px 28px rgba(20, 54, 66, 0.1);
}

.menu-hero__image :deep(.v-img__img) {
  object-position: center;
}

.menu-section {
  animation: rise-in 0.5s ease both;
  animation-delay: var(--delay, 0ms);
}

.chip-scroll--sticky {
  position: sticky;
  top: 56px;
  z-index: 2;
  padding-block: 0.5rem;
  margin-block: -0.25rem 1rem;
  background: linear-gradient(180deg, rgba(232, 244, 243, 0.96), rgba(232, 244, 243, 0.88));
  backdrop-filter: blur(8px);
}

.product-grid {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.menu-page--cart {
  padding-bottom: calc(5.5rem + var(--bottom-nav-space, 0px));
}

@keyframes rise-in {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: none;
  }
}

@media (min-width: 600px) {
  .menu-page--cart {
    padding-bottom: 6rem;
  }
}

.menu-privacy-footer {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  margin-top: 2rem;
  padding-bottom: 1rem;
  font-size: 0.8rem;
  color: rgba(20, 54, 66, 0.55);
}

.menu-privacy-footer a,
.menu-privacy-footer__btn {
  color: rgba(20, 54, 66, 0.65);
  text-decoration: none;
  background: none;
  border: none;
  font: inherit;
  cursor: pointer;
  padding: 0;
}

.menu-privacy-footer a:hover,
.menu-privacy-footer__btn:hover {
  text-decoration: underline;
  color: var(--bo-teal-deep);
}
</style>
