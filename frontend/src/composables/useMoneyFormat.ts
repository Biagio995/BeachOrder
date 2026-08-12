import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useMenuStore } from '@/stores/menu'
import { money as formatTenantMoney } from '@/utils/moneyFormat'

/** Format money with active tenant currency + UI locale. */
export function useMoneyFormat() {
  const { locale } = useI18n()
  const auth = useAuthStore()
  const menu = useMenuStore()

  const currency = computed(
    () => auth.user?.tenant?.currency || menu.tenant?.currency || 'EUR',
  )

  function money(amount: number | string) {
    return formatTenantMoney(amount, currency.value, String(locale.value))
  }

  return { currency, money }
}
