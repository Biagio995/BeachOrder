<script setup lang="ts">
defineProps<{
  title: string
  subtitle?: string
}>()
</script>

<template>
  <div class="admin-page-header d-flex justify-space-between align-start flex-wrap ga-2 mb-4">
    <div class="min-w-0">
      <h1 class="display-font text-h4 mb-0" style="color: var(--bo-teal-deep)">{{ title }}</h1>
      <p v-if="subtitle" class="text-body-2 text-medium-emphasis mb-0 mt-1">{{ subtitle }}</p>
    </div>
    <div class="d-flex flex-wrap ga-2 align-center">
      <slot name="actions" />
    </div>
  </div>
</template>
