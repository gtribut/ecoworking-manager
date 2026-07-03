import type { Paginated } from '@/lib/api-types'
import { http } from '@/lib/http'
import type { DirectoryEntry, FloorPlan } from './types'

export async function fetchDirectory(page = 1, q = ''): Promise<Paginated<DirectoryEntry>> {
  const { data } = await http.get<Paginated<DirectoryEntry>>('/api/directory', {
    params: { page, ...(q !== '' ? { q } : {}) },
  })
  return data
}

export async function fetchFloorPlan(date: string): Promise<FloorPlan> {
  const { data } = await http.get<FloorPlan>('/api/directory/floor-plan', {
    params: { date },
  })
  return data
}
