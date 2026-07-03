<x-mail::message>
# Connexion au portail Ecoworking

Bonjour,

Vous avez demandé un lien de connexion au portail membre. Cliquez sur le bouton
ci-dessous pour vous connecter — le lien est valable **{{ $ttlMinutes }} minutes**
et ne peut être utilisé qu'une seule fois.

<x-mail::button :url="$url">
Me connecter
</x-mail::button>

Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet email :
personne ne peut se connecter à votre compte sans accès à cette boîte mail.

À bientôt,<br>
L'équipe Ecoworking
</x-mail::message>
