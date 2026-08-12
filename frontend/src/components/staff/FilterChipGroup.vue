<script setup lang="ts">
export type FilterChipOption = {
  value: string
  label: string
}

const selected = defineModel<string[]>({ required: true })

defineProps<{
  options: FilterChipOption[]
}>()

/** Keep at least one selected (staff board filters). */
function toggle(value: string) {
  const set = new Set(selected.value)
  if (set.has(value)) {
    if (set.size <= 1) return
    set.delete(value)
  } else {
    set.add(value)
  }
  selected.value = [...set]
}
</script>

<template>
  <div class="chip-scroll">
    <v-chip
      v-for="opt in options"
      :key="opt.value"
      :color="selected.includes(opt.value) ? 'primary' : undefined"
      :variant="selected.includes(opt.value) ? 'flat' : 'outlined'"
      filter
      @click="toggle(opt.value)"
    >
      {{ opt.label }}
    </v-chip>
  </div>
</template>
