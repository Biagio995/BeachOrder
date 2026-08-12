/** Staff landing path after login, by role. */
export function homePathForRole(role?: string | null): string {
  switch (role) {
    case 'super_admin':
      return '/admin/tenants'
    case 'staff':
      return '/kitchen'
    case 'manager':
    case 'admin':
    default:
      return '/admin'
  }
}
