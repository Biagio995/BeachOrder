<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import ChipNav from '@/components/admin/ChipNav.vue'
import { useTenantSettings } from '@/composables/useTenantSettings'

const { t } = useI18n()
const { load, loading } = useTenantSettings()

const links = computed(() => [
  { to: '/admin/settings/subscription', label: t('admin.settingsSubscription') },
  { to: '/admin/settings/general', label: t('admin.settingsGeneral') },
  { to: '/admin/settings/payments', label: t('admin.settingsPayments') },
  { to: '/admin/settings/appearance', label: t('admin.settingsAppearance') },
  { to: '/admin/settings/fiscal', label: t('admin.settingsFiscal') },
  { to: '/admin/settings/pos', label: t('admin.settingsPos') },
  { to: '/admin/settings/pos-integration', label: t('admin.settingsPosIntegration') },
  { to: '/admin/settings/pos-mappings', label: t('admin.posMappingTitle') },
  { to: '/admin/settings/pos-syncs', label: t('admin.posSyncLogTitle') },
  { to: '/admin/settings/printing', label: t('admin.settingsPrinting') },
  { to: '/admin/settings/privacy', label: t('legal.privacySettings') },
])

onMounted(() => load())
</script>

<template>
  <div>
    <div class="d-flex justify-space-between align-center flex-wrap ga-2 mb-4">
      <div>
        <h1 class="display-font text-h4 mb-1" style="color: var(--bo-teal-deep)">
          {{ t('admin.settings') }}
        </h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          {{ t('admin.settingsHint') }}
        </p>
      </div>
    </div>

    <ChipNav :links="links" />

    <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-4" />
    <router-view />
  </div>
</template>
