<script setup lang="ts">
import { useI18n } from 'vue-i18n'

defineProps<{
  tenantName?: string | null
  tagline?: string | null
}>()

const { t } = useI18n()
</script>

<template>
  <div class="service-unavailable page-shell">
    <div class="service-unavailable__card">
      <v-icon size="56" color="primary" class="mb-4">mdi-store-off-outline</v-icon>
      <p v-if="tagline" class="text-medium-emphasis text-body-2 mb-1">{{ tagline }}</p>
      <h1 v-if="tenantName" class="display-font text-h5 mb-2" style="color: var(--bo-teal-deep)">
        {{ tenantName }}
      </h1>
      <h2 class="text-h6 font-weight-medium mb-2">{{ t('tenant.serviceUnavailableTitle') }}</h2>
      <p class="text-body-2 text-medium-emphasis mb-0">{{ t('tenant.serviceUnavailableMessage') }}</p>
    </div>
  </div>
</template>

<style scoped>
.service-unavailable {
  min-height: 60vh;
  display: grid;
  place-items: center;
}

.service-unavailable__card {
  max-width: 420px;
  text-align: center;
  padding: 2rem 1.5rem;
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.75);
  border: 1px solid rgba(11, 110, 107, 0.12);
}
</style>
