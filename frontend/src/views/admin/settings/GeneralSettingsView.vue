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
  online_payments_enabled: true,
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
    online_payments_enabled: data.settings.online_payments_enabled,
  }
}

async function onSave() {
  await save({
    name: form.value.name,
    default_locale: form.value.default_locale,
    currency: form.value.currency,
    settings: {
      country: form.value.country,
      online_payments_enabled: form.value.online_payments_enabled,
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
        · {{ t('admin.onlinePayments') }}:
        {{ current.settings.online_payments_enabled ? 'on' : 'off' }}
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
      <v-col cols="12" md="6" class="d-flex align-center">
        <v-switch
          v-model="form.online_payments_enabled"
          :label="t('admin.onlinePayments')"
          color="primary"
          hide-details
        />
      </v-col>
      <v-col cols="12">
        <p class="text-body-2 text-medium-emphasis mb-0">
          {{ t('admin.nexiPaymentsHint') }}
          <router-link to="/admin/settings/payments">{{ t('admin.settingsPayments') }}</router-link>
        </p>
      </v-col>
    </v-row>

    <template #actions>
      <v-btn color="primary" :loading="saving" @click="onSave">{{ t('admin.save') }}</v-btn>
    </template>
  </SettingsPanel>
</template>
