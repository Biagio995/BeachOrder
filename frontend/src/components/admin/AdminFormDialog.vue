<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'

const open = defineModel<boolean>({ required: true })

withDefaults(
  defineProps<{
    title: string
    saving?: boolean
    canSave?: boolean
    maxWidth?: number | string
    hideActions?: boolean
  }>(),
  {
    saving: false,
    canSave: true,
    maxWidth: 640,
    hideActions: false,
  },
)

const emit = defineEmits<{
  save: []
  close: []
}>()

const { t } = useI18n()
const { smAndDown } = useDisplay()

function onClose() {
  open.value = false
  emit('close')
}
</script>

<template>
  <v-dialog
    v-model="open"
    :fullscreen="smAndDown"
    :max-width="smAndDown ? undefined : maxWidth"
    persistent
  >
    <v-card>
      <v-card-title class="d-flex justify-space-between align-center">
        <span class="display-font text-h6" style="color: var(--bo-teal-deep)">{{ title }}</span>
        <v-btn icon="mdi-close" variant="text" density="comfortable" @click="onClose" />
      </v-card-title>
      <v-card-text>
        <slot />
      </v-card-text>
      <v-card-actions v-if="!hideActions" class="px-4 pb-4">
        <slot name="actions-left" />
        <v-spacer />
        <v-btn variant="text" @click="onClose">{{ t('common.close') }}</v-btn>
        <v-btn color="primary" :loading="saving" :disabled="!canSave" @click="emit('save')">
          {{ t('admin.save') }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
