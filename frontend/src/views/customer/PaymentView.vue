<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api, { getApiErrorMessage, tenantPath } from '@/api/client'
import { useMenuStore } from '@/stores/menu'
import ServiceUnavailable from '@/components/customer/ServiceUnavailable.vue'
import { formatMoney } from '@/utils/money'

interface RedirectPaymentSession {
  type: 'redirect'
  provider: 'nexi'
  payment_status: string
  amount: number
  currency: string
  payment_reference?: string | null
  gateway_url: string
  fields: Record<string, string>
}

const route = useRoute()
const router = useRouter()
const { t, locale } = useI18n()
const menu = useMenuStore()

const loading = ref(true)
const redirecting = ref(false)
const error = ref('')
const payment = ref<RedirectPaymentSession | null>(null)

const tenant = computed(() => String(route.params.tenant || menu.tenantSlug))
const orderId = computed(() => String(route.params.id))
const session = computed(() => String(route.query.session || menu.session))
const currency = computed(() => payment.value?.currency || menu.tenant?.currency || 'EUR')

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

async function submitToGateway() {
  if (!payment.value?.gateway_url || !payment.value.fields) return

  redirecting.value = true
  await nextTick()

  const form = document.createElement('form')
  form.method = 'POST'
  form.action = payment.value.gateway_url
  form.acceptCharset = 'ISO-8859-1'

  for (const [key, value] of Object.entries(payment.value.fields)) {
    const input = document.createElement('input')
    input.type = 'hidden'
    input.name = key
    input.value = value
    form.appendChild(input)
  }

  document.body.appendChild(form)
  form.submit()
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

  if (payment.value?.type === 'redirect') {
    await submitToGateway()
  }
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
    <p class="text-medium-emphasis mb-4">{{ t('payment.cardLead') }}</p>

    <v-progress-linear v-if="loading || redirecting" indeterminate color="primary" class="mb-4" />

    <template v-if="payment && !redirecting">
      <div class="payment-summary mb-4">
        <span>{{ t('cart.total') }}</span>
        <strong>{{ formatMoney(payment.amount, currency, locale) }}</strong>
      </div>

      <v-alert v-if="error" type="error" class="mb-3">{{ error }}</v-alert>

      <v-btn
        v-else
        block
        size="large"
        color="accent"
        :loading="redirecting"
        @click="submitToGateway"
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

    <p v-else-if="redirecting" class="text-body-2 text-medium-emphasis">
      {{ t('payment.redirecting') }}
    </p>

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
</style>
