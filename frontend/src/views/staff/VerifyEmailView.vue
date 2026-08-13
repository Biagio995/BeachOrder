<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { getApiErrorMessage } from '@/api/client'
import { homePathForRole } from '@/utils/roleHome'

const { t } = useI18n()
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const loading = ref(false)
const resending = ref(false)
const verifying = ref(false)
const error = ref('')
const verified = ref(false)
const sent = ref(false)
const notice = ref('')
const otpCode = ref('')

const hasLinkParams =
  !!route.query.id &&
  !!route.query.hash &&
  !!route.query.expires &&
  !!route.query.signature

async function verifyFromLink() {
  if (!hasLinkParams) return

  loading.value = true
  error.value = ''
  try {
    await auth.verifyEmail({
      id: String(route.query.id),
      hash: String(route.query.hash),
      expires: String(route.query.expires),
      signature: String(route.query.signature),
    })
    verified.value = true
  } catch (e) {
    error.value = getApiErrorMessage(e, t('common.error'))
  } finally {
    loading.value = false
  }
}

async function verifyOtp() {
  verifying.value = true
  error.value = ''
  try {
    await auth.verifyEmailWithOtp(otpCode.value.trim())
    verified.value = true
  } catch (e) {
    error.value = getApiErrorMessage(e, t('common.error'))
  } finally {
    verifying.value = false
  }
}

async function resend() {
  resending.value = true
  error.value = ''
  sent.value = false
  try {
    await auth.resendVerificationEmail()
    sent.value = true
  } catch (e) {
    error.value = getApiErrorMessage(e, t('common.error'))
  } finally {
    resending.value = false
  }
}

function continueToApp() {
  router.push(homePathForRole(auth.user?.role, auth.user?.staff_position))
}

async function goToLogin() {
  if (auth.isAuthenticated) {
    await auth.logout()
  }
  router.push('/login')
}

async function goToRegister() {
  await auth.logout()
  router.push('/register')
}

const canVerifyOtp = computed(() => otpCode.value.trim().length >= 4)

onMounted(async () => {
  if (route.query.checkout === 'success') {
    notice.value = t('subscription.checkoutSuccess')
    await router.replace({ query: {} })
  } else if (hasLinkParams) {
    await verifyFromLink()
  } else if (auth.token && !auth.user) {
    await auth.fetchMe()
  }
})
</script>

<template>
  <div class="page-shell" style="max-width: 420px">
    <h1 class="display-font text-h4 mb-2" style="color: var(--bo-teal-deep)">
      {{ t('auth.verifyTitle') }}
    </h1>

    <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-4" />

    <v-alert v-else-if="verified" type="success" class="mb-4" variant="tonal">
      {{ t('auth.verifyDone') }}
      <div class="mt-3">
        <v-btn color="primary" @click="continueToApp">{{ t('auth.verifyContinue') }}</v-btn>
      </div>
    </v-alert>

    <v-alert v-else-if="notice" type="success" class="mb-4" variant="tonal">
      {{ notice }}
    </v-alert>

    <template v-else>
      <p class="text-body-2 text-medium-emphasis mb-4">
        {{ hasLinkParams ? t('auth.verifyLinkInvalid') : t('auth.verifyLead') }}
      </p>
      <p v-if="auth.user?.email" class="text-body-2 mb-4">
        <strong>{{ auth.user.email }}</strong>
      </p>

      <v-alert v-if="sent" type="success" class="mb-4" variant="tonal">
        {{ t('auth.verifySent') }}
      </v-alert>
      <v-alert v-if="error" type="error" class="mb-4">{{ error }}</v-alert>

      <template v-if="auth.isAuthenticated && !auth.isEmailVerified">
        <v-otp-input
          v-model="otpCode"
          length="6"
          type="number"
          class="mb-2 justify-center"
        />
        <p class="text-caption text-medium-emphasis text-center mb-4">{{ t('auth.otpHint') }}</p>
        <v-btn
          block
          size="large"
          color="primary"
          class="mb-2"
          :loading="verifying"
          :disabled="!canVerifyOtp"
          @click="verifyOtp"
        >
          {{ t('auth.verifySubmit') }}
        </v-btn>
        <v-btn block size="large" variant="tonal" color="primary" :loading="resending" @click="resend">
          {{ t('auth.verifyResend') }}
        </v-btn>
      </template>
    </template>

    <p class="text-body-2 text-center mt-6">
      <a
        href="#"
        style="color: var(--bo-teal-deep); font-weight: 600; text-decoration: none"
        @click.prevent="goToLogin"
      >
        {{ t('auth.backToLogin') }}
      </a>
    </p>
    <p v-if="auth.isAuthenticated && !auth.isEmailVerified" class="text-body-2 text-center mt-3">
      <a
        href="#"
        style="color: var(--bo-teal-deep); font-weight: 600; text-decoration: none"
        @click.prevent="goToRegister"
      >
        {{ t('auth.registerDifferentAccount') }}
      </a>
    </p>
  </div>
</template>
