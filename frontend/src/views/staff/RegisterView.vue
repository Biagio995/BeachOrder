<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { homePathForRole } from '@/utils/roleHome'
import { resetEcho } from '@/plugins/echo'
import api from '@/api/client'
import axios from 'axios'

const { t, locale } = useI18n()
const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const companyName = ref('')
const slug = ref('')
const slugTouched = ref(false)
const name = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const acceptTerms = ref(false)
const loading = ref(false)
const error = ref('')
const notice = ref('')
const fieldErrors = ref<Record<string, string>>({})

const checkoutPending = ref(false)

onMounted(async () => {
  if (route.query.checkout === 'canceled') {
    notice.value = t('subscription.checkoutCanceled')
    checkoutPending.value = auth.isAuthenticated
    await router.replace({ query: {} })
    return
  }

  if (auth.isAuthenticated && auth.isEmailVerified) {
    router.replace(homePathForRole(auth.user?.role, auth.user?.staff_position))
    return
  }
  if (auth.token) {
    await auth.logout()
  }
})

function slugify(value: string) {
  return value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 80)
}

watch(companyName, (value) => {
  if (!slugTouched.value) {
    slug.value = slugify(value)
  }
})

const canSubmit = computed(
  () =>
    companyName.value.trim().length > 1 &&
    name.value.trim().length > 1 &&
    email.value.includes('@') &&
    password.value.length >= 8 &&
    password.value === passwordConfirmation.value &&
    acceptTerms.value,
)

async function goToCheckout(url: string) {
  window.location.href = url
}

async function startCheckout() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.post<{ url: string }>('/admin/subscription/checkout')
    if (data.url) {
      await goToCheckout(data.url)
      return
    }
    error.value = t('subscription.checkoutError')
  } catch (e) {
    error.value = axios.isAxiosError(e)
      ? (e.response?.data?.message as string) || t('subscription.checkoutError')
      : t('subscription.checkoutError')
  } finally {
    loading.value = false
  }
}

async function submit() {
  loading.value = true
  error.value = ''
  fieldErrors.value = {}
  try {
    resetEcho()
    const data = await auth.register({
      company_name: companyName.value.trim(),
      slug: slug.value.trim() || undefined,
      name: name.value.trim(),
      email: email.value.trim(),
      password: password.value,
      password_confirmation: passwordConfirmation.value,
      default_locale: String(locale.value || 'it'),
      accept_terms: true,
    })

    if (data.checkout_url) {
      await goToCheckout(data.checkout_url)
      return
    }

    error.value = t('subscription.checkoutError')
  } catch (e: unknown) {
    if (axios.isAxiosError(e) && e.response?.status === 422) {
      const errors = e.response.data?.errors as Record<string, string[]> | undefined
      if (errors) {
        fieldErrors.value = Object.fromEntries(
          Object.entries(errors).map(([k, v]) => [k, v[0] || '']),
        )
      }
      error.value = t('auth.registerError')
    } else if (axios.isAxiosError(e) && e.response?.status === 502) {
      notice.value = t('auth.registerCheckoutPending')
      checkoutPending.value = true
    } else {
      error.value = axios.isAxiosError(e)
        ? (e.response?.data?.message as string) || t('common.error')
        : t('common.error')
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="page-shell register">
    <h1 class="display-font text-h4 mb-2" style="color: var(--bo-teal-deep)">
      {{ t('auth.registerTitle') }}
    </h1>
    <p class="text-body-2 text-medium-emphasis mb-4">{{ t('auth.registerLead') }}</p>

    <v-alert v-if="notice" type="warning" variant="tonal" class="mb-4">{{ notice }}</v-alert>

    <template v-if="checkoutPending">
      <v-btn
        block
        size="large"
        color="primary"
        :loading="loading"
        prepend-icon="mdi-credit-card-outline"
        class="mb-4"
        @click="startCheckout"
      >
        {{ t('auth.registerRetryCheckout') }}
      </v-btn>
    </template>

    <v-form v-else @submit.prevent="submit">
      <v-text-field
        v-model="companyName"
        :label="t('auth.companyName')"
        :hint="t('auth.companyNameHint')"
        persistent-hint
        :error-messages="fieldErrors.company_name"
        autocomplete="organization"
        class="mb-1"
      />
      <v-text-field
        v-model="slug"
        :label="t('auth.slug')"
        :hint="t('auth.slugHint')"
        persistent-hint
        :error-messages="fieldErrors.slug"
        @update:model-value="slugTouched = true"
        class="mb-1"
      />
      <v-text-field
        v-model="name"
        :label="t('auth.adminName')"
        :error-messages="fieldErrors.name"
        autocomplete="name"
      />
      <v-text-field
        v-model="email"
        :label="t('auth.email')"
        type="email"
        :error-messages="fieldErrors.email"
        autocomplete="email"
      />
      <v-text-field
        v-model="password"
        :label="t('auth.password')"
        type="password"
        :hint="t('auth.passwordHint')"
        persistent-hint
        :error-messages="fieldErrors.password"
        autocomplete="new-password"
        class="mb-1"
      />
      <v-text-field
        v-model="passwordConfirmation"
        :label="t('auth.passwordConfirm')"
        type="password"
        autocomplete="new-password"
      />

      <v-checkbox v-model="acceptTerms" :error-messages="fieldErrors.accept_terms" class="mb-2">
        <template #label>
          <span class="text-body-2">
            {{ t('legal.acceptTermsPrefix') }}
            <router-link to="/terms" target="_blank" class="register__link">{{ t('legal.termsTitle') }}</router-link>
            {{ t('legal.acceptTermsAnd') }}
            <router-link to="/privacy" target="_blank" class="register__link">{{ t('legal.privacyTitle') }}</router-link>
          </span>
        </template>
      </v-checkbox>

      <v-alert v-if="error" type="error" class="mb-3" density="comfortable">{{ error }}</v-alert>

      <v-btn
        type="submit"
        block
        size="large"
        color="primary"
        :loading="loading"
        :disabled="!canSubmit"
        prepend-icon="mdi-credit-card-outline"
      >
        {{ t('auth.registerSubmit') }}
      </v-btn>
    </v-form>

    <p class="text-body-2 text-center mt-6">
      {{ t('auth.hasAccount') }}
      <router-link to="/login" class="register__link">{{ t('auth.submit') }}</router-link>
    </p>
  </div>
</template>

<style scoped>
.register {
  max-width: 460px;
}

.register__link {
  color: var(--bo-teal-deep);
  font-weight: 600;
  text-decoration: none;
}

.register__link:hover {
  text-decoration: underline;
}
</style>
