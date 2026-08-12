<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { lineUnitPrice } from '@/stores/cart'
import { formatMoney } from '@/utils/money'
import QtyStepper from '@/components/shared/QtyStepper.vue'
import type { CartItem } from '@/types'

defineProps<{
  item: CartItem
  currency: string
}>()

const emit = defineEmits<{
  decrease: []
  increase: []
  'update:notes': [notes: string]
}>()

const { t, locale } = useI18n()
</script>

<template>
  <div class="cart-row">
    <div class="cart-row__info">
      <div class="font-weight-bold">{{ item.product.name }}</div>
      <div class="text-medium-emphasis">
        {{ formatMoney(lineUnitPrice(item.product, item.variants, item.addons), currency, locale) }}
      </div>
      <div v-if="item.variants?.length" class="text-caption text-medium-emphasis mt-1">
        <div v-for="variant in item.variants" :key="`${item.lineId}-v-${variant.optionId}`">
          {{ variant.groupName }}: {{ variant.optionName }}
          <span v-if="variant.price > 0">(+{{ formatMoney(variant.price, currency, locale) }})</span>
        </div>
      </div>
      <div v-if="item.addons?.length" class="text-caption text-medium-emphasis mt-1">
        <div v-for="addon in item.addons" :key="`${item.lineId}-a-${addon.id}`">
          {{ addon.quantity > 1 ? `${addon.quantity}× ` : '' }}+ {{ addon.name }}
          <span v-if="addon.price > 0">
            ({{ formatMoney(addon.price * addon.quantity, currency, locale) }})
          </span>
        </div>
      </div>
      <v-text-field
        :model-value="item.notes"
        :label="t('cart.itemNotes')"
        density="comfortable"
        variant="underlined"
        hide-details
        class="mt-1"
        @update:model-value="emit('update:notes', String($event || ''))"
      />
    </div>
    <div class="cart-row__qty">
      <QtyStepper
        :model-value="item.quantity"
        :decrease-label="t('cart.decrease')"
        :increase-label="t('cart.increase')"
        @decrease="emit('decrease')"
        @increase="emit('increase')"
      />
    </div>
  </div>
</template>

<style scoped>
.cart-row {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding: 1rem 0;
  border-bottom: 1px solid rgba(11, 110, 107, 0.12);
}

.cart-row__info {
  min-width: 0;
}

.cart-row__qty {
  display: flex;
  align-items: center;
  justify-content: flex-end;
}

@media (min-width: 600px) {
  .cart-row {
    flex-direction: row;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
  }
}
</style>
