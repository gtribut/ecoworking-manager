<x-mail::message>
# Bienvenue chez Ecoworking, {{ $firstName }} !

Votre compte vient d'être créé sur le portail membre Ecoworking. Il ne vous
reste qu'à choisir votre mot de passe pour y accéder.

<x-mail::button :url="$url">
Définir mon mot de passe
</x-mail::button>

Ce lien est valable **{{ $ttlDays }} jours**. Passé ce délai, utilisez le lien
« Mot de passe oublié ? » de la page de connexion, ou demandez-nous un nouvel
email d'accueil.

Une fois connecté, vous pourrez compléter votre profil, réserver une salle et
consulter vos factures.

À très vite,<br>
L'équipe Ecoworking
</x-mail::message>
