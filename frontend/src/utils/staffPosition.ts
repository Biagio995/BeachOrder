export type StaffPosition = 'kitchen' | 'bar' | 'waiter'

export const STAFF_POSITIONS: StaffPosition[] = ['kitchen', 'bar', 'waiter']

export const STAFF_POSITION_LABELS: Record<StaffPosition, string> = {
  kitchen: 'Cucina',
  bar: 'Bar',
  waiter: 'Cameriere',
}

export const STAFF_POSITION_ROUTES: Record<StaffPosition, string> = {
  kitchen: '/kitchen',
  bar: '/bar',
  waiter: '/waiter',
}

export function homePathForStaffPosition(position?: string | null): string {
  if (position && position in STAFF_POSITION_ROUTES) {
    return STAFF_POSITION_ROUTES[position as StaffPosition]
  }
  return '/kitchen'
}

export function staffCanAccessRoute(
  position: string | null | undefined,
  routePosition?: StaffPosition,
): boolean {
  if (!routePosition) return true
  return position === routePosition
}

/** Values shown in the admin user role picker (staff positions are first-class options). */
export type AssignableUserRole = 'admin' | 'manager' | StaffPosition

export const ASSIGNABLE_USER_ROLES: AssignableUserRole[] = [
  'admin',
  'manager',
  'kitchen',
  'bar',
  'waiter',
]

export const ASSIGNABLE_USER_ROLE_LABELS: Record<AssignableUserRole, string> = {
  admin: 'Owner / Admin',
  manager: 'Manager',
  kitchen: 'Cucina',
  bar: 'Bar',
  waiter: 'Cameriere',
}

export function isStaffPosition(value?: string | null): value is StaffPosition {
  return STAFF_POSITIONS.includes(value as StaffPosition)
}

export function userRolePickerValue(user: {
  role?: string | null
  staff_position?: string | null
}): AssignableUserRole {
  if (user.role === 'staff' && isStaffPosition(user.staff_position)) {
    return user.staff_position
  }
  if (user.role === 'admin' || user.role === 'manager') {
    return user.role
  }
  return 'kitchen'
}

export function userRoleLabel(user: {
  role?: string | null
  staff_position?: string | null
}): string {
  if (user.role === 'staff' && isStaffPosition(user.staff_position)) {
    return STAFF_POSITION_LABELS[user.staff_position]
  }
  if (user.role === 'admin') return ASSIGNABLE_USER_ROLE_LABELS.admin
  if (user.role === 'manager') return ASSIGNABLE_USER_ROLE_LABELS.manager
  if (user.role === 'super_admin') return 'Super Admin'
  return user.role || '—'
}

export function payloadFromUserRolePicker(selected: AssignableUserRole): {
  role: 'admin' | 'manager' | 'staff'
  staff_position: StaffPosition | null
} {
  if (isStaffPosition(selected)) {
    return { role: 'staff', staff_position: selected }
  }
  return { role: selected, staff_position: null }
}
