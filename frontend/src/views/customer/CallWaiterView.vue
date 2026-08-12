<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api, { tenantPath } from '@/api/client'
import { useMenuStore } from '@/stores/menu'
import ServiceUnavailable from '@/components/customer/ServiceUnavailable.vue'

const { t } = useI18n()
const route = useRoute()
const menu = useMenuStore()
const reason = ref('assistance')
const note = ref('')
const sent = ref(false)
const loading = ref(false)
const error = ref('')

const reasons = [
  { id: 'bill', icon: 'mdi-receipt-text-outline' },
  { id: 'assistance', icon: 'mdi-hand-wave-outline' },
  { id: 'ashtray', icon: 'mdi-smoking' },
  { id: 'water', icon: 'mdi-cup-water' },
  { id: 'other', icon: 'mdi-dots-horizontal-circle-outline' },
]

onMounted(async () => {
  const tenant = String(route.params.tenant || '')
  if (tenant) menu.setTenant(tenant)
  await menu.checkTenantStatus()
})

async function send() {
  if (!menu.locationCode || !menu.tenantSlug) {
    error.value = t('waiter.needLocation')
    return
  }
  loading.value = true
  error.value = ''
  try {
    await api.post(tenantPath(menu.tenantSlug, '/waiter-call'), {
      location_code: menu.locationCode,
      reason: reason.value,
      note: note.value || null,
      customer_session: menu.session,
    })
    sent.value = true
  } catch {
    error.value = t('common.error')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <ServiceUnavailable
    v-if="menu.tenantInactive"
    :tenant-name="menu.tenant?.name"
    :tagline="menu.tenant?.branding?.tagline"
  />
  <div v-else class="page-shell call-page">
    <h1 class="display-font call-title mb-2" style="color: var(--bo-teal-deep)">{{ t('waiter.title') }}</h1>
    <p v-if="menu.location" class="text-medium-emphasis mb-5">
      {{ menu.location.name }} · {{ menu.location.zone }}
    </p>

    <div v-if="sent" class="sent-panel">
      <div class="sent-panel__ring" aria-hidden="true">
        <v-icon size="40" color="primary">mdi-check</v-icon>
      </div>
      <h2 class="display-font sent-panel__title">{{ t('waiter.sent') }}</h2>
      <p class="text-medium-emphasis mb-4">{{ t('waiter.sentHint') }}</p>
      <v-btn variant="tonal" color="primary" @click="sent = false">{{ t('waiter.sendAnother') }}</v-btn>
    </div>

    <template v-else>
      <div class="reason-grid mb-4">
        <button
          v-for="r in reasons"
          :key="r.id"
          type="button"
          class="reason-card"
          :class="{ 'reason-card--active': reason === r.id }"
          @click="reason = r.id"
        >
          <v-icon :icon="r.icon" size="28" />
          <span>{{ t(`waiter.reasons.${r.id}`) }}</span>
        </button>
      </div>
      <v-textarea v-model="note" :label="t('waiter.note')" rows="3" density="comfortable" />
      <v-alert v-if="error" type="error" class="mb-3">{{ error }}</v-alert>
      <v-btn block size="large" color="accent" :loading="loading" @click="send">
        {{ t('waiter.send') }}
      </v-btn>
    </template>
  </div>
</template>

<style scoped>
.call-title {
  font-size: clamp(1.5rem, 5vw, 2.1rem);
  line-height: 1.2;
}

.reason-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
}

.reason-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
  min-height: 92px;
  padding: 0.85rem 0.5rem;
  border-radius: 16px;
  border: 1px solid rgba(11, 110, 107, 0.16);
  background: rgba(255, 255, 255, 0.65);
  color: var(--bo-ink);
  font: inherit;
  font-weight: 600;
  cursor: pointer;
  transition: transform 0.15s ease, border-color 0.15s ease, background 0.15s ease;
}

.reason-card:active {
  transform: scale(0.98);
}

.reason-card--active {
  border-color: transparent;
  background: linear-gradient(160deg, rgba(11, 110, 107, 0.14), rgba(224, 122, 95, 0.12));
  box-shadow: inset 0 0 0 2px var(--bo-teal);
  color: var(--bo-teal-deep);
}

.sent-panel {
  text-align: center;
  padding: 2rem 1rem;
  border-radius: 20px;
  background: rgba(255, 255, 255, 0.7);
  border: 1px solid rgba(11, 110, 107, 0.12);
  animation: rise-in 0.45s ease both;
}

.sent-panel__ring {
  width: 84px;
  height: 84px;
  margin: 0 auto 1rem;
  border-radius: 50%;
  display: grid;
  place-items: center;
  background: rgba(11, 110, 107, 0.1);
  animation: pop 0.5s ease both;
}

.sent-panel__title {
  color: var(--bo-teal-deep);
  margin: 0 0 0.35rem;
}

@keyframes rise-in {
  from {
    opacity: 0;
    transform: translateY(8px);
  }
  to {
    opacity: 1;
    transform: none;
  }
}

@keyframes pop {
  0% {
    transform: scale(0.7);
    opacity: 0;
  }
  100% {
    transform: scale(1);
    opacity: 1;
  }
}

@media (min-width: 600px) {
  .reason-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}
</style>
