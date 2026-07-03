import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { fetchDirectory, fetchFloorPlan } from './api'

export const directoryQueryKey = (page: number, q: string) =>
  ['directory', 'list', page, q] as const
export const floorPlanQueryKey = (date: string) => ['directory', 'plan', date] as const

export function useDirectory(page: number, q: string) {
  return useQuery({
    queryKey: directoryQueryKey(page, q),
    queryFn: () => fetchDirectory(page, q),
    placeholderData: keepPreviousData,
  })
}

export function useFloorPlan(date: string) {
  return useQuery({
    queryKey: floorPlanQueryKey(date),
    queryFn: () => fetchFloorPlan(date),
    placeholderData: keepPreviousData,
  })
}
