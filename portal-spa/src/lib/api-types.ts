/** Réponse paginée Laravel standard (Resource collection). */
export interface Paginated<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}
