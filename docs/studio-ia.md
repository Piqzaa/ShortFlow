# Studio IA BYOK — cadrage du 19 octobre 2026

Statut : premier parcours local implémenté le 19 octobre, avec API Fal simulée en tests ; essais payants désactivés. Le propriétaire ne choisit pas de fournisseur personnel et demande que les utilisateurs puissent choisir leurs IA et modèles vidéo. Cette décision remplace la limite antérieure « un fournisseur/un modèle ». La disponibilité de chaque modèle reste conditionnée à son API, ses accès, ses capacités et ses tests.

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


## Première livraison locale — 19 octobre

Depuis Studio → « Générer une vidéo », saisir nom, prompt et choisir Kling 2.5 Turbo Pro ou Wan 2.5 via Fal. Le formulaire propose 5/10 secondes et les formats vertical, paysage ou carré. Wan est limité à 720p dans ce lot ; Kling utilise son réglage fournisseur. Aucun choix de modèle imposé. Les tarifs indicatifs sont 0,07 USD/seconde pour Kling et 0,10 USD/seconde pour Wan à 720p, vérifiés sur les pages officielles citées ci-dessous. Un tarif de plus de 14 jours bloque un nouveau départ. Ces estimations ne garantissent ni la facture ni une limite de dépense fournisseur.

Le récapitulatif enregistre l’idée sans envoi. L’ajout d’une clé peut se faire en conservant les champs du formulaire ; depuis une préparation enregistrée, le retour mène à celle-ci. La liste des douze préparations/générations les plus récentes est privée et distincte des brouillons de publication. Les modèles d’autres fournisseurs ne sont pas annoncés utilisables : l’adaptateur Replicate, image vers vidéo, catalogue large/recherche et réglages avancés restent les prochaines extensions.

### Composants et exécution Symfony

- `AiStudioController` expose `/studio/ia`, `/studio/ia/generations/{id}` et `/studio/connexions/ia`. Security exige ROLE_VERIFIED et la préparation est retrouvée par propriétaire, y compris sur POST. `AiIdeaType` valide modèle, durée, format et longueurs côté serveur. Les POST réussis redirigent en 303.
- `AiCredential` conserve la clé Fal chiffrée et datée ; `AiKeys` réutilise le chiffrement sodium `TokenVault` avec une **clé de chiffrement indépendante** `AI_TOKEN_KEY`. Aucun secret ne passe dans une URL ou dans Messenger. La clé n’est pas réaffichée, et « enregistrée » ne signifie pas « authentifiée auprès de Fal ». Remplacement et retrait CSRF, confirmation explicite du retrait, limiteur de modifications.
- `AiPrivacyListener` désactive la collecte du profiler sur les routes IA. Sur le POST de clé, il retire avant routage les corps brut et analysé contenant la clé ; une `SensitiveParameterValue` transmet celle-ci au contrôleur, qui retire aussi cet attribut. Le formulaire de clé n’utilise pas le collecteur de formulaire Symfony. Le client `ai.http` est créé sans logger/profiler, à l’image des clients OAuth existants.
- `AiGeneration` conserve propriétaire, idée, modèle, paramètres, estimation datée, état et référence de résultat. La migration `Version20261019010000` ajoute seulement ces deux tables ; aucune donnée existante n’est effacée.
- `AiGenerations::confirm` verrouille le propriétaire et la préparation, vérifie clé/activation/tarif et limite à trois départs simultanés. Le message `GenerateAiVideo` ne contient que l’identifiant. `GenerateAiVideoHandler` utilise la file Messenger existante `publications` et le worker Docker, sans nouveau service cloud.
- `FalVideoApi` traduit les réglages vers les endpoints contrôlés. L’état `submitting` est enregistré **avant** le POST facturable. En cas de réponse ambiguë ou de redélivrance après coupure, passer à « À vérifier » plutôt que renvoyer. Les consultations ultérieures attendent 20 secondes ; au-delà de 45 minutes, demander une reprise explicite de la même consultation. Un refus fournisseur est présenté comme un état à vérifier, sans reprendre automatiquement la soumission.
- Le résultat n’est téléchargé que depuis Fal Media ou le préfixe `storage.googleapis.com/falserverless/`, sans clé d’API ni redirection. Limites de temps/taille, validation `VideoProbe`, quotas et stockage privé `VideoLibrary` sont réutilisés. L’import et l’association au résultat sont une même transaction ; la redélivrance ne crée pas deux vidéos. Une erreur d’import, de domaine ou de quota propose de reprendre la récupération, sans nouvelle génération.
- Une clé retirée avant le départ bloque celui-ci dans un état spécifique, sans requête fournisseur. Son remplacement permet une reprise avec nouvelle confirmation du coût. Une clé retirée après soumission n’annule pas l’opération chez Fal.
- Twig et `ai-studio.css` réutilisent la navigation et les tokens du studio, avec une colonne principale pour l’idée et une colonne de modèles sur grand écran, puis une seule colonne sur téléphone. Aucun nouvel outil frontend ni dépendance ajouté.

À comprendre en Symfony : le contrôleur reçoit le formulaire et prépare une réponse ; le service porte les règles ; Doctrine conserve l’état ; Messenger poursuit le travail après la réponse HTTP. Le suivi en base permet de distinguer une nouvelle demande payante d’une simple récupération du même résultat. Une vidéo disponible retourne au parcours existant de publication : **aucun envoi social automatique**.

### Configuration et essais

`AI_TOKEN_KEY` : clé sodium de 32 octets en base64, uniquement `.env.local` ignoré ou environnement. Une clé locale indépendante a été créée sans être affichée. La conserver avec les sauvegardes privées ; la changer rend les clés BYOK existantes illisibles. `AI_GENERATION_ENABLED=0` reste le défaut, et reste désactivé dans la configuration locale réelle. Ne pas l’activer avant un essai payant explicitement demandé avec modèle/paramètres/coût. Le bouton de confirmation est désactivé et le serveur refuse aussi un POST forgé. Une fois l’essai autorisé, recharger le cache et le worker pour prendre la configuration en compte.

Tests locaux : migrations test, puis `docker compose exec -T -e SHELL_VERBOSITY=-1 php vendor/bin/phpunit tests/Functional/AiStudioTest.php`. Les clés et réponses HTTP y sont synthétiques et `AI_GENERATION_ENABLED=1` ne s’applique qu’à `shortflow_test`. Aucun fournisseur n’est contacté et aucune facture créée. Tester aussi le parcours sans clé, la conservation de l’idée, le récapitulatif, le retour à la préparation et le menu Connexions sur téléphone. Le premier essai réel, les contraintes propres au compte fournisseur et les formats réellement produits restent à valider séparément.

Tarifs et schémas vérifiés : [Kling 2.5 Turbo Pro chez Fal](https://fal.ai/models/fal-ai/kling-video/v2.5-turbo/pro/text-to-video) et [schéma](https://fal.ai/models/fal-ai/kling-video/v2.5-turbo/pro/text-to-video/api) ; [Wan 2.5 chez Fal](https://fal.ai/models/fal-ai/wan-25-preview/text-to-video) et [schéma](https://fal.ai/models/fal-ai/wan-25-preview/text-to-video/api). La documentation Veo consultée indique une dépréciation prochaine de l’endpoint ; il n’a pas été intégré dans ce premier lot.


### Évolution de l’espace de création

Le 19 octobre, l’interface devient **Studio** pour accueillir à terme création et montage, et la bibliothèque devient **Vidéos**. Entrée principale : `/studio/creation`, avec compatibilité `/studio/ia`. Les retours de configuration conservent l’idée et conduisent au nouveau Studio. Nom facultatif, estimation indicative locale, comparaison repliable et aperçu de la dernière création : voir [studio-compte.md](studio-compte.md). Ces changements n’activent ni montage réel, ni séries, ni génération payante. Les règles de coût, confirmation et récupération du premier lot restent les mêmes.
