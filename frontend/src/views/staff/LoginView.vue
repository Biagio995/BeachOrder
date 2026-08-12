<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { getApiErrorMessage } from '@/api/client'
import { resetEcho } from '@/plugins/echo'
import { homePathForRole } from '@/utils/roleHome'

const { t } = useI18n()
const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

// Preview/DevTunnel uses production build — still show demo tenant hints when configured.
const showDemoAccounts =
  import.meta.env.DEV || Boolean(import.meta.env.VITE_DEMO_TENANT)
const showDevTunnelWarning =
  import.meta.env.DEV &&
  typeof window !== 'undefined' &&
  window.location.hostname.includes('devtunnels.ms')
const email = ref(showDemoAccounts ? 'admin@azure.test' : '')
const password = ref(showDemoAccounts ? 'password' : '')
const tenant = ref('')
const loading = ref(false)
const error = ref('')

onMounted(() => {
  if (auth.isAuthenticated) {
    router.replace(homePathForRole(auth.user?.role))
  }
})

async function submit() {
  loading.value = true
  error.value = ''
  try {
    resetEcho()
    await auth.login(email.value, password.value, tenant.value || undefined)
    const redirect = (route.query.redirect as string) || homePathForRole(auth.user?.role)
    router.push(redirect)
  } catch (e) {
    error.value = getApiErrorMessage(e, t('common.error'))
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="page-shell" style="max-width: 420px">
    <h1 class="display-font text-h4 mb-6" style="color: var(--bo-teal-deep)">{{ t('auth.title') }}</h1>
    <v-form @submit.prevent="submit">
      <v-text-field v-model="email" :label="t('auth.email')" type="email" autocomplete="username" />
      <v-text-field v-model="password" :label="t('auth.password')" type="password" autocomplete="current-password" />
      <v-text-field v-model="tenant" :label="t('auth.tenant')" :hint="t('auth.tenantHint')" persistent-hint />
      <v-alert v-if="showDevTunnelWarning" type="warning" variant="tonal" class="mb-3">
        DevTunnel non supporta la modalità dev di Vite (pagina bianca dopo il login).
        Ferma <code>npm run dev</code> e avvia <code>npm run dev:tunnel</code> nella cartella frontend.
      </v-alert>
      <v-alert v-if="error" type="error" class="mb-3">{{ error }}</v-alert>
      <v-btn type="submit" block size="large" color="primary" :loading="loading">{{ t('auth.submit') }}</v-btn>
    </v-form>
    <p class="text-body-2 text-center mt-4">
      <router-link to="/forgot-password" style="color: var(--bo-teal-deep); font-weight: 600; text-decoration: none">
        {{ t('auth.forgotLink') }}
      </router-link>
    </p>
    <p class="text-body-2 text-center mt-4">
      {{ t('auth.noAccount') }}
      <router-link to="/register" style="color: var(--bo-teal-deep); font-weight: 600; text-decoration: none">
        {{ t('auth.registerLink') }}
      </router-link>
    </p>
    <div v-if="showDemoAccounts" class="text-caption text-medium-emphasis mt-6">
      <div><strong>Azure Beach</strong> (slug: azure-beach)</div>
      <div>admin@azure.test · manager@azure.test · staff@azure.test</div>
      <div class="mt-2"><strong>Sunset Lido</strong> (slug: sunset-lido)</div>
      <div>admin@sunset.test · manager@sunset.test · staff@sunset.test</div>
      <div class="mt-2">password: password</div>
    </div>
  </div>
</template>
