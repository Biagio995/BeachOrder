<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useUiStore } from '@/stores/ui'

const { t } = useI18n()
const ui = useUiStore()
</script>

<template>
  <v-dialog
    :model-value="ui.confirmOpen"
    max-width="440"
    persistent
    @update:model-value="(v) => !v && ui.resolveConfirm(false)"
  >
    <v-card class="confirm-dialog">
      <v-card-title class="confirm-dialog__title display-font">
        {{ ui.confirmOptions.title }}
      </v-card-title>
      <v-card-text class="confirm-dialog__body text-body-1">
        {{ ui.confirmOptions.message }}
      </v-card-text>
      <v-card-actions class="px-4 pb-4">
        <v-spacer />
        <v-btn variant="text" color="primary" @click="ui.resolveConfirm(false)">
          {{ ui.confirmOptions.cancelLabel || t('common.cancel') }}
        </v-btn>
        <v-btn
          :color="ui.confirmOptions.danger ? 'error' : 'primary'"
          variant="flat"
          @click="ui.resolveConfirm(true)"
        >
          {{ ui.confirmOptions.confirmLabel || t('common.confirm') }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.confirm-dialog {
  border: 1px solid rgba(11, 110, 107, 0.14);
}

.confirm-dialog__title {
  color: var(--bo-teal-deep);
  font-size: 1.35rem !important;
  padding-bottom: 0.25rem;
}

.confirm-dialog__body {
  color: var(--bo-ink);
  opacity: 0.88;
  white-space: pre-line;
}
</style>
