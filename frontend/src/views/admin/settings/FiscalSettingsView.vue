<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import SettingsPanel from '@/components/admin/SettingsPanel.vue'
import { useFlashSaved } from '@/composables/useFlashSaved'
import {
  fiscalModeValues,
  fiscalProviderValues,
  useTenantSettings,
  type TenantSettingsPayload,
} from '@/composables/useTenantSettings'
import type { FiscalMode, FiscalProvider } from '@/types'

const { t } = useI18n()
const { current, load, save, saving } = useTenantSettings()
const { saved, flashSaved } = useFlashSaved()

const form = ref({
  enabled: false,
  provider: null as FiscalProvider | null,
  mode: 'local_bridge' as FiscalMode,
  device_id: '',
  credentials_ref: '',
  auto_fiscalize_on_pay: true,
})

const fiscalProviders = computed(() =>
  fiscalProviderValues.map((value) => ({
    title: t(`options.fiscalProvider.${value}`),
    value,
  })),
)

const fiscalModes = computed(() =>
  fiscalModeValues.map((value) => ({
    title: t(`options.fiscalMode.${value}`),
    value,
  })),
)

async function hydrate() {
  const data = await load()
  form.value = { ...data.settings.fiscal }
}

async function onSave() {
  await save({
    settings: {
      fiscal: {
        enabled: form.value.enabled,
        provider: form.value.provider || null,
        mode: form.value.mode,
        device_id: form.value.device_id || null,
        credentials_ref: form.value.credentials_ref || null,
        auto_fiscalize_on_pay: form.value.auto_fiscalize_on_pay,
      },
    },
  })
  flashSaved()
  await hydrate()
}

onMounted(hydrate)

function statusLabel(data: TenantSettingsPayload['settings']['fiscal']) {
  if (!data.enabled) return 'off'
  return data.provider || 'on'
}
</script>

<template>
  <SettingsPanel :saved="saved">
    <template #saved>{{ t('admin.saved') }}</template>
    <template #summary>
      <div class="text-body-2">
        {{ t('admin.settingsFiscal') }}:
        <strong>{{ statusLabel(current.settings.fiscal) }}</strong>
        <span v-if="current.settings.fiscal.enabled" class="text-medium-emphasis">
          · {{ current.settings.fiscal.mode }}
          <template v-if="current.settings.fiscal.device_id">
            · {{ current.settings.fiscal.device_id }}
          </template>
        </span>
      </div>
    </template>

    <v-row dense>
      <v-col cols="12" md="4" class="d-flex align-center">
        <v-switch v-model="form.enabled" :label="t('admin.enableFiscal')" color="primary" hide-details />
      </v-col>
      <v-col cols="12" md="4">
        <v-select
          v-model="form.provider"
          :items="fiscalProviders"
          item-title="title"
          item-value="value"
          :label="t('admin.provider')"
          clearable
          :disabled="!form.enabled"
        />
      </v-col>
      <v-col cols="12" md="4">
        <v-select
          v-model="form.mode"
          :items="fiscalModes"
          item-title="title"
          item-value="value"
          :label="t('admin.fiscalMode')"
          :disabled="!form.enabled"
        />
      </v-col>
      <v-col cols="12" md="4">
        <v-text-field v-model="form.device_id" :label="t('admin.deviceId')" :disabled="!form.enabled" />
      </v-col>
      <v-col cols="12" md="4">
        <v-text-field
          v-model="form.credentials_ref"
          :label="t('admin.credentialsRef')"
          :hint="t('admin.credentialsHint')"
          persistent-hint
          :disabled="!form.enabled"
        />
      </v-col>
      <v-col cols="12" md="4" class="d-flex align-center">
        <v-switch
          v-model="form.auto_fiscalize_on_pay"
          :label="t('admin.autoFiscalize')"
          color="primary"
          hide-details
          :disabled="!form.enabled"
        />
      </v-col>
    </v-row>

    <template #actions>
      <v-btn color="primary" :loading="saving" @click="onSave">{{ t('admin.save') }}</v-btn>
    </template>
  </SettingsPanel>
</template>
