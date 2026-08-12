<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import api from '@/api/client'
import axios from 'axios'

const { t } = useI18n()
const auth = useAuthStore()
const ui = useUiStore()
const router = useRouter()

const inventory = ref<Record<string, unknown> | null>(null)
const loadingInventory = ref(false)
const exporting = ref(false)
const deleting = ref(false)
const deletePassword = ref('')
const deleteConfirm = ref(false)
const showDeleteDialog = ref(false)

onMounted(async () => {
  if (auth.can(auth.PERMISSIONS.SETTINGS_MANAGE)) {
    loadingInventory.value = true
    try {
      const { data } = await api.get('/admin/privacy/inventory')
      inventory.value = data
    } catch {
      // non-admin or error — inventory section hidden
    } finally {
      loadingInventory.value = false
    }
  }
})

async function exportData() {
  exporting.value = true
  try {
    const { data } = await api.get('/me/export')
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `beachorder-export-${Date.now()}.json`
    a.click()
    URL.revokeObjectURL(url)
    ui.success(t('legal.exportSuccess'))
  } catch {
    ui.error(t('common.error'))
  } finally {
    exporting.value = false
  }
}

async function deleteAccount() {
  deleting.value = true
  try {
    await api.delete('/me/account', {
      data: { password: deletePassword.value, confirm: true },
    })
    await auth.logout()
    ui.success(t('legal.deleteSuccess'))
    router.push('/')
  } catch (e: unknown) {
    const msg =
      axios.isAxiosError(e) && e.response?.data?.message
        ? String(e.response.data.message)
        : t('common.error')
    ui.error(msg)
  } finally {
    deleting.value = false
    showDeleteDialog.value = false
  }
}
</script>

<template>
  <div>
    <h2 class="text-h6 mb-1" style="color: var(--bo-teal-deep)">{{ t('legal.privacySettings') }}</h2>
    <p class="text-body-2 text-medium-emphasis mb-4">{{ t('legal.privacySettingsHint') }}</p>

    <v-card variant="outlined" class="mb-4">
      <v-card-title class="text-subtitle-1">{{ t('legal.yourData') }}</v-card-title>
      <v-card-text>
        <p class="text-body-2 mb-3">{{ t('legal.exportHint') }}</p>
        <v-btn color="primary" variant="tonal" :loading="exporting" @click="exportData">
          {{ t('legal.exportData') }}
        </v-btn>
      </v-card-text>
    </v-card>

    <v-card variant="outlined" class="mb-4">
      <v-card-title class="text-subtitle-1">{{ t('legal.deleteAccount') }}</v-card-title>
      <v-card-text>
        <p class="text-body-2 mb-3">{{ t('legal.deleteHint') }}</p>
        <v-btn color="error" variant="outlined" @click="showDeleteDialog = true">
          {{ t('legal.deleteAccount') }}
        </v-btn>
      </v-card-text>
    </v-card>

    <v-card v-if="inventory" variant="outlined" class="mb-4">
      <v-card-title class="text-subtitle-1">{{ t('legal.dataInventory') }}</v-card-title>
      <v-card-text>
        <p class="text-body-2 mb-3">{{ t('legal.dataInventoryHint') }}</p>
        <v-progress-linear v-if="loadingInventory" indeterminate color="primary" />
        <v-table v-else density="compact">
          <thead>
            <tr>
              <th>{{ t('legal.category') }}</th>
              <th>{{ t('legal.legalBasis') }}</th>
              <th>{{ t('legal.tables') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="item in (inventory.inventory as Array<Record<string, unknown>>)"
              :key="String(item.category)"
            >
              <td>{{ item.category }}</td>
              <td>{{ item.legal_basis }}</td>
              <td>{{ (item.tables as string[])?.join(', ') }}</td>
            </tr>
          </tbody>
        </v-table>
        <div class="mt-3">
          <v-btn to="/dpa" variant="text" size="small">{{ t('legal.dpaTitle') }}</v-btn>
          <v-btn to="/data-processing-roles" variant="text" size="small">{{ t('legal.rolesTitle') }}</v-btn>
        </div>
      </v-card-text>
    </v-card>

    <v-dialog v-model="showDeleteDialog" max-width="440">
      <v-card>
        <v-card-title>{{ t('legal.deleteAccount') }}</v-card-title>
        <v-card-text>
          <p class="text-body-2 mb-3">{{ t('legal.deleteConfirm') }}</p>
          <v-text-field
            v-model="deletePassword"
            :label="t('auth.password')"
            type="password"
            autocomplete="current-password"
          />
          <v-checkbox v-model="deleteConfirm" :label="t('legal.deleteConfirmCheckbox')" />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showDeleteDialog = false">{{ t('common.cancel') }}</v-btn>
          <v-btn
            color="error"
            variant="flat"
            :loading="deleting"
            :disabled="!deletePassword || !deleteConfirm"
            @click="deleteAccount"
          >
            {{ t('legal.deleteAccount') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
