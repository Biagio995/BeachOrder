<script setup lang="ts">
import { computed } from 'vue'
import { resolvePublicAssetUrl } from '@/utils/publicAssetUrl'

const props = defineProps<{
  label: string
  hint?: string
  previewUrl?: string | null
  uploading?: boolean
  inputId: string
  aspect?: 'square' | 'wide'
}>()

const emit = defineEmits<{
  upload: [file: File]
  remove: []
}>()

const resolvedPreview = computed(() => resolvePublicAssetUrl(props.previewUrl))

function onPick(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (file) emit('upload', file)
}

function openPicker() {
  document.getElementById(props.inputId)?.click()
}
</script>

<template>
  <div class="branding-asset">
    <div class="text-caption text-medium-emphasis mb-1">{{ label }}</div>
    <div
      class="branding-asset__preview"
      :class="{
        'branding-asset__preview--wide': aspect === 'wide',
        'branding-asset__preview--empty': !resolvedPreview,
      }"
    >
      <v-img v-if="resolvedPreview" :src="resolvedPreview" cover width="100%" height="100%" />
      <v-icon v-else size="32" color="medium-emphasis">mdi-image-outline</v-icon>
    </div>
    <p v-if="hint" class="text-caption text-medium-emphasis mt-1 mb-2">{{ hint }}</p>
    <div class="d-flex flex-wrap ga-2 mt-2">
      <v-btn size="small" variant="tonal" color="primary" :loading="uploading" @click="openPicker">
        {{ resolvedPreview ? $t('admin.replaceImage') : $t('admin.uploadImage') }}
      </v-btn>
      <v-btn
        v-if="resolvedPreview"
        size="small"
        variant="text"
        color="error"
        :disabled="uploading"
        @click="emit('remove')"
      >
        {{ $t('admin.removeImage') }}
      </v-btn>
    </div>
    <input :id="inputId" type="file" accept="image/*" class="d-none" @change="onPick" />
  </div>
</template>

<style scoped>
.branding-asset__preview {
  width: 120px;
  height: 120px;
  border-radius: 12px;
  overflow: hidden;
  border: 1px dashed rgba(11, 110, 107, 0.28);
  background: rgba(232, 244, 243, 0.55);
  display: grid;
  place-items: center;
}

.branding-asset__preview--wide {
  width: 100%;
  max-width: 360px;
  height: 140px;
}

.branding-asset__preview--empty {
  color: rgba(20, 54, 66, 0.45);
}

.branding-asset__preview :deep(.v-img) {
  width: 100%;
  height: 100%;
}
</style>
