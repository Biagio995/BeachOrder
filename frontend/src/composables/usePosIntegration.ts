import { ref } from 'vue'
import api, { getApiErrorMessage } from '@/api/client'

export type PosIntegrationProvider = 'epsilon_pylon' | 'softone' | 'custom'
export type PosConnectionStatus = 'disconnected' | 'connected' | 'error'
export type PosSyncStatus = 'pending' | 'processing' | 'synced' | 'failed' | 'retrying'
export type PosMappingEntityType = 'product' | 'variant_option' | 'addon' | 'category' | 'tax' | 'modifier'

export interface PosIntegration {
  id: number
  provider: PosIntegrationProvider
  api_endpoint: string | null
  store_location_id: string | null
  extra_params: Record<string, unknown>
  is_enabled: boolean
  connection_status: PosConnectionStatus
  last_sync_at: string | null
  last_connection_test_at: string | null
  last_connection_error: string | null
  has_credentials: boolean
  credentials: {
    api_key?: string
    username?: string
    password?: string
    stub_mode?: boolean
  }
  webhook_url: string
  providers: PosIntegrationProvider[]
}

export interface PosMapping {
  id: number
  entity_type: PosMappingEntityType
  local_id: number
  external_id: string
  external_sku?: string | null
  metadata?: Record<string, unknown> | null
  local_label?: string
  local_name?: Record<string, string> | null
}

export interface PosMappingEntityOption {
  id: number
  label: string
  name?: Record<string, string> | null
}

export interface PosOrderSync {
  id: number
  order_id: number
  idempotency_key: string
  external_order_id: string | null
  sync_status: PosSyncStatus
  retry_count: number
  max_retries: number
  last_error: string | null
  synced_at: string | null
  created_at?: string
  order?: {
    id: number
    order_number: string
    status: string
    total: number
    location?: { id: number; name: string; code: string }
  }
  attempts?: Array<{
    id: number
    attempt_number: number
    status: string
    error_message: string | null
    attempted_at: string
  }>
}

const integration = ref<PosIntegration | null>(null)
const loading = ref(false)
const saving = ref(false)
const testing = ref(false)

export function usePosIntegration() {
  async function load() {
    loading.value = true
    try {
      const { data } = await api.get<PosIntegration>('/admin/pos-integration')
      integration.value = data
      return data
    } finally {
      loading.value = false
    }
  }

  async function save(payload: Record<string, unknown>) {
    saving.value = true
    try {
      const { data } = await api.put<PosIntegration>('/admin/pos-integration', payload)
      integration.value = data
      return data
    } finally {
      saving.value = false
    }
  }

  async function testConnection() {
    testing.value = true
    try {
      const { data } = await api.post<PosIntegration>('/admin/pos-integration/test')
      integration.value = data
      return data
    } finally {
      testing.value = false
    }
  }

  async function loadMappings(entityType?: PosMappingEntityType) {
    const { data } = await api.get('/admin/pos-mappings', {
      params: entityType ? { entity_type: entityType } : undefined,
    })
    return data
  }

  async function loadMappingEntities(entityType: PosMappingEntityType, locale?: string) {
    const { data } = await api.get<{ data: PosMappingEntityOption[] }>('/admin/pos-mappings/entities', {
      params: {
        entity_type: entityType,
        locale: locale || localStorage.getItem('bo_locale') || undefined,
      },
    })
    return data.data ?? []
  }

  async function saveMapping(mapping: Omit<PosMapping, 'id'>) {
    const { data } = await api.post<PosMapping>('/admin/pos-mappings', mapping)
    return data
  }

  async function deleteMapping(id: number) {
    await api.delete(`/admin/pos-mappings/${id}`)
  }

  async function loadSyncs(params?: { sync_status?: string; order_id?: number }) {
    const { data } = await api.get('/admin/pos-syncs', { params })
    return data
  }

  async function retrySync(id: number) {
    const { data } = await api.post<PosOrderSync>(`/admin/pos-syncs/${id}/retry`)
    return data
  }

  return {
    integration,
    loading,
    saving,
    testing,
    load,
    save,
    testConnection,
    loadMappings,
    loadMappingEntities,
    saveMapping,
    deleteMapping,
    loadSyncs,
    retrySync,
    getApiErrorMessage,
  }
}

export const posIntegrationProviderValues = ['epsilon_pylon', 'softone', 'custom'] as const
export const posMappingEntityValues = ['product', 'variant_option', 'addon', 'category', 'tax', 'modifier'] as const
