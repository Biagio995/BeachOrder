<script setup lang="ts">
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import SettingsPanel from '@/components/admin/SettingsPanel.vue'
import { useTenantSettings } from '@/composables/useTenantSettings'

const { t } = useI18n()
const { current, load } = useTenantSettings()

onMounted(() => load())
</script>

<template>
  <SettingsPanel>
    <template #summary>
      <div class="text-body-2">
        {{ t('cart.payment.card_online') }}:
        <strong>
          {{ current.settings.online_payments_enabled ? 'on' : 'off' }}
        </strong>
      </div>
    </template>

    <p class="text-body-2 mb-3">{{ t('admin.cardPosHint') }}</p>
    <p class="text-body-2 text-medium-emphasis mb-0">
      {{ t('admin.cardPosHow') }}
      <router-link to="/admin/settings/general">{{ t('admin.settingsGeneral') }}</router-link>.
    </p>
  </SettingsPanel>
</template>
