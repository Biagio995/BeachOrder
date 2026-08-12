<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import SettingsPanel from '@/components/admin/SettingsPanel.vue'
import { useFlashSaved } from '@/composables/useFlashSaved'
import {
  posIntegrationProviderValues,
  usePosIntegration,
  type PosIntegrationProvider,
} from '@/composables/usePosIntegration'

const { t } = useI18n()
const { integration, loading, saving, testing, load, save, testConnection } = usePosIntegration()
const { saved, flashSaved } = useFlashSaved()

const form = ref({
  is_enabled: false,
  provider: 'epsilon_pylon' as PosIntegrationProvider,
  api_endpoint: '',
  store_location_id: '',
  credentials: {
    api_key: '',
    username: '',
    password: '',
    stub_mode: true,
  },
})

const providers = computed(() =>
  posIntegrationProviderValues.map((value) => ({
    title: t(`options.posIntegrationProvider.${value}`),
    value,
  })),
)

const statusIcon = computed(() => {
  const status = integration.value?.connection_status
  if (status === 'connected') return '🟢'
  if (status === 'error') return '🔴'
  return '⚪'
})

const statusLabel = computed(() => {
  const status = integration.value?.connection_status ?? 'disconnected'
  return t(`admin.posConnectionStatus.${status}`)
})

const lastSyncLabel = computed(() => {
  const at = integration.value?.last_sync_at
  if (!at) return t('admin.posNeverSynced')
  return new Date(at).toLocaleString()
})

async function hydrate() {
  const data = await load()
  form.value = {
    is_enabled: data.is_enabled,
    provider: data.provider,
    api_endpoint: data.api_endpoint || '',
    store_location_id: data.store_location_id || '',
    credentials: {
      api_key: '',
      username: data.credentials?.username || '',
      password: '',
      stub_mode: data.credentials?.stub_mode ?? !data.api_endpoint,
    },
  }
}

async function onSave() {
  await save({
    is_enabled: form.value.is_enabled,
    provider: form.value.provider,
    api_endpoint: form.value.api_endpoint || null,
    store_location_id: form.value.store_location_id || null,
    credentials: {
      api_key: form.value.credentials.api_key || undefined,
      username: form.value.credentials.username || undefined,
      password: form.value.credentials.password || undefined,
      stub_mode: form.value.credentials.stub_mode,
    },
  })
  flashSaved()
  await hydrate()
}

async function onTest() {
  await onSave()
  await testConnection()
  await hydrate()
}

onMounted(hydrate)
</script>

<template>
  <SettingsPanel :saved="saved">
    <template #saved>{{ t('admin.saved') }}</template>
    <template #summary>
      <div class="text-body-2">
        {{ t('admin.settingsPosIntegration') }}:
        <strong>{{ statusIcon }} {{ statusLabel }}</strong>
        <span v-if="integration?.provider" class="text-medium-emphasis">
          · {{ t(`options.posIntegrationProvider.${integration.provider}`) }}
        </span>
        <span class="text-medium-emphasis"> · {{ t('admin.posLastSync') }}: {{ lastSyncLabel }}</span>
      </div>
    </template>

    <v-alert type="info" variant="tonal" class="mb-4" density="compact">
      {{ t('admin.posIntegrationHint') }}
    </v-alert>

    <v-row dense>
      <v-col cols="12" md="4" class="d-flex align-center">
        <v-switch v-model="form.is_enabled" :label="t('admin.enablePosIntegration')" color="primary" hide-details />
      </v-col>
      <v-col cols="12" md="4">
        <v-select
          v-model="form.provider"
          :items="providers"
          item-title="title"
          item-value="value"
          :label="t('admin.posIntegrationProvider')"
          :disabled="!form.is_enabled"
        />
      </v-col>
      <v-col cols="12" md="4">
        <v-text-field
          v-model="form.store_location_id"
          :label="t('admin.posStoreLocationId')"
          :disabled="!form.is_enabled"
        />
      </v-col>
      <v-col cols="12" md="8">
        <v-text-field
          v-model="form.api_endpoint"
          :label="t('admin.posApiEndpoint')"
          :hint="t('admin.posApiEndpointHint')"
          persistent-hint
          :disabled="!form.is_enabled"
        />
      </v-col>
      <v-col cols="12" md="4" class="d-flex align-center">
        <v-switch
          v-model="form.credentials.stub_mode"
          :label="t('admin.posStubMode')"
          color="primary"
          hide-details
          :disabled="!form.is_enabled"
        />
      </v-col>
      <v-col cols="12" md="4">
        <v-text-field
          v-model="form.credentials.api_key"
          :label="t('admin.posApiKey')"
          type="password"
          :placeholder="integration?.has_credentials ? '••••••••' : ''"
          :disabled="!form.is_enabled"
        />
      </v-col>
      <v-col cols="12" md="4">
        <v-text-field
          v-model="form.credentials.username"
          :label="t('admin.posUsername')"
          :disabled="!form.is_enabled"
        />
      </v-col>
      <v-col cols="12" md="4">
        <v-text-field
          v-model="form.credentials.password"
          :label="t('admin.posPassword')"
          type="password"
          :disabled="!form.is_enabled"
        />
      </v-col>
      <v-col v-if="integration?.webhook_url" cols="12">
        <v-text-field
          :model-value="integration.webhook_url"
          :label="t('admin.posWebhookUrl')"
          readonly
          :hint="t('admin.posWebhookHint')"
          persistent-hint
        />
      </v-col>
      <v-col v-if="integration?.last_connection_error" cols="12">
        <v-alert type="error" variant="tonal" density="compact">
          {{ integration.last_connection_error }}
        </v-alert>
      </v-col>
    </v-row>

    <template #actions>
      <v-btn variant="outlined" :loading="testing" :disabled="saving" @click="onTest">
        {{ t('admin.posTestConnection') }}
      </v-btn>
      <v-btn color="primary" :loading="saving" @click="onSave">{{ t('admin.save') }}</v-btn>
    </template>
  </SettingsPanel>

  <v-progress-linear v-if="loading" indeterminate color="primary" class="mt-4" />
</template>
