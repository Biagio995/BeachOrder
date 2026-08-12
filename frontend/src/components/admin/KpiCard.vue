<script setup lang="ts">
withDefaults(
  defineProps<{
    label: string
    value: string | number
    hint?: string
    display?: boolean
    compact?: boolean
  }>(),
  {
    hint: '',
    display: false,
    compact: false,
  },
)
</script>

<template>
  <div class="kpi-card">
    <div class="kpi-card__label">{{ label }}</div>
    <div
      class="kpi-card__value"
      :class="{
        'display-font': display,
        'kpi-card__value--sm': compact,
      }"
    >
      {{ value }}
    </div>
    <div v-if="hint" class="kpi-card__hint">{{ hint }}</div>
  </div>
</template>

<style scoped>
.kpi-card {
  padding: 14px 16px;
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.72);
  border: 1px solid rgba(11, 110, 107, 0.14);
  height: 100%;
}

.kpi-card__label {
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: rgba(20, 54, 66, 0.55);
  font-weight: 600;
}

.kpi-card__value {
  margin-top: 4px;
  font-size: 1.55rem;
  font-weight: 700;
  color: var(--bo-teal-deep);
  line-height: 1.15;
}

.kpi-card__value--sm {
  font-size: 1.2rem;
}

.kpi-card__hint {
  margin-top: 4px;
  font-size: 0.8rem;
  color: rgba(20, 54, 66, 0.55);
}
</style>
