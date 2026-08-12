import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { displayLocalizedName } from '@/utils/localeName'

/** Localized label for DB JSON maps (products, categories, tags) tied to UI locale. */
export function useLocalizedName() {
  const { locale } = useI18n()

  const currentLocale = computed(() => String(locale.value || 'el'))

  function localizedName(
    name: string | Record<string, string> | null | undefined,
    fallback = '',
  ): string {
    return displayLocalizedName(name, currentLocale.value) || fallback
  }

  return { locale: currentLocale, localizedName }
}
