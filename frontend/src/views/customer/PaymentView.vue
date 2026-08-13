<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { loadStripe, type Stripe, type StripeElements, type StripePaymentElement } from '@stripe/stripe-js'
import api, { getApiErrorMessage, tenantPath } from '@/api/client'
import { useMenuStore } from '@/stores/menu'
import { useUiStore } from '@/stores/ui'
import ServiceUnavailable from '@/components/customer/ServiceUnavailable.vue'
import { formatMoney } from '@/utils/money'
import { stripeLocaleFromApp } from '@/utils/stripeLocale'
import { waitForOrderPayment } from '@/utils/waitForOrderPayment'
import { useActiveOrderStore } from '@/stores/activeOrder'

interface PaymentSession {
  client_secret: string
  publishable_key: string
  payment_intent_id: string
  payment_status: string
  amount: number
  currency: string
}

const route = useRoute()
const router = useRouter()
const { t, locale } = useI18n()
const menu = useMenuStore()
const ui = useUiStore()
const activeOrder = useActiveOrderStore()

const loading = ref(true)
const paying = ref(false)
const error = ref('')
const payment = ref<PaymentSession | null>(null)
const stripe = ref<Stripe | null>(null)
const elements = ref<StripeElements | null>(null)
let paymentElement: StripePaymentElement | null = null

const tenant = computed(() => String(route.params.tenant || menu.tenantSlug))
const orderId = computed(() => String(route.params.id))
const session = computed(() => String(route.query.session || menu.session))
const currency = computed(() => payment.value?.currency || menu.tenant?.currency || 'EUR')
const stripeLocale = computed(() => stripeLocaleFromApp(String(locale.value)))

async function loadPaymentSession() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get(tenantPath(tenant.value, `/orders/${orderId.value}/payment`), {
      params: { session: session.value },
    })
    payment.value = data
  } catch (e: unknown) {
    error.value = getApiErrorMessage(e, t('payment.loadError'))
  } finally {
    loading.value = false
  }
}

async function mountStripeElement() {
  if (!payment.value?.client_secret || !payment.value.publishable_key) return

  if (!stripe.value) {
    stripe.value = await loadStripe(payment.value.publishable_key)
  }
  if (!stripe.value) {
    error.value = t('payment.stripeUnavailable')
    return
  }

  paymentElement?.unmount()
  paymentElement = null
  elements.value = null

  elements.value = stripe.value.elements({
    clientSecret: payment.value.client_secret,
    locale: stripeLocale.value,
    appearance: {
      theme: 'stripe',
      variables: {
        colorPrimary: menu.tenant?.branding?.primary_color || '#0b6e6b',
        borderRadius: '12px',
      },
    },
  })

  paymentElement = elements.value.create('payment')
  paymentElement.mount('#payment-element')
}

async function confirmPayment() {
  if (!stripe.value || !elements.value || !payment.value) return

  paying.value = true
  error.value = ''

  const returnUrl = `${window.location.origin}/t/${tenant.value}/order/${orderId.value}?session=${encodeURIComponent(session.value)}`

  const { error: stripeError } = await stripe.value.confirmPayment({
    elements: elements.value,
    confirmParams: { return_url: returnUrl },
    redirect: 'if_required',
  })

  paying.value = false

  if (stripeError) {
    error.value = stripeError.message || t('payment.failed')
    return
  }

  paying.value = true
  const confirmed = await waitForOrderPayment(tenant.value, orderId.value, session.value)
  paying.value = false

  if (confirmed) {
    activeOrder.track(confirmed, tenant.value, session.value)
  }

  ui.success(t('payment.success'))
  router.push({
    name: 'order-status',
    params: { tenant: tenant.value, id: orderId.value },
    query: { session: session.value },
  })
}

onMounted(async () => {
  menu.setTenant(tenant.value)
  const active = await menu.checkTenantStatus()
  if (!active) {
    loading.value = false
    return
  }

  await loadPaymentSession()
  if (payment.value?.payment_status === 'paid') {
    router.replace({
      name: 'order-status',
      params: { tenant: tenant.value, id: orderId.value },
      query: { session: session.value },
    })
    return
  }

  await mountStripeElement()
})

watch(stripeLocale, () => {
  if (payment.value?.payment_status !== 'paid' && !loading.value) {
    void mountStripeElement()
  }
})

onBeforeUnmount(() => {
  paymentElement?.unmount()
  paymentElement = null
  elements.value = null
  stripe.value = null
})
</script>

<template>
  <ServiceUnavailable
    v-if="menu.tenantInactive"
    :tenant-name="menu.tenant?.name"
    :tagline="menu.tenant?.branding?.tagline"
  />
  <div v-else class="page-shell payment-page">
    <h1 class="display-font payment-title mb-2">{{ t('payment.title') }}</h1>
    <p class="text-medium-emphasis mb-4">{{ t('payment.lead') }}</p>

    <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-4" />

    <template v-else-if="payment">
      <div class="payment-summary mb-4">
        <span>{{ t('cart.total') }}</span>
        <strong>{{ formatMoney(payment.amount, currency, locale) }}</strong>
      </div>

      <div id="payment-element" class="payment-element mb-4" />

      <v-alert v-if="error" type="error" class="mb-3">{{ error }}</v-alert>

      <v-btn
        block
        size="large"
        color="accent"
        :loading="paying"
        :disabled="!stripe"
        @click="confirmPayment"
      >
        {{ t('payment.payNow') }}
      </v-btn>

      <v-btn
        class="mt-3"
        block
        variant="text"
        :to="{
          name: 'order-status',
          params: { tenant, id: orderId },
          query: { session },
        }"
      >
        {{ t('payment.backToOrder') }}
      </v-btn>
    </template>

    <v-alert v-else-if="error" type="error">{{ error }}</v-alert>
  </div>
</template>

<style scoped>
.payment-title {
  font-size: clamp(1.5rem, 5vw, 2rem);
  color: var(--bo-teal-deep);
}

.payment-summary {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.1rem;
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.75);
  border: 1px solid rgba(11, 110, 107, 0.12);
  font-size: 1.1rem;
}

.payment-element {
  padding: 0.75rem;
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.85);
  border: 1px solid rgba(11, 110, 107, 0.12);
}
</style>
