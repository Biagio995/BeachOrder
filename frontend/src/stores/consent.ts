import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

const STORAGE_KEY = 'bo_cookie_consent'

export type ConsentChoice = 'accepted' | 'declined' | null

export const useConsentStore = defineStore('consent', () => {
  const choice = ref<ConsentChoice>(
    (localStorage.getItem(STORAGE_KEY) as ConsentChoice) || null,
  )

  const hasAnswered = computed(() => choice.value !== null)
  const isAccepted = computed(() => choice.value === 'accepted')

  function accept() {
    choice.value = 'accepted'
    localStorage.setItem(STORAGE_KEY, 'accepted')
  }

  function decline() {
    choice.value = 'declined'
    localStorage.setItem(STORAGE_KEY, 'declined')
  }

  function reset() {
    choice.value = null
    localStorage.removeItem(STORAGE_KEY)
  }

  return { choice, hasAnswered, isAccepted, accept, decline, reset }
})
