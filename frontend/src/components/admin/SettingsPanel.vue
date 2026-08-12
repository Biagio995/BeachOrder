<script setup lang="ts">
defineProps<{
  saved?: boolean
}>()
</script>

<template>
  <div>
    <v-alert v-if="saved" type="success" class="mb-4" density="comfortable">
      <slot name="saved">Saved</slot>
    </v-alert>
    <div v-if="$slots.summary" class="summary-panel pa-4 mb-4">
      <slot name="summary" />
    </div>
    <div class="summary-panel pa-4">
      <slot />
      <div v-if="$slots.actions" class="d-flex justify-end mt-2">
        <slot name="actions" />
      </div>
    </div>
  </div>
</template>
