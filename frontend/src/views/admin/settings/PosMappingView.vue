<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import SettingsPanel from '@/components/admin/SettingsPanel.vue'
import {
  posMappingEntityValues,
  usePosIntegration,
  type PosMapping,
  type PosMappingEntityOption,
  type PosMappingEntityType,
} from '@/composables/usePosIntegration'
import { displayLocalizedName } from '@/utils/localeName'

const { t, locale } = useI18n()
const { loadMappings, loadMappingEntities, saveMapping, deleteMapping } = usePosIntegration()

const mappings = ref<PosMapping[]>([])
const entityOptionsForType = ref<PosMappingEntityOption[]>([])
const loading = ref(false)
const loadingEntities = ref(false)
const saving = ref(false)
const entityType = ref<PosMappingEntityType>('product')

const dialog = ref(false)
const form = ref({
  local_id: null as number | null,
  external_id: '',
  external_sku: '',
})

const entityTypeOptions = computed(() =>
  posMappingEntityValues.map((value) => ({
    title: t(`admin.posEntityType.${value}`),
    value,
  })),
)

const hasEntityCatalog = computed(() =>
  ['product', 'variant_option', 'addon', 'category'].includes(entityType.value),
)

const localEntitySelectItems = computed(() =>
  entityOptionsForType.value.map((entry) => ({
    title: entityOptionTitle(entry),
    value: entry.id,
  })),
)

const filteredMappings = computed(() =>
  mappings.value.filter((m) => m.entity_type === entityType.value),
)

function entityOptionTitle(entry: PosMappingEntityOption): string {
  const name = displayLocalizedName(entry.name, String(locale.value))
  if (name) return `${name} (#${entry.id})`
  return entry.label || `#${entry.id}`
}

async function loadEntityCatalog() {
  if (!hasEntityCatalog.value) {
    entityOptionsForType.value = []
    return
  }

  loadingEntities.value = true
  try {
    entityOptionsForType.value = await loadMappingEntities(entityType.value, String(locale.value))
  } finally {
    loadingEntities.value = false
  }
}

async function hydrate() {
  loading.value = true
  try {
    const mappingRes = await loadMappings()
    mappings.value = mappingRes.data ?? mappingRes
    await loadEntityCatalog()
  } finally {
    loading.value = false
  }
}

function openCreate() {
  form.value = { local_id: null, external_id: '', external_sku: '' }
  dialog.value = true
}

function openEdit(row: PosMapping) {
  form.value = {
    local_id: row.local_id,
    external_id: row.external_id,
    external_sku: row.external_sku || '',
  }
  dialog.value = true
}

async function onSave() {
  if (!form.value.local_id || !form.value.external_id) return
  saving.value = true
  try {
    await saveMapping({
      entity_type: entityType.value,
      local_id: form.value.local_id,
      external_id: form.value.external_id,
      external_sku: form.value.external_sku || null,
    })
    dialog.value = false
    await hydrate()
  } finally {
    saving.value = false
  }
}

async function onDelete(row: PosMapping) {
  await deleteMapping(row.id)
  await hydrate()
}

function localLabel(row: PosMapping) {
  const name = displayLocalizedName(row.local_name, String(locale.value))
  if (name) return `${name} (#${row.local_id})`
  return row.local_label || `#${row.local_id}`
}

watch(entityType, () => {
  void loadEntityCatalog()
})

watch(locale, () => {
  void hydrate()
})

onMounted(hydrate)
</script>

<template>
  <SettingsPanel>
    <template #summary>
      <div class="text-body-2">
        {{ t('admin.posMappingTitle') }}
        <span class="text-medium-emphasis"> · {{ filteredMappings.length }} {{ t('admin.posMappingsCount') }}</span>
      </div>
    </template>

    <v-row dense class="mb-4">
      <v-col cols="12" md="4">
        <v-select
          v-model="entityType"
          :items="entityTypeOptions"
          item-title="title"
          item-value="value"
          :label="t('admin.posEntityTypeLabel')"
        />
      </v-col>
      <v-col cols="12" md="8" class="d-flex justify-end align-center">
        <v-btn color="primary" @click="openCreate">{{ t('admin.posAddMapping') }}</v-btn>
      </v-col>
    </v-row>

    <v-table density="comfortable">
      <thead>
        <tr>
          <th>{{ t('admin.posLocalEntity') }}</th>
          <th>{{ t('admin.posExternalId') }}</th>
          <th>{{ t('admin.posExternalSku') }}</th>
          <th />
        </tr>
      </thead>
      <tbody>
        <tr v-if="loading">
          <td colspan="4">{{ t('common.loading') }}</td>
        </tr>
        <tr v-else-if="filteredMappings.length === 0">
          <td colspan="4" class="text-medium-emphasis">{{ t('admin.posMappingsEmpty') }}</td>
        </tr>
        <tr v-for="row in filteredMappings" :key="row.id">
          <td>{{ localLabel(row) }}</td>
          <td><code>{{ row.external_id }}</code></td>
          <td>{{ row.external_sku || '—' }}</td>
          <td class="text-right">
            <v-btn size="small" variant="text" @click="openEdit(row)">{{ t('admin.edit') }}</v-btn>
            <v-btn size="small" variant="text" color="error" @click="onDelete(row)">{{ t('admin.delete') }}</v-btn>
          </td>
        </tr>
      </tbody>
    </v-table>
  </SettingsPanel>

  <v-dialog v-model="dialog" max-width="520">
    <v-card>
      <v-card-title>{{ t('admin.posAddMapping') }}</v-card-title>
      <v-card-text>
        <v-select
          v-if="hasEntityCatalog"
          v-model="form.local_id"
          :items="localEntitySelectItems"
          item-title="title"
          item-value="value"
          :label="t(`admin.posEntityType.${entityType}`)"
          :loading="loadingEntities"
        />
        <v-text-field
          v-else
          v-model.number="form.local_id"
          type="number"
          :label="t('admin.posLocalId')"
        />
        <v-text-field v-model="form.external_id" :label="t('admin.posExternalId')" />
        <v-text-field v-model="form.external_sku" :label="t('admin.posExternalSku')" />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="dialog = false">{{ t('common.cancel') }}</v-btn>
        <v-btn color="primary" :loading="saving" @click="onSave">{{ t('admin.save') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
