import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { homePathForRole } from '@/utils/roleHome'
import { hasAnyPermission, PERMISSIONS, type Permission } from '@/utils/permissions'
import { staffCanAccessRoute, type StaffPosition } from '@/utils/staffPosition'

const demoTenant = import.meta.env.VITE_DEMO_TENANT || 'azure-beach'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      name: 'landing',
      component: () => import('@/views/LandingView.vue'),
    },
    {
      path: '/t/:tenant/q/:code',
      name: 'menu',
      component: () => import('@/views/customer/MenuView.vue'),
    },
    {
      path: '/t/:tenant/cart',
      name: 'cart',
      component: () => import('@/views/customer/CartView.vue'),
    },
    {
      path: '/t/:tenant/order/:id/pay',
      name: 'order-payment',
      component: () => import('@/views/customer/PaymentView.vue'),
    },
    {
      path: '/t/:tenant/order/:id',
      name: 'order-status',
      component: () => import('@/views/customer/OrderStatusView.vue'),
    },
    {
      path: '/t/:tenant/call-waiter',
      name: 'call-waiter',
      component: () => import('@/views/customer/CallWaiterView.vue'),
    },
    { path: '/q/:code', redirect: (to) => `/t/${demoTenant}/q/${to.params.code}` },
    { path: '/cart', redirect: `/t/${demoTenant}/cart` },
    { path: '/call-waiter', redirect: `/t/${demoTenant}/call-waiter` },
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/staff/LoginView.vue'),
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/views/staff/RegisterView.vue'),
    },
    {
      path: '/forgot-password',
      name: 'forgot-password',
      component: () => import('@/views/staff/ForgotPasswordView.vue'),
    },
    {
      path: '/reset-password',
      name: 'reset-password',
      component: () => import('@/views/staff/ResetPasswordView.vue'),
    },
    {
      path: '/verify-email',
      name: 'verify-email',
      component: () => import('@/views/staff/VerifyEmailView.vue'),
    },
    {
      path: '/privacy',
      name: 'privacy',
      component: () => import('@/views/legal/LegalDocumentView.vue'),
      props: { document: 'privacy' },
    },
    {
      path: '/terms',
      name: 'terms',
      component: () => import('@/views/legal/LegalDocumentView.vue'),
      props: { document: 'terms' },
    },
    {
      path: '/cookies',
      name: 'cookies',
      component: () => import('@/views/legal/LegalDocumentView.vue'),
      props: { document: 'cookies' },
    },
    {
      path: '/dpa',
      name: 'dpa',
      component: () => import('@/views/legal/LegalDocumentView.vue'),
      props: { document: 'dpa' },
    },
    {
      path: '/data-processing-roles',
      name: 'data-processing-roles',
      component: () => import('@/views/legal/LegalDocumentView.vue'),
      props: { document: 'data-processing-roles' },
    },
    {
      path: '/kitchen',
      name: 'kitchen',
      component: () => import('@/views/staff/KitchenView.vue'),
      meta: {
        requiresAuth: true,
        permissions: [PERMISSIONS.ORDERS_VIEW],
        staffPosition: 'kitchen' as StaffPosition,
        station: 'kitchen',
      },
    },
    {
      path: '/bar',
      name: 'bar',
      component: () => import('@/views/staff/KitchenView.vue'),
      meta: {
        requiresAuth: true,
        permissions: [PERMISSIONS.ORDERS_VIEW],
        staffPosition: 'bar' as StaffPosition,
        station: 'bar',
      },
    },
    {
      path: '/waiter',
      name: 'waiter',
      component: () => import('@/views/staff/WaiterView.vue'),
      meta: {
        requiresAuth: true,
        permissions: [PERMISSIONS.WAITER_CALLS_MANAGE],
        staffPosition: 'waiter' as StaffPosition,
      },
    },
    {
      path: '/admin',
      component: () => import('@/views/admin/AdminLayout.vue'),
      meta: {
        requiresAuth: true,
        permissions: [
          PERMISSIONS.MENU_MANAGE,
          PERMISSIONS.QR_MANAGE,
          PERMISSIONS.USERS_MANAGE,
          PERMISSIONS.SETTINGS_MANAGE,
          PERMISSIONS.ORDERS_MANAGE,
        ],
      },
      children: [
        {
          path: '',
          name: 'admin-dashboard',
          component: () => import('@/views/admin/DashboardView.vue'),
          meta: { permissions: [PERMISSIONS.ORDERS_MANAGE, PERMISSIONS.MENU_MANAGE] },
        },
        {
          path: 'analytics/products',
          name: 'admin-product-analytics',
          component: () => import('@/views/admin/ProductAnalyticsView.vue'),
          meta: { permissions: [PERMISSIONS.ORDERS_MANAGE] },
        },
        {
          path: 'analytics',
          name: 'admin-analytics',
          component: () => import('@/views/admin/SalesAnalyticsView.vue'),
          meta: { permissions: [PERMISSIONS.ORDERS_MANAGE] },
        },
        {
          path: 'products',
          name: 'admin-products',
          component: () => import('@/views/admin/ProductsView.vue'),
          meta: { permissions: [PERMISSIONS.MENU_MANAGE] },
        },
        {
          path: 'categories',
          name: 'admin-categories',
          component: () => import('@/views/admin/CategoriesView.vue'),
          meta: { permissions: [PERMISSIONS.MENU_MANAGE] },
        },
        {
          path: 'tags',
          name: 'admin-tags',
          component: () => import('@/views/admin/TagsView.vue'),
          meta: { permissions: [PERMISSIONS.MENU_MANAGE] },
        },
        {
          path: 'locations',
          name: 'admin-locations',
          component: () => import('@/views/admin/LocationsView.vue'),
          meta: { permissions: [PERMISSIONS.QR_MANAGE] },
        },
        {
          path: 'users',
          name: 'admin-users',
          component: () => import('@/views/admin/UsersView.vue'),
          meta: { permissions: [PERMISSIONS.USERS_MANAGE] },
        },
        {
          path: 'settings',
          component: () => import('@/views/admin/settings/SettingsLayout.vue'),
          meta: { permissions: [PERMISSIONS.SETTINGS_MANAGE] },
          children: [
            { path: '', redirect: { name: 'admin-settings-general' } },
            {
              path: 'subscription',
              name: 'admin-settings-subscription',
              component: () => import('@/views/admin/settings/SubscriptionSettingsView.vue'),
            },
            {
              path: 'general',
              name: 'admin-settings-general',
              component: () => import('@/views/admin/settings/GeneralSettingsView.vue'),
            },
            { path: 'payments', redirect: { name: 'admin-settings-subscription' } },
            { path: 'pos', redirect: { name: 'admin-settings-general' } },
            { path: 'pos-integration', redirect: { name: 'admin-settings-general' } },
            { path: 'pos-mappings', redirect: { name: 'admin-settings-general' } },
            { path: 'pos-syncs', redirect: { name: 'admin-settings-general' } },
            {
              path: 'appearance',
              name: 'admin-settings-appearance',
              component: () => import('@/views/admin/settings/AppearanceSettingsView.vue'),
            },
            {
              path: 'fiscal',
              name: 'admin-settings-fiscal',
              component: () => import('@/views/admin/settings/FiscalSettingsView.vue'),
            },
            {
              path: 'printing',
              name: 'admin-settings-printing',
              component: () => import('@/views/admin/settings/PrintingSettingsView.vue'),
            },
            {
              path: 'privacy',
              name: 'admin-settings-privacy',
              component: () => import('@/views/admin/settings/PrivacySettingsView.vue'),
            },
          ],
        },
        { path: 'branding', redirect: { name: 'admin-settings-general' } },
        {
          path: 'tenants',
          name: 'admin-tenants',
          component: () => import('@/views/admin/TenantsView.vue'),
          meta: { permissions: [PERMISSIONS.TENANTS_MANAGE] },
        },
      ],
    },
  ],
  scrollBehavior: () => ({ top: 0 }),
})

function homeForRole(role?: string, staffPosition?: string | null) {
  return homePathForRole(role, staffPosition)
}

function requiredPermissionsForRoute(to: { matched: { meta: { permissions?: Permission[] } }[] }): Permission[] | undefined {
  const fromMatched = [...to.matched]
    .reverse()
    .map((record) => record.meta.permissions as Permission[] | undefined)
    .find(Boolean)
  return fromMatched
}

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  const isPublic =
    to.name === 'landing' ||
    to.name === 'login' ||
    to.name === 'register' ||
    to.name === 'forgot-password' ||
    to.name === 'reset-password' ||
    to.name === 'verify-email' ||
    to.name === 'privacy' ||
    to.name === 'terms' ||
    to.name === 'cookies' ||
    to.name === 'dpa' ||
    to.name === 'data-processing-roles' ||
    to.path.startsWith('/t/')

  if (auth.token && !auth.user) {
    if (isPublic) {
      void auth.fetchMe()
    } else {
      await auth.fetchMe()
    }
  }

  if (
    (to.name === 'login' || to.name === 'forgot-password' || to.name === 'reset-password') &&
    auth.isAuthenticated
  ) {
    if (!auth.isEmailVerified) {
      return { name: 'verify-email' }
    }
    return homeForRole(auth.user?.role, auth.user?.staff_position)
  }

  if (to.name === 'register' && auth.isAuthenticated && auth.isEmailVerified) {
    return homeForRole(auth.user?.role, auth.user?.staff_position)
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (
    to.meta.requiresAuth &&
    auth.isAuthenticated &&
    !auth.isEmailVerified &&
    to.name !== 'verify-email'
  ) {
    return { name: 'verify-email', query: { redirect: to.fullPath } }
  }

  const required = requiredPermissionsForRoute(to)
  if (required && auth.user) {
    if (!hasAnyPermission(auth.permissions, required, auth.user.role)) {
      return homeForRole(auth.user.role, auth.user.staff_position)
    }
  }

  const requiredStaffPosition = [...to.matched]
    .reverse()
    .map((record) => record.meta.staffPosition as StaffPosition | undefined)
    .find(Boolean)

  if (
    requiredStaffPosition &&
    auth.user?.role === 'staff' &&
    !staffCanAccessRoute(auth.user.staff_position, requiredStaffPosition)
  ) {
    return homeForRole(auth.user.role, auth.user.staff_position)
  }

  if (
    auth.user?.role === 'admin' &&
    auth.user.tenant?.is_active === false &&
    !auth.user.tenant?.is_demo &&
    to.path.startsWith('/admin') &&
    to.name !== 'admin-settings-subscription' &&
    to.name !== 'admin-tenants'
  ) {
    return { name: 'admin-settings-subscription', query: { checkout: 'required' } }
  }

  return true
})

export default router
