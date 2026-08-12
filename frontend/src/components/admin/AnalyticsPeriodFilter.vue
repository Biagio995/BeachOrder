<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  ANALYTICS_PERIOD_OPTIONS,
  DEFAULT_ANALYTICS_PERIOD,
  type AnalyticsPeriod,
} from '@/constants/analyticsPeriods'

const period = defineModel<AnalyticsPeriod>('period', { default: DEFAULT_ANALYTICS_PERIOD })
const from = defineModel<string>('from', { default: '' })
const to = defineModel<string>('to', { default: '' })

const { t } = useI18n()

const today = new Date().toISOString().slice(0, 10)
const customFrom = ref(from.value || today)
const customTo = ref(to.value || today)

const isCustom = computed(() => period.value === 'custom')

watch([customFrom, customTo], () => {
  if (!isCustom.value) return
  from.value = customFrom.value
  to.value = customTo.value
})

watch(period, (next) => {
  if (next === 'custom') {
    customFrom.value = from.value || today
    customTo.value = to.value || today
    from.value = customFrom.value
    to.value = customTo.value
  } else {
    from.value = ''
    to.value = ''
  }
})

function select(next: AnalyticsPeriod) {
  period.value = next
}
</script>

<template>
  <div class="analytics-filter">
    <div class="analytics-filter__pills" role="group" :aria-label="t('admin.analytics.periodLabel')">
      <button
        v-for="opt in ANALYTICS_PERIOD_OPTIONS"
        :key="opt.value"
        type="button"
        class="analytics-filter__btn"
        :class="{ 'analytics-filter__btn--active': period === opt.value }"
        :aria-pressed="period === opt.value"
        @click="select(opt.value)"
      >
        {{ t(opt.labelKey) }}
      </button>
    </div>

    <div v-if="isCustom" class="analytics-filter__custom">
      <v-text-field
        v-model="customFrom"
        type="date"
        :label="t('admin.analytics.from')"
        density="compact"
        hide-details
        :max="customTo"
      />
      <v-text-field
        v-model="customTo"
        type="date"
        :label="t('admin.analytics.to')"
        density="compact"
        hide-details
        :min="customFrom"
        :max="today"
      />
    </div>
  </div>
</template>

<style scoped>
.analytics-filter {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.analytics-filter__pills {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}

.analytics-filter__btn {
  border: 1px solid rgba(11, 110, 107, 0.22);
  background: rgba(255, 255, 255, 0.65);
  color: rgba(20, 54, 66, 0.78);
  border-radius: 999px;
  padding: 0.35rem 0.85rem;
  font-size: 0.82rem;
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s, color 0.15s;
}

.analytics-filter__btn--active {
  background: var(--bo-teal-deep);
  border-color: var(--bo-teal-deep);
  color: #fff;
}

.analytics-filter__custom {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
  max-width: 420px;
}

@media (max-width: 480px) {
  .analytics-filter__custom {
    grid-template-columns: 1fr;
  }
}
</style>
