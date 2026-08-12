<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import api, { getApiErrorMessage } from '@/api/client'
import SettingsPanel from '@/components/admin/SettingsPanel.vue'
import { useFlashSaved } from '@/composables/useFlashSaved'
import { printDriverValues, useTenantSettings } from '@/composables/useTenantSettings'
import { useUiStore } from '@/stores/ui'
import type { PrintDriver } from '@/types'

const { t } = useI18n()
const ui = useUiStore()
const { current, load, save, saving } = useTenantSettings()
const { saved, flashSaved } = useFlashSaved()

const form = ref({
  enabled: false,
  driver: 'escpos_tcp' as PrintDriver,
  credentials_ref: '',
  kitchen: { host: '', port: 9100, copies: 1 },
  bar: { host: '', port: 9100, copies: 1 },
})

const preview = ref('')
const previewLoading = ref(false)
const testLoading = ref(false)
const previewStation = ref<'kitchen' | 'bar'>('kitchen')

const printDrivers = computed(() =>
  printDriverValues.map((value) => ({
    title: t(`options.printDriver.${value}`),
    value,
  })),
)

const showCredentials = computed(() => form.value.driver === 'printnode')

async function hydrate() {
  const data = await load()
  form.value = {
    enabled: data.settings.printing.enabled,
    driver: data.settings.printing.driver,
    credentials_ref: data.settings.printing.credentials_ref || '',
    kitchen: { ...data.settings.printing.stations.kitchen },
    bar: { ...data.settings.printing.stations.bar },
  }
}

async function onSave() {
  await save({
    settings: {
      printing: {
        enabled: form.value.enabled,
        driver: form.value.driver,
        credentials_ref: form.value.credentials_ref || null,
        stations: {
          kitchen: {
            host: form.value.kitchen.host || null,
            port: Number(form.value.kitchen.port) || 9100,
            copies: Number(form.value.kitchen.copies) || 1,
          },
          bar: {
            host: form.value.bar.host || null,
            port: Number(form.value.bar.port) || 9100,
            copies: Number(form.value.bar.copies) || 1,
          },
        },
      },
    },
  })
  flashSaved()
  await hydrate()
}

async function loadPreview() {
  previewLoading.value = true
  try {
    const { data } = await api.get('/admin/settings/printing/preview', {
      params: { station: previewStation.value },
    })
    preview.value = data.preview
  } catch (e: unknown) {
    ui.error(getApiErrorMessage(e, t('common.error')))
  } finally {
    previewLoading.value = false
  }
}

async function sendTestPrint() {
  testLoading.value = true
  try {
    await api.post('/admin/settings/printing/test', { station: previewStation.value })
    ui.success(t('admin.testPrintSent'))
  } catch (e: unknown) {
    ui.error(getApiErrorMessage(e, t('admin.testPrintFailed')))
  } finally {
    testLoading.value = false
  }
}

onMounted(hydrate)
</script>

<template>
  <SettingsPanel :saved="saved">
    <template #saved>{{ t('admin.saved') }}</template>
    <template #summary>
      <div class="text-body-2">
        {{ t('admin.settingsPrinting') }}:
        <strong>{{ current.settings.printing.enabled ? current.settings.printing.driver : 'off' }}</strong>
      </div>
    </template>

    <v-row dense>
      <v-col cols="12" md="4" class="d-flex align-center">
        <v-switch v-model="form.enabled" :label="t('admin.enablePrinting')" color="primary" hide-details />
      </v-col>
      <v-col cols="12" md="4">
        <v-select
          v-model="form.driver"
          :items="printDrivers"
          item-title="title"
          item-value="value"
          :label="t('admin.printDriver')"
          :disabled="!form.enabled"
        />
      </v-col>
      <v-col v-if="showCredentials" cols="12" md="4">
        <v-text-field
          v-model="form.credentials_ref"
          :label="t('admin.printCredentialsRef')"
          :hint="t('admin.credentialsHint')"
          persistent-hint
          :disabled="!form.enabled"
        />
      </v-col>
      <v-col cols="12" md="6">
        <v-text-field
          v-model="form.kitchen.host"
          :label="t('admin.kitchenHost')"
          :disabled="!form.enabled"
        />
      </v-col>
      <v-col cols="6" md="3">
        <v-text-field
          v-model.number="form.kitchen.port"
          :label="t('admin.kitchenPort')"
          type="number"
          :disabled="!form.enabled"
        />
      </v-col>
      <v-col cols="6" md="3">
        <v-text-field
          v-model.number="form.kitchen.copies"
          :label="t('admin.kitchenCopies')"
          type="number"
          min="1"
          max="5"
          :disabled="!form.enabled"
        />
      </v-col>
      <v-col cols="12" md="6">
        <v-text-field v-model="form.bar.host" :label="t('admin.barHost')" :disabled="!form.enabled" />
      </v-col>
      <v-col cols="6" md="3">
        <v-text-field
          v-model.number="form.bar.port"
          :label="t('admin.barPort')"
          type="number"
          :disabled="!form.enabled"
        />
      </v-col>
      <v-col cols="6" md="3">
        <v-text-field
          v-model.number="form.bar.copies"
          :label="t('admin.barCopies')"
          type="number"
          min="1"
          max="5"
          :disabled="!form.enabled"
        />
      </v-col>
    </v-row>

    <v-divider class="my-4" />

    <div class="d-flex flex-wrap align-center ga-3 mb-3">
      <v-select
        v-model="previewStation"
        :items="[
          { title: t('admin.kitchenHost'), value: 'kitchen' },
          { title: t('admin.barHost'), value: 'bar' },
        ]"
        item-title="title"
        item-value="value"
        :label="t('admin.printPreviewStation')"
        density="compact"
        style="max-width: 220px"
        hide-details
      />
      <v-btn variant="outlined" :loading="previewLoading" @click="loadPreview">
        {{ t('admin.printPreview') }}
      </v-btn>
      <v-btn
        color="primary"
        variant="tonal"
        :loading="testLoading"
        :disabled="!form.enabled"
        @click="sendTestPrint"
      >
        {{ t('admin.testPrint') }}
      </v-btn>
    </div>

    <v-sheet v-if="preview" class="pa-4 print-preview" rounded="lg" border>
      <pre class="print-preview__text">{{ preview }}</pre>
    </v-sheet>

    <template #actions>
      <v-btn color="primary" :loading="saving" @click="onSave">{{ t('admin.save') }}</v-btn>
    </template>
  </SettingsPanel>
</template>

<style scoped>
.print-preview {
  background: rgba(255, 255, 255, 0.85);
}

.print-preview__text {
  margin: 0;
  font-family: ui-monospace, 'Cascadia Code', monospace;
  font-size: 0.82rem;
  line-height: 1.45;
  white-space: pre-wrap;
}
</style>
