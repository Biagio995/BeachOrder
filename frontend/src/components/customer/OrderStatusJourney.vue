<script setup lang="ts">
import { useI18n } from 'vue-i18n'

defineProps<{
  steps: string[]
  currentIdx: number
  progress: number
}>()

const { t } = useI18n()
</script>

<template>
  <div class="journey mb-6">
    <div class="journey__track">
      <div class="journey__fill" :style="{ width: `${progress}%` }" />
    </div>
    <ol class="journey__steps">
      <li
        v-for="(step, idx) in steps"
        :key="step"
        class="journey__step"
        :class="{
          'journey__step--done': idx < currentIdx,
          'journey__step--current': idx === currentIdx,
        }"
      >
        <span class="journey__dot" />
        <span class="journey__label">{{ t(`order.statuses.${step}`) }}</span>
      </li>
    </ol>
  </div>
</template>

<style scoped>
.journey__track {
  height: 6px;
  border-radius: 999px;
  background: rgba(11, 110, 107, 0.12);
  overflow: hidden;
  margin-bottom: 1rem;
}

.journey__fill {
  height: 100%;
  border-radius: inherit;
  background: linear-gradient(90deg, var(--bo-teal), var(--bo-coral));
  transition: width 0.55s ease;
}

.journey__steps {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.65rem;
}

.journey__step {
  display: grid;
  grid-template-columns: 18px 1fr;
  gap: 0.75rem;
  align-items: center;
  opacity: 0.45;
  transition: opacity 0.3s ease, transform 0.3s ease;
}

.journey__step--done,
.journey__step--current {
  opacity: 1;
}

.journey__step--current {
  transform: translateX(2px);
}

.journey__dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: rgba(11, 110, 107, 0.25);
  justify-self: center;
}

.journey__step--done .journey__dot {
  background: var(--bo-teal);
}

.journey__step--current .journey__dot {
  background: var(--bo-coral);
  box-shadow: 0 0 0 6px rgba(224, 122, 95, 0.2);
  animation: pulse-dot 1.6s ease infinite;
}

.journey__label {
  font-weight: 500;
}

.journey__step--current .journey__label {
  font-weight: 700;
  color: var(--bo-teal-deep);
}

@keyframes pulse-dot {
  0%,
  100% {
    box-shadow: 0 0 0 4px rgba(224, 122, 95, 0.18);
  }
  50% {
    box-shadow: 0 0 0 8px rgba(224, 122, 95, 0.12);
  }
}
</style>
