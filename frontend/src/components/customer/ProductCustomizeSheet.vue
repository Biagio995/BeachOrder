<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { lineUnitPrice } from '@/stores/cart'
import { formatMoney } from '@/utils/money'
import { categoryIcon } from '@/utils/categoryIcon'
import { resolvePublicAssetUrl } from '@/utils/publicAssetUrl'
import QtyStepper from '@/components/shared/QtyStepper.vue'
import type { CartAddon, CartVariant, Product } from '@/types'

const open = defineModel<boolean>({ required: true })

const props = defineProps<{
  product: Product | null
  categoryMeta?: { slug?: string; name?: string } | null
  currency: string
}>()

const emit = defineEmits<{
  add: [payload: {
    quantity: number
    notes: string
    variants: CartVariant[]
    addons: CartAddon[]
  }]
}>()

const { t, locale } = useI18n()

const productImageUrl = computed(() => resolvePublicAssetUrl(props.product?.image_url))

const sheetQty = ref(1)
const sheetNotes = ref('')
const sheetVariantChoices = ref<Record<number, number>>({})
const sheetAddonQty = ref<Record<number, number>>({})
const justAdded = ref(false)

let pulseTimer: number | undefined
const ADDED_FEEDBACK_MS = 2500

const selectedVariants = computed<CartVariant[]>(() => {
  if (!props.product) return []
  const result: CartVariant[] = []
  for (const group of props.product.variant_groups || []) {
    const optionId = sheetVariantChoices.value[group.id]
    if (!optionId) continue
    const option = (group.options || []).find((o) => o.id === optionId)
    if (!option) continue
    result.push({
      groupId: group.id,
      groupName: group.name,
      optionId: option.id,
      optionName: option.name,
      price: Number(option.price) || 0,
    })
  }
  return result
})

const selectedAddons = computed<CartAddon[]>(() => {
  if (!props.product) return []
  const result: CartAddon[] = []
  for (const group of props.product.addon_groups || []) {
    for (const addon of group.addons || []) {
      const qty = Number(sheetAddonQty.value[addon.id] || 0)
      if (qty <= 0) continue
      result.push({
        id: addon.id,
        groupId: group.id,
        groupName: group.name,
        name: addon.name,
        price: Number(addon.price) || 0,
        quantity: qty,
      })
    }
  }
  return result
})

const unitPrice = computed(() =>
  props.product ? lineUnitPrice(props.product, selectedVariants.value, selectedAddons.value) : 0,
)

const lineTotal = computed(() => unitPrice.value * sheetQty.value)

const customizationValid = computed(() => {
  if (!props.product) return false
  for (const group of props.product.variant_groups || []) {
    if (group.is_required && !sheetVariantChoices.value[group.id]) return false
  }
  for (const group of props.product.addon_groups || []) {
    const selectedCount = (group.addons || []).filter(
      (addon) => Number(sheetAddonQty.value[addon.id] || 0) > 0,
    ).length
    const minSelections = Number(group.min_selections) || 0
    const maxSelections = group.max_selections == null ? null : Number(group.max_selections)
    if (selectedCount < minSelections) return false
    if (maxSelections != null && selectedCount > maxSelections) return false
  }
  return true
})

function initSelections(product: Product) {
  const variantChoices: Record<number, number> = {}
  for (const group of product.variant_groups || []) {
    if (group.is_required && group.options?.[0]) {
      variantChoices[group.id] = group.options[0].id
    }
  }
  sheetVariantChoices.value = variantChoices

  const addonQty: Record<number, number> = {}
  for (const group of product.addon_groups || []) {
    for (const addon of group.addons || []) {
      const minQty = Number(addon.min_quantity) || 0
      if (minQty > 0) addonQty[addon.id] = minQty
    }
  }
  sheetAddonQty.value = addonQty
}

function resetSheet() {
  sheetQty.value = 1
  sheetNotes.value = ''
  justAdded.value = false
  window.clearTimeout(pulseTimer)
}

watch(
  () => [open.value, props.product?.id] as const,
  ([isOpen, productId]) => {
    if (!isOpen || !productId || !props.product) return
    resetSheet()
    initSelections(props.product)
  },
)

function setVariantChoice(groupId: number, optionId: number) {
  sheetVariantChoices.value = { ...sheetVariantChoices.value, [groupId]: optionId }
}

function clearVariantChoice(groupId: number) {
  const next = { ...sheetVariantChoices.value }
  delete next[groupId]
  sheetVariantChoices.value = next
}

function setAddonQty(addonId: number, qty: number, min = 0, max = 1) {
  const clamped = Math.min(max, Math.max(min, qty))
  const next = { ...sheetAddonQty.value }
  if (clamped <= 0) delete next[addonId]
  else next[addonId] = clamped
  sheetAddonQty.value = next
}

function addToCart() {
  if (!props.product || !customizationValid.value) return
  emit('add', {
    quantity: sheetQty.value,
    notes: sheetNotes.value.trim(),
    variants: selectedVariants.value,
    addons: selectedAddons.value,
  })
  justAdded.value = true
  window.clearTimeout(pulseTimer)
  pulseTimer = window.setTimeout(() => {
    justAdded.value = false
    open.value = false
  }, ADDED_FEEDBACK_MS)
}
</script>

<template>
  <v-bottom-sheet v-model="open" scrim opacity="0.45">
    <div v-if="product" class="product-sheet">
      <div class="product-sheet__handle" aria-hidden="true" />
      <div
        class="product-sheet__hero"
        :style="productImageUrl ? { backgroundImage: `url(${productImageUrl})` } : undefined"
      >
        <div v-if="!productImageUrl" class="product-sheet__hero-fallback">
          <v-icon size="48" color="primary">
            {{ categoryIcon(categoryMeta?.slug, categoryMeta?.name) }}
          </v-icon>
        </div>
      </div>
      <div class="product-sheet__body">
        <h2 class="display-font product-sheet__title">{{ product.name }}</h2>
        <p class="price mb-2">{{ formatMoney(unitPrice, currency, locale) }}</p>
        <p v-if="product.description" class="text-body-2 text-medium-emphasis mb-3">
          {{ product.description }}
        </p>
        <div v-if="(product.tags || []).length" class="d-flex flex-wrap ga-2 mb-3">
          <v-chip
            v-for="tag in product.tags"
            :key="`sheet-tag-${tag.id}`"
            size="small"
            color="primary"
            variant="tonal"
          >
            {{ tag.name }}
          </v-chip>
        </div>
        <div v-if="product.allergens.length" class="mb-4">
          <div class="text-caption text-medium-emphasis mb-1">{{ t('menu.allergens') }}</div>
          <div class="d-flex flex-wrap ga-2">
            <v-chip v-for="a in product.allergens" :key="a" size="small" variant="outlined">
              {{ a }}
            </v-chip>
          </div>
        </div>

        <div
          v-for="group in product.variant_groups || []"
          :key="`variant-group-${group.id}`"
          class="mb-4"
        >
          <div class="d-flex align-center justify-space-between mb-1">
            <div class="font-weight-medium">
              {{ group.name }}
              <span v-if="group.is_required" class="text-caption text-medium-emphasis">
                ({{ t('menu.required') }})
              </span>
              <span v-else class="text-caption text-medium-emphasis">
                ({{ t('menu.optional') }})
              </span>
            </div>
            <v-btn
              v-if="!group.is_required && sheetVariantChoices[group.id]"
              size="x-small"
              variant="text"
              @click="clearVariantChoice(group.id)"
            >
              {{ t('common.clear') }}
            </v-btn>
          </div>
          <div class="addon-list">
            <button
              v-for="option in group.options"
              :key="option.id"
              type="button"
              class="addon-option"
              :class="{ 'addon-option--selected': sheetVariantChoices[group.id] === option.id }"
              @click="setVariantChoice(group.id, option.id)"
            >
              <span class="addon-option__check">
                <v-icon size="18">
                  {{
                    sheetVariantChoices[group.id] === option.id
                      ? 'mdi-radiobox-marked'
                      : 'mdi-radiobox-blank'
                  }}
                </v-icon>
              </span>
              <span class="addon-option__name">{{ option.name }}</span>
              <span class="addon-option__price">
                <template v-if="option.price > 0">+{{ formatMoney(option.price, currency, locale) }}</template>
                <template v-else>{{ t('menu.included') }}</template>
              </span>
            </button>
          </div>
        </div>

        <div
          v-for="group in product.addon_groups || []"
          :key="`addon-group-${group.id}`"
          class="mb-4"
        >
          <div class="font-weight-medium mb-1">{{ group.name }}</div>
          <p class="text-caption text-medium-emphasis mb-2">
            {{ t('menu.addonsHint') }}
            <span v-if="group.min_selections">
              · {{ t('menu.minSelections', { n: group.min_selections }) }}
            </span>
            <span v-if="group.max_selections != null">
              · {{ t('menu.maxSelections', { n: group.max_selections }) }}
            </span>
          </p>
          <div class="addon-list">
            <div
              v-for="addon in group.addons"
              :key="addon.id"
              class="addon-option addon-option--qty"
            >
              <div class="addon-option__copy">
                <span class="addon-option__name">{{ addon.name }}</span>
                <span class="addon-option__price">+{{ formatMoney(addon.price, currency, locale) }}</span>
              </div>
              <QtyStepper
                compact
                :model-value="sheetAddonQty[addon.id] || 0"
                :min="addon.min_quantity || 0"
                :max="addon.max_quantity || 1"
                :decrease-label="t('cart.decrease')"
                :increase-label="t('cart.increase')"
                @update:model-value="setAddonQty(addon.id, $event, addon.min_quantity || 0, addon.max_quantity || 1)"
              />
            </div>
          </div>
        </div>

        <div class="d-flex align-center justify-space-between mb-3">
          <span class="font-weight-medium">{{ t('cart.quantity') }}</span>
          <QtyStepper
            v-model="sheetQty"
            :decrease-label="t('cart.decrease')"
            :increase-label="t('cart.increase')"
          />
        </div>

        <v-textarea
          v-model="sheetNotes"
          :label="t('cart.itemNotes')"
          rows="2"
          density="comfortable"
          variant="outlined"
          class="mb-4"
        />

        <v-btn
          block
          size="large"
          :color="justAdded ? 'success' : 'accent'"
          :class="{ 'product-add--added': justAdded }"
          :disabled="justAdded || !customizationValid"
          @click="addToCart"
        >
          <template v-if="justAdded">
            <v-icon start>mdi-check</v-icon>
            {{ t('menu.added') }}
          </template>
          <template v-else>
            {{ t('menu.addToCart') }} · {{ formatMoney(lineTotal, currency, locale) }}
          </template>
        </v-btn>
      </div>
    </div>
  </v-bottom-sheet>
</template>

<style scoped>
.product-sheet {
  background: #f7fbfb;
  border-radius: 24px 24px 0 0;
  overflow: hidden;
  max-height: 92vh;
}

.product-sheet__handle {
  width: 42px;
  height: 4px;
  border-radius: 999px;
  background: rgba(20, 54, 66, 0.2);
  margin: 0.65rem auto 0;
}

.product-sheet__hero {
  height: 180px;
  margin-top: 0.65rem;
  background:
    linear-gradient(180deg, transparent 40%, rgba(247, 251, 251, 0.95)),
    radial-gradient(circle at 30% 30%, rgba(244, 201, 95, 0.35), transparent 55%),
    rgba(11, 110, 107, 0.1);
  background-size: cover;
  background-position: center;
}

.product-sheet__hero-fallback {
  height: 100%;
  display: grid;
  place-items: center;
}

.product-sheet__body {
  padding: 1rem 1.15rem calc(1.25rem + env(safe-area-inset-bottom, 0px));
  overflow-y: auto;
  max-height: calc(92vh - 200px);
}

.addon-list {
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
}

.addon-option {
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: center;
  gap: 0.65rem;
  width: 100%;
  text-align: left;
  padding: 0.7rem 0.8rem;
  border-radius: 12px;
  border: 1px solid rgba(11, 110, 107, 0.16);
  background: rgba(255, 255, 255, 0.7);
  color: inherit;
  cursor: pointer;
  transition: border-color 0.2s ease, background 0.2s ease;
}

.addon-option--selected {
  border-color: var(--bo-teal);
  background: rgba(11, 110, 107, 0.08);
}

.addon-option__check {
  color: var(--bo-teal);
  display: inline-flex;
}

.addon-option__name {
  font-weight: 600;
}

.addon-option__price {
  font-weight: 700;
  color: var(--bo-teal);
  white-space: nowrap;
}

.addon-option--qty {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  cursor: default;
}

.addon-option__copy {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  min-width: 0;
}

.product-sheet__title {
  font-size: 1.55rem;
  color: var(--bo-teal-deep);
  margin: 0 0 0.25rem;
  line-height: 1.2;
}

.price {
  font-weight: 700;
  color: var(--bo-teal);
}

.product-add--added {
  animation: add-confirm 0.45s ease;
}

@keyframes add-confirm {
  0% {
    transform: scale(1);
  }
  40% {
    transform: scale(1.04);
  }
  100% {
    transform: scale(1);
  }
}
</style>
