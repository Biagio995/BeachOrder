<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useDisplay, useTheme } from 'vuetify'
import { useAuthStore } from '@/stores/auth'
import { PERMISSIONS } from '@/utils/permissions'
import { homePathForRole } from '@/utils/roleHome'
import { staffCanAccessRoute } from '@/utils/staffPosition'
import { useCartStore } from '@/stores/cart'
import { useMenuStore } from '@/stores/menu'
import { useActiveOrderStore } from '@/stores/activeOrder'
import { useUiStore } from '@/stores/ui'
import { resetEcho } from '@/plugins/echo'
import { applyBrandingTheme, applyPageBranding, resetBrandingTheme, resetPageBranding } from '@/utils/branding'
import { APP_NAME } from '@/config/brand'
import { resolvePublicAssetUrl } from '@/utils/publicAssetUrl'
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue'
import CookieConsentBanner from '@/components/shared/CookieConsentBanner.vue'

const route = useRoute()
const router = useRouter()
const { t, locale } = useI18n()
const theme = useTheme()
const { smAndDown, mdAndUp } = useDisplay()
const auth = useAuthStore()
const cart = useCartStore()
const menu = useMenuStore()
const activeOrder = useActiveOrderStore()
const ui = useUiStore()
const staffMenuOpen = ref(false)

const demoTenant = import.meta.env.VITE_DEMO_TENANT || 'azure-beach'
const demoLocation = import.meta.env.VITE_DEMO_LOCATION || 'umbrella12'

const isLanding = computed(() => route.name === 'landing')
const isAuthPage = computed(
  () =>
    route.name === 'login' ||
    route.name === 'register' ||
    route.name === 'forgot-password' ||
    route.name === 'reset-password',
)
const isLegalPage = computed(
  () =>
    route.name === 'privacy' ||
    route.name === 'terms' ||
    route.name === 'cookies' ||
    route.name === 'dpa' ||
    route.name === 'data-processing-roles',
)
/** Marketing/platform surfaces — never inherit a tenant white-label. */
const isPlatformSurface = computed(
  () => isLanding.value || isAuthPage.value || isLegalPage.value || route.name === 'admin-tenants',
)

const isCustomer = computed(
  () =>
    !isLanding.value &&
    !isAuthPage.value &&
    !route.path.startsWith('/admin') &&
    !route.path.startsWith('/kitchen') &&
    !route.path.startsWith('/bar') &&
    !route.path.startsWith('/waiter'),
)

const isStaffArea = computed(
  () =>
    route.path.startsWith('/admin') ||
    route.path.startsWith('/kitchen') ||
    route.path.startsWith('/bar') ||
    route.path.startsWith('/waiter'),
)

const tenantBase = computed(() => {
  const slug = (route.params.tenant as string) || menu.tenantSlug
  return slug ? `/t/${slug}` : `/t/${demoTenant}`
})

const brandTitle = computed(() => {
  if (isLanding.value || isAuthPage.value) return t('app.name', { appName: APP_NAME })
  if (isStaffArea.value) return auth.user?.tenant?.name || t('app.name', { appName: APP_NAME })
  return menu.tenant?.name || ''
})

const brandLogoUrl = computed(() => {
  if (isPlatformSurface.value) return null
  let raw: string | null = null
  if (isCustomer.value) raw = menu.tenant?.branding?.logo_url ?? null
  else if (isStaffArea.value) {
    raw = auth.user?.tenant?.branding?.logo_url ?? menu.tenant?.branding?.logo_url ?? null
  }
  return resolvePublicAssetUrl(raw)
})

const brandTo = computed(() => {
  if (isLanding.value || isAuthPage.value) return '/'
  if (isStaffArea.value) {
    if (!auth.isAuthenticated) return '/'
    if (auth.isSuperAdmin) return '/admin/tenants'
    if (auth.isStaffRole) return homePathForRole(auth.user?.role, auth.user?.staff_position)
    if (auth.can(PERMISSIONS.MENU_MANAGE) || auth.can(PERMISSIONS.ORDERS_MANAGE)) return '/admin'
    if (auth.can(PERMISSIONS.WAITER_CALLS_MANAGE)) return '/waiter'
    return '/admin'
  }
  if (menu.locationCode) return `${tenantBase.value}/q/${menu.locationCode}`
  return '/'
})

const locales = [
  { code: 'el', label: 'EL', name: 'Ελληνικά', flag: '/flags/el.svg' },
  { code: 'en', label: 'EN', name: 'English', flag: '/flags/en.svg' },
  { code: 'it', label: 'IT', name: 'Italiano', flag: '/flags/it.svg' },
  { code: 'de', label: 'DE', name: 'Deutsch', flag: '/flags/de.svg' },
]

const currentLocale = computed(
  () => locales.find((l) => l.code === String(locale.value)) || locales[0],
)

watch(
  locale,
  (code) => {
    document.documentElement.lang = String(code || 'el')
  },
  { immediate: true },
)

// Tenant white-label only inside that tenant (customer /t/:slug or staff for the tenant).
watch(
  () => {
    if (isPlatformSurface.value) {
      return { scope: 'platform' as const, branding: null }
    }
    if (isCustomer.value) {
      return { scope: 'tenant' as const, branding: menu.tenant?.branding ?? null }
    }
    if (isStaffArea.value) {
      return {
        scope: 'tenant' as const,
        branding: auth.user?.tenant?.branding ?? menu.tenant?.branding ?? null,
      }
    }
    return { scope: 'platform' as const, branding: null }
  },
  ({ scope, branding }) => {
    if (scope === 'platform') {
      resetBrandingTheme(theme)
      resetPageBranding()
    } else {
      applyBrandingTheme(theme, branding)
    }
  },
  { immediate: true, deep: true },
)

watch(
  () => {
    if (isPlatformSurface.value) {
      return { name: null as string | null, favicon: null as string | null }
    }
    if (isCustomer.value) {
      return {
        name: menu.tenant?.name ?? null,
        favicon: resolvePublicAssetUrl(menu.tenant?.branding?.favicon_url) ?? null,
      }
    }
    if (isStaffArea.value) {
      const tenant = auth.user?.tenant ?? menu.tenant
      return {
        name: tenant?.name ?? null,
        favicon: resolvePublicAssetUrl(tenant?.branding?.favicon_url) ?? null,
      }
    }
    return { name: null as string | null, favicon: null as string | null }
  },
  ({ name, favicon }) => {
    if (isPlatformSurface.value) resetPageBranding()
    else applyPageBranding(name, favicon, false)
  },
  { immediate: true, deep: true },
)

watch(
  () => [isCustomer.value, String(route.params.tenant || menu.tenantSlug || '')] as const,
  ([customer, tenant]) => {
    if (!customer || !tenant) {
      activeOrder.stopWatching()
      return
    }
    menu.setTenant(tenant)
    activeOrder.hydrate(tenant)
    if (activeOrder.isActive) {
      void activeOrder.startWatching()
    } else {
      activeOrder.stopWatching()
    }
  },
  { immediate: true },
)

watch(
  () => activeOrder.isActive,
  (active) => {
    if (active && isCustomer.value) void activeOrder.startWatching()
    if (!active) activeOrder.stopWatching()
  },
)

async function setLocale(code: string | null) {
  if (!code) return
  locale.value = code
  localStorage.setItem('bo_locale', code)
  if (isCustomer.value) {
    await menu.loadMenu(code)
    cart.syncLabelsFromCategories(menu.categories)
  }
}

async function logout() {
  staffMenuOpen.value = false
  resetEcho()
  await auth.logout()
  router.push('/login')
}

function goStaff(path: string) {
  staffMenuOpen.value = false
  router.push(path)
}
</script>

<template>
  <v-app>
    <v-app-bar
      flat
      color="transparent"
      class="app-bar px-1 px-sm-2"
      density="comfortable"
    >
      <v-app-bar-title class="display-font app-bar__title">
        <router-link class="app-bar__brand" :class="{ 'app-bar__brand--logo': !!brandLogoUrl }" :to="brandTo">
          <img v-if="brandLogoUrl" :src="brandLogoUrl" :alt="brandTitle" class="app-bar__logo" />
          <span v-else>{{ brandTitle }}</span>
        </router-link>
      </v-app-bar-title>

      <v-menu location="bottom end" :offset="8" transition="fade-transition">
        <template #activator="{ props: menuProps, isActive }">
          <button
            v-bind="menuProps"
            type="button"
            class="locale-trigger mr-2"
            :class="{ 'locale-trigger--open': isActive }"
            :aria-label="t('common.language')"
          >
            <img
              class="locale-trigger__flag"
              :src="currentLocale.flag"
              alt=""
              width="18"
              height="12"
              decoding="async"
            />
            <span class="locale-trigger__code">{{ currentLocale.label }}</span>
            <v-icon size="16" class="locale-trigger__chevron">mdi-chevron-down</v-icon>
          </button>
        </template>
        <div class="locale-menu" role="listbox" :aria-label="t('common.language')">
          <button
            v-for="l in locales"
            :key="l.code"
            type="button"
            class="locale-menu__item"
            :class="{ 'locale-menu__item--active': locale === l.code }"
            role="option"
            :aria-selected="locale === l.code"
            @click="setLocale(l.code)"
          >
            <img
              class="locale-menu__flag"
              :src="l.flag"
              alt=""
              width="22"
              height="15"
              decoding="async"
            />
            <span class="locale-menu__name">{{ l.name }}</span>
            <v-icon v-if="locale === l.code" size="16" class="locale-menu__check">mdi-check</v-icon>
          </button>
        </div>
      </v-menu>

      <template v-if="isLanding">
        <v-btn
          :to="`/t/${demoTenant}/q/${demoLocation}`"
          variant="text"
          color="primary"
          class="d-none d-sm-flex"
        >
          {{ t('landing.ctaDemo') }}
        </v-btn>
        <v-btn
          to="/login"
          color="primary"
          variant="flat"
          class="ml-1"
          :size="smAndDown ? 'small' : 'default'"
        >
          <span class="d-none d-sm-inline">{{ t('nav.login') }}</span>
          <v-icon class="d-sm-none">mdi-login</v-icon>
        </v-btn>
      </template>

      <template v-else-if="isCustomer">
        <v-btn
          v-if="mdAndUp && activeOrder.isActive && activeOrder.statusRoute"
          :to="activeOrder.statusRoute"
          variant="tonal"
          color="accent"
          class="mr-1"
        >
          <v-icon start>mdi-progress-clock</v-icon>
          {{ t('nav.order') }}
        </v-btn>
        <v-btn
          v-if="mdAndUp"
          :to="`${tenantBase}/call-waiter`"
          variant="text"
          color="primary"
        >
          {{ t('nav.callWaiter') }}
        </v-btn>
        <v-btn
          v-if="mdAndUp"
          :to="`${tenantBase}/cart`"
          color="primary"
          variant="flat"
          class="ml-1"
        >
          <v-icon start>mdi-cart</v-icon>
          {{ cart.count }}
        </v-btn>
      </template>

      <template v-else-if="auth.isAuthenticated && isStaffArea">
        <template v-if="mdAndUp">
          <v-chip v-if="auth.user?.tenant?.name" size="small" class="mr-2" variant="tonal" color="primary">
            {{ auth.user.tenant.name }}
          </v-chip>
          <v-btn
            v-if="auth.can(PERMISSIONS.ORDERS_VIEW) && auth.isStaffRole && staffCanAccessRoute(auth.staffPosition, 'kitchen')"
            to="/kitchen"
            variant="text"
          >{{ t('nav.kitchen') }}</v-btn>
          <v-btn
            v-if="auth.can(PERMISSIONS.ORDERS_VIEW) && auth.isStaffRole && staffCanAccessRoute(auth.staffPosition, 'bar')"
            to="/bar"
            variant="text"
          >{{ t('nav.bar') }}</v-btn>
          <v-btn
            v-if="auth.can(PERMISSIONS.WAITER_CALLS_MANAGE) && auth.isStaffRole && staffCanAccessRoute(auth.staffPosition, 'waiter')"
            to="/waiter"
            variant="text"
          >{{ t('nav.waiter') }}</v-btn>
          <v-btn
            v-if="auth.canAny([PERMISSIONS.MENU_MANAGE, PERMISSIONS.QR_MANAGE, PERMISSIONS.USERS_MANAGE, PERMISSIONS.SETTINGS_MANAGE])"
            :to="auth.isSuperAdmin ? '/admin/tenants' : '/admin'"
            variant="text"
          >{{ t('nav.admin') }}</v-btn>
          <v-btn variant="text" @click="logout">{{ t('nav.logout') }}</v-btn>
        </template>
        <v-btn
          v-else
          icon="mdi-menu"
          variant="text"
          aria-label="Menu"
          @click="staffMenuOpen = true"
        />
      </template>

      <template v-else-if="auth.isAuthenticated">
        <v-btn variant="text" size="small" @click="logout">{{ t('nav.logout') }}</v-btn>
      </template>
    </v-app-bar>

    <v-navigation-drawer
      v-model="staffMenuOpen"
      temporary
      location="right"
      width="280"
    >
      <div class="pa-4">
        <div class="display-font text-h6 mb-1" style="color: var(--bo-teal-deep)">
          {{ auth.user?.tenant?.name || t('app.name', { appName: APP_NAME }) }}
        </div>
        <div v-if="auth.user?.name" class="text-medium-emphasis text-body-2 mb-4">
          {{ auth.user.name }}
        </div>
        <v-list nav density="comfortable">
          <v-list-item
            v-if="auth.can(PERMISSIONS.ORDERS_VIEW) && auth.isStaffRole && staffCanAccessRoute(auth.staffPosition, 'kitchen')"
            prepend-icon="mdi-chef-hat"
            :title="t('nav.kitchen')"
            @click="goStaff('/kitchen')"
          />
          <v-list-item
            v-if="auth.can(PERMISSIONS.ORDERS_VIEW) && auth.isStaffRole && staffCanAccessRoute(auth.staffPosition, 'bar')"
            prepend-icon="mdi-glass-cocktail"
            :title="t('nav.bar')"
            @click="goStaff('/bar')"
          />
          <v-list-item
            v-if="auth.can(PERMISSIONS.WAITER_CALLS_MANAGE) && auth.isStaffRole && staffCanAccessRoute(auth.staffPosition, 'waiter')"
            prepend-icon="mdi-room-service"
            :title="t('nav.waiter')"
            @click="goStaff('/waiter')"
          />
          <v-list-item
            v-if="auth.canAny([PERMISSIONS.MENU_MANAGE, PERMISSIONS.QR_MANAGE, PERMISSIONS.USERS_MANAGE, PERMISSIONS.SETTINGS_MANAGE])"
            prepend-icon="mdi-view-dashboard"
            :title="t('nav.admin')"
            @click="goStaff(auth.isSuperAdmin ? '/admin/tenants' : '/admin')"
          />
          <v-list-item
            prepend-icon="mdi-logout"
            :title="t('nav.logout')"
            @click="logout"
          />
        </v-list>
      </div>
    </v-navigation-drawer>

    <v-main
      :class="{
        'has-bottom-nav': isCustomer && smAndDown,
      }"
    >
      <router-view />
    </v-main>

    <v-snackbar v-model="ui.snackbar" :color="ui.color" timeout="2500" location="bottom">
      {{ ui.message }}
    </v-snackbar>
    <ConfirmDialog />
    <CookieConsentBanner v-if="isCustomer || isLanding || isLegalPage" />

    <v-bottom-navigation
      v-if="isCustomer && smAndDown"
      grow
      color="primary"
      class="bottom-nav"
      height="64"
    >
      <v-btn :to="menu.locationCode ? `${tenantBase}/q/${menu.locationCode}` : `${tenantBase}/q/${demoLocation}`">
        <v-icon>mdi-silverware-fork-knife</v-icon>
        <span>{{ t('nav.menu') }}</span>
      </v-btn>
      <v-btn :to="`${tenantBase}/cart`">
        <v-badge :content="cart.count" :model-value="cart.count > 0" color="accent" offset-x="2" offset-y="2">
          <v-icon>mdi-cart</v-icon>
        </v-badge>
        <span>{{ t('nav.cart') }}</span>
      </v-btn>
      <v-btn
        v-if="activeOrder.isActive && activeOrder.statusRoute"
        :to="activeOrder.statusRoute"
      >
        <v-icon>mdi-progress-clock</v-icon>
        <span>{{ t('nav.order') }}</span>
      </v-btn>
      <v-btn :to="`${tenantBase}/call-waiter`">
        <v-icon>mdi-bell-ring</v-icon>
        <span>{{ t('nav.callWaiterShort') }}</span>
      </v-btn>
    </v-bottom-navigation>
  </v-app>
</template>

<style scoped>
.app-bar__title {
  font-weight: 700;
  color: var(--bo-teal-deep);
  overflow: hidden;
}

.app-bar__brand {
  text-decoration: none;
  color: inherit;
  display: block;
  max-width: min(52vw, 220px);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.app-bar__brand--logo {
  display: flex;
  align-items: center;
  max-width: min(52vw, 180px);
}

.app-bar__logo {
  display: block;
  max-height: 36px;
  max-width: 100%;
  width: auto;
  object-fit: contain;
}

@media (min-width: 600px) {
  .app-bar__brand {
    max-width: 320px;
  }
}

.locale-trigger {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  min-height: 36px;
  padding: 0.35rem 0.65rem 0.35rem 0.55rem;
  border: 1px solid transparent;
  border-radius: 999px;
  background: color-mix(in srgb, var(--bo-teal) 9%, transparent);
  color: var(--bo-teal-deep);
  font: inherit;
  font-size: 0.8rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  cursor: pointer;
  transition:
    background 0.2s ease,
    border-color 0.2s ease,
    transform 0.2s ease;
}

.locale-trigger:hover,
.locale-trigger--open {
  background: color-mix(in srgb, var(--bo-teal) 16%, transparent);
  border-color: color-mix(in srgb, var(--bo-teal) 22%, transparent);
}

.locale-trigger__flag {
  width: 18px;
  height: 12px;
  object-fit: cover;
  border-radius: 2px;
  box-shadow: 0 0 0 1px rgba(20, 54, 66, 0.12);
  flex-shrink: 0;
}

.locale-trigger__chevron {
  opacity: 0.65;
  transition: transform 0.2s ease;
}

.locale-trigger--open .locale-trigger__chevron {
  transform: rotate(180deg);
}

.locale-menu {
  min-width: 11.5rem;
  padding: 0.35rem;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.92);
  border: 1px solid rgba(11, 110, 107, 0.12);
  box-shadow: 0 16px 40px rgba(20, 54, 66, 0.14);
  backdrop-filter: blur(12px);
}

.locale-menu__item {
  display: grid;
  grid-template-columns: 1.5rem 1fr auto;
  align-items: center;
  gap: 0.55rem;
  width: 100%;
  padding: 0.55rem 0.65rem;
  border: 0;
  border-radius: 10px;
  background: transparent;
  color: var(--bo-ink);
  font: inherit;
  text-align: left;
  cursor: pointer;
  transition: background 0.15s ease;
}

.locale-menu__item:hover {
  background: color-mix(in srgb, var(--bo-teal) 10%, transparent);
}

.locale-menu__item--active {
  background: color-mix(in srgb, var(--bo-teal) 14%, transparent);
  color: var(--bo-teal-deep);
}

.locale-menu__flag {
  width: 22px;
  height: 15px;
  object-fit: cover;
  border-radius: 3px;
  box-shadow: 0 0 0 1px rgba(20, 54, 66, 0.12);
  flex-shrink: 0;
}

.locale-menu__name {
  font-size: 0.92rem;
  font-weight: 500;
}

.locale-menu__check {
  color: var(--bo-teal);
}

.bottom-nav {
  padding-bottom: env(safe-area-inset-bottom, 0);
}
</style>
