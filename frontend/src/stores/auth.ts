import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/api/client'
import type { User } from '@/types'
import {
  hasAnyPermission,
  hasPermission,
  permissionsForRole,
  PERMISSIONS,
  type Permission,
} from '@/utils/permissions'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem('bo_token'))

  const permissions = computed<Permission[]>(() => {
    if (user.value?.permissions?.length) {
      return user.value.permissions
    }
    return permissionsForRole(user.value?.role)
  })

  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const isEmailVerified = computed(() => !!user.value?.email_verified_at)
  const isSuperAdmin = computed(() => user.value?.role === 'super_admin')
  const isAdmin = computed(() => user.value?.role === 'admin' || isSuperAdmin.value)
  const isManager = computed(() => user.value?.role === 'manager')
  const isStaffRole = computed(() => user.value?.role === 'staff')

  function can(permission: Permission): boolean {
    return hasPermission(permissions.value, permission, user.value?.role)
  }

  function canAny(required: Permission[]): boolean {
    return hasAnyPermission(permissions.value, required, user.value?.role)
  }

  async function login(email: string, password: string, tenant?: string) {
    const { data } = await api.post('/login', { email, password, tenant: tenant || undefined })
    token.value = data.token
    user.value = data.user
    localStorage.setItem('bo_token', data.token)
    if (data.user?.tenant?.slug) {
      localStorage.setItem('bo_tenant_slug', data.user.tenant.slug)
    }
  }

  async function register(payload: {
    company_name: string
    slug?: string
    name: string
    email: string
    password: string
    password_confirmation: string
    default_locale?: string
    accept_terms?: boolean
  }) {
    const { data } = await api.post<{
      token: string
      user: User
      checkout_url?: string | null
    }>('/register', payload)
    token.value = data.token
    user.value = data.user
    localStorage.setItem('bo_token', data.token)
    if (data.user?.tenant?.slug) {
      localStorage.setItem('bo_tenant_slug', data.user.tenant.slug)
    }
    return data
  }

  async function fetchMe() {
    if (!token.value) return
    try {
      const { data } = await api.get('/me')
      user.value = data
      if (data.tenant?.slug) {
        localStorage.setItem('bo_tenant_slug', data.tenant.slug)
      }
    } catch {
      clearSession()
    }
  }

  function clearSession() {
    token.value = null
    user.value = null
    localStorage.removeItem('bo_token')
  }

  async function logout() {
    const current = token.value
    clearSession()
    try {
      if (current) {
        await api.post('/logout', null, {
          headers: { Authorization: `Bearer ${current}` },
        })
      }
    } catch {
      // ignore — local session already cleared
    }
  }

  async function forgotPassword(email: string) {
    await api.post('/forgot-password', { email })
  }

  async function resetPassword(payload: {
    email: string
    code: string
    password: string
    password_confirmation: string
  }) {
    await api.post('/reset-password', payload)
  }

  async function changePassword(payload: {
    current_password: string
    password: string
    password_confirmation: string
  }) {
    await api.post('/change-password', payload)
  }

  async function verifyEmail(params: {
    id: string
    hash: string
    expires: string
    signature: string
  }) {
    const { data } = await api.get(`/email/verify/${params.id}/${params.hash}`, {
      params: { expires: params.expires, signature: params.signature },
    })
    if (user.value) {
      await fetchMe()
    }
    return data
  }

  async function verifyEmailWithOtp(code: string) {
    const { data } = await api.post('/email/verify-otp', { code })
    await fetchMe()
    return data
  }

  async function resendVerificationEmail() {
    await api.post('/email/verification-notification')
  }

  return {
    user,
    token,
    permissions,
    isAuthenticated,
    isEmailVerified,
    isSuperAdmin,
    isAdmin,
    isManager,
    isStaffRole,
    can,
    canAny,
    PERMISSIONS,
    login,
    register,
    fetchMe,
    logout,
    forgotPassword,
    resetPassword,
    changePassword,
    verifyEmail,
    verifyEmailWithOtp,
    resendVerificationEmail,
  }
})
