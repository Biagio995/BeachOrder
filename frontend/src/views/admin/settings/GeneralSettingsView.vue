<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import SettingsPanel from '@/components/admin/SettingsPanel.vue'
import { countryValues, useTenantSettings } from '@/composables/useTenantSettings'
import { useFlashSaved } from '@/composables/useFlashSaved'

const { t } = useI18n()
const { current, load, save, saving } = useTenantSettings()
const { saved, flashSaved } = useFlashSaved()

const form = ref({
  name: '',
  default_locale: 'el',
  currency: 'EUR',
  country: 'IT',
})

const countryItems = computed(() =>
  countryValues.map((value) => ({
    title: t(`options.country.${value}`),
    value,
  })),
)

const localeItems = ['el', 'en', 'it', 'de']

async function hydrate() {
  const data = await load()
  form.value = {
    name: data.name,
    default_locale: data.default_locale,
    currency: data.currency,
    country: data.settings.country,
  }
}

async function onSave() {
  await save({
    name: form.value.name,
    default_locale: form.value.default_locale,
    currency: form.value.currency,
    settings: {
      country: form.value.country,
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
      <div class="text-h6 mb-1">{{ current.name || '—' }}</div>
      <div class="text-body-2 text-medium-emphasis">
        {{ current.settings.country }} · {{ current.currency }} · {{ current.default_locale }}
      </div>
    </template>

    <v-row dense>
      <v-col cols="12" md="6">
        <v-text-field v-model="form.name" :label="t('admin.venueName')" />
      </v-col>
      <v-col cols="12" md="6">
        <v-select
          v-model="form.country"
          :items="countryItems"
          item-title="title"
          item-value="value"
          :label="t('admin.fiscalCountry')"
        />
      </v-col>
      <v-col cols="6" md="3">
        <v-text-field v-model="form.currency" :label="t('admin.currency')" />
      </v-col>
      <v-col cols="6" md="3">
        <v-select
          v-model="form.default_locale"
          :items="localeItems"
          :label="t('admin.defaultLocale')"
        />
      </v-col>
    </v-row>

    <template #actions>
      <v-btn color="primary" :loading="saving" @click="onSave">{{ t('admin.save') }}</v-btn>
    </template>
  </SettingsPanel>
</template>
