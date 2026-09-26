import axios from 'axios'
import router from '@/router'
import { getActiveLocale } from '@/plugins/i18n'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('bo_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  const tenant = localStorage.getItem('bo_tenant_slug')
  if (tenant) {
    config.headers['X-Tenant'] = tenant
  }

  config.headers['X-Locale'] = getActiveLocale()

  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error?.response?.status === 401) {
      localStorage.removeItem('bo_token')
      const path = router.currentRoute.value.path
      if (!path.startsWith('/t/') && path !== '/login' && path !== '/') {
        router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } })
      }
    }
    if (error?.response?.status === 403 && error?.response?.data?.code === 'tenant_inactive') {
      localStorage.removeItem('bo_token')
      const path = router.currentRoute.value.path
      if (!path.startsWith('/t/') && path !== '/login' && path !== '/') {
        router.push({ name: 'login', query: { reason: 'tenant_inactive' } })
      }
    }
    return Promise.reject(error)
  },
)

export function tenantPath(tenantSlug: string, path = ''): string {
  const clean = path.startsWith('/') ? path : `/${path}`
  return `/t/${tenantSlug}${clean === '/' ? '' : clean}`
}

export function getApiErrorMessage(error: unknown, fallback = 'Something went wrong'): string {
  const err = error as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
  const errors = err?.response?.data?.errors
  if (errors) {
    const first = Object.values(errors)[0]
    if (first?.[0]) return first[0]
  }
  return err?.response?.data?.message || fallback
}

export default api
