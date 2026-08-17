import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api, { tenantPath } from '@/api/client'
import type { Category, Location, TenantBranding, TenantSettings } from '@/types'

export interface TenantInfo {
  id: number
  name: string
  slug: string
  branding?: TenantBranding | null
  currency?: string
  default_locale?: string
  settings?: Pick<TenantSettings, 'online_payments_enabled' | 'card_online_available'> | null
}

const ACCESS_KEY = 'bo_access_token'
const ACCESS_EXPIRES_KEY = 'bo_access_expires'
const ACCESS_LOCATION_KEY = 'bo_access_location'

function getOrCreateSession(): string {
  let session = localStorage.getItem('bo_session')
  if (!session) {
    const c = globalThis.crypto as Crypto | undefined
    if (c?.randomUUID) {
      session = c.randomUUID()
    } else {
      session = `bo-${Date.now()}-${Math.random().toString(16).slice(2)}`
    }
    localStorage.setItem('bo_session', session)
  }
  return session
}

function readStoredAccess(locationCode: string): { token: string; expiresAt: string } | null {
  const token = sessionStorage.getItem(ACCESS_KEY)
  const expiresAt = sessionStorage.getItem(ACCESS_EXPIRES_KEY)
  const boundLocation = sessionStorage.getItem(ACCESS_LOCATION_KEY)
  if (!token || !expiresAt || boundLocation !== locationCode) return null
  if (Date.parse(expiresAt) <= Date.now()) {
    sessionStorage.removeItem(ACCESS_KEY)
    sessionStorage.removeItem(ACCESS_EXPIRES_KEY)
    sessionStorage.removeItem(ACCESS_LOCATION_KEY)
    return null
  }
  return { token, expiresAt }
}

export const useMenuStore = defineStore('menu', () => {
  const tenantSlug = ref(localStorage.getItem('bo_tenant_slug') || '')
  const tenant = ref<TenantInfo | null>(null)
  const locationCode = ref(localStorage.getItem('bo_location_code') || '')
  const location = ref<Location | null>(null)
  const categories = ref<Category[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const tenantInactive = ref(false)
  const session = ref(getOrCreateSession())
  const accessToken = ref<string | null>(null)
  const accessExpiresAt = ref<string | null>(null)

  const hasTenant = computed(() => !!tenantSlug.value)
  const canOrder = computed(() => {
    if (!accessToken.value || !accessExpiresAt.value) return false
    return Date.parse(accessExpiresAt.value) > Date.now()
  })

  function hydrateAccessFromStorage() {
    if (!locationCode.value) return
    const stored = readStoredAccess(locationCode.value)
    accessToken.value = stored?.token ?? null
    accessExpiresAt.value = stored?.expiresAt ?? null
  }

  hydrateAccessFromStorage()

  function setTenant(slug: string) {
    tenantSlug.value = slug
    localStorage.setItem('bo_tenant_slug', slug)
  }

  function setLocationCode(code: string) {
    if (locationCode.value && locationCode.value !== code) {
      clearAccess()
    }
    locationCode.value = code
    localStorage.setItem('bo_location_code', code)
    hydrateAccessFromStorage()
  }

  function persistAccess(token: string, expiresAt: string, code: string) {
    accessToken.value = token
    accessExpiresAt.value = expiresAt
    sessionStorage.setItem(ACCESS_KEY, token)
    sessionStorage.setItem(ACCESS_EXPIRES_KEY, expiresAt)
    sessionStorage.setItem(ACCESS_LOCATION_KEY, code)
  }

  function clearAccess() {
    accessToken.value = null
    accessExpiresAt.value = null
    sessionStorage.removeItem(ACCESS_KEY)
    sessionStorage.removeItem(ACCESS_EXPIRES_KEY)
    sessionStorage.removeItem(ACCESS_LOCATION_KEY)
  }

  /** Claim a one-order access token from the stable QR location code. */
  async function claimAccess(code?: string) {
    const loc = code || locationCode.value
    if (!tenantSlug.value || !loc) {
      throw new Error('Missing tenant or location')
    }
    const { data } = await api.post(tenantPath(tenantSlug.value, `/locations/code/${loc}/claim`), {
      customer_session: session.value,
    })
    persistAccess(data.access_token, data.expires_at, loc)
    location.value = data.location
    setLocationCode(loc)
    return data
  }

  function mergeTenantInfo(info?: Partial<TenantInfo> | null) {
    if (!info) return
    tenant.value = {
      id: info.id ?? tenant.value?.id ?? 0,
      name: info.name ?? tenant.value?.name ?? '',
      slug: info.slug ?? tenantSlug.value,
      branding: info.branding ?? tenant.value?.branding,
      currency: info.currency ?? tenant.value?.currency,
      default_locale: info.default_locale ?? tenant.value?.default_locale,
      settings: info.settings ?? tenant.value?.settings,
    }
  }

  function applyInactiveTenant(info?: Partial<TenantInfo> | null) {
    tenantInactive.value = true
    mergeTenantInfo(info)
  }

  function isInactiveError(e: unknown): boolean {
    const err = e as { response?: { status?: number; data?: { code?: string; tenant?: Partial<TenantInfo> } } }
    if (err?.response?.status === 403 && err?.response?.data?.code === 'tenant_inactive') {
      applyInactiveTenant(err.response.data.tenant)
      return true
    }
    return false
  }

  async function checkTenantStatus(): Promise<boolean> {
    if (!tenantSlug.value) return false
    try {
      const { data } = await api.get(tenantPath(tenantSlug.value, '/status'))
      if (!data.is_active) {
        applyInactiveTenant(data.tenant)
        return false
      }
      tenantInactive.value = false
      mergeTenantInfo(data.tenant)
      return true
    } catch {
      return false
    }
  }

  async function loadMenu(locale = 'el') {
    if (!tenantSlug.value) {
      error.value = 'Tenant missing'
      return
    }
    if (tenantInactive.value) return
    loading.value = true
    error.value = null
    try {
      const { data } = await api.get(tenantPath(tenantSlug.value, '/menu'), {
        params: { locale, code: locationCode.value || undefined },
      })
      categories.value = data.categories
      location.value = data.location
      tenant.value = data.tenant
      tenantInactive.value = false
      if (data.tenant?.default_locale && !localStorage.getItem('bo_locale')) {
        localStorage.setItem('bo_locale', data.tenant.default_locale)
      }
    } catch (e: unknown) {
      if (isInactiveError(e)) return
      error.value = 'Failed to load menu'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function eraseSessionData(): Promise<void> {
    if (!tenantSlug.value || !session.value) return
    await api.post(tenantPath(tenantSlug.value, '/privacy/erase-session'), {
      customer_session: session.value,
    })
    localStorage.removeItem('bo_session')
    session.value = getOrCreateSession()
    clearAccess()
  }

  return {
    tenantSlug,
    tenant,
    locationCode,
    location,
    categories,
    loading,
    error,
    tenantInactive,
    session,
    accessToken,
    accessExpiresAt,
    hasTenant,
    canOrder,
    setTenant,
    setLocationCode,
    claimAccess,
    clearAccess,
    checkTenantStatus,
    loadMenu,
    eraseSessionData,
  }
})
