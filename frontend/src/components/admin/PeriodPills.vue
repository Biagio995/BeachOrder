<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { REPORT_PERIOD_OPTIONS } from '@/constants/periods'

const days = defineModel<number>({ required: true })

const props = withDefaults(
  defineProps<{
    options?: readonly { value: number; labelKey: string }[]
  }>(),
  {
    options: () => [...REPORT_PERIOD_OPTIONS],
  },
)

const { t } = useI18n()
</script>

<template>
  <div class="period-pills" role="group">
    <button
      v-for="opt in props.options"
      :key="opt.value"
      type="button"
      class="period-pills__btn"
      :class="{ 'period-pills__btn--active': days === opt.value }"
      :aria-pressed="days === opt.value"
      @click="days = opt.value"
    >
      {{ t(opt.labelKey) }}
    </button>
  </div>
</template>
