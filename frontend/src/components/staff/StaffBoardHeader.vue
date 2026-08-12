<script setup lang="ts">
import { useI18n } from 'vue-i18n'

withDefaults(
  defineProps<{
    title: string
    live?: boolean
    loading?: boolean
    subtitle?: string
  }>(),
  {
    live: false,
    loading: false,
    subtitle: '',
  },
)

defineEmits<{
  refresh: []
}>()

const { t } = useI18n()
</script>

<template>
  <div class="staff-board-header d-flex justify-space-between align-start flex-wrap ga-2 mb-4">
    <div class="min-w-0">
      <h1 class="display-font staff-title mb-1" style="color: var(--bo-teal-deep)">{{ title }}</h1>
      <div v-if="subtitle || $slots.meta" class="text-body-2 text-medium-emphasis d-flex align-center flex-wrap ga-2">
        <span v-if="subtitle" class="text-truncate">{{ subtitle }}</span>
        <v-chip size="x-small" :color="live ? 'success' : 'warning'" variant="tonal" label>
          <span class="live-dot mr-1" :class="{ 'live-dot--on': live }" />
          {{ live ? t('staff.live') : t('staff.polling') }}
        </v-chip>
        <slot name="meta" />
      </div>
    </div>
    <v-btn
      icon="mdi-refresh"
      variant="tonal"
      :loading="loading"
      :aria-label="t('common.refresh')"
      @click="$emit('refresh')"
    />
  </div>
</template>

<style scoped>
.live-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: currentColor;
  display: inline-block;
  opacity: 0.45;
}

.live-dot--on {
  opacity: 1;
  animation: pulse 1.4s ease-in-out infinite;
}

@keyframes pulse {
  0%,
  100% {
    opacity: 0.45;
  }
  50% {
    opacity: 1;
  }
}
</style>
