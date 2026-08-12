import { hasPrimaryName } from '@/utils/namePayload'

export interface OptionFormRow {
  key: string
  id: number | null
  name_el: string
  name_en: string
  price: number
  is_active: boolean
}

export interface VariantGroupFormRow {
  key: string
  id: number | null
  name_el: string
  name_en: string
  is_required: boolean
  is_active: boolean
  options: OptionFormRow[]
}

export interface AddonFormRow {
  key: string
  id: number | null
  name_el: string
  name_en: string
  price: number
  is_active: boolean
  min_quantity: number
  max_quantity: number
}

export interface AddonGroupFormRow {
  key: string
  id: number | null
  name_el: string
  name_en: string
  min_selections: number
  max_selections: number | null
  is_active: boolean
  addons: AddonFormRow[]
}

function newKey(prefix: string) {
  return `${prefix}-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

export function emptyVariantOption(): OptionFormRow {
  return { key: newKey('vo'), id: null, name_el: '', name_en: '', price: 0, is_active: true }
}

export function emptyVariantGroup(): VariantGroupFormRow {
  return {
    key: newKey('vg'),
    id: null,
    name_el: '',
    name_en: '',
    is_required: true,
    is_active: true,
    options: [emptyVariantOption()],
  }
}

export function emptyAddon(): AddonFormRow {
  return {
    key: newKey('ad'),
    id: null,
    name_el: '',
    name_en: '',
    price: 0,
    is_active: true,
    min_quantity: 0,
    max_quantity: 1,
  }
}

export function emptyAddonGroup(): AddonGroupFormRow {
  return {
    key: newKey('ag'),
    id: null,
    name_el: '',
    name_en: '',
    min_selections: 0,
    max_selections: null,
    is_active: true,
    addons: [emptyAddon()],
  }
}

export function customizationsValid(
  variants: VariantGroupFormRow[],
  addons: AddonGroupFormRow[],
): boolean {
  const variantsOk = variants.every(
    (group) =>
      hasPrimaryName(group.name_el, group.name_en) &&
      group.options.length > 0 &&
      group.options.every((opt) => hasPrimaryName(opt.name_el, opt.name_en) && Number(opt.price) >= 0),
  )
  const addonsOk = addons.every(
    (group) =>
      hasPrimaryName(group.name_el, group.name_en) &&
      group.addons.every(
        (addon) =>
          hasPrimaryName(addon.name_el, addon.name_en) &&
          Number(addon.price) >= 0 &&
          Number(addon.min_quantity) >= 0 &&
          Number(addon.max_quantity) >= Math.max(1, Number(addon.min_quantity) || 0),
      ),
  )
  return variantsOk && addonsOk
}
