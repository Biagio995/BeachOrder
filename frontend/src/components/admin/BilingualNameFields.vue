<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const nameEl = defineModel<string>('nameEl', { required: true })
const nameEn = defineModel<string>('nameEn', { required: true })

const props = withDefaults(
  defineProps<{
    labelEl?: string
    labelEn?: string
    hint?: string
  }>(),
  {
    labelEl: undefined,
    labelEn: undefined,
    hint: undefined,
  },
)

const { t } = useI18n()

const resolvedLabelEl = computed(() => props.labelEl || t('admin.nameEl'))
const resolvedLabelEn = computed(() => props.labelEn || t('admin.nameEn'))
const resolvedHint = computed(() => props.hint || t('admin.nameLocaleHint'))
</script>

<template>
  <div>
    <v-row dense>
      <v-col cols="12" md="6">
        <v-text-field v-model="nameEl" :label="resolvedLabelEl" :hint="resolvedHint" persistent-hint />
      </v-col>
      <v-col cols="12" md="6">
        <v-text-field v-model="nameEn" :label="resolvedLabelEn" :hint="resolvedHint" persistent-hint />
      </v-col>
    </v-row>
  </div>
</template>
