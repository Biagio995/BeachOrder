<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatMoney } from '@/utils/money'
import type { OrderItem } from '@/types'

const props = withDefaults(
  defineProps<{
    item: OrderItem
    currency?: string
    showPrice?: boolean
  }>(),
  {
    currency: 'EUR',
    showPrice: true,
  },
)

const { t, locale } = useI18n()
</script>

<template>
  <div class="order-line-details">
    <div class="order-line-details__main">
      <div class="order-line-details__title">
        <strong>{{ item.quantity }}×</strong> {{ item.product_name }}
      </div>
      <div v-if="showPrice" class="order-line-details__price">
        {{ formatMoney(item.line_total, currency, locale) }}
      </div>
    </div>

    <div v-if="item.variants?.length" class="order-line-details__block">
      <div class="order-line-details__label">{{ t('menu.variants') }}</div>
      <div v-for="(variant, idx) in item.variants" :key="`${item.id}-v-${variant.option_id || idx}`" class="order-line-details__row">
        <span>{{ variant.group_name }}: {{ variant.option_name }}</span>
        <span v-if="Number(variant.price) > 0">+{{ formatMoney(variant.price, currency, locale) }}</span>
      </div>
    </div>

    <div v-if="item.addons?.length" class="order-line-details__block">
      <div class="order-line-details__label">{{ t('menu.addons') }}</div>
      <div v-for="(addon, idx) in item.addons" :key="`${item.id}-a-${addon.id || idx}`" class="order-line-details__row">
        <span>
          {{ addon.quantity && addon.quantity > 1 ? `${addon.quantity}× ` : '' }}{{ addon.name }}
          <span v-if="addon.group_name" class="text-medium-emphasis"> ({{ addon.group_name }})</span>
        </span>
        <span v-if="Number(addon.price) > 0">
          +{{ formatMoney(Number(addon.price) * (addon.quantity || 1), currency, locale) }}
        </span>
      </div>
    </div>

    <div v-if="item.notes" class="order-line-details__notes">{{ item.notes }}</div>
  </div>
</template>

<style scoped>
.order-line-details__main {
  display: flex;
  justify-content: space-between;
  gap: 0.75rem;
}

.order-line-details__title {
  min-width: 0;
}

.order-line-details__price {
  white-space: nowrap;
  font-weight: 600;
}

.order-line-details__block {
  margin-top: 0.35rem;
}

.order-line-details__label {
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: rgba(20, 54, 66, 0.55);
  margin-bottom: 0.15rem;
}

.order-line-details__row {
  display: flex;
  justify-content: space-between;
  gap: 0.75rem;
  font-size: 0.85rem;
  color: rgba(20, 54, 66, 0.78);
}

.order-line-details__notes {
  margin-top: 0.3rem;
  font-size: 0.8rem;
  color: rgba(20, 54, 66, 0.6);
}
</style>
