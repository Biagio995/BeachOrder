<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { getApiErrorMessage } from '@/api/client'

const { t } = useI18n()
const auth = useAuthStore()
const router = useRouter()

const step = ref<'email' | 'code'>('email')
const email = ref('')
const code = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const loading = ref(false)
const error = ref('')
const done = ref(false)

const canSubmitCode = computed(
  () =>
    email.value.includes('@') &&
    code.value.trim().length >= 4 &&
    password.value.length >= 8 &&
    password.value === passwordConfirmation.value,
)

async function requestCode() {
  loading.value = true
  error.value = ''
  try {
    await auth.forgotPassword(email.value.trim())
    step.value = 'code'
  } catch (e) {
    error.value = getApiErrorMessage(e, t('common.error'))
  } finally {
    loading.value = false
  }
}

async function submitReset() {
  loading.value = true
  error.value = ''
  try {
    await auth.resetPassword({
      email: email.value.trim(),
      code: code.value.trim(),
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    done.value = true
  } catch (e) {
    error.value = getApiErrorMessage(e, t('common.error'))
  } finally {
    loading.value = false
  }
}

function goLogin() {
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="page-shell" style="max-width: 420px">
    <h1 class="display-font text-h4 mb-2" style="color: var(--bo-teal-deep)">
      {{ t('auth.forgotTitle') }}
    </h1>
    <p class="text-body-2 text-medium-emphasis mb-6">
      {{ step === 'email' ? t('auth.forgotLead') : t('auth.forgotCodeLead') }}
    </p>

    <v-alert v-if="done" type="success" class="mb-4" variant="tonal">
      {{ t('auth.resetDone') }}
      <div class="mt-3">
        <v-btn color="primary" @click="goLogin">{{ t('auth.submit') }}</v-btn>
      </div>
    </v-alert>

    <v-form v-else-if="step === 'email'" @submit.prevent="requestCode">
      <v-text-field
        v-model="email"
        :label="t('auth.email')"
        type="email"
        autocomplete="email"
        required
      />
      <v-alert v-if="error" type="error" class="mb-3">{{ error }}</v-alert>
      <v-btn type="submit" block size="large" color="primary" :loading="loading" :disabled="!email.includes('@')">
        {{ t('auth.forgotSubmit') }}
      </v-btn>
    </v-form>

    <v-form v-else @submit.prevent="submitReset">
      <v-alert type="info" class="mb-4" variant="tonal">
        {{ t('auth.forgotSent') }}
      </v-alert>
      <v-text-field v-model="email" :label="t('auth.email')" type="email" autocomplete="username" />
      <v-otp-input
        v-model="code"
        length="6"
        type="number"
        class="mb-4 justify-center"
      />
      <p class="text-caption text-medium-emphasis text-center mb-4">{{ t('auth.otpHint') }}</p>
      <v-text-field
        v-model="password"
        :label="t('auth.password')"
        type="password"
        autocomplete="new-password"
        :hint="t('auth.passwordHint')"
        persistent-hint
      />
      <v-text-field
        v-model="passwordConfirmation"
        :label="t('auth.passwordConfirm')"
        type="password"
        autocomplete="new-password"
      />
      <v-alert v-if="error" type="error" class="mb-3">{{ error }}</v-alert>
      <v-btn type="submit" block size="large" color="primary" :loading="loading" :disabled="!canSubmitCode">
        {{ t('auth.resetSubmit') }}
      </v-btn>
      <v-btn class="mt-2" block variant="text" :disabled="loading" @click="requestCode">
        {{ t('auth.otpResend') }}
      </v-btn>
    </v-form>

    <p class="text-body-2 text-center mt-6">
      <router-link to="/login" style="color: var(--bo-teal-deep); font-weight: 600; text-decoration: none">
        {{ t('auth.backToLogin') }}
      </router-link>
    </p>
  </div>
</template>
