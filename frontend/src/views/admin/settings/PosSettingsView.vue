<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import SettingsPanel from '@/components/admin/SettingsPanel.vue'
import { useFlashSaved } from '@/composables/useFlashSaved'
import { posProviderValues, useTenantSettings } from '@/composables/useTenantSettings'
import type { PosProvider } from '@/types'

const { t } = useI18n()
const { current, load, save, saving } = useTenantSettings()
const { saved, flashSaved } = useFlashSaved()

const form = ref({
  enabled: false,
  provider: null as PosProvider | null,
  terminal_id: '',
  credentials_ref: '',
})

const posProviders = computed(() =>
  posProviderValues.map((value) => ({
    title: t(`options.posProvider.${value}`),
    value,
  })),
)

async function hydrate() {
  const data = await load()
  form.value = { ...data.settings.pos }
}

async function onSave() {
  await save({
    settings: {
      pos: {
        enabled: form.value.enabled,
        provider: form.value.provider || null,
        terminal_id: form.value.terminal_id || null,
        credentials_ref: form.value.credentials_ref || null,
      },
    },
  })
  flashSaved()
  await hydrate()
}

onMounted(hydrate)
</script>

<template>
  <SettingsPanel :saved="saved">
    <template #saved>{{ t('admin.saved') }}</template>
    <template #summary>
      <div class="text-body-2">
        {{ t('admin.settingsPos') }}:
        <strong>{{ current.settings.pos.enabled ? (current.settings.pos.provider || 'on') : 'off' }}</strong>
        <span v-if="current.settings.pos.terminal_id" class="text-medium-emphasis">
          · {{ current.settings.pos.terminal_id }}
        </span>
      </div>
    </template>

    <v-row dense>
      <v-col cols="12" md="4" class="d-flex align-center">
        <v-switch v-model="form.enabled" :label="t('admin.enablePos')" color="primary" hide-details />
      </v-col>
      <v-col cols="12" md="4">
        <v-select
          v-model="form.provider"
          :items="posProviders"
          item-title="title"
          item-value="value"
          :label="t('admin.posProvider')"
          clearable
          :disabled="!form.enabled"
        />
      </v-col>
      <v-col cols="12" md="4">
        <v-text-field v-model="form.terminal_id" :label="t('admin.terminalId')" :disabled="!form.enabled" />
      </v-col>
      <v-col cols="12" md="6">
        <v-text-field
          v-model="form.credentials_ref"
          :label="t('admin.posCredentialsRef')"
          :disabled="!form.enabled"
        />
      </v-col>
    </v-row>

    <template #actions>
      <v-btn color="primary" :loading="saving" @click="onSave">{{ t('admin.save') }}</v-btn>
    </template>
  </SettingsPanel>
</template>
