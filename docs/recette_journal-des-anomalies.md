# Recette — journal des anomalies

> Fichier \*\*dédié à la saisie\*\* des anomalies rencontrées pendant la recette (`recette.md`).
> Séparé de la checklist pour pouvoir être édité dans un autre logiciel sans conflit d'écriture.
>
> \*\*Usage\*\* : une ligne par anomalie, identifiant `R-nn` croissant, ne jamais réutiliser un
> identifiant. Quand une anomalie est corrigée, je passe son statut à `corrigé (commit …)` — ne
> pas supprimer la ligne. Pour lancer une correction : « corrige R-nn ».
>
> Gravité : 🔴 = donnée d'autrui visible, argent faux, perte de données · 🟠 = parcours impossible ·
> 🟡 = gênant mais contournable · 💡 = amélioration / écart PRD à trancher.
>
> Statuts : `à corriger` · `à trancher` (écart PRD, décision Guillaume) · `en cours` · `corrigé (commit)` · `rejeté (raison)`.

## Journal

|ID|Écran / §|Compte|Constaté|Attendu (PRD)|Gravité|Statut|
|-|-|-|-|-|-|-|
|R-01|portal / login||message de limitation après 6 tentatives ratées en 1 min → le message est en anglais|le passer en français|🟡|corrigé (SPA : message 429 en français + délai Retry-After, `lib/errors.ts`)|
|R-02|portal / login-magic link|claire.fontaine@atelier-lumiere.demo|sur le test "Lien de connexion (magic link)", après avoir rensiegné l'email "claire.fontaine@atelier-lumiere.demo" et envoyé, le message générique est bien présenté, mais aucun email n'est envoyé|email envoyé, c'est un compte existant et actif|🟠|corrigé (cause : aucun worker de queue en dev, 2 mails bloqués dans `jobs` — livrés au premier `queue:work` ; recette §0 exige désormais `queue:listen` pendant la recette)|
|R-03|portal / login|tous|pour le test "Mot de passe oublié → mail Mailpit → nouveau mot de passe → connexion OK → l'ancien magic link éventuel est invalidé", aucun lien/bouton "mot de passe oublié", uniquement un lien "Recevoir un lien de connexion par email"|C'est bien comme ça, pas forcément besoin d'un doublon mot de passe perdu avec le magic link, à checker l'attendu PRD|🟡|corrigé (b1a2959 : lien « Mot de passe oublié ? » + page /reset-password/:token ; PRD §3.2 le demande bien en plus du magic link ; réponse anti-énumération côté back)|
|R-04|portal / profil|claire.fontaine@atelier-lumiere.demo|Pas d'option 2FA membre activable|2FA membre optionnelle, pas mis par défaut, mais activable dans la page profil|🟡|corrigé (18f622f : section « Double authentification » sur le profil — activation QR/code, codes de récupération, désactivation)|
|R-05|portal / accueil|tous|Bloc \*\*Mes dernières factures\*\* + Bloc \*\*Mes prochaines réservations\*\* + Bouton \*\*Nous contacter\*\* non présents sur la page d'accueil du dashboard|Reprendre tous les attendus du PRD 3.3, beaucoup de choses manquent|🟠|corrigé (e44a036 : blocs factures (billing_contact) + prochaines résas + « Nous contacter », grille 2 colonnes)|
|R-06|portal / accueil|tous|Bloc "Documents à valider"<br />> Tous vos documents sont à jour. reste présent sur l'accueil|Sur l'accueil, ce serait mieux si le bloc était entièrement masqué quand aucun doc en attente de validation, ça prend de la place pour rien.|💡|corrigé (bloc masqué si aucun document à valider)|
||||||||
||||||||
||||||||
||||||||

\---

*Ouvert le 2026-09-13. Checklist associée : `docs/recette.md`.*

