<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/api/client'
import AdminFormDialog from '@/components/admin/AdminFormDialog.vue'
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue'
import BilingualNameFields from '@/components/admin/BilingualNameFields.vue'
import { useAdminDialog } from '@/composables/useAdminDialog'
import { useLocalizedName } from '@/composables/useLocalizedName'
import { useDeleteConfirm } from '@/composables/useDeleteConfirm'
import { useUiStore } from '@/stores/ui'
import { buildNamePayload, hasPrimaryName, pickNameFields } from '@/utils/namePayload'

type CategoryForm = {
  id: number | null
  name_el: string
  name_en: string
  is_active: boolean
  sort_order: number
}

const { t, locale } = useI18n()
const { localizedName } = useLocalizedName()
const { confirmDelete } = useDeleteConfirm()
const ui = useUiStore()
const categories = ref<any[]>([])

const emptyForm = (): CategoryForm => ({
  id: null,
  name_el: '',
  name_en: '',
  is_active: true,
  sort_order: 0,
})

const {
  dialogOpen,
  saving,
  form,
  dialogTitle,
  openCreate,
  openEdit,
  closeDialog,
  withSaving,
} = useAdminDialog({
  emptyForm,
  createTitle: computed(() => t('admin.createCategory')),
  editTitle: computed(() => t('admin.editCategory')),
})

const canSave = computed(() => hasPrimaryName(form.value.name_el, form.value.name_en))

async function load() {
  const { data } = await api.get('/admin/categories')
  categories.value = data
}

function edit(cat: any) {
  openEdit({
    id: cat.id,
    ...pickNameFields(cat.name),
    is_active: cat.is_active ?? true,
    sort_order: cat.sort_order ?? 0,
  })
}

async function save() {
  if (!canSave.value) return
  await withSaving(async () => {
    const payload = {
      name: buildNamePayload(form.value.name_el, form.value.name_en),
      is_active: form.value.is_active,
      sort_order: form.value.sort_order,
    }
    if (form.value.id) {
      await api.put(`/admin/categories/${form.value.id}`, payload)
    } else {
      await api.post('/admin/categories', payload)
    }
    closeDialog()
    await load()
  })
}

async function remove(cat: { id: number; name?: Record<string, string> }) {
  const ok = await confirmDelete(localizedName(cat.name))
  if (!ok) return
  await api.delete(`/admin/categories/${cat.id}`)
  ui.success(t('admin.deleted'))
  await load()
}

onMounted(load)
watch(locale, load)
</script>

<template>
  <div>
    <AdminPageHeader :title="t('admin.categories')">
      <template #actions>
        <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreate">{{ t('admin.create') }}</v-btn>
      </template>
    </AdminPageHeader>

    <div v-for="cat in categories" :key="cat.id" class="admin-list-row">
      <div class="min-w-0">
        <strong>{{ localizedName(cat.name) }}</strong>
        <div class="text-medium-emphasis text-body-2">
          {{ cat.products_count }} {{ t('admin.products').toLowerCase() }}
          <span v-if="!cat.is_active"> · inactive</span>
        </div>
      </div>
      <div class="stack-actions">
        <v-btn size="small" variant="text" color="primary" @click="edit(cat)">{{ t('admin.edit') }}</v-btn>
        <v-btn size="small" color="error" variant="text" @click="remove(cat)">{{ t('admin.delete') }}</v-btn>
      </div>
    </div>

    <AdminFormDialog
      v-model="dialogOpen"
      :title="dialogTitle"
      :saving="saving"
      :can-save="canSave"
      :max-width="520"
      @save="save"
      @close="closeDialog"
    >
      <BilingualNameFields v-model:name-el="form.name_el" v-model:name-en="form.name_en" />
      <v-row dense class="mt-1">
        <v-col cols="6">
          <v-text-field v-model.number="form.sort_order" type="number" label="Sort" />
        </v-col>
        <v-col cols="6" class="d-flex align-center">
          <v-switch v-model="form.is_active" :label="t('admin.active')" color="primary" hide-details />
        </v-col>
      </v-row>
    </AdminFormDialog>
  </div>
</template>
