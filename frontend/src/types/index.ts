export type LocaleCode = 'it' | 'en' | 'el' | 'de'

export type FiscalProvider = 'epson_epos' | 'custom' | 'rch' | 'mydata' | 'other'
export type FiscalMode = 'local_bridge' | 'cloud_api'
export type PosProvider = 'nexi' | 'sumup' | 'axerve' | 'other'
export type PrintDriver = 'escpos_tcp' | 'printnode' | 'local_bridge'

export interface PrintStationSettings {
  host?: string | null
  port?: number
  copies?: number
}

export interface FiscalSettings {
  enabled?: boolean
  provider?: FiscalProvider | null
  mode?: FiscalMode
  device_id?: string | null
  credentials_ref?: string | null
  auto_fiscalize_on_pay?: boolean
}

export interface PosSettings {
  enabled?: boolean
  provider?: PosProvider | null
  terminal_id?: string | null
  credentials_ref?: string | null
}

export interface PrintingSettings {
  enabled?: boolean
  driver?: PrintDriver
  credentials_ref?: string | null
  stations?: {
    kitchen?: PrintStationSettings
    bar?: PrintStationSettings
  }
}

export interface TenantSettings {
  loyalty_enabled?: boolean
  online_payments_enabled?: boolean
  country?: string
  fiscal?: FiscalSettings
  pos?: PosSettings
  printing?: PrintingSettings
}

export interface TenantBranding {
  primary_color?: string
  accent_color?: string
  secondary_color?: string
  tagline?: string
  logo_url?: string | null
  favicon_url?: string | null
  menu_header_url?: string | null
  menu_cover_url?: string | null
  logo_path?: string | null
  favicon_path?: string | null
  menu_header_path?: string | null
  menu_cover_path?: string | null
}

export type SubscriptionStatus =
  | 'active'
  | 'past_due'
  | 'canceled'
  | 'unpaid'
  | 'expired'
  | 'inactive'

export interface SubscriptionPricing {
  amount_cents: number
  currency: string
  interval: 'year'
  vat_note: string
}

export interface SubscriptionInfo {
  status: SubscriptionStatus
  plan: string
  price_cents: number
  currency: string
  started_at?: string | null
  current_period_start?: string | null
  current_period_end?: string | null
  next_renewal_at?: string | null
  expires_at?: string | null
  canceled_at?: string | null
  ended_at?: string | null
  grace_period_ends_at?: string | null
  grants_access: boolean
  pricing: SubscriptionPricing
}

export interface Tenant {
  id: number
  name: string
  restaurant_name?: string
  slug: string
  timezone?: string
  currency?: string
  default_locale?: string
  branding?: TenantBranding | null
  settings?: TenantSettings | null
  is_active?: boolean
  is_demo?: boolean
  subscription?: SubscriptionInfo | null
}

export interface Location {
  id: number
  tenant_id?: number
  name: string
  slug: string
  type: 'table' | 'umbrella' | 'sunbed'
  code: string
  zone?: string
  capacity: number
  is_active: boolean
}

export interface ProductTag {
  id: number
  slug: string
  name: string
}

export interface ProductVariantOption {
  id: number
  name: string
  name_i18n?: Record<string, string>
  price: number
  is_active?: boolean
  sort_order?: number
}

export interface ProductVariantGroup {
  id: number
  name: string
  name_i18n?: Record<string, string>
  is_required: boolean
  is_active?: boolean
  sort_order?: number
  options: ProductVariantOption[]
}

export interface ProductAddon {
  id: number
  name: string
  name_i18n?: Record<string, string>
  price: number
  is_active?: boolean
  min_quantity?: number
  max_quantity?: number
  sort_order?: number
}

export interface ProductAddonGroup {
  id: number
  name: string
  name_i18n?: Record<string, string>
  min_selections?: number
  max_selections?: number | null
  is_active?: boolean
  sort_order?: number
  addons: ProductAddon[]
}

export interface Product {
  id: number
  slug: string
  name: string
  name_i18n?: Record<string, string>
  description?: string | null
  price: number
  image_path?: string | null
  image_url?: string | null
  allergens: string[]
  tags?: ProductTag[]
  variant_groups?: ProductVariantGroup[]
  addon_groups?: ProductAddonGroup[]
  /** @deprecated use addon_groups */
  addons?: ProductAddon[]
  is_available: boolean
  station?: 'kitchen' | 'bar'
  prep_time_minutes?: number | null
  category_id?: number
  is_active?: boolean
  track_inventory?: boolean
  stock_quantity?: number | null
  low_stock_threshold?: number | null
}

export interface Category {
  id: number
  slug: string
  name: string
  name_i18n?: Record<string, string>
  description?: string | null
  image_path?: string | null
  sort_order: number
  products: Product[]
  is_active?: boolean
}

export interface CartVariant {
  groupId: number
  groupName: string
  optionId: number
  optionName: string
  price: number
}

export interface CartAddon {
  id: number
  groupId?: number
  groupName?: string
  name: string
  price: number
  quantity: number
}

export interface CartItem {
  lineId: string
  product: Product
  quantity: number
  notes: string
  variants: CartVariant[]
  addons: CartAddon[]
}

export interface OrderItemVariant {
  group_id: number
  group_name: string
  option_id: number
  option_name: string
  price: number
}

export interface OrderItemAddon {
  id: number
  group_id?: number
  group_name?: string
  name: string
  price: number
  quantity?: number
}

export interface OrderItem {
  id: number
  product_id: number
  station?: 'kitchen' | 'bar'
  product_name: string
  unit_price: number
  quantity: number
  notes?: string | null
  variants?: OrderItemVariant[] | null
  addons?: OrderItemAddon[] | null
  line_total: number
}

export interface PaymentReceipt {
  reference?: string | null
  stripe_payment_intent_id?: string | null
  paid_at?: string | null
  amount: number
  currency: string
  method?: string
}

export interface Order {
  id: number
  tenant_id?: number
  order_number: string
  location_id: number
  status: string
  kitchen_status?: string | null
  bar_status?: string | null
  station_status?: string | null
  customer_name?: string | null
  customer_session?: string | null
  notes?: string | null
  subtotal: number
  total: number
  payment_method?: string
  payment_status?: string
  payment_reference?: string | null
  payment_error?: string | null
  payment_receipt?: PaymentReceipt | null
  items: OrderItem[]
  location?: Location
  created_at?: string
}

export interface WaiterCall {
  id: number
  tenant_id?: number
  location_id: number
  reason: string
  note?: string | null
  status: string
  location?: Location
  created_at?: string
}

export interface User {
  id: number
  tenant_id?: number | null
  name: string
  email: string
  role: 'super_admin' | 'admin' | 'manager' | 'staff'
  staff_position?: 'kitchen' | 'bar' | 'waiter' | null
  permissions?: import('@/utils/permissions').Permission[]
  is_active: boolean
  email_verified_at?: string | null
  tenant?: Tenant | null
}
