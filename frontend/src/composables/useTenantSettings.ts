import { computed, ref, toRaw } from 'vue'
import api from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useMenuStore } from '@/stores/menu'
import { resolvePublicAssetUrl } from '@/utils/publicAssetUrl'
import type {
  FiscalMode,
  FiscalProvider,
  PosProvider,
  PrintDriver,
  TenantSettings,
} from '@/types'

export type TenantSettingsPayload = {
  name: string
  slug: string
  default_locale: string
  currency: string
  timezone?: string
  branding: {
    primary_color: string
    accent_color: string
    tagline: string
    logo_url: string | null
    favicon_url: string | null
    menu_header_url: string | null
  }
  settings: {
    online_payments_enabled: boolean
    country: string
    nexi: {
      alias: string
      secret_key: string
      environment: 'test' | 'production'
    }
    fiscal: {
      enabled: boolean
      provider: FiscalProvider | null
      mode: FiscalMode
      device_id: string
      credentials_ref: string
      auto_fiscalize_on_pay: boolean
    }
    pos: {
      enabled: boolean
      provider: PosProvider | null
      terminal_id: string
      credentials_ref: string
    }
    printing: {
      enabled: boolean
      driver: PrintDriver
      credentials_ref: string
      stations: {
        kitchen: { host: string; port: number; copies: number }
        bar: { host: string; port: number; copies: number }
      }
    }
  }
}

const emptyPayload = (): TenantSettingsPayload => ({
  name: '',
  slug: '',
  default_locale: 'it',
  currency: 'EUR',
  branding: {
    primary_color: '#0B6E6B',
    accent_color: '#E07A5F',
    tagline: '',
    logo_url: null,
    favicon_url: null,
    menu_header_url: null,
  },
  settings: {
    online_payments_enabled: false,
    country: 'IT',
    nexi: {
      alias: '',
      secret_key: '',
      environment: 'test',
    },
    fiscal: {
      enabled: false,
      provider: null,
      mode: 'local_bridge',
      device_id: '',
      credentials_ref: '',
      auto_fiscalize_on_pay: true,
    },
    pos: {
      enabled: false,
      provider: null,
      terminal_id: '',
      credentials_ref: '',
    },
    printing: {
      enabled: false,
      driver: 'escpos_tcp',
      credentials_ref: '',
      stations: {
        kitchen: { host: '', port: 9100, copies: 1 },
        bar: { host: '', port: 9100, copies: 1 },
      },
    },
  },
})

function mapResponse(data: Record<string, any>): TenantSettingsPayload {
  const s = data.settings || {}
  return {
    name: data.name || '',
    slug: data.slug || '',
    default_locale: data.default_locale || 'it',
    currency: data.currency || 'EUR',
    timezone: data.timezone,
    branding: {
      primary_color: data.branding?.primary_color || '#0B6E6B',
      accent_color: data.branding?.accent_color || '#E07A5F',
      tagline: data.branding?.tagline || '',
      logo_url: resolvePublicAssetUrl(data.branding?.logo_url) || null,
      favicon_url: resolvePublicAssetUrl(data.branding?.favicon_url) || null,
      menu_header_url: resolvePublicAssetUrl(data.branding?.menu_header_url) || null,
    },
    settings: {
      online_payments_enabled: false,
      country: (s.country || 'IT').toUpperCase(),
      nexi: {
        alias: s.nexi?.alias || '',
        secret_key: s.nexi?.secret_key || '',
        environment: s.nexi?.environment === 'production' ? 'production' : 'test',
      },
      fiscal: {
        enabled: s.fiscal?.enabled ?? false,
        provider: s.fiscal?.provider ?? null,
        mode: s.fiscal?.mode || 'local_bridge',
        device_id: s.fiscal?.device_id || '',
        credentials_ref: s.fiscal?.credentials_ref || '',
        auto_fiscalize_on_pay: s.fiscal?.auto_fiscalize_on_pay ?? true,
      },
      pos: {
        enabled: s.pos?.enabled ?? false,
        provider: s.pos?.provider ?? null,
        terminal_id: s.pos?.terminal_id || '',
        credentials_ref: s.pos?.credentials_ref || '',
      },
      printing: {
        enabled: s.printing?.enabled ?? false,
        driver: s.printing?.driver || 'escpos_tcp',
        credentials_ref: s.printing?.credentials_ref || '',
        stations: {
          kitchen: {
            host: s.printing?.stations?.kitchen?.host || '',
            port: s.printing?.stations?.kitchen?.port ?? 9100,
            copies: s.printing?.stations?.kitchen?.copies ?? 1,
          },
          bar: {
            host: s.printing?.stations?.bar?.host || '',
            port: s.printing?.stations?.bar?.port ?? 9100,
            copies: s.printing?.stations?.bar?.copies ?? 1,
          },
        },
      },
    },
  }
}

function clone<T>(value: T): T {
  return structuredClone(toRaw(value as object)) as T
}

const current = ref<TenantSettingsPayload>(emptyPayload())
const loading = ref(false)
const saving = ref(false)
const loaded = ref(false)

function syncTenantStores(mapped: TenantSettingsPayload) {
  const auth = useAuthStore()
  const menu = useMenuStore()

  if (auth.user?.tenant) {
    auth.user.tenant = {
      ...auth.user.tenant,
      name: mapped.name,
      currency: mapped.currency,
      default_locale: mapped.default_locale,
      branding: { ...mapped.branding },
      settings: mapped.settings as TenantSettings,
    }
  }
  if (menu.tenant) {
    menu.tenant = {
      ...menu.tenant,
      name: mapped.name,
      currency: mapped.currency,
      default_locale: mapped.default_locale,
      branding: { ...mapped.branding },
      settings: {
        online_payments_enabled: false,
        card_online_available: false,
      },
    }
  }
}

export function useTenantSettings() {
  async function load(force = false) {
    if (loaded.value && !force) return current.value
    loading.value = true
    try {
      const { data } = await api.get('/admin/settings')
      const mapped = mapResponse(data)
      current.value = mapped
      loaded.value = true
      syncTenantStores(mapped)
      return mapped
    } finally {
      loading.value = false
    }
  }

  async function save(partial: Record<string, unknown>) {
    saving.value = true
    try {
      const { data } = await api.put('/admin/settings', partial)
      const mapped = mapResponse(data)
      current.value = mapped
      loaded.value = true
      syncTenantStores(mapped)
      return mapped
    } finally {
      saving.value = false
    }
  }

  return {
    current: computed(() => current.value),
    loading: computed(() => loading.value),
    saving: computed(() => saving.value),
    loaded: computed(() => loaded.value),
    load,
    save,
    clone,
    emptyPayload,
  }
}

export const fiscalProviderValues = ['epson_epos', 'custom', 'rch', 'mydata', 'other'] as const
export const fiscalModeValues = ['local_bridge', 'cloud_api'] as const
export const posProviderValues = ['nexi', 'sumup', 'axerve', 'other'] as const
export const printDriverValues = ['escpos_tcp', 'printnode', 'local_bridge'] as const
export const countryValues = ['IT', 'GR'] as const
