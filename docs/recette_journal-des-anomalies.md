# Recette — journal des anomalies

> Fichier **dédié à la saisie** des anomalies rencontrées pendant la recette (`recette.md`).
> Séparé de la checklist pour pouvoir être édité dans un autre logiciel sans conflit d'écriture.
>
> **Usage** : une ligne par anomalie, identifiant `R-nn` croissant, ne jamais réutiliser un
> identifiant. Quand une anomalie est corrigée, je passe son statut à `corrigé (commit …)` — ne
> pas supprimer la ligne. Pour lancer une correction : « corrige R-nn ».
>
> Gravité : 🔴 = donnée d'autrui visible, argent faux, perte de données · 🟠 = parcours impossible ·
> 🟡 = gênant mais contournable · 💡 = amélioration / écart PRD à trancher.
>
> Statuts : `à corriger` · `à trancher` (écart PRD, décision Guillaume) · `en cours` · `corrigé (commit)` · `rejeté (raison)`.

## Journal

| ID | Écran / § | Compte | Constaté | Attendu (PRD) | Gravité | Statut |
|---|---|---|---|---|---|---|
| R-01 | portal / login |  | message de limitation après 6 tentatives ratées en 1 min → le message est en anglais | le passer en français | 🟡 | à corriger |
| R-02 | portal / login-magic link | claire.fontaine@atelier-lumiere.demo | sur le test "Lien de connexion (magic link)", après avoir rensiegné l'email "claire.fontaine@atelier-lumiere.demo" et envoyé, le message générique est bien présenté, mais aucun email n'est envoyé | email envoyé, c'est un compte existant et actif | 🟠 | à corriger |
| R-03 | portal / login |  | pour le test "Mot de passe oublié → mail Mailpit → nouveau mot de passe → connexion OK → l'ancien magic link éventuel est invalidé", aucun lien/bouton "mot de passe oublié", uniquement un lien "Recevoir un lien de connexion par email" | C'est bien comme ça, pas forcément besoin d'un doublon mot de passe perdu avec le magic link, à checker l'attendu PRD | 🟡 | à checker l'attendu PRD et en discuter |
| R-04 | portal / profil | claire.fontaine@atelier-lumiere.demo | Pas d'option 2FA membre activable | 2FA membre optionnelle, pas mis par défaut, mais activable dans la page profil | 🟡 | à corriger |
| R-05 |  |  |  |  |  | à corriger |

---

*Ouvert le 2026-09-13. Checklist associée : `docs/recette.md`.*
