<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatMoney } from '@/utils/money'
import { categoryIcon } from '@/utils/categoryIcon'
import { resolvePublicAssetUrl } from '@/utils/publicAssetUrl'
import type { Product } from '@/types'

const props = defineProps<{
  product: Product
  categorySlug?: string
  categoryName?: string
  currency: string
  quantity: number
  pulsing?: boolean
}>()

const emit = defineEmits<{
  open: []
  add: [event: Event]
  increase: [event: Event]
  decrease: [event: Event]
}>()

const { t, locale } = useI18n()
const imageUrl = computed(() => resolvePublicAssetUrl(props.product.image_url))
</script>

<template>
  <article
    class="product-row"
    :class="{
      'product-row--disabled': !product.is_available,
      'product-row--pulse': pulsing,
    }"
    role="button"
    tabindex="0"
    @click="emit('open')"
    @keydown.enter="emit('open')"
  >
    <div class="product-media">
      <img
        v-if="imageUrl"
        :src="imageUrl"
        :alt="product.name"
        class="product-thumb"
        loading="lazy"
      />
      <div v-else class="product-thumb product-thumb--placeholder" aria-hidden="true">
        <v-icon size="28" color="primary">{{ categoryIcon(categorySlug, categoryName) }}</v-icon>
      </div>
    </div>
    <div class="product-copy">
      <h3 class="text-subtitle-1 font-weight-bold mb-1">{{ product.name }}</h3>
      <p v-if="product.description" class="text-body-2 text-medium-emphasis mb-2 product-desc">
        {{ product.description }}
      </p>
      <div class="d-flex align-center flex-wrap ga-2">
        <span class="price">{{ formatMoney(product.price, currency, locale) }}</span>
        <v-chip
          v-for="tag in (product.tags || []).slice(0, 3)"
          :key="`tag-${tag.id}`"
          size="x-small"
          color="primary"
          variant="tonal"
        >
          {{ tag.name }}
        </v-chip>
        <v-chip
          v-if="product.track_inventory && product.stock_quantity != null && product.stock_quantity <= 5"
          size="x-small"
          color="warning"
          variant="tonal"
        >
          {{ t('menu.left', { n: product.stock_quantity }) }}
        </v-chip>
      </div>
    </div>
    <v-btn
      v-if="!product.is_available || quantity === 0"
      class="product-add"
      color="primary"
      :disabled="!product.is_available"
      :aria-label="t('menu.add')"
      @click="emit('add', $event)"
    >
      <v-icon start>mdi-plus</v-icon>
      {{ product.is_available ? t('menu.add') : t('menu.unavailable') }}
    </v-btn>
    <div
      v-else
      class="product-add product-qty"
      :class="{ 'product-add--added': pulsing }"
      @click.stop
    >
      <v-btn
        icon="mdi-minus"
        size="small"
        variant="tonal"
        :aria-label="t('cart.decrease')"
        @click="emit('decrease', $event)"
      />
      <span class="product-qty__value">{{ quantity }}</span>
      <v-btn
        icon="mdi-plus"
        size="small"
        variant="tonal"
        color="primary"
        :aria-label="t('cart.increase')"
        @click="emit('increase', $event)"
      />
    </div>
  </article>
</template>

<style scoped>
.product-row {
  display: grid;
  grid-template-columns: 88px 1fr;
  grid-template-areas:
    'media copy'
    'add add';
  gap: 0.75rem 1rem;
  padding: 0.9rem 0;
  border-bottom: 1px solid rgba(11, 110, 107, 0.12);
  cursor: pointer;
  transition: transform 0.2s ease, background 0.2s ease;
  border-radius: 12px;
}

.product-row:active {
  transform: scale(0.99);
}

.product-row--pulse {
  animation: soft-pulse 0.55s ease;
}

.product-row--disabled {
  opacity: 0.55;
  cursor: default;
}

.product-media {
  grid-area: media;
}

.product-thumb {
  width: 88px;
  height: 88px;
  object-fit: cover;
  border-radius: 14px;
  display: block;
}

.product-thumb--placeholder {
  display: grid;
  place-items: center;
  background: rgba(11, 110, 107, 0.08);
}

.product-copy {
  grid-area: copy;
  min-width: 0;
}

.product-desc {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.product-add {
  grid-area: add;
  width: 100%;
  min-height: 44px;
  transition: background-color 0.25s ease, color 0.25s ease, transform 0.2s ease;
}

.product-qty {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  border-radius: 12px;
  background: rgba(11, 110, 107, 0.08);
}

.product-qty__value {
  min-width: 1.5rem;
  text-align: center;
  font-weight: 700;
  font-size: 1.05rem;
}

.product-add--added {
  animation: add-confirm 0.45s ease;
}

.price {
  font-weight: 700;
  color: var(--bo-teal);
}

@keyframes soft-pulse {
  0% {
    background: transparent;
  }
  35% {
    background: rgba(11, 110, 107, 0.1);
  }
  100% {
    background: transparent;
  }
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

@media (min-width: 600px) {
  .product-row {
    grid-template-columns: 96px 1fr auto;
    grid-template-areas: 'media copy add';
    align-items: center;
  }

  .product-thumb {
    width: 96px;
    height: 72px;
  }

  .product-add {
    width: auto;
    min-width: 7.5rem;
  }

  .product-qty {
    min-width: 8.5rem;
    padding-inline: 0.35rem;
  }
}
</style>
