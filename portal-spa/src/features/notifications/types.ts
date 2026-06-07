/** Charge utile métier d'une notification in-app (clé `data` côté Laravel). */
export interface NotificationData {
  type: string
  message: string
  url?: string
  [key: string]: unknown
}

export interface NotificationItem {
  id: string
  data: NotificationData
  read_at: string | null
  is_read: boolean
  created_at: string | null
}

/** Liste paginée + compteur de non-lues (badge de la cloche). */
export interface NotificationsResponse {
  data: NotificationItem[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    unread_count: number
  }
}
