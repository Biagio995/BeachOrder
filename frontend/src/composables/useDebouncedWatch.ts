import { watch, type Ref, type WatchSource } from 'vue'

/** Debounce a watch callback (search → reload patterns). */
export function useDebouncedWatch<T>(
  source: WatchSource<T> | Ref<T>,
  callback: () => void,
  delayMs = 300,
) {
  let timer: ReturnType<typeof setTimeout> | null = null

  watch(source, () => {
    if (timer) clearTimeout(timer)
    timer = setTimeout(() => {
      timer = null
      callback()
    }, delayMs)
  })
}
