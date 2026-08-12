import { computed, ref, type ComputedRef, type Ref } from 'vue'

/**
 * Shared admin create/edit dialog lifecycle.
 * Keep save/load payloads in the view — this only owns dialog + form state.
 */
export function useAdminDialog<T extends { id: number | null }>(options: {
  emptyForm: () => T
  editTitle: string | ComputedRef<string>
  createTitle: string | ComputedRef<string>
}) {
  const dialogOpen = ref(false)
  const saving = ref(false)
  const form = ref(options.emptyForm()) as Ref<T>

  const dialogTitle = computed(() =>
    form.value.id
      ? (typeof options.editTitle === 'string' ? options.editTitle : options.editTitle.value)
      : (typeof options.createTitle === 'string' ? options.createTitle : options.createTitle.value),
  )

  function openCreate() {
    form.value = options.emptyForm()
    dialogOpen.value = true
  }

  function openEdit(next: T) {
    form.value = next
    dialogOpen.value = true
  }

  function closeDialog() {
    dialogOpen.value = false
    form.value = options.emptyForm()
  }

  async function withSaving(fn: () => Promise<void>) {
    if (saving.value) return
    saving.value = true
    try {
      await fn()
    } finally {
      saving.value = false
    }
  }

  return {
    dialogOpen,
    saving,
    form,
    dialogTitle,
    openCreate,
    openEdit,
    closeDialog,
    withSaving,
  }
}
