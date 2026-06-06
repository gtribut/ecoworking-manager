import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Label } from '@/components/ui/Label'
import { Select } from '@/components/ui/Select'
import { Spinner } from '@/components/ui/Spinner'
import { Textarea } from '@/components/ui/Textarea'
import { getApiErrorMessage } from '@/lib/errors'
import type { CompanyData } from './types'
import { useProfile, useUpdateProfile } from './useProfile'

const optionalUrl = z.union([z.literal(''), z.string().url('URL invalide.')])

const schema = z.object({
  theme: z.enum(['', 'light', 'dark']),
  job_title: z.string().max(150).optional(),
  bio: z.string().max(500, '500 caractères maximum.').optional(),
  interests: z.string().max(200, '200 caractères maximum.').optional(),
  linkedin_url: optionalUrl,
  website_url: optionalUrl,
  birth_date: z.string().optional(),
  show_in_directory: z.boolean(),
  newsletter_opt_in: z.boolean(),
})
type FormValues = z.infer<typeof schema>

function nullable(value: string | undefined): string | null {
  return value && value.length > 0 ? value : null
}

export function ProfilePage() {
  const { data, isLoading, isError } = useProfile()
  const updateProfile = useUpdateProfile()
  const [feedback, setFeedback] = useState<{ type: 'success' | 'error'; message: string } | null>(
    null,
  )

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      theme: '',
      job_title: '',
      bio: '',
      interests: '',
      linkedin_url: '',
      website_url: '',
      birth_date: '',
      show_in_directory: false,
      newsletter_opt_in: false,
    },
  })

  const { reset } = form

  useEffect(() => {
    if (!data) return
    reset({
      theme: data.user.theme ?? '',
      job_title: data.profile?.job_title ?? '',
      bio: data.profile?.bio ?? '',
      interests: data.profile?.interests ?? '',
      linkedin_url: data.profile?.linkedin_url ?? '',
      website_url: data.profile?.website_url ?? '',
      birth_date: data.profile?.birth_date ?? '',
      show_in_directory: data.profile?.show_in_directory ?? false,
      newsletter_opt_in: data.profile?.newsletter_opt_in ?? false,
    })
  }, [data, reset])

  if (isLoading) {
    return <Spinner label="Chargement du profil…" />
  }
  if (isError || !data) {
    return <Alert variant="error">Impossible de charger votre profil.</Alert>
  }

  const hasProfile = data.profile !== null

  const onSubmit = form.handleSubmit(async (values) => {
    setFeedback(null)
    try {
      await updateProfile.mutateAsync({
        theme: values.theme === '' ? null : values.theme,
        ...(hasProfile
          ? {
              job_title: nullable(values.job_title),
              bio: nullable(values.bio),
              interests: nullable(values.interests),
              linkedin_url: nullable(values.linkedin_url),
              website_url: nullable(values.website_url),
              birth_date: nullable(values.birth_date),
              show_in_directory: values.show_in_directory,
              newsletter_opt_in: values.newsletter_opt_in,
            }
          : {}),
      })
      setFeedback({ type: 'success', message: 'Profil mis à jour.' })
    } catch (error) {
      setFeedback({ type: 'error', message: getApiErrorMessage(error) })
    }
  })

  return (
    <div className="mx-auto max-w-2xl space-y-8">
      <h1 className="text-2xl font-semibold">Mon profil</h1>

      {feedback && <Alert variant={feedback.type}>{feedback.message}</Alert>}

      <form onSubmit={onSubmit} className="space-y-8" noValidate>
        <fieldset className="space-y-4">
          <legend className="text-lg font-medium">Mes informations</legend>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <Label htmlFor="first_name">Prénom</Label>
              <Input id="first_name" value={data.user.first_name} disabled readOnly />
            </div>
            <div>
              <Label htmlFor="last_name">Nom</Label>
              <Input id="last_name" value={data.user.last_name} disabled readOnly />
            </div>
          </div>
          <div>
            <Label htmlFor="email">Email</Label>
            <Input id="email" value={data.user.email} disabled readOnly />
            <p className="mt-1 text-xs text-neutral-500">
              La modification de l’email se fait via une procédure dédiée.
            </p>
          </div>

          <div>
            <Label htmlFor="theme">Thème</Label>
            <Select id="theme" {...form.register('theme')}>
              <option value="">Automatique (système)</option>
              <option value="light">Clair</option>
              <option value="dark">Sombre</option>
            </Select>
          </div>
        </fieldset>

        {hasProfile && (
          <fieldset className="space-y-4">
            <legend className="text-lg font-medium">Profil public</legend>

            <div>
              <Label htmlFor="job_title">Fonction / poste</Label>
              <Input id="job_title" {...form.register('job_title')} />
            </div>
            <div>
              <Label htmlFor="bio">Présentation</Label>
              <Textarea
                id="bio"
                rows={4}
                aria-invalid={Boolean(form.formState.errors.bio)}
                {...form.register('bio')}
              />
              {form.formState.errors.bio && (
                <p className="mt-1 text-sm text-red-600">{form.formState.errors.bio.message}</p>
              )}
            </div>
            <div>
              <Label htmlFor="interests">Centres d’intérêt</Label>
              <Input id="interests" {...form.register('interests')} />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <Label htmlFor="linkedin_url">LinkedIn</Label>
                <Input
                  id="linkedin_url"
                  type="url"
                  placeholder="https://…"
                  aria-invalid={Boolean(form.formState.errors.linkedin_url)}
                  {...form.register('linkedin_url')}
                />
                {form.formState.errors.linkedin_url && (
                  <p className="mt-1 text-sm text-red-600">
                    {form.formState.errors.linkedin_url.message}
                  </p>
                )}
              </div>
              <div>
                <Label htmlFor="website_url">Site web</Label>
                <Input
                  id="website_url"
                  type="url"
                  placeholder="https://…"
                  aria-invalid={Boolean(form.formState.errors.website_url)}
                  {...form.register('website_url')}
                />
                {form.formState.errors.website_url && (
                  <p className="mt-1 text-sm text-red-600">
                    {form.formState.errors.website_url.message}
                  </p>
                )}
              </div>
            </div>
            <div>
              <Label htmlFor="birth_date">Date de naissance</Label>
              <Input id="birth_date" type="date" {...form.register('birth_date')} />
            </div>

            <label className="flex items-center gap-2 text-sm">
              <input type="checkbox" className="size-4" {...form.register('show_in_directory')} />
              Afficher mon profil dans l’annuaire Ecoworking
            </label>
            <label className="flex items-center gap-2 text-sm">
              <input type="checkbox" className="size-4" {...form.register('newsletter_opt_in')} />
              M’inscrire à la newsletter
            </label>
          </fieldset>
        )}

        {data.company && <CompanyBlock company={data.company} />}

        <Button type="submit" disabled={form.formState.isSubmitting || updateProfile.isPending}>
          Enregistrer mes modifications
        </Button>
      </form>
    </div>
  )
}

function CompanyBlock({ company }: { company: CompanyData }) {
  const rows: Array<[string, string | null]> = [
    ['Raison sociale', company.legal_name ?? company.name],
    ['Forme juridique', company.legal_form],
    ['SIRET', company.siret],
    ['N° TVA', company.vat_number],
    ['Email de facturation', company.billing_email],
    [
      'Adresse',
      [company.address.line1, company.address.postal_code, company.address.city]
        .filter(Boolean)
        .join(', ') || null,
    ],
  ]

  return (
    <section
      aria-labelledby="company-heading"
      className="space-y-3 rounded-lg border border-neutral-200 p-4 dark:border-neutral-800"
    >
      <h2 id="company-heading" className="text-lg font-medium">
        Mon entreprise
      </h2>
      <dl className="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
        {rows.map(([label, value]) => (
          <div key={label}>
            <dt className="text-neutral-500">{label}</dt>
            <dd>{value ?? '—'}</dd>
          </div>
        ))}
      </dl>
      <p className="text-xs text-neutral-500">
        Ces informations sont gérées par Ecoworking.{' '}
        <a
          className="underline"
          href="mailto:contact@ecoworking.fr?subject=[backend ecowo] Demande de modification"
        >
          Demander une modification
        </a>
      </p>
    </section>
  )
}
