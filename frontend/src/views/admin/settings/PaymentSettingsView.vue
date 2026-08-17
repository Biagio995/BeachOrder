<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import SettingsPanel from '@/components/admin/SettingsPanel.vue'
import { useFlashSaved } from '@/composables/useFlashSaved'
import { useTenantSettings, type TenantSettingsPayload } from '@/composables/useTenantSettings'

const { t } = useI18n()
const { current, load, save, saving } = useTenantSettings()
const { saved, flashSaved } = useFlashSaved()

const form = ref({
  alias: '',
  secret_key: '',
  environment: 'test' as 'test' | 'production',
})

const environmentItems = computed(() => [
  { title: t('admin.nexiEnvironmentTest'), value: 'test' },
  { title: t('admin.nexiEnvironmentProduction'), value: 'production' },
])

const isConfigured = computed(() => form.value.alias.trim() !== '' && form.value.secret_key.trim() !== '')

async function hydrate() {
  const data = await load()
  form.value = {
    alias: data.settings.nexi.alias,
    secret_key: data.settings.nexi.secret_key,
    environment: data.settings.nexi.environment,
  }
}

async function onSave() {
  await save({
    settings: {
      nexi: {
        alias: form.value.alias.trim() || null,
        secret_key: form.value.secret_key.trim() || null,
        environment: form.value.environment,
      },
    },
  })
  flashSaved()
  await hydrate()
}

onMounted(hydrate)

function statusLabel(data: TenantSettingsPayload['settings']['nexi']) {
  if (!data.alias) return 'incomplete'
  return `${data.alias} · ${data.environment}`
}
</script>

<template>
  <SettingsPanel :saved="saved">
    <template #saved>{{ t('admin.saved') }}</template>
    <template #summary>
      <div class="text-body-2">
        Nexi XPay:
        <strong>{{ statusLabel(current.settings.nexi) }}</strong>
      </div>
      <div v-if="!current.settings.online_payments_enabled" class="text-caption text-warning mt-1">
        {{ t('admin.cardPaymentsDisabledHint') }}
      </div>
    </template>

    <p class="text-body-2 text-medium-emphasis mb-4">{{ t('admin.nexiPaymentsHint') }}</p>

    <v-row dense>
      <v-col cols="12" md="6">
        <v-text-field
          v-model="form.alias"
          :label="t('admin.nexiAlias')"
          :hint="t('admin.nexiAliasHint')"
          persistent-hint
        />
      </v-col>
      <v-col cols="12" md="6">
        <v-select
          v-model="form.environment"
          :items="environmentItems"
          item-title="title"
          item-value="value"
          :label="t('admin.nexiEnvironment')"
        />
      </v-col>
      <v-col cols="12">
        <v-text-field
          v-model="form.secret_key"
          :label="t('admin.nexiSecretKey')"
          :hint="t('admin.nexiSecretKeyHint')"
          persistent-hint
          type="password"
          autocomplete="new-password"
        />
      </v-col>
    </v-row>

    <v-alert v-if="!isConfigured" type="info" variant="tonal" class="mt-2">
      {{ t('admin.nexiIncomplete') }}
    </v-alert>

    <template #actions>
      <v-btn color="primary" :loading="saving" @click="onSave">{{ t('admin.save') }}</v-btn>
    </template>
  </SettingsPanel>
</template>
