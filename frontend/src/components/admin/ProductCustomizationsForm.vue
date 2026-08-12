<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BilingualNameFields from '@/components/admin/BilingualNameFields.vue'
import {
  emptyAddon,
  emptyAddonGroup,
  emptyVariantGroup,
  emptyVariantOption,
  type AddonGroupFormRow,
  type VariantGroupFormRow,
} from '@/utils/productCustomizations'

const variantGroups = defineModel<VariantGroupFormRow[]>('variantGroups', { required: true })
const addonGroups = defineModel<AddonGroupFormRow[]>('addonGroups', { required: true })

const { t } = useI18n()

const variantCount = computed(() =>
  variantGroups.value.reduce((sum, g) => sum + g.options.length, 0),
)
const addonCount = computed(() =>
  addonGroups.value.reduce((sum, g) => sum + g.addons.length, 0),
)

function addVariantGroup() {
  variantGroups.value.push(emptyVariantGroup())
}
function removeVariantGroup(index: number) {
  variantGroups.value.splice(index, 1)
}
function addVariantOption(groupIndex: number) {
  variantGroups.value[groupIndex].options.push(emptyVariantOption())
}
function removeVariantOption(groupIndex: number, optionIndex: number) {
  variantGroups.value[groupIndex].options.splice(optionIndex, 1)
}

function addAddonGroup() {
  addonGroups.value.push(emptyAddonGroup())
}
function removeAddonGroup(index: number) {
  addonGroups.value.splice(index, 1)
}
function addAddon(groupIndex: number) {
  addonGroups.value[groupIndex].addons.push(emptyAddon())
}
function removeAddon(groupIndex: number, addonIndex: number) {
  addonGroups.value[groupIndex].addons.splice(addonIndex, 1)
}
</script>

<template>
  <div class="customizations">
    <section class="customizations__section">
      <div class="d-flex align-center justify-space-between mb-2">
        <div>
          <div class="font-weight-medium">{{ t('admin.variants') }}</div>
          <div class="text-caption text-medium-emphasis">{{ t('admin.variantsHint') }}</div>
        </div>
        <v-btn size="small" variant="tonal" color="primary" prepend-icon="mdi-plus" @click="addVariantGroup">
          {{ t('admin.addVariantGroup') }}
        </v-btn>
      </div>
      <div v-if="variantGroups.length === 0" class="text-body-2 text-medium-emphasis mb-2">
        {{ t('admin.variantsEmpty') }}
      </div>
      <div v-for="(group, gIndex) in variantGroups" :key="group.key" class="group-card mb-3">
        <BilingualNameFields v-model:name-el="group.name_el" v-model:name-en="group.name_en" />
        <v-row dense class="mt-1">
          <v-col cols="6" sm="4" class="d-flex align-center">
            <v-switch v-model="group.is_required" :label="t('admin.variantRequired')" color="primary" hide-details density="compact" />
          </v-col>
          <v-col cols="6" sm="4" class="d-flex align-center">
            <v-switch v-model="group.is_active" :label="t('admin.active')" color="primary" hide-details density="compact" />
          </v-col>
          <v-col cols="12" sm="4" class="d-flex justify-end">
            <v-btn size="small" color="error" variant="text" @click="removeVariantGroup(gIndex)">{{ t('admin.delete') }}</v-btn>
          </v-col>
        </v-row>

        <div class="text-caption font-weight-medium mt-3 mb-1">{{ t('admin.variantOptions') }}</div>
        <div v-for="(option, oIndex) in group.options" :key="option.key" class="option-card mb-2">
          <BilingualNameFields v-model:name-el="option.name_el" v-model:name-en="option.name_en" />
          <v-row dense class="mt-1">
            <v-col cols="6" sm="4">
              <v-text-field v-model.number="option.price" :label="t('admin.addonPrice')" type="number" step="0.01" min="0" density="comfortable" hide-details />
            </v-col>
            <v-col cols="6" sm="4" class="d-flex align-center">
              <v-switch v-model="option.is_active" :label="t('admin.active')" color="primary" hide-details density="compact" />
            </v-col>
            <v-col cols="12" sm="4" class="d-flex justify-end">
              <v-btn size="small" color="error" variant="text" :disabled="group.options.length <= 1" @click="removeVariantOption(gIndex, oIndex)">
                {{ t('admin.delete') }}
              </v-btn>
            </v-col>
          </v-row>
        </div>
        <v-btn size="small" variant="text" color="primary" prepend-icon="mdi-plus" @click="addVariantOption(gIndex)">
          {{ t('admin.addVariantOption') }}
        </v-btn>
      </div>
      <div v-if="variantCount" class="text-caption text-medium-emphasis">{{ t('admin.variantsCount', { n: variantCount }) }}</div>
    </section>

    <section class="customizations__section mt-4">
      <div class="d-flex align-center justify-space-between mb-2">
        <div>
          <div class="font-weight-medium">{{ t('admin.addons') }}</div>
          <div class="text-caption text-medium-emphasis">{{ t('admin.addonsHint') }}</div>
        </div>
        <v-btn size="small" variant="tonal" color="primary" prepend-icon="mdi-plus" @click="addAddonGroup">
          {{ t('admin.addAddonGroup') }}
        </v-btn>
      </div>
      <div v-if="addonGroups.length === 0" class="text-body-2 text-medium-emphasis mb-2">
        {{ t('admin.addonsEmpty') }}
      </div>
      <div v-for="(group, gIndex) in addonGroups" :key="group.key" class="group-card mb-3">
        <BilingualNameFields v-model:name-el="group.name_el" v-model:name-en="group.name_en" />
        <v-row dense class="mt-1">
          <v-col cols="6" sm="3">
            <v-text-field v-model.number="group.min_selections" type="number" min="0" :label="t('admin.minSelections')" density="comfortable" hide-details />
          </v-col>
          <v-col cols="6" sm="3">
            <v-text-field
              :model-value="group.max_selections ?? ''"
              type="number"
              min="0"
              :label="t('admin.maxSelections')"
              :hint="t('admin.maxSelectionsHint')"
              persistent-hint
              density="comfortable"
              @update:model-value="group.max_selections = $event === '' || $event === null ? null : Number($event)"
            />
          </v-col>
          <v-col cols="6" sm="3" class="d-flex align-center">
            <v-switch v-model="group.is_active" :label="t('admin.active')" color="primary" hide-details density="compact" />
          </v-col>
          <v-col cols="6" sm="3" class="d-flex justify-end">
            <v-btn size="small" color="error" variant="text" @click="removeAddonGroup(gIndex)">{{ t('admin.delete') }}</v-btn>
          </v-col>
        </v-row>

        <div class="text-caption font-weight-medium mt-3 mb-1">{{ t('admin.addonItems') }}</div>
        <div v-for="(addon, aIndex) in group.addons" :key="addon.key" class="option-card mb-2">
          <BilingualNameFields v-model:name-el="addon.name_el" v-model:name-en="addon.name_en" />
          <v-row dense class="mt-1">
            <v-col cols="6" sm="3">
              <v-text-field v-model.number="addon.price" :label="t('admin.addonPrice')" type="number" step="0.01" min="0" density="comfortable" hide-details />
            </v-col>
            <v-col cols="3" sm="2">
              <v-text-field v-model.number="addon.min_quantity" type="number" min="0" :label="t('admin.minQty')" density="comfortable" hide-details />
            </v-col>
            <v-col cols="3" sm="2">
              <v-text-field v-model.number="addon.max_quantity" type="number" min="1" :label="t('admin.maxQty')" density="comfortable" hide-details />
            </v-col>
            <v-col cols="6" sm="3" class="d-flex align-center">
              <v-switch v-model="addon.is_active" :label="t('admin.available')" color="primary" hide-details density="compact" />
            </v-col>
            <v-col cols="6" sm="2" class="d-flex justify-end">
              <v-btn size="small" color="error" variant="text" @click="removeAddon(gIndex, aIndex)">{{ t('admin.delete') }}</v-btn>
            </v-col>
          </v-row>
        </div>
        <v-btn size="small" variant="text" color="primary" prepend-icon="mdi-plus" @click="addAddon(gIndex)">
          {{ t('admin.addAddon') }}
        </v-btn>
      </div>
      <div v-if="addonCount" class="text-caption text-medium-emphasis">{{ t('admin.addonsCount', { n: addonCount }) }}</div>
    </section>
  </div>
</template>

<style scoped>
.group-card,
.option-card {
  padding: 0.85rem;
  border: 1px solid rgba(11, 110, 107, 0.14);
  border-radius: 12px;
  background: rgba(11, 110, 107, 0.03);
}
.option-card {
  background: rgba(255, 255, 255, 0.65);
}
</style>
