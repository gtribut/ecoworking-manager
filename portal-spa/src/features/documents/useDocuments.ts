import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  fetchAdministrativeDocuments,
  fetchInternalDocuments,
  validateInternalDocument,
} from './api'

export const internalDocumentsQueryKey = ['documents', 'internal'] as const
export const administrativeDocumentsQueryKey = (page: number) =>
  ['documents', 'administrative', page] as const

export function useInternalDocuments() {
  return useQuery({
    queryKey: internalDocumentsQueryKey,
    queryFn: fetchInternalDocuments,
  })
}

/** `enabled: false` pour les membres sans permission billing (section masquée). */
export function useAdministrativeDocuments(page: number, enabled = true) {
  return useQuery({
    queryKey: administrativeDocumentsQueryKey(page),
    queryFn: () => fetchAdministrativeDocuments(page),
    placeholderData: keepPreviousData,
    enabled,
  })
}

export function useValidateInternalDocument() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (documentId: number) => validateInternalDocument(documentId),
    // Invalide la liste partagée entre la page Documents et le bloc dashboard.
    onSuccess: () => queryClient.invalidateQueries({ queryKey: internalDocumentsQueryKey }),
  })
}
