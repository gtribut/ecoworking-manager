import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { z } from 'zod'
import { MarkdownContent } from '@/components/MarkdownContent'
import { QueryError } from '@/components/QueryError'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { NativeSelect } from '@/components/ui/native-select'
import { Spinner } from '@/components/ui/spinner'
import { Textarea } from '@/components/ui/textarea'
import { EntityBlock } from '@/features/billing/EntityBlock'
import { getApiErrorMessage } from '@/lib/errors'
import { usePageTitle } from '@/lib/usePageTitle'
import { PasswordSection } from './PasswordSection'
import { PhotoSection } from './PhotoSection'
import { TwoFactorSection } from './TwoFactorSection'
import { useProfile, useUpdateProfile } from './useProfile'

const BIO_MAX_LENGTH = 500

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
  notify_email: z.boolean(),
  notify_in_app: z.boolean(),
})
type FormValues = z.infer<typeof schema>

function nullable(value: string | undefined): string | null {
  return value && value.length > 0 ? value : null
}

export function ProfilePage() {
  usePageTitle('Mon profil — Portail Ecoworking')

  const { data, isLoading, isError, refetch } = useProfile()
  const updateProfile = useUpdateProfile()
  const [bioPreview, setBioPreview] = useState(false)

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
      notify_email: true,
      notify_in_app: true,
    },
  })

  const { reset } = form
  // Lu ici (pas seulement dans l'effet) : c'est cet accès, pendant le rendu,
  // qui abonne react-hook-form à `isDirty` (proxy de `formState`).
  const { isDirty } = form.formState

  useEffect(() => {
    if (!data) return
    // Défense en profondeur (review) : le thème change désormais par une
    // mutation dédiée qui ne touche plus ce cache (cf. useUpdateTheme), mais
    // toute autre invalidation de `profileQueryKey` ne doit jamais écraser
    // une saisie en cours (bio, etc.) pendant que l'utilisateur édite.
    if (isDirty) return
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
      notify_email: data.user.notify_email,
      notify_in_app: data.user.notify_in_app,
    })
  }, [data, reset, isDirty])

  if (isLoading) {
    return <Spinner label="Chargement du profil…" />
  }
  if (isError || !data) {
    return (
      <QueryError message="Impossible de charger votre profil." onRetry={() => void refetch()} />
    )
  }

  const hasProfile = data.profile !== null
  const bioValue = form.watch('bio') ?? ''

  const onSubmit = form.handleSubmit(async (values) => {
    try {
      await updateProfile.mutateAsync({
        theme: values.theme === '' ? null : values.theme,
        notify_email: values.notify_email,
        notify_in_app: values.notify_in_app,
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
      toast.success('Profil mis à jour.')
    } catch (error) {
      toast.error(getApiErrorMessage(error))
    }
  })

  return (
    <div className="mx-auto max-w-2xl space-y-8">
      <h1 className="text-2xl font-semibold">Mon profil</h1>

      {hasProfile && (
        <PhotoSection
          firstName={data.user.first_name}
          lastName={data.user.last_name}
          photo={data.profile?.photo ?? null}
        />
      )}

      <form onSubmit={onSubmit} className="space-y-8" noValidate>
        <fieldset className="space-y-4">
          <legend className="text-lg font-medium">Mes informations</legend>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
            <p className="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
              Pour modifier votre adresse email, contactez Ecoworking.
            </p>
          </div>

          <div>
            <Label htmlFor="theme">Thème</Label>
            <NativeSelect id="theme" {...form.register('theme')}>
              <option value="">Automatique (système)</option>
              <option value="light">Clair</option>
              <option value="dark">Sombre</option>
            </NativeSelect>
          </div>
        </fieldset>

        <fieldset className="space-y-3">
          <legend className="text-lg font-medium">Notifications</legend>
          <p className="text-sm text-neutral-500 dark:text-neutral-400">
            Choisissez comment être prévenu (facture émise, document à valider, réservation…).
          </p>
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" className="size-4" {...form.register('notify_in_app')} />
            Notifications dans le portail (cloche)
          </label>
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" className="size-4" {...form.register('notify_email')} />
            Notifications par email
          </label>
        </fieldset>

        {hasProfile && (
          <fieldset className="space-y-4">
            <legend className="text-lg font-medium">Profil public</legend>

            <div>
              <Label htmlFor="job_title">Fonction / poste</Label>
              <Input id="job_title" {...form.register('job_title')} />
            </div>
            <div>
              <div className="flex items-center justify-between">
                {/* En mode Aperçu le textarea est démonté : `htmlFor="bio"`
                    désignerait un élément inexistant (RGAA 11.1). On garde
                    l'identifiant, qui nomme la zone d'aperçu, mais plus la
                    liaison de formulaire. */}
                {bioPreview ? (
                  <span
                    id="bio-label"
                    className="mb-1 block text-sm font-medium text-neutral-800 dark:text-neutral-200"
                  >
                    Présentation
                  </span>
                ) : (
                  <Label id="bio-label" htmlFor="bio">
                    Présentation
                  </Label>
                )}
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  aria-pressed={bioPreview}
                  onClick={() => setBioPreview((previous) => !previous)}
                >
                  {bioPreview ? 'Éditer' : 'Aperçu'}
                </Button>
              </div>

              {bioPreview ? (
                <section
                  aria-labelledby="bio-label"
                  className="min-h-20 rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900"
                >
                  {bioValue.trim() === '' ? (
                    <p className="text-neutral-400 dark:text-neutral-500">
                      Rien à prévisualiser pour l’instant.
                    </p>
                  ) : (
                    <MarkdownContent markdown={bioValue} />
                  )}
                </section>
              ) : (
                <Textarea
                  id="bio"
                  rows={4}
                  maxLength={BIO_MAX_LENGTH}
                  describedBy="bio-help bio-counter"
                  error={form.formState.errors.bio?.message}
                  {...form.register('bio')}
                />
              )}

              <p id="bio-help" className="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                Markdown simple : **gras**, *italique*, listes, liens.
              </p>
              <p
                id="bio-counter"
                aria-live="polite"
                className="mt-1 text-xs text-neutral-500 dark:text-neutral-400"
              >
                {bioValue.length}/{BIO_MAX_LENGTH} caractères
              </p>
            </div>
            <div>
              <Label htmlFor="interests">Centres d’intérêt</Label>
              <Input id="interests" {...form.register('interests')} />
            </div>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <Label htmlFor="linkedin_url">LinkedIn</Label>
                <Input
                  id="linkedin_url"
                  type="url"
                  placeholder="https://…"
                  error={form.formState.errors.linkedin_url?.message}
                  {...form.register('linkedin_url')}
                />
              </div>
              <div>
                <Label htmlFor="website_url">Site web</Label>
                <Input
                  id="website_url"
                  type="url"
                  placeholder="https://…"
                  error={form.formState.errors.website_url?.message}
                  {...form.register('website_url')}
                />
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

        <Button type="submit" disabled={form.formState.isSubmitting || updateProfile.isPending}>
          Enregistrer mes modifications
        </Button>
      </form>

      {/* Entité juridique (PRD §3.4.3) — lecture seule, hors du formulaire. */}
      {data.company ? (
        <EntityBlock entity={data.company} />
      ) : (
        // Cas atypique (PRD §3.4.3) : compte sans entité juridique rattachée.
        <p className="text-sm text-neutral-600 dark:text-neutral-300">
          Aucune entité juridique n’est rattachée à votre compte. Contactez Ecoworking si c’est une
          erreur.
        </p>
      )}

      {/* Sécurité (PRD §3.2 / §3.4.5) — hors du formulaire profil : ses propres appels Fortify. */}
      <PasswordSection />
      <TwoFactorSection />
    </div>
  )
}
