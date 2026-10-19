# TikTok — intégration locale et premier essai réel

État du 19 octobre 2026 : connexion OAuth Sandbox, préfixe HTTPS vérifié et premier envoi réel réussi par le propriétaire. Contrôle en lecture seule : une publication au statut `published`. Application non auditée, compte de test privé et visibilité `SELF_ONLY` ; statistiques réelles TikTok toujours désactivées. Les sections datées ci-dessous conservent l’historique du lot local et de son activation.

Mise à jour du 18 octobre : le propriétaire a créé son application Sandbox et ajouté son compte test. Pour renseigner le portail, le tunnel de test existant a été redémarré : `https://pushing-yarn-curtis-rows.trycloudflare.com`. Le routeur autorise désormais précisément `/cgu` et `/confidentialite`. Website : origine du tunnel ; Login Kit Web : origine + `/studio/connexions/tiktok/retour`. Les pages légales sont accessibles, mais le dépôt et la validation du fichier de propriété TikTok restent à faire. Aucun accès TikTok enregistré, aucune connexion ni publication effectuée pendant cette préparation. L’adresse temporaire peut changer au redémarrage ; les callbacks Instagram/YouTube configurés sur l’ancienne origine n’ont pas été modifiés.

## Objectif et périmètre initial du 17 octobre

Connecter un compte TikTok puis préparer jusqu’à cinq vidéos, confirmer explicitement leurs réglages et suivre les publications dans le studio. Ce lot est autorisé sans créer de compte développeur ce soir. L’intégration est testée avec une API simulée ; aucune connexion, publication réelle ou exposition HTTPS du serveur local n’a eu lieu. Instagram possède déjà son parcours Reels local, décrit dans [instagram-publication.md](instagram-publication.md), avec la même attente des accès réels.

Le parcours principal depuis la bibliothèque prépare désormais plusieurs réseaux avec une confirmation commune ; voir [publication-multireseaux.md](publication-multireseaux.md). Le formulaire TikTok individuel reste disponible. Calendrier, IA et statistiques ne sont pas ajoutés dans ce lot.

## Fichiers et composants

- `src/Entity/TikTokConnection.php` conserve un compte par propriétaire, les identifiants OAuth chiffrés, les expirations d’accès et de renouvellement. `TikTokPublication.php` conserve la source, le compte choisi, les réglages confirmés, l’intention d’envoi et les identifiants de suivi. La migration `Version20261016220806` ajoute uniquement ces deux tables.
- `src/Service/TikTokApi.php` dialogue avec les endpoints officiels, vérifie identité/permissions/réponses et renouvelle les jetons avec rotation chiffrée sous verrou. `tiktok.http` est un client séparé, sans traceur Symfony ; les erreurs restent neutres. `tiktok.vault` réutilise TokenVault avec une clé propre.
- `TikTokConnectionController` gère le départ POST/CSRF, le retour OAuth avec state à usage unique lié au propriétaire pendant dix minutes, puis la déconnexion avec tentative de révocation distante. Si cette révocation échoue, les accès locaux sont retirés et l’utilisateur est invité à retirer l’autorisation dans TikTok. Une reconnexion ratée conserve l’ancien compte.
- `TikTokPublicationController` et `TikTokPublishType` gèrent sélection privée, aperçu, légende, visibilité sans présélection, interactions décochées, contenu commercial et consentement musique/contenus de marque. Le serveur vérifie tous les choix ; un partenariat ne peut pas être privé. La limite de légende est contrôlée en unités UTF-16, comme TikTok.
- `TikTokPublishing` vérifie propriétaire, format, capacité actuelle du compte et identité attendue, puis enregistre vidéos/messages dans une transaction atomique. Un même identifiant de soumission ne crée pas deux lots.
- `PublishTikTok` et `PublishTikTokHandler` utilisent le transport Doctrine et le worker existants. Les départs d’un lot sont espacés ; un limiteur limite les initialisations à cinq par minute et propriétaire. Le worker recontrôle le compte et ses capacités avant l’envoi.
- `TikTokMediaLink` et `TikTokMediaController` servent seulement la source privée du job par URL HMAC expirant après 24 heures, GET/HEAD/Range. L’accès cesse à la fin du suivi ou après annulation. La bibliothèque entière ne devient pas publique. Une source active ne peut pas être supprimée.
- Les templates `publication/tiktok_prepare.html.twig`, Connexions et `_job.html.twig` présentent le parcours. `PublicationOverview`, DashboardController et VideoAssetRepository ajoutent TikTok aux filtres, compteurs et badges, sans appel social sur le dashboard.

## Flux d’exécution et point Symfony

1. Security exige ROLE_VERIFIED ; CurrentUser identifie le propriétaire. Le contrôleur recherche chaque vidéo avec ce propriétaire.
2. OAuth associe une autorisation à la session, échange le code côté serveur et chiffre les jetons avant persistence Doctrine. Aucun mot de passe TikTok n’est demandé par VydFlow.
3. La préparation appelle creator_info uniquement lorsqu’un compte et l’envoi sont configurés. Elle propose les visibilités réelles et désactive les interactions interdites. Aucun choix de visibilité ni interaction n’est activé implicitement.
4. Le formulaire Symfony contrôle CSRF, clé de préparation et consentement. Le service vérifie à nouveau les capacités actuelles à la confirmation. Chaque vidéo devient une entité et un message Messenger dans la même transaction.
5. Le worker enregistre l’intention **avant** le POST d’initialisation, qui peut déjà aboutir à une publication. Si sa réponse est perdue ou que le processus s’arrête avant d’enregistrer le publish_id, l’état devient « À vérifier ». Aucun second POST automatique n’est tenté.
6. Avec un publish_id connu, seule la consultation de statut est répétée. Après trente consultations ou cinq erreurs, le suivi est suspendu et peut reprendre. TikTok peut confirmer une vidéo privée sans identifiant public : le succès est conservé, aucun lien inventé.

À comprendre en Symfony : le contrôleur orchestre HTTP, le FormType traite la saisie, le service porte les règles métier et Doctrine gère les transactions. Messenger exécute le travail hors de la requête navigateur. Une redélivrance de message est normale ; le handler doit consulter l’état persisté pour éviter de publier deux fois. Les simulations HTTP valident notre comportement, pas l’acceptation de l’application par TikTok.

## Configuration ultérieure

Uniquement dans `.env.local` ignoré ou l’environnement de déploiement :

```dotenv
TIKTOK_CLIENT_KEY=
TIKTOK_CLIENT_SECRET=
TIKTOK_TOKEN_KEY=
TIKTOK_REDIRECT_URI=
TIKTOK_MEDIA_BASE_URL=
TIKTOK_URL_VERIFIED=0
TIKTOK_PUBLISH_ENABLED=0
TIKTOK_AUDITED=0
```

Créer une application TikTok avec Login Kit et Content Posting API / Direct Post ; demander `user.info.basic` et `video.publish`. Fournir un callback HTTPS statique et identique à la configuration TikTok, se terminant par `/studio/connexions/tiktok/retour`. Le serveur stocke déjà les médias : utiliser PULL_FROM_URL, ce qui exige la vérification par TikTok du domaine ou préfixe HTTPS des médias. Ne pas activer le drapeau URL_VERIFIED avant cette vérification. Générer une clé de chiffrement de 32 octets aléatoires encodés en base64 et la sauvegarder sans modifier les clés des autres réseaux.

L’application non auditée exige des comptes privés et des envois SELF_ONLY, avec restrictions TikTok sur les utilisateurs et les quotas. Le code réduit les choix à SELF_ONLY et refuse un compte public dans ce mode. Passer AUDITED à 1 uniquement après validation effective de l’application ; ce drapeau ne remplace aucune revue. Le lancement SaaS public nécessite l’approbation TikTok. Aucun tunnel ni serveur debug exposé dans ce lot. L’environnement HTTPS, les accès et le premier essai réel restent à préparer et autoriser séparément.

`.env` contient des valeurs vides et des drapeaux inactifs ; `.env.test` contient uniquement des valeurs synthétiques. Le client TikTok des tests est toujours MockHttpClient. Après modification des accès ou du code, redémarrer le worker Docker.

## Comment tester

Suivre README pour migrer shortflow_test, puis exécuter `docker compose exec -T -e SHELL_VERBOSITY=-1 php php vendor/bin/phpunit --filter TikTok`. Le contrôle global reste `php scripts/check.php` dans Docker, puis `npm run check` et le build Vite (volume Node Docker sur ce poste).

Les tests couvrent OAuth, permissions, chiffrement et rotation, CSRF/state/rejeu/expiration/propriétaire, confidentialité sans présélection, consentement, contenu commercial, emoji, transactions, isolation des sources, doublons, intention persistée, réponse perdue, reprise par statut, URL signée/Range/expiration/annulation, suppression protégée et badges. Sans configuration réelle, Connexions indique que TikTok attend l’activation et la préparation ne permet pas de publier.

## Références officielles consultées

- [Login Kit Web](https://developers.tiktok.com/doc/login-kit-web) et [gestion des jetons](https://developers.tiktok.com/doc/oauth-user-access-token-management).
- [Direct Post](https://developers.tiktok.com/doc/content-posting-api-reference-direct-post), [creator_info](https://developers.tiktok.com/doc/content-posting-api-reference-query-creator-info), [statut](https://developers.tiktok.com/doc/content-posting-api-reference-get-video-status).
- [Règles d’interface et d’audit](https://developers.tiktok.com/doc/content-sharing-guidelines) et [transfert des médias](https://developers.tiktok.com/doc/content-posting-api-media-transfer-guide).

Les résultats effectivement exécutés et les limites de vérification figurent dans [verification.md](verification.md).


### Préparation OAuth Sandbox — 19 octobre 2026

Identifiants du Sandbox renseignés par le propriétaire dans `.env.local`, clé de chiffrement et retour HTTPS configurés. Les caches local et entrée HTTPS sans debug ont été rechargés. Les drapeaux de publication, audit, vérification du média et statistiques réelles restent à zéro : connexion uniquement, sans envoi. Le propriétaire doit ouvrir le studio depuis le domaine HTTPS du callback avant de cliquer sur Connecter TikTok.

`TikTokApi::authorizationUrl` demande maintenant `user.info.basic` et `video.publish` lorsque les statistiques sont désactivées. `video.list` est ajouté uniquement si `TIKTOK_STATISTICS_ENABLED=1`. Symfony injecte ce drapeau au service ; les tests couvrent les deux variantes. Cela évite de demander au sandbox une permission de lecture non configurée lors du simple essai de connexion.


### Vérification de propriété et diagnostic d’envoi — 19 octobre

Le propriétaire a fourni le fichier de vérification TikTok. Il a été copié sans modification dans `public/`, puis autorisé individuellement dans le routeur du tunnel, en GET/HEAD. La réponse publique correspond exactement au fichier original. Le clic Verify et la confirmation du portail restent à faire ; `TIKTOK_URL_VERIFIED` et `TIKTOK_PUBLISH_ENABLED` restent donc à zéro. Le routeur accepte aussi le format exact des liens TikTok signés ; le contrôleur maintient ses contrôles HMAC, expiration, propriétaire et état actif. Aucun téléchargement d’une vraie vidéo ni envoi n’a été lancé.


### Activation de l’envoi Sandbox — 19 octobre 2026

Le propriétaire confirme que TikTok affiche le préfixe du tunnel comme vérifié. Activation locale de `TIKTOK_URL_VERIFIED=1` et `TIKTOK_PUBLISH_ENABLED=1`, sans modifier les identifiants ni les connexions existantes. `TIKTOK_AUDITED=0` et statistiques réelles désactivées. Caches sans debug du serveur local et de l’entrée HTTPS rechargés, worker redémarré après vérification d’absence de transfert actif. Diagnostic en lecture seule : OAuth prêt, liens signés prêts et un compte TikTok connecté avec autorisation non expirée. Aucun appel d’initialisation de publication effectué ; le propriétaire doit reprendre sa préparation, choisir une visibilité autorisée et confirmer le récapitulatif. L’activation ne vaut pas validation d’un premier transfert réel réussi.


### Premier transfert réel réussi — 19 octobre 2026

Le propriétaire confirme qu’une vidéo a été publiée depuis VydFlow sur TikTok. Une requête de lecture confirme une ligne `published`, sans lancer d’autre transfert ni collecter de statistiques. La connexion et l’envoi immédiat TikTok sont donc validés en sandbox ; un essai de programmation TikTok reste distinct. Le compte de test doit rester privé pour l’application non auditée ; la visibilité de la vidéo est choisie explicitement, sans valeur prédéfinie. Références : [règles de partage](https://developers.tiktok.com/docs/en/content-sharing-guidelines) et [informations du créateur](https://developers.tiktok.com/docs/en/content-posting-api-reference-query-creator-info).


### Accès au profil depuis Connexions — 19 octobre 2026

Le compte connecté possède désormais « Voir le profil ↗ ». `TikTokConnectionController::profile` retrouve exclusivement la connexion du propriétaire, applique un limiteur et appelle `TikTokApi::profileUrl`. Cette lecture utilise `creator_info/query` avec l’autorisation `video.publish` déjà nécessaire à l’envoi. Le `creator_username` renvoyé doit respecter le format attendu avant de construire une URL HTTPS TikTok ; le nom affiché n’est jamais pris pour un pseudo. Aucun champ supplémentaire, migration ou nouvelle autorisation OAuth. Le lien est résolu au clic pour suivre aussi les changements de pseudo, sans appel social lors du simple affichage des connexions.

La redirection privée utilise `no-store` et `no-referrer`. Une absence de compte, un échec fournisseur ou un pseudo invalide ramène aux connexions avec un message, sans toucher aux autres réseaux. Tester avec un compte connecté, un autre propriétaire et une réponse fournisseur inattendue. Aucun envoi ni relevé statistique n’est déclenché par cette route.

Référence : [Query Creator Info — documentation TikTok](https://developers.tiktok.com/docs/en/content-posting-api-reference-query-creator-info?enter_method=left_navigation).
