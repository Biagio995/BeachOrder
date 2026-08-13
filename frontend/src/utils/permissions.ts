export const PERMISSIONS = {
  TENANTS_MANAGE: 'tenants.manage',
  USERS_MANAGE: 'users.manage',
  MENU_MANAGE: 'menu.manage',
  ORDERS_MANAGE: 'orders.manage',
  ORDERS_VIEW: 'orders.view',
  ORDERS_UPDATE_STATUS: 'orders.update_status',
  ORDERS_UPDATE_PAYMENT: 'orders.update_payment',
  QR_MANAGE: 'qr.manage',
  SETTINGS_MANAGE: 'settings.manage',
  PRODUCTS_AVAILABILITY: 'products.availability',
  WAITER_CALLS_MANAGE: 'waiter_calls.manage',
} as const

export type Permission = (typeof PERMISSIONS)[keyof typeof PERMISSIONS]

export type StaffRole = 'super_admin' | 'admin' | 'manager' | 'staff'

export type StaffPosition = 'kitchen' | 'bar' | 'waiter'

const STAFF_POSITION_PERMISSIONS: Record<StaffPosition, Permission[]> = {
  kitchen: [PERMISSIONS.ORDERS_VIEW, PERMISSIONS.ORDERS_UPDATE_STATUS],
  bar: [PERMISSIONS.ORDERS_VIEW, PERMISSIONS.ORDERS_UPDATE_STATUS],
  waiter: [
    PERMISSIONS.ORDERS_VIEW,
    PERMISSIONS.ORDERS_UPDATE_STATUS,
    PERMISSIONS.ORDERS_UPDATE_PAYMENT,
    PERMISSIONS.WAITER_CALLS_MANAGE,
  ],
}

const ROLE_PERMISSIONS: Record<StaffRole, Permission[]> = {
  super_admin: Object.values(PERMISSIONS),
  admin: [
    PERMISSIONS.USERS_MANAGE,
    PERMISSIONS.MENU_MANAGE,
    PERMISSIONS.ORDERS_MANAGE,
    PERMISSIONS.ORDERS_VIEW,
    PERMISSIONS.ORDERS_UPDATE_STATUS,
    PERMISSIONS.ORDERS_UPDATE_PAYMENT,
    PERMISSIONS.QR_MANAGE,
    PERMISSIONS.SETTINGS_MANAGE,
    PERMISSIONS.PRODUCTS_AVAILABILITY,
    PERMISSIONS.WAITER_CALLS_MANAGE,
  ],
  manager: [
    PERMISSIONS.MENU_MANAGE,
    PERMISSIONS.ORDERS_MANAGE,
    PERMISSIONS.ORDERS_VIEW,
    PERMISSIONS.ORDERS_UPDATE_STATUS,
    PERMISSIONS.ORDERS_UPDATE_PAYMENT,
    PERMISSIONS.QR_MANAGE,
    PERMISSIONS.PRODUCTS_AVAILABILITY,
  ],
  staff: [],
}

export function permissionsForRole(
  role?: string | null,
  staffPosition?: string | null,
): Permission[] {
  if (!role) return []
  if (role === 'staff') {
    return STAFF_POSITION_PERMISSIONS[staffPosition as StaffPosition] ?? []
  }
  return ROLE_PERMISSIONS[role as StaffRole] ?? []
}

export function hasPermission(
  permissions: Permission[] | undefined,
  permission: Permission,
  role?: string | null,
): boolean {
  if (role === 'super_admin') return true
  return (permissions ?? []).includes(permission)
}

export function hasAnyPermission(
  permissions: Permission[] | undefined,
  required: Permission[],
  role?: string | null,
): boolean {
  if (role === 'super_admin') return true
  const granted = permissions ?? []
  return required.some((p) => granted.includes(p))
}

export const ASSIGNABLE_ROLES: StaffRole[] = ['admin', 'manager', 'staff']

export const ROLE_LABELS: Record<StaffRole, string> = {
  super_admin: 'Super Admin',
  admin: 'Owner / Admin',
  manager: 'Manager',
  staff: 'Staff',
}
