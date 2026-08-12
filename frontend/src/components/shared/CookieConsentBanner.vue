<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useConsentStore } from '@/stores/consent'

const { t } = useI18n()
const consent = useConsentStore()
const visible = ref(false)

onMounted(() => {
  if (!consent.hasAnswered) {
    visible.value = true
  }
})

function accept() {
  consent.accept()
  visible.value = false
}

function decline() {
  consent.decline()
  visible.value = false
}
</script>

<template>
  <v-snackbar
    v-model="visible"
    location="bottom"
    :timeout="-1"
    color="surface"
    class="cookie-banner"
    elevation="8"
  >
    <div class="cookie-banner__inner">
      <p class="cookie-banner__text">{{ t('legal.cookieBanner') }}</p>
      <div class="cookie-banner__actions">
        <v-btn size="small" variant="text" @click="decline">{{ t('legal.cookieDecline') }}</v-btn>
        <v-btn size="small" variant="text" :to="'/cookies'">{{ t('legal.cookiesTitle') }}</v-btn>
        <v-btn size="small" color="primary" variant="flat" @click="accept">{{ t('legal.cookieAccept') }}</v-btn>
      </div>
    </div>
  </v-snackbar>
</template>

<style scoped>
.cookie-banner__inner {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  max-width: 640px;
}

@media (min-width: 600px) {
  .cookie-banner__inner {
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
  }
}

.cookie-banner__text {
  margin: 0;
  font-size: 0.875rem;
  line-height: 1.45;
  color: rgba(20, 54, 66, 0.85);
}

.cookie-banner__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
  flex-shrink: 0;
}
</style>
