import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'
import type { CartAddon, CartItem, CartVariant, Product } from '@/types'

type PaymentMethod = 'pay_at_location'

interface PersistedCart {
  tenantSlug: string
  locationCode: string
  items: CartItem[]
  customerName: string
  orderNotes: string
  paymentMethod: PaymentMethod
}

function storageKey(tenantSlug: string, locationCode: string) {
  return `bo_cart:${tenantSlug}:${locationCode || 'none'}`
}

function newLineId() {
  const c = globalThis.crypto as Crypto | undefined
  if (c?.randomUUID) {
    return c.randomUUID()
  }

  return `line-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

function normalizeVariants(variants?: CartVariant[] | null): CartVariant[] {
  return [...(variants || [])]
    .map((variant) => ({
      groupId: Number(variant.groupId),
      groupName: String(variant.groupName || ''),
      optionId: Number(variant.optionId),
      optionName: String(variant.optionName || ''),
      price: Number(variant.price) || 0,
    }))
    .sort((a, b) => a.groupId - b.groupId || a.optionId - b.optionId)
}

function normalizeAddons(addons?: CartAddon[] | null): CartAddon[] {
  return [...(addons || [])]
    .map((addon) => ({
      id: Number(addon.id),
      groupId: addon.groupId != null ? Number(addon.groupId) : undefined,
      groupName: addon.groupName ? String(addon.groupName) : undefined,
      name: String(addon.name || ''),
      price: Number(addon.price) || 0,
      quantity: Math.max(1, Number(addon.quantity) || 1),
    }))
    .filter((addon) => addon.quantity > 0)
    .sort((a, b) => a.id - b.id)
}

function customizationKey(variants: CartVariant[], addons: CartAddon[]) {
  const variantPart = normalizeVariants(variants)
    .map((v) => `${v.groupId}:${v.optionId}`)
    .join('|')
  const addonPart = normalizeAddons(addons)
    .map((a) => `${a.id}x${a.quantity}`)
    .join('|')
  return `${variantPart}#${addonPart}`
}

export function lineUnitPrice(
  product: Product,
  variants: CartVariant[] = [],
  addons: CartAddon[] = [],
) {
  const variantExtra = normalizeVariants(variants).reduce((sum, v) => sum + v.price, 0)
  const addonExtra = normalizeAddons(addons).reduce((sum, a) => sum + a.price * a.quantity, 0)
  return Number(product.price) + variantExtra + addonExtra
}

export function productHasCustomizations(product: Product) {
  const hasVariants = (product.variant_groups || []).some((g) => (g.options || []).length > 0)
  const hasAddons = (product.addon_groups || []).some((g) => (g.addons || []).length > 0)
    || (product.addons || []).length > 0
  return hasVariants || hasAddons
}

export const useCartStore = defineStore('cart', () => {
  const tenantSlug = ref('')
  const locationCode = ref('')
  const items = ref<CartItem[]>([])
  const customerName = ref('')
  const orderNotes = ref('')
  const paymentMethod = ref<PaymentMethod>('pay_at_location')

  const count = computed(() => items.value.reduce((sum, i) => sum + i.quantity, 0))
  const total = computed(() =>
    items.value.reduce(
      (sum, i) => sum + lineUnitPrice(i.product, i.variants, i.addons) * i.quantity,
      0,
    ),
  )

  function persist() {
    if (!tenantSlug.value) return
    const payload: PersistedCart = {
      tenantSlug: tenantSlug.value,
      locationCode: locationCode.value,
      items: items.value,
      customerName: customerName.value,
      orderNotes: orderNotes.value,
      paymentMethod: paymentMethod.value,
    }
    sessionStorage.setItem(storageKey(tenantSlug.value, locationCode.value), JSON.stringify(payload))
  }

  function bindContext(nextTenant: string, nextLocation: string) {
    if (tenantSlug.value === nextTenant && locationCode.value === nextLocation) return

    if (tenantSlug.value) {
      persist()
    }

    tenantSlug.value = nextTenant
    locationCode.value = nextLocation

    const raw = sessionStorage.getItem(storageKey(nextTenant, nextLocation))
    if (!raw) {
      items.value = []
      customerName.value = ''
      orderNotes.value = ''
      paymentMethod.value = 'pay_at_location'
      return
    }

    try {
      const data = JSON.parse(raw) as PersistedCart
      items.value = (data.items || []).map((item) => ({
        ...item,
        lineId: item.lineId || newLineId(),
        variants: normalizeVariants(item.variants),
        addons: normalizeAddons(item.addons),
      }))
      customerName.value = data.customerName || ''
      orderNotes.value = data.orderNotes || ''
      paymentMethod.value = 'pay_at_location'
    } catch {
      items.value = []
    }
  }

  function add(
    product: Product,
    quantity = 1,
    notes = '',
    variants: CartVariant[] = [],
    addons: CartAddon[] = [],
  ) {
    const selectedVariants = normalizeVariants(variants)
    const selectedAddons = normalizeAddons(addons)
    const key = customizationKey(selectedVariants, selectedAddons)
    const existing = items.value.find(
      (i) =>
        i.product.id === product.id &&
        i.notes === notes &&
        customizationKey(i.variants, i.addons) === key,
    )
    if (existing) {
      existing.quantity += quantity
    } else {
      items.value.push({
        lineId: newLineId(),
        product,
        quantity,
        notes,
        variants: selectedVariants,
        addons: selectedAddons,
      })
    }
  }

  function updateQuantity(lineId: string, quantity: number) {
    const item = items.value.find((i) => i.lineId === lineId)
    if (!item) return
    if (quantity <= 0) {
      remove(lineId)
      return
    }
    item.quantity = quantity
  }

  function updateNotes(lineId: string, notes: string) {
    const item = items.value.find((i) => i.lineId === lineId)
    if (!item) return
    item.notes = notes
  }

  function remove(lineId: string) {
    items.value = items.value.filter((i) => i.lineId !== lineId)
  }

  function clear() {
    items.value = []
    customerName.value = ''
    orderNotes.value = ''
    paymentMethod.value = 'pay_at_location'
    if (tenantSlug.value) {
      sessionStorage.removeItem(storageKey(tenantSlug.value, locationCode.value))
    }
  }

  function syncLabelsFromCategories(categories: { products: Product[] }[]) {
    const byId = new Map<number, Product>()
    for (const category of categories) {
      for (const product of category.products || []) {
        byId.set(product.id, product)
      }
    }
    let changed = false
    items.value = items.value.map((item) => {
      const fresh = byId.get(item.product.id)
      if (!fresh) return item
      changed = true

      const optionById = new Map<number, { groupId: number; groupName: string; name: string; price: number }>()
      for (const group of fresh.variant_groups || []) {
        for (const option of group.options || []) {
          optionById.set(option.id, {
            groupId: group.id,
            groupName: group.name,
            name: option.name,
            price: Number(option.price) || 0,
          })
        }
      }

      const addonById = new Map<number, { groupId: number; groupName: string; name: string; price: number }>()
      for (const group of fresh.addon_groups || []) {
        for (const addon of group.addons || []) {
          addonById.set(addon.id, {
            groupId: group.id,
            groupName: group.name,
            name: addon.name,
            price: Number(addon.price) || 0,
          })
        }
      }

      return {
        ...item,
        product: {
          ...item.product,
          name: fresh.name,
          description: fresh.description,
          tags: fresh.tags,
          variant_groups: fresh.variant_groups,
          addon_groups: fresh.addon_groups,
          name_i18n: fresh.name_i18n,
        },
        variants: item.variants.map((selected) => {
          const freshOption = optionById.get(selected.optionId)
          if (!freshOption) return selected
          return {
            groupId: freshOption.groupId,
            groupName: freshOption.groupName,
            optionId: selected.optionId,
            optionName: freshOption.name,
            price: freshOption.price,
          }
        }),
        addons: item.addons.map((selected) => {
          const freshAddon = addonById.get(selected.id)
          if (!freshAddon) return selected
          return {
            id: selected.id,
            groupId: freshAddon.groupId,
            groupName: freshAddon.groupName,
            name: freshAddon.name,
            price: freshAddon.price,
            quantity: selected.quantity,
          }
        }),
      }
    })
    if (changed) persist()
  }

  watch([items, customerName, orderNotes, paymentMethod], persist, { deep: true })

  return {
    tenantSlug,
    locationCode,
    items,
    customerName,
    orderNotes,
    paymentMethod,
    count,
    total,
    bindContext,
    add,
    updateQuantity,
    updateNotes,
    remove,
    clear,
    syncLabelsFromCategories,
  }
})
