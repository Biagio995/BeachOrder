import { ref } from 'vue'

/** Flash a short-lived "saved" success flag (settings pages). */
export function useFlashSaved(ms = 2000) {
  const saved = ref(false)
  let timer: ReturnType<typeof setTimeout> | null = null

  function flashSaved() {
    saved.value = true
    if (timer) clearTimeout(timer)
    timer = setTimeout(() => {
      saved.value = false
      timer = null
    }, ms)
  }

  return { saved, flashSaved }
}
