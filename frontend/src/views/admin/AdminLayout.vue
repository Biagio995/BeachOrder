<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import ChipNav from '@/components/admin/ChipNav.vue'
import api from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import type { SubscriptionInfo } from '@/types'

const { t } = useI18n()
const route = useRoute()
const auth = useAuthStore()

const subscription = ref<SubscriptionInfo | null>(null)

const managedTenantSlug = computed(() => localStorage.getItem('bo_tenant_slug') || '')
const isPlatformPage = computed(() => route.name === 'admin-tenants')

const tenantLinks = computed(() => [
  { to: '/admin', label: t('admin.dashboard'), exact: true },
  { to: '/admin/analytics', label: t('admin.analytics.title') },
  { to: '/admin/products', label: t('admin.products') },
  { to: '/admin/categories', label: t('admin.categories') },
  { to: '/admin/tags', label: t('admin.tags') },
  { to: '/admin/locations', label: t('admin.locations') },
  { to: '/admin/users', label: t('admin.users') },
  { to: '/admin/settings', label: t('admin.settings') },
])

const showTenantNav = computed(() => {
  if (!auth.isSuperAdmin) return true
  return !isPlatformPage.value && !!managedTenantSlug.value
})

const subscriptionBanner = computed(() => {
  if (auth.isSuperAdmin || isPlatformPage.value) return null
  if (!subscription.value) return null
  if (subscription.value.status === 'past_due') return t('subscription.bannerPastDue')
  if (!subscription.value.grants_access) return t('subscription.bannerInactive')
  return null
})

onMounted(async () => {
  if (auth.isSuperAdmin) return
  try {
    const { data } = await api.get<SubscriptionInfo>('/admin/subscription')
    subscription.value = data
  } catch {
    subscription.value = null
  }
})
</script>

<template>
  <div class="page-shell admin-shell">
    <div
      v-if="auth.isSuperAdmin && managedTenantSlug && !isPlatformPage"
      class="manage-bar mb-3"
    >
      <span class="text-body-2 text-medium-emphasis">
        {{ t('platform.managing') }}: <strong>{{ managedTenantSlug }}</strong>
      </span>
      <v-btn to="/admin/tenants" size="small" variant="text" color="primary" prepend-icon="mdi-arrow-left">
        {{ t('platform.backToOverview') }}
      </v-btn>
    </div>

    <div v-else-if="!auth.isSuperAdmin && auth.user?.tenant?.name" class="mb-3 text-medium-emphasis text-body-2">
      {{ t('admin.tenants') }}: <strong>{{ auth.user.tenant.name }}</strong>
    </div>

    <v-alert
      v-if="subscriptionBanner"
      :type="subscription?.status === 'past_due' ? 'warning' : 'info'"
      variant="tonal"
      class="mb-3"
      prominent
    >
      {{ subscriptionBanner }}
      <template #append>
        <v-btn
          to="/admin/settings/subscription"
          size="small"
          variant="flat"
          :color="subscription?.status === 'past_due' ? 'warning' : 'primary'"
        >
          {{ t('admin.settingsSubscription') }}
        </v-btn>
      </template>
    </v-alert>

    <ChipNav v-if="showTenantNav" :links="tenantLinks" />

    <router-view />
  </div>
</template>

<style scoped>
.manage-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  padding: 8px 0;
}
</style>
