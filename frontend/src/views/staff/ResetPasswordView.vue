<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { getApiErrorMessage } from '@/api/client'

const { t } = useI18n()
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const email = ref(String(route.query.email || ''))
const code = ref(String(route.query.code || ''))
const password = ref('')
const passwordConfirmation = ref('')
const loading = ref(false)
const error = ref('')
const done = ref(false)

const canSubmit = computed(
  () =>
    code.value.trim().length >= 4 &&
    email.value.includes('@') &&
    password.value.length >= 8 &&
    password.value === passwordConfirmation.value,
)

async function submit() {
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
      {{ t('auth.resetTitle') }}
    </h1>
    <p class="text-body-2 text-medium-emphasis mb-6">{{ t('auth.resetLead') }}</p>

    <v-alert v-if="done" type="success" class="mb-4" variant="tonal">
      {{ t('auth.resetDone') }}
      <div class="mt-3">
        <v-btn color="primary" @click="goLogin">{{ t('auth.submit') }}</v-btn>
      </div>
    </v-alert>

    <v-form v-else @submit.prevent="submit">
      <v-text-field v-model="email" :label="t('auth.email')" type="email" autocomplete="username" />
      <v-otp-input v-model="code" length="6" type="number" class="mb-4 justify-center" />
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
      <v-btn type="submit" block size="large" color="primary" :loading="loading" :disabled="!canSubmit">
        {{ t('auth.resetSubmit') }}
      </v-btn>
    </v-form>

    <p class="text-body-2 text-center mt-6">
      <router-link to="/forgot-password" style="color: var(--bo-teal-deep); font-weight: 600; text-decoration: none">
        {{ t('auth.forgotLink') }}
      </router-link>
    </p>
    <p class="text-body-2 text-center mt-3">
      <router-link to="/login" style="color: var(--bo-teal-deep); font-weight: 600; text-decoration: none">
        {{ t('auth.backToLogin') }}
      </router-link>
    </p>
  </div>
</template>
