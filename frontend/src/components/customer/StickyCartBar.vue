<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatMoney } from '@/utils/money'

defineProps<{
  count: number
  total: number
  currency: string
  to: string
  bottom?: string
}>()

const { t, locale } = useI18n()
</script>

<template>
  <Transition name="cart-bar">
    <router-link :to="to" class="sticky-cart" :style="bottom ? { bottom } : undefined">
      <div class="sticky-cart__left">
        <span class="sticky-cart__badge">{{ count }}</span>
        <span class="sticky-cart__label">{{ t('nav.cart') }}</span>
      </div>
      <strong class="sticky-cart__total">{{ formatMoney(total, currency, locale) }}</strong>
    </router-link>
  </Transition>
</template>

<style scoped>
.sticky-cart {
  position: fixed;
  left: 50%;
  transform: translateX(-50%);
  width: min(520px, calc(100% - 2rem));
  z-index: 20;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.85rem 1.1rem;
  border-radius: 999px;
  text-decoration: none;
  color: #fff;
  background: linear-gradient(135deg, var(--bo-teal-deep), var(--bo-teal) 55%, #1a8a86);
  box-shadow: 0 14px 36px rgba(8, 78, 76, 0.28);
}

.sticky-cart__left {
  display: flex;
  align-items: center;
  gap: 0.65rem;
}

.sticky-cart__badge {
  min-width: 1.6rem;
  height: 1.6rem;
  border-radius: 999px;
  display: inline-grid;
  place-items: center;
  background: var(--bo-coral);
  font-size: 0.85rem;
  font-weight: 700;
}

.sticky-cart__label {
  font-weight: 600;
}

.cart-bar-enter-active,
.cart-bar-leave-active {
  transition: opacity 0.25s ease, transform 0.25s ease;
}

.cart-bar-enter-from,
.cart-bar-leave-to {
  opacity: 0;
  transform: translate(-50%, 16px);
}
</style>
