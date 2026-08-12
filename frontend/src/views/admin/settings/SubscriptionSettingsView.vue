<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import api, { getApiErrorMessage } from '@/api/client'
import SettingsPanel from '@/components/admin/SettingsPanel.vue'
import type { SubscriptionInfo, SubscriptionStatus } from '@/types'
import { money } from '@/utils/moneyFormat'

const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()

const loading = ref(true)
const actionLoading = ref(false)
const error = ref('')
const notice = ref('')
const subscription = ref<SubscriptionInfo | null>(null)

const statusColor: Record<SubscriptionStatus, string> = {
  active: 'success',
  past_due: 'warning',
  canceled: 'grey',
  unpaid: 'error',
  expired: 'error',
  inactive: 'grey',
}

const priceLabel = computed(() => {
  if (!subscription.value) return '—'
  const amount = subscription.value.pricing.amount_cents / 100
  return `${money(amount, subscription.value.pricing.currency, locale.value)} / ${t('subscription.year')} ${subscription.value.pricing.vat_note}`
})

const canSubscribe = computed(() =>
  subscription.value && ['inactive', 'expired', 'canceled', 'unpaid'].includes(subscription.value.status),
)

const canManageBilling = computed(() =>
  subscription.value && ['active', 'past_due', 'canceled'].includes(subscription.value.status) && subscription.value.grants_access !== false,
)

function formatDate(value?: string | null) {
  if (!value) return '—'
  return new Intl.DateTimeFormat(locale.value, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get<SubscriptionInfo>('/admin/subscription')
    subscription.value = data
  } catch (e) {
    error.value = getApiErrorMessage(e, t('subscription.loadError'))
  } finally {
    loading.value = false
  }
}

async function startCheckout() {
  actionLoading.value = true
  error.value = ''
  try {
    const { data } = await api.post<{ url: string }>('/admin/subscription/checkout')
    if (data.url) {
      window.location.href = data.url
      return
    }
    error.value = t('subscription.checkoutError')
  } catch (e) {
    error.value = getApiErrorMessage(e, t('subscription.checkoutError'))
  } finally {
    actionLoading.value = false
  }
}

async function openPortal() {
  actionLoading.value = true
  error.value = ''
  try {
    const { data } = await api.post<{ url: string }>('/admin/subscription/portal')
    if (data.url) {
      window.location.href = data.url
      return
    }
    error.value = t('subscription.portalError')
  } catch (e) {
    error.value = getApiErrorMessage(e, t('subscription.portalError'))
  } finally {
    actionLoading.value = false
  }
}

onMounted(async () => {
  if (route.query.checkout === 'success') {
    notice.value = t('subscription.checkoutSuccess')
    await router.replace({ query: {} })
  } else if (route.query.checkout === 'canceled') {
    notice.value = t('subscription.checkoutCanceled')
    await router.replace({ query: {} })
  } else if (route.query.checkout === 'required') {
    await startCheckout()
    await router.replace({ query: {} })
    return
  }

  await load()
})
</script>

<template>
  <SettingsPanel>
    <template #summary>
      <div class="d-flex align-center flex-wrap ga-2 mb-2">
        <div class="text-h6">{{ t('subscription.title') }}</div>
        <v-chip
          v-if="subscription"
          size="small"
          :color="statusColor[subscription.status]"
          variant="flat"
        >
          {{ t(`subscription.status.${subscription.status}`) }}
        </v-chip>
      </div>
      <div class="text-body-2 text-medium-emphasis">
        {{ priceLabel }}
      </div>
    </template>

    <v-alert v-if="notice" type="success" variant="tonal" class="mb-4">{{ notice }}</v-alert>
    <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>
    <v-alert
      v-if="subscription?.status === 'past_due'"
      type="warning"
      variant="tonal"
      class="mb-4"
    >
      {{ t('subscription.pastDueHint') }}
    </v-alert>
    <v-alert
      v-if="subscription && !subscription.grants_access"
      type="info"
      variant="tonal"
      class="mb-4"
    >
      {{ t('subscription.inactiveHint') }}
    </v-alert>

    <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-4" />

    <v-row v-else dense>
      <v-col cols="12" md="6">
        <div class="text-caption text-medium-emphasis">{{ t('subscription.startedAt') }}</div>
        <div class="text-body-1 mb-3">{{ formatDate(subscription?.started_at) }}</div>
      </v-col>
      <v-col cols="12" md="6">
        <div class="text-caption text-medium-emphasis">{{ t('subscription.nextRenewal') }}</div>
        <div class="text-body-1 mb-3">{{ formatDate(subscription?.next_renewal_at) }}</div>
      </v-col>
      <v-col cols="12" md="6">
        <div class="text-caption text-medium-emphasis">{{ t('subscription.expiresAt') }}</div>
        <div class="text-body-1 mb-3">{{ formatDate(subscription?.expires_at) }}</div>
      </v-col>
      <v-col cols="12" md="6">
        <div class="text-caption text-medium-emphasis">{{ t('subscription.plan') }}</div>
        <div class="text-body-1 mb-3">{{ t('subscription.annualPlan') }}</div>
      </v-col>
    </v-row>

    <template #actions>
      <v-btn
        v-if="canSubscribe"
        color="primary"
        :loading="actionLoading"
        prepend-icon="mdi-credit-card-outline"
        @click="startCheckout"
      >
        {{ t('subscription.subscribe') }}
      </v-btn>
      <v-btn
        v-if="canManageBilling"
        variant="outlined"
        color="primary"
        :loading="actionLoading"
        prepend-icon="mdi-open-in-new"
        @click="openPortal"
      >
        {{ t('subscription.manageBilling') }}
      </v-btn>
      <v-btn variant="text" :loading="loading" @click="load">{{ t('subscription.refresh') }}</v-btn>
    </template>
  </SettingsPanel>
</template>
