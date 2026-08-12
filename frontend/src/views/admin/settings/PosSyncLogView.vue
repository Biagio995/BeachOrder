<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import SettingsPanel from '@/components/admin/SettingsPanel.vue'
import { usePosIntegration, type PosOrderSync } from '@/composables/usePosIntegration'

const { t } = useI18n()
const { loadSyncs, retrySync } = usePosIntegration()

const syncs = ref<PosOrderSync[]>([])
const loading = ref(false)
const retryingId = ref<number | null>(null)

async function hydrate() {
  loading.value = true
  try {
    const data = await loadSyncs()
    syncs.value = data.data ?? data
  } finally {
    loading.value = false
  }
}

async function onRetry(row: PosOrderSync) {
  retryingId.value = row.id
  try {
    await retrySync(row.id)
    await hydrate()
  } finally {
    retryingId.value = null
  }
}

function statusColor(status: string) {
  if (status === 'synced') return 'success'
  if (status === 'failed') return 'error'
  if (status === 'retrying' || status === 'processing') return 'warning'
  return 'default'
}

onMounted(hydrate)
</script>

<template>
  <SettingsPanel>
    <template #summary>
      <div class="text-body-2">{{ t('admin.posSyncLogTitle') }}</div>
    </template>

    <v-table density="comfortable">
      <thead>
        <tr>
          <th>{{ t('order.number') }}</th>
          <th>{{ t('admin.posSyncStatus') }}</th>
          <th>{{ t('admin.posExternalOrderId') }}</th>
          <th>{{ t('admin.posLastError') }}</th>
          <th />
        </tr>
      </thead>
      <tbody>
        <tr v-if="loading">
          <td colspan="5">{{ t('common.loading') }}</td>
        </tr>
        <tr v-else-if="syncs.length === 0">
          <td colspan="5" class="text-medium-emphasis">{{ t('admin.posSyncsEmpty') }}</td>
        </tr>
        <tr v-for="row in syncs" :key="row.id">
          <td>
            <div>{{ row.order?.order_number || `#${row.order_id}` }}</div>
            <div v-if="row.order?.location" class="text-caption text-medium-emphasis">
              {{ row.order.location.name }}
            </div>
          </td>
          <td>
            <v-chip size="small" :color="statusColor(row.sync_status)" variant="tonal">
              {{ t(`admin.posSyncStatusValue.${row.sync_status}`) }}
            </v-chip>
          </td>
          <td>{{ row.external_order_id || '—' }}</td>
          <td class="text-error text-body-2">{{ row.last_error || '—' }}</td>
          <td class="text-right">
            <v-btn
              v-if="row.sync_status !== 'synced'"
              size="small"
              variant="text"
              :loading="retryingId === row.id"
              @click="onRetry(row)"
            >
              {{ t('admin.posRetrySync') }}
            </v-btn>
          </td>
        </tr>
      </tbody>
    </v-table>
  </SettingsPanel>
</template>
