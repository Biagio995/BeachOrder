/** Staff landing path after login, by role. */
import { homePathForStaffPosition } from '@/utils/staffPosition'

export function homePathForRole(
  role?: string | null,
  staffPosition?: string | null,
): string {
  switch (role) {
    case 'super_admin':
      return '/admin/tenants'
    case 'staff':
      return homePathForStaffPosition(staffPosition)
    case 'manager':
    case 'admin':
    default:
      return '/admin'
  }
}
