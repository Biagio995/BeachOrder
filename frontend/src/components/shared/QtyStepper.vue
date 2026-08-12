<script setup lang="ts">
const props = withDefaults(
  defineProps<{
    modelValue: number
    min?: number
    max?: number
    compact?: boolean
    decreaseLabel?: string
    increaseLabel?: string
  }>(),
  {
    min: 1,
    max: 99,
    compact: false,
    decreaseLabel: 'Decrease',
    increaseLabel: 'Increase',
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: number]
  decrease: []
  increase: []
}>()

function dec() {
  emit('update:modelValue', Math.max(props.min, props.modelValue - 1))
  emit('decrease')
}

function inc() {
  emit('update:modelValue', Math.min(props.max, props.modelValue + 1))
  emit('increase')
}
</script>

<template>
  <div class="qty-stepper" :class="{ 'qty-stepper--compact': compact }">
    <v-btn
      icon="mdi-minus"
      :size="compact ? 'x-small' : 'small'"
      variant="tonal"
      :aria-label="decreaseLabel"
      :disabled="modelValue <= min"
      @click="dec"
    />
    <span class="qty-stepper__value">{{ modelValue }}</span>
    <v-btn
      icon="mdi-plus"
      :size="compact ? 'x-small' : 'small'"
      variant="tonal"
      color="primary"
      :aria-label="increaseLabel"
      :disabled="modelValue >= max"
      @click="inc"
    />
  </div>
</template>

<style scoped>
.qty-stepper {
  display: inline-flex;
  align-items: center;
  gap: 0.75rem;
}

.qty-stepper--compact {
  gap: 0.4rem;
}

.qty-stepper__value {
  min-width: 1.5rem;
  text-align: center;
  font-weight: 700;
}

.qty-stepper--compact .qty-stepper__value {
  min-width: 1.25rem;
}
</style>
