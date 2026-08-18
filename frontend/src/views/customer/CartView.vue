<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api, { getApiErrorMessage, tenantPath } from '@/api/client'
import { useCartStore } from '@/stores/cart'
import { useMenuStore } from '@/stores/menu'
import { useActiveOrderStore } from '@/stores/activeOrder'
import { useUiStore } from '@/stores/ui'
import { useDeleteConfirm } from '@/composables/useDeleteConfirm'
import ServiceUnavailable from '@/components/customer/ServiceUnavailable.vue'
import CartLineRow from '@/components/customer/CartLineRow.vue'
import { formatMoney } from '@/utils/money'

const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()
const cart = useCartStore()
const menu = useMenuStore()
const activeOrder = useActiveOrderStore()
const ui = useUiStore()
const { confirmCartRemove } = useDeleteConfirm()
const submitting = ref(false)
const error = ref('')

const currency = computed(() => menu.tenant?.currency || 'EUR')

onMounted(async () => {
  const tenant = String(route.params.tenant || '')
  if (tenant) menu.setTenant(tenant)
  cart.bindContext(menu.tenantSlug || tenant, menu.locationCode)
  await menu.checkTenantStatus()
})

async function decreaseItem(lineId: string, quantity: number) {
  if (quantity === 1) {
    const item = cart.items.find((i) => i.lineId === lineId)
    const ok = await confirmCartRemove(item?.product?.name)
    if (!ok) return
  }
  cart.updateQuantity(lineId, quantity - 1)
}

async function checkout() {
  if (!menu.tenantSlug || cart.items.length === 0) return

  if (!menu.locationCode) {
    error.value = t('cart.needLocation')
    return
  }

  if (!menu.canOrder || !menu.accessToken) {
    error.value = t('cart.needRescan')
    return
  }

  submitting.value = true
  error.value = ''
  try {
    const { data } = await api.post(tenantPath(menu.tenantSlug, '/orders'), {
      location_code: menu.locationCode,
      access_token: menu.accessToken,
      customer_session: menu.session,
      notes: cart.orderNotes || null,
      locale: locale.value,
      payment_method: 'pay_at_location',
      items: cart.items.map((i) => ({
        product_id: i.product.id,
        quantity: i.quantity,
        notes: i.notes || null,
        variant_option_ids: (i.variants || []).map((variant) => variant.optionId),
        addons: (i.addons || []).map((addon) => ({
          id: addon.id,
          quantity: addon.quantity,
        })),
      })),
    })
    cart.clear()
    menu.clearAccess()
    activeOrder.track(data, menu.tenantSlug, menu.session)

    ui.success(t('order.placed'))
    router.push({
      name: 'order-status',
      params: { tenant: menu.tenantSlug, id: data.id },
      query: { session: menu.session },
    })
  } catch (e: unknown) {
    error.value = getApiErrorMessage(e, t('common.error'))
    const msg = error.value.toLowerCase()
    if (msg.includes('access') || msg.includes('scan') || msg.includes('qr')) {
      menu.clearAccess()
      error.value = t('cart.needRescan')
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <ServiceUnavailable
    v-if="menu.tenantInactive"
    :tenant-name="menu.tenant?.name"
    :tagline="menu.tenant?.branding?.tagline"
  />
  <div v-else class="page-shell cart-page">
    <h1 class="display-font cart-title mb-4" style="color: var(--bo-teal-deep)">{{ t('cart.title') }}</h1>

    <div v-if="cart.items.length === 0" class="empty-cart mb-4">
      <v-icon size="40" color="primary" class="mb-3">mdi-cart-outline</v-icon>
      <p class="mb-3">{{ t('cart.empty') }}</p>
      <v-btn
        v-if="activeOrder.isActive && activeOrder.statusRoute"
        color="accent"
        class="mb-3"
        block
        :to="activeOrder.statusRoute"
      >
        {{ t('order.followActive') }}
      </v-btn>
      <v-btn
        v-if="menu.locationCode"
        color="primary"
        :to="`/t/${menu.tenantSlug}/q/${menu.locationCode}`"
      >
        {{ t('nav.menu') }}
      </v-btn>
    </div>

    <v-alert v-else-if="!menu.locationCode" type="warning" class="mb-4">
      {{ t('cart.needLocation') }}
    </v-alert>

    <v-alert
      v-else-if="cart.items.length > 0 && !menu.canOrder"
      type="warning"
      class="mb-4"
    >
      {{ t('cart.needRescan') }}
      <v-btn
        class="mt-3"
        color="primary"
        block
        :to="`/t/${menu.tenantSlug}/q/${menu.locationCode}`"
      >
        {{ t('cart.rescanCta') }}
      </v-btn>
    </v-alert>

    <template v-if="cart.items.length > 0">
      <CartLineRow
        v-for="item in cart.items"
        :key="item.lineId"
        :item="item"
        :currency="currency"
        @decrease="decreaseItem(item.lineId, item.quantity)"
        @increase="cart.updateQuantity(item.lineId, item.quantity + 1)"
        @update:notes="cart.updateNotes(item.lineId, $event)"
      />

      <v-textarea v-model="cart.orderNotes" :label="t('cart.orderNotes')" class="mt-4" rows="2" density="comfortable" />

      <div class="checkout-bar">
        <div class="d-flex justify-space-between align-center mb-3">
          <span class="text-h6">{{ t('cart.total') }}</span>
          <span class="text-h5 font-weight-bold" style="color: var(--bo-teal)">
            {{ formatMoney(cart.total, currency, locale) }}
          </span>
        </div>
        <v-alert v-if="error" type="error" class="mb-3">{{ error }}</v-alert>
        <v-btn
          block
          size="large"
          color="primary"
          :loading="submitting"
          :disabled="!menu.locationCode || !menu.canOrder"
          @click="checkout"
        >
          {{ t('cart.checkout') }}
        </v-btn>
      </div>
    </template>
  </div>
</template>

<style scoped>
.cart-title {
  font-size: clamp(1.5rem, 5vw, 2.1rem);
  line-height: 1.2;
}

.empty-cart {
  text-align: center;
  padding: 2rem 1rem;
  border-radius: 18px;
  background: rgba(255, 255, 255, 0.65);
  border: 1px dashed rgba(11, 110, 107, 0.25);
}

.checkout-bar {
  margin-top: 1.25rem;
  padding-top: 0.5rem;
}
</style>
