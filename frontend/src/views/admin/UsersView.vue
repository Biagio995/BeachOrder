<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/api/client'
import AdminFormDialog from '@/components/admin/AdminFormDialog.vue'
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue'
import { useAdminDialog } from '@/composables/useAdminDialog'
import { useDeleteConfirm } from '@/composables/useDeleteConfirm'
import { useUiStore } from '@/stores/ui'
import {
  ASSIGNABLE_USER_ROLES,
  ASSIGNABLE_USER_ROLE_LABELS,
  payloadFromUserRolePicker,
  userRoleLabel,
  userRolePickerValue,
  type AssignableUserRole,
} from '@/utils/staffPosition'

type UserForm = {
  id: number | null
  name: string
  email: string
  password: string
  selectedRole: AssignableUserRole
  is_active: boolean
}

const { t } = useI18n()
const { confirmDelete } = useDeleteConfirm()
const ui = useUiStore()
const users = ref<any[]>([])

const emptyForm = (): UserForm => ({
  id: null,
  name: '',
  email: '',
  password: '',
  selectedRole: 'kitchen',
  is_active: true,
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
  createTitle: computed(() => t('admin.createUser')),
  editTitle: computed(() => t('admin.editUser')),
})

const roleOptions = computed(() =>
  ASSIGNABLE_USER_ROLES.map((role) => ({
    title: ASSIGNABLE_USER_ROLE_LABELS[role],
    value: role,
  })),
)

const canSave = computed(() => {
  if (!form.value.name.trim() || !form.value.email.trim()) return false
  if (!form.value.id && form.value.password.length < 8) return false
  if (form.value.id && form.value.password && form.value.password.length < 8) return false
  return true
})

async function load() {
  const { data } = await api.get('/admin/users')
  users.value = data
}

function edit(user: any) {
  openEdit({
    id: user.id,
    name: user.name || '',
    email: user.email || '',
    password: '',
    selectedRole: userRolePickerValue(user),
    is_active: user.is_active ?? true,
  })
}

async function save() {
  if (!canSave.value) return
  await withSaving(async () => {
    const rolePayload = payloadFromUserRolePicker(form.value.selectedRole)
    const payload: Record<string, unknown> = {
      name: form.value.name,
      email: form.value.email,
      role: rolePayload.role,
      staff_position: rolePayload.staff_position,
      is_active: form.value.is_active,
    }
    if (form.value.password) {
      payload.password = form.value.password
    }
    if (form.value.id) {
      await api.put(`/admin/users/${form.value.id}`, payload)
    } else {
      payload.password = form.value.password
      await api.post('/admin/users', payload)
    }
    closeDialog()
    await load()
  })
}

async function remove(user: { id: number; name?: string }) {
  const ok = await confirmDelete(user.name)
  if (!ok) return
  await api.delete(`/admin/users/${user.id}`)
  ui.success(t('admin.deleted'))
  await load()
}

onMounted(load)
</script>

<template>
  <div>
    <AdminPageHeader :title="t('admin.users')">
      <template #actions>
        <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreate">{{ t('admin.create') }}</v-btn>
      </template>
    </AdminPageHeader>

    <div v-for="user in users" :key="user.id" class="admin-list-row">
      <div class="min-w-0">
        <strong>{{ user.name }}</strong>
        <div class="text-medium-emphasis text-body-2">
          {{ user.email }} · {{ userRoleLabel(user) }}
          <span v-if="!user.is_active"> · inactive</span>
        </div>
      </div>
      <div class="stack-actions">
        <v-btn size="small" variant="text" color="primary" @click="edit(user)">{{ t('admin.edit') }}</v-btn>
        <v-btn size="small" color="error" variant="text" @click="remove(user)">{{ t('admin.delete') }}</v-btn>
      </div>
    </div>

    <AdminFormDialog
      v-model="dialogOpen"
      :title="dialogTitle"
      :saving="saving"
      :can-save="canSave"
      :max-width="560"
      @save="save"
      @close="closeDialog"
    >
      <v-row dense>
        <v-col cols="12" md="6">
          <v-text-field v-model="form.name" label="Name" />
        </v-col>
        <v-col cols="12" md="6">
          <v-text-field v-model="form.email" label="Email" type="email" />
        </v-col>
        <v-col cols="12" md="6">
          <v-text-field
            v-model="form.password"
            label="Password"
            type="password"
            :hint="form.id ? 'Lascia vuoto per non cambiare' : 'Minimo 8 caratteri'"
            persistent-hint
          />
        </v-col>
        <v-col cols="12" md="6">
          <v-select
            v-model="form.selectedRole"
            :items="roleOptions"
            item-title="title"
            item-value="value"
            label="Ruolo"
          />
        </v-col>
        <v-col cols="12" class="d-flex align-center">
          <v-switch v-model="form.is_active" :label="t('admin.active')" color="primary" hide-details />
        </v-col>
      </v-row>
    </AdminFormDialog>
  </div>
</template>
