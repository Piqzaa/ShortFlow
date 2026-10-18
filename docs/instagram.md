# Instagram — connexion OAuth et publication Reels, 16 octobre 2026

État actualisé le 18 octobre 2026 : première connexion OAuth Instagram réelle réussie par le propriétaire, confirmée en base avec YouTube sur le même compte VydFlow. Aucun Reel encore publié par cette intervention. Les paragraphes initiaux ci-dessous décrivent le lot historique ; le tunnel HTTPS autorisé et les diagnostics récents sont documentés plus bas.

## Objectif et périmètre

Connecter un compte Instagram Créateur/Entreprise directement depuis Connexions, puis préparer et publier des Reels depuis la bibliothèque. Le premier lot ci-dessous décrit OAuth ; le fonctionnement du second lot est détaillé dans [instagram-publication.md](instagram-publication.md). L’essai réel attend les accès Meta et un environnement HTTPS adapté ; aucune connexion ni publication Instagram réelle annoncée. TikTok, calendrier et IA restent hors de ce lot.

## Fichiers et composants

- `src/Entity/InstagramConnection.php` : propriétaire unique, identifiant du compte professionnel, nom de profil, type, jeton chiffré, expiration. Une nouvelle table additive ; aucune modification des comptes, vidéos ou publications YouTube existants.
- `src/Service/InstagramApi.php` : URL Instagram, échange du code, contrôle des permissions, échange longue durée, lecture du profil et validation de son identité/type. Deux formats de réponse acceptés : objet direct ou un seul objet dans data. Délai limité, aucune redirection HTTP, aucune exception fournisseur brute propagée.
- `src/Controller/InstagramConnectionController.php` : départ et déconnexion POST/CSRF, retour GET avec state lié à la session et au propriétaire, valable dix minutes et consommé une seule fois. Le verrou propriétaire existant évite les changements concurrents. La reconnexion remplace la connexion seulement après validation complète ; un échec conserve l’ancien accès.
- `src/Controller/YouTubeConnectionController.php` : sa page Connexions commune fournit aussi la connexion Instagram du propriétaire ; les actions YouTube restent séparées.
- `config/services.yaml` : client HTTP Instagram dédié sans traceur/profiler, et instance `instagram.vault` de TokenVault utilisant une clé Instagram distincte. Les secrets de requête ne passent jamais par le navigateur ; le coffre YouTube existant reste inchangé.
- `templates/publication/_instagram_connection.html.twig`, inclus dans Connexions : carte, icône, compte courant, indisponibilité, avertissement d’expiration, reconnexion/déconnexion. Styles dans `assets/styles/publication.css`. Bouton désactivé si la configuration privée manque.
- `migrations/Version20261015231230.php` : création de instagram_connection, unicité propriétaire, suppression de sa connexion en cascade seulement lors de la suppression de son compte. Migration générée, relue puis appliquée sans suppression de données.
- `tests/Unit/InstagramApiTest.php`, `tests/Functional/InstagramConnectionTest.php` et `config/services_test.yaml` : Instagram HTTP toujours simulé, même si des identifiants réels sont présents localement. Base exclusivement shortflow_test.

## Flux Symfony

1. Une requête autorisée par ROLE_VERIFIED affiche Connexions avec les données filtrées sur CurrentUser.
2. Le POST vérifie le CSRF et limite les tentatives ; le contrôleur conserve un state aléatoire en session puis redirige vers Instagram.
3. Le retour consomme et vérifie ce state avant tout appel Meta. Refus, expiration ou rejeu ne contactent pas l’API.
4. Le service échange le code et vérifie les deux permissions. Il obtient un jeton longue durée et vérifie le profil professionnel et l’identité associée au jeton.
5. TokenVault chiffre le jeton avec libsodium. Doctrine enregistre la connexion dans une transaction ; la réponse 303 revient vers Connexions avec cache privé non stockable et Referrer-Policy no-referrer. La page ne reçoit jamais le jeton.
6. Déconnecter retire uniquement la ligne appartenant au compte connecté et invalide son flow OAuth local. Aucun contenu Instagram supprimé, aucune révocation distante exécutée.

À comprendre : OAuth autorise VydFlow à accéder au compte, sans fournir son mot de passe à VydFlow. Le state protège le lien entre le départ et le retour. Le conteneur Symfony injecte deux clients HTTP et deux coffres distincts ; Doctrine stocke les métadonnées et le secret chiffré, Twig affiche uniquement les champs nécessaires.

## Configuration privée ultérieure

Mise à jour du 18 octobre : tunnel HTTPS temporaire explicitement autorisé pour les essais personnels, avec entrée Docker dédiée sans debug. Procédure et limites : [instagram-test-https.md](instagram-test-https.md). Cela remplace l'interdiction d'ouvrir un tunnel sans accord dans les paragraphes historiques ci-dessous ; le serveur de développement habituel ne doit toujours pas être exposé.

Les variables publiques .env sont vides ; elles ne rendent pas la connexion disponible. Compléter seulement .env.local ignoré après création de l’application Meta :

    INSTAGRAM_CLIENT_ID=identifiant-app-instagram
    INSTAGRAM_CLIENT_SECRET=secret-app-instagram
    INSTAGRAM_TOKEN_KEY=cle-base64-de-32-octets-aleatoires
    INSTAGRAM_REDIRECT_URI=http://localhost:8000/studio/connexions/instagram/retour
    INSTAGRAM_API_VERSION=v26.0

Utiliser les identifiants du produit Instagram, pas un jeton copié dans le chat. Générer la clé une seule fois à partir de random_bytes(32), sauvegarder en sécurité et ne pas changer les clés existantes. Aucun secret réel généré ou renseigné pendant cette étape. L’application accepte HTTP uniquement pour localhost/127.0.0.1 ; l’acceptation exacte du callback local doit encore être vérifiée dans le tableau de bord Meta. Ne pas ouvrir de tunnel ni exposer le serveur de développement/debug pour contourner cette restriction. Si Meta demande HTTPS, préparer séparément un environnement adapté avec accord du propriétaire.

Les scopes sont instagram_business_basic et instagram_business_content_publish ; aucun accès aux messages ou commentaires. Version v26.0 observée dans le guide Meta lors de cette étape ; vérifier sa disponibilité avec l’application créée avant l’essai réel. Le state est utilisé ; aucun PKCE non documenté n’est inventé.

L’expiration renvoyée par Meta est conservée, limitée à 60 jours ; la page avertit sept jours avant. Une reconnexion renouvelle l’autorisation. Le lot Reels suspend les envois si le jeton est expiré ; le renouvellement automatique n’est pas implémenté. Un jeton révoqué à distance peut paraître valide jusqu’au prochain appel API : la carte indique l’état local, sans appel réseau à chaque affichage.

## Comment tester

- Préparer et migrer shortflow_test selon README, puis `docker compose exec -T -e SHELL_VERBOSITY=-1 php php vendor/bin/phpunit --filter Instagram`.
- Parcours sans configuration réelle : ouvrir `/studio/connexions`, vérifier le bouton Instagram indisponible et la connexion YouTube préservée. Aucun faux compte dev ajouté pour l’interface.
- Les tests couvrent scopes minimaux, formats de réponse, profil personnel/incohérent refusé, chiffrement, propriété, CSRF, state erroné/expiré/rejoué, refus utilisateur, erreur fournisseur masquée, ancien compte conservé et état expiré. Les callbacks simulés ne sont pas une validation du service Meta réel.
- Essai réel futur : application Meta configurée, compte test professionnel ajouté aux rôles appropriés, callback identique, puis autorisation personnelle dans Instagram. La revue Meta pour des comptes tiers reste un préalable au lancement public ; cette étape ne la valide pas.

## Sources officielles

Documentation Meta lue dans le navigateur intégré le 16 octobre 2026, les requêtes du moteur web étant limitées par Meta :

- [Business Login](https://developers.facebook.com/documentation/instagram-platform/instagram-api-with-instagram-login/business-login) : permissions, state, échanges des tokens et durées. Page mise à jour le 13 mars 2026.
- [Commencer](https://developers.facebook.com/documentation/instagram-platform/instagram-api-with-instagram-login/get-started) : profil, identifiants et types de compte.

Résultats exacts dans docs/verification.md. L’accès développeur Meta reste distinct de la connexion Instagram proposée aux futurs utilisateurs.

## Diagnostic des premiers essais HTTPS — 18 octobre

Le premier message précis a localisé le refus au contrôle des permissions après échange du code. Le code n'acceptait qu'une chaîne séparée par virgules/espaces ; il accepte désormais aussi une liste JSON de chaînes, en exigeant toujours les deux permissions minimales. Les listes absentes, imbriquées, malformées ou partielles restent refusées. Le message distingue une liste non exploitable des permissions minimales non confirmées, dont les noms proviennent seulement de notre liste fixe. Le propriétaire a ensuite réussi sa connexion réelle. Tests finaux de ce lot : `verification.md`.

L'échec OAuth affichait auparavant un message unique dans le contrôleur, ce qui masquait l'étape réellement refusée. `InstagramApi::json()` accepte désormais un message fixe par étape : échange du code, prolongation du jeton, lecture du profil. Seuls le statut HTTP et les codes Meta entiers `code`/`error_subcode` peuvent compléter ce message. Aucun corps d'erreur, message fournisseur, trace distante, code OAuth ou jeton n'est affiché ni journalisé. `InstagramConnectionController` rend les `PublicationException` contrôlées ; toute autre exception conserve le message neutre. Les contrôles de permissions, identité et compte professionnel restent inchangés.

Les tests unitaires simulent le refus à chacune des trois étapes, ainsi que des erreurs malformées et des champs contenant des secrets synthétiques. Le test fonctionnel de conservation YouTube simule successivement un échec puis une réussite Instagram et vérifie le même identifiant de connexion YouTube, les mêmes jetons chiffrés et la chaîne affichée. Exécuter après migration de `shortflow_test` : `docker compose exec -T -e SHELL_VERBOSITY=-1 php php vendor/bin/phpunit --filter 'InstagramApiTest|InstagramConnectionTest'`.

Localhost et le domaine HTTPS ont des sessions navigateur indépendantes. Si leurs emails VydFlow diffèrent, leurs réseaux connectés diffèrent également. Mais constater deux emails ne suffit pas à expliquer tous les incidents : le propriétaire a précisé pouvoir connecter YouTube sur son compte de test, puis constater un état incohérent après Instagram. La configuration utilisait un retour YouTube localhost et un retour Instagram HTTPS : commencer le parcours depuis la mauvaise origine perd le state de la session au retour. Le contrôleur charge uniquement les connexions appartenant à `CurrentUser` ; les tests sur une même session vérifient qu'un échec Instagram conserve la connexion YouTube.

`OAuthCallbackOrigin::assertSameOrigin()` compare désormais schéma, hôte et port du départ avec l'URI de retour configurée, avant de créer le state et de rediriger vers le réseau. Il est appelé par les deux contrôleurs YouTube et Instagram ; un mélange d'origines reçoit une explication et l'adresse attendue, sans appel social ni changement des connexions. Les contrôles state/propriétaire/expiration au retour restent obligatoires. `.env.test` fixe explicitement les retours fictifs sur l'origine du navigateur fonctionnel, indépendamment des secrets et adresses du poste.
