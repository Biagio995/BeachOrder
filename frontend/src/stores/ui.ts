import { defineStore } from 'pinia'
import { ref } from 'vue'

export type ConfirmOptions = {
  title: string
  message: string
  confirmLabel?: string
  cancelLabel?: string
  /** Destructive action styling (default true). */
  danger?: boolean
}

const defaultOptions: ConfirmOptions = {
  title: '',
  message: '',
  danger: true,
}

export const useUiStore = defineStore('ui', () => {
  const snackbar = ref(false)
  const message = ref('')
  const color = ref<'success' | 'error' | 'info' | 'warning'>('info')

  const confirmOpen = ref(false)
  const confirmOptions = ref<ConfirmOptions>({ ...defaultOptions })
  let confirmResolver: ((value: boolean) => void) | null = null

  function notify(text: string, type: typeof color.value = 'info') {
    message.value = text
    color.value = type
    snackbar.value = true
  }

  function success(text: string) {
    notify(text, 'success')
  }

  function error(text: string) {
    notify(text, 'error')
  }

  /** Promise-based confirm modal. Resolves true if the user confirms. */
  function askConfirm(options: ConfirmOptions): Promise<boolean> {
    if (confirmResolver) {
      confirmResolver(false)
      confirmResolver = null
    }
    confirmOptions.value = {
      danger: true,
      ...options,
    }
    confirmOpen.value = true
    return new Promise((resolve) => {
      confirmResolver = resolve
    })
  }

  function resolveConfirm(value: boolean) {
    confirmOpen.value = false
    const resolve = confirmResolver
    confirmResolver = null
    resolve?.(value)
  }

  return {
    snackbar,
    message,
    color,
    notify,
    success,
    error,
    confirmOpen,
    confirmOptions,
    askConfirm,
    resolveConfirm,
  }
})
