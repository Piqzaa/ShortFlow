# Studio IA BYOK — cadrage du 19 octobre 2026

Statut : lot autorisé ; cadrage commencé, génération non implémentée. Le propriétaire ne choisit pas de fournisseur personnel et demande que les utilisateurs puissent choisir leurs IA et modèles vidéo. Cette décision remplace la limite antérieure « un fournisseur/un modèle ». La disponibilité de chaque modèle reste conditionnée à son API, ses accès, ses capacités et ses tests.

## Expérience visée

Depuis le Studio, « Générer une vidéo » ouvre l’idée et un choix de modèles avec recherche, filtres texte/image vers vidéo, format vertical, durée et audio. Afficher d’abord les modèles utilisables avec les connexions de l’utilisateur ; conserver un accès au catalogue complet pris en charge. Les réglages avancés restent repliés. Aucun modèle sélectionné automatiquement en remplacement d’un autre.

Le modèle et le fournisseur sont distincts : le même modèle peut être accessible chez plusieurs fournisseurs avec des tarifs et paramètres différents. Choisir un modèle affiche ses fournisseurs pris en charge, la connexion nécessaire et une estimation datée pour les paramètres retenus. Une clé Fal ne devient pas une clé Google, Runway ou Replicate. Un abonnement grand public ne prouve pas l’existence d’un accès API.

Si la clé manque, conserver le prompt et les paramètres pendant le passage à la rubrique « IA et clés API » dans Connexions. Les accès sociaux et IA restent identifiés séparément dans cette même page. Après configuration, revenir à l’idée conservée. La confirmation précise le fournisseur facturant la génération ; l’utilisateur le finance directement. Aucun wallet VydFlow.

Après confirmation : demande enregistrée → file de génération → génération fournisseur → récupération et validation du fichier → bibliothèque privée → parcours existant de préparation/publication. La génération ne déclenche aucune publication sociale.

## Catalogue et intégrations

Orientation technique proposée : commencer par des connecteurs multi-modèles Fal et Replicate, puis compléter les API directes utiles. Ce sont des fournisseurs d’accès ; l’utilisateur peut garder son fournisseur pour un modèle donné. L’intégration d’un fournisseur ne rend pas automatiquement tous ses endpoints compatibles avec VydFlow.

Le catalogue pris en charge devra associer explicitement fournisseur, identifiant/version du modèle, schéma des paramètres, entrées requises, durées, formats, audio, tarification et date de vérification. Les formats incompatibles avec la bibliothèque existante doivent être bloqués avant dépense ou passer par une transformation explicitement prise en charge. Ne pas afficher un tarif inventé ; bloquer la confirmation si l’estimation nécessaire ne peut pas être obtenue de façon fiable.

Découverte distante et prise en charge sont deux étapes différentes. Une synchronisation peut signaler des nouveautés pour intégration ; elle ne doit pas activer silencieusement un modèle, ses réglages ou ses coûts. Préférer des schémas validés et une adaptation limitée par fournisseur au formulaire brut de centaines d’endpoints. Pas de saisie libre d’URL d’API.

## Livraison progressive

1. Catalogue et sélection de modèles avec paramètres réellement compatibles, connexions BYOK privées et reprise de l’idée. Montrer explicitement ce qui est en préparation tant que le moteur n’est pas disponible.
2. Premier parcours complet sur plusieurs modèles d’un fournisseur : validation serveur, estimation, confirmation, génération asynchrone simulée, copie privée et erreurs récupérables.
3. Deuxième fournisseur et possibilité de choisir où exécuter un même modèle, sans modifier le parcours.
4. Essai payant demandé explicitement, avec modèle, paramètres et coût présentés avant départ ; étendre ensuite le catalogue par familles testées.

Ces étapes sont un ordre de réalisation dans le lot autorisé ; elles ne constituent pas une promesse de couverture universelle. Ne pas créer de classes pour des fournisseurs encore non intégrés. Pas de génération réelle, création de compte fournisseur ou dépense par cette préparation.

## Sécurité et fiabilité à vérifier

Clé chiffrée par propriétaire, jamais renvoyée au navigateur, absente des logs/profiler et des messages Messenger. Formulaires CSRF, suppression/remplacement explicites. Conserver la référence fournisseur et le coût estimé avec la demande. Éviter une seconde soumission facturable après délai réseau ambigu ; afficher un état à vérifier plutôt que relancer aveuglément. Les reprises de consultation et de récupération de fichier doivent être distinctes d’une nouvelle génération.

Téléchargement limité aux domaines fournisseur explicitement acceptés, sans transmission de clé à une URL de résultat arbitraire, taille et délais bornés, validation vidéo existante et quotas propriétaire. Définir rétention et suppression des prompts et médias temporaires lors de l’implémentation. Les résultats fournisseur peuvent expirer : la copie privée durable fait partie de la réussite, pas seulement le succès distant.

Tests attendus : API HTTP simulées uniquement, isolation propriétaire des clés et générations, estimation/paramètres invalides, double clic et timeout de soumission, attente/échec/modération/quota, récupération partielle et quotas bibliothèque, conservation de l’idée pendant la connexion, mobile et clavier. Les migrations de test concernent exclusivement `shortflow_test`.

## Documentation officielle consultée le 19 octobre 2026

- [Fal : catalogue de modèles](https://fal.ai/docs/documentation/model-apis/models) et [file asynchrone](https://fal.ai/docs/documentation/model-apis/inference/queue).
- [Replicate : modèles vidéo](https://replicate.com/collections/text-to-video) et [création asynchrone de générations](https://replicate.com/docs/topics/predictions/create-a-prediction).
- [Google : génération vidéo Veo par API](https://ai.google.dev/gemini-api/docs/veo?hl=en).

Les versions et prix précis seront revérifiés à l’implémentation. Cette recherche n’a envoyé aucun prompt ni identifiant privé à ces services.
