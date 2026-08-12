import { useI18n } from 'vue-i18n'
import { useUiStore } from '@/stores/ui'

/** Shared confirm modals with i18n copy (UI locale). */
export function useDeleteConfirm() {
  const { t } = useI18n()
  const ui = useUiStore()

  function confirmDelete(name?: string | null) {
    const label = (name || '').trim()
    return ui.askConfirm({
      title: t('common.confirmDeleteTitle'),
      message: label
        ? t('common.confirmDeleteMessage', { name: label })
        : t('common.confirmDeleteMessageGeneric'),
      confirmLabel: t('admin.delete'),
      cancelLabel: t('common.cancel'),
      danger: true,
    })
  }

  function confirmCartRemove(name?: string | null) {
    const label = (name || '').trim()
    return ui.askConfirm({
      title: t('cart.confirmRemoveTitle'),
      message: label
        ? t('cart.confirmRemoveMessage', { name: label })
        : t('cart.confirmRemove'),
      confirmLabel: t('common.confirm'),
      cancelLabel: t('common.cancel'),
      danger: true,
    })
  }

  return { confirmDelete, confirmCartRemove }
}
