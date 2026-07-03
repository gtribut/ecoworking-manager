export type InternalDocumentType = 'charter' | 'cgu' | 'image_rights' | 'other'

export type AdministrativeDocumentType = 'contract' | 'amendment' | 'domiciliation' | 'other'

/** Document interne applicable au membre, avec SON statut de validation (version courante). */
export interface InternalDocument {
  id: number
  type: InternalDocumentType
  title: string
  version: string
  body: string | null
  published_at: string | null
  pdf_available: boolean
  is_validated: boolean
  validated_at: string | null
}

/** Document administratif d'une entité du périmètre billing du membre. */
export interface AdministrativeDocument {
  id: number
  type: AdministrativeDocumentType
  title: string
  document_date: string | null
  company_name: string | null
  pdf_available: boolean
}
