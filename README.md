# VydFlow — bêta, studio IA et publication

Épisodes et calendrier : préparation liée à la version validée, suivi des réseaux depuis la série ; rédacteur local avec erreurs explicites. [Parcours, composants et tests](docs/series-publications-redacteur.md).

Corrections du 24 octobre : rédaction locale en arrière-plan avec suivi et reprise, navigation libérée, choix d’enchaînement adapté au nombre de clips, épisodes initiaux configurables et ajoutables. Guide Symfony et état de la récurrence : [docs/corrections-studio-series-recurrence.md](docs/corrections-studio-series-recurrence.md).

Scénario local supervisé : proposition d’histoire et de scènes depuis le studio ou un épisode, application volontaire et confirmation vidéo inchangée. Guide et comparaison 4B/8B : [docs/scenario-local-supervise.md](docs/scenario-local-supervise.md).

Préparation unifiée, 23 octobre : studio et épisodes modifiables partagent l’idée, la durée finale, les modèles, les références et les plans sur un écran. Raccords guidés et options avancées repliées ; confirmation du coût conservée. Essai local Mistral/Qwen3 effectué sans API payante, mais qualité des scénarios encore insuffisante pour activer l’autonomie. Guide et résultats : [docs/preparation-unifiee-essai-local.md](docs/preparation-unifiee-essai-local.md).

Vidéos à plans, 23 octobre : studio et séries partagent désormais une durée totale au choix jusqu’à 60 secondes, des propositions de découpage compatibles et 1 à 8 scènes ajoutables, supprimables et réordonnables. Les épisodes « À confirmer » peuvent remplacer leur ancienne estimation non lancée ; les créations du studio ne nécessitent aucune série. Son conservé par défaut, coût total confirmé avant génération, raccords image compatibles, montage automatique et validation avant préparation/programmation. Migrations additives `Version20261023115349` et `Version20261023195047`, caches web/workers/assets à actualiser hors traitement actif. Aucun nouvel essai payant ou social réel. Guide : [docs/episodes-multiplans.md](docs/episodes-multiplans.md).

Séries IA, premier lot du 22 octobre : `/studio/series` conserve un univers et des réglages BYOK communs, prépare trois épisodes avec prompts modifiables et affiche leur génération liée. Préparation sans appel fournisseur ; confirmation individuelle du coût dans le parcours existant. Lot fini de générations et programmation depuis la série restent les étapes suivantes. Migration additive `Version20261022153610`, cache dev et build frontend à actualiser. Guide et pédagogie : [docs/series-mvp.md](docs/series-mvp.md).

Fiabilité du 22 octobre : contrôles vidéo communs avant envoi, visibilité YouTube confirmée séparée de la demande, reprise du suivi et nouvelle préparation limitée à une destination en échec. Les générations IA/statistiques sont isolées dans la file `background` et le service `background-worker`. Après mise à jour : appliquer les migrations, vider le cache dev puis relancer `worker`, `background-worker` et `edit-worker` avec `docker compose up -d --no-deps --force-recreate worker background-worker edit-worker`. Faire cette mise à jour hors traitement actif ; ne pas vider les files. Guide, limites et pédagogie : [docs/fiabilite-publication.md](docs/fiabilite-publication.md).

Sécurité PHP : base Docker fixée à PHP 8.4.27 pour corriger CVE-2026-109618 et CVE-2026-109619. Après récupération du changement, reconstruire les deux images et recréer les quatre services PHP ; un redémarrage seul conserve l'ancienne image. Procédure et explications : [docs/securite-php-2026-10.md](docs/securite-php-2026-10.md).

Navigation locale : `APP_DEBUG=0` dans `.env.local` évite les coûteuses vérifications de fichiers Windows/Docker pour utiliser le studio ; remettre `1` pour déboguer. Après modification des routes/services/configuration sans debug : `docker compose exec php php bin/console cache:clear --env=dev --no-debug`. Les templates restent actualisés en dev. Mesures et aperçu local avant import : [docs/performance-import.md](docs/performance-import.md).

Studio IA guidé : modèle et résolution séparés, aide aux connexions, suivi automatique, reprise d'idées et historique complet. Parcours, contrôles de stockage et repères Symfony : [docs/studio-ia-guide.md](docs/studio-ia-guide.md).

Micro-SaaS Symfony : landing immersive avec inscription, comptes confirmés, dashboard et bibliothèque vidéo privée. Import MP4/WebM, lecture, renommage et suppression. Le lot YouTube ajoute sélection multiple, connexion OAuth, préparation et suivi des envois Messenger, avec worker Docker automatique. Résultats des essais réels : docs/verification.md. Brouillons, programmation ponctuelle et calendrier disponibles localement ; premier Reel Instagram programmé testé réellement et premier envoi TikTok Sandbox réussi le 19 octobre. Le studio IA est intégré à la bêta ; un premier montage guidé est disponible ; sous-titres locaux disponibles ; premier lot Séries disponible ; génération par lots et abonnement restent à venir. Aucun service cloud ni compte payant nécessaire pour explorer localement ; les essais YouTube réels demandent une application Google. La liste d’attente historique est conservée sur /liste-attente, hors parcours principal.

Le studio et l’espace personnel ont été remaniés le 18 octobre : galerie et brouillons, préparation sur un écran, photo privée et préférences du compte. Parcours et guide Symfony dans [docs/studio-compte.md](docs/studio-compte.md).

## Vision de lancement

Vision validée le 15 octobre 2026 : « Importe ou génère tes vidéos courtes, prépare-les et programme leur publication sur tes réseaux depuis un seul studio. » Deux parcours : vidéos importées avec montage facultatif et textes adaptés, puis publication immédiate ou calendrier ; séries IA BYOK préparées en avance avec montage, textes et programmation, validation activée par défaut ou automatisation explicitement choisie selon les possibilités des plateformes. Choix BYOK multi-fournisseurs et multi-modèles validé le 19 octobre ; quelques recettes de montage, pause et limites de générations/dépense ; aucun éditeur vidéo complet. Le calendrier ponctuel est implémenté localement ; les sous-titres locaux et une recette Reel sont intégrés ; les séries sont préparables en avance ; lancement groupé et programmation depuis la série restent à construire. Cible et étapes dans docs/roadmap.md, sans autorisation implicite de commencer les lots suivants.

Montage guidé commun aux imports et aux résultats IA : sélection de 1 à 8 clips, coupes/ordre, format et export privé MP4 1080p. Le nouveau service Docker `edit-worker` consomme uniquement la file `video_edits`, séparée des publications. Après les migrations : `docker compose up -d --no-deps edit-worker`. Guide : [docs/montage-guide.md](docs/montage-guide.md). Éditeur visuel et sous-titres locaux : [docs/montage-sous-titres.md](docs/montage-sous-titres.md). Le worker utilise désormais `vydflow-edit` : `docker compose build edit-worker` puis `docker compose up -d --no-deps edit-worker`. Modèle multilingue Small installé à la construction, `LOCAL_CAPTIONS_ENABLED=0` par défaut ; mettre `1` dans `.env.local` pour les sous-titres gratuits sur ce poste.

## Démarrage local

Catalogue IA : **54 configurations chez Fal, Replicate et LTX**, dont 33 image vers vidéo et 19 acceptant une image de fin. Trois choix Fal sont mis en avant ; le catalogue complet et les résolutions restent accessibles. Premier essai image confirmé réussi par le propriétaire ; les essais réels ne valident pas chaque modèle. Connexion Fal par navigateur ou clé, autres fournisseurs par clé. Le défaut versionné reste `AI_GENERATION_ENABLED=0` ; les essais manuels sur ce poste sont activés dans `.env.local`. Voir [le guide image](docs/studio-image-video-complet.md), [le suivi](docs/suivi-generation-ia.md) et [le parcours simplifié](docs/studio-simple-recherche.md).

Prérequis : Docker Desktop démarré (conteneurs Linux), Docker Compose et Git. Node local est optionnel : un conteneur outils est prévu.

Depuis le dossier du projet :

```sh
docker compose build
docker compose run --rm --no-deps php composer install
docker compose --profile tools run --rm node
docker compose up -d
docker compose exec php composer db:sync
```

Créer .env.local avant le premier démarrage sur un nouveau clone : APP_SECRET doit être une chaîne aléatoire privée. Exemple de génération : docker compose run --rm --no-deps php php -r "echo bin2hex(random_bytes(32));". Le fichier est ignoré par Git ; une valeur locale a déjà été générée dans cet espace de travail.

### Après un git pull et accès PostgreSQL

Git transfère le code et les migrations, pas la base, les vidéos importées ni `.env.local`. Après un pull :

```sh
docker compose stop worker background-worker edit-worker
docker compose up -d database mailpit php
docker compose exec php composer db:sync
docker compose --profile tools run --rm --no-deps node
docker compose up -d --no-deps --force-recreate worker background-worker edit-worker
```

Si `composer.lock` a changé, réinstaller d'abord les dépendances comme indiqué ci-dessous. Si le Dockerfile a changé, reconstruire l'image PHP. Si les assets ont changé, relancer le build frontend.

La commande Node ci-dessus installe les dépendances et reconstruit les assets : `public/build` est ignoré par Git. Après un pull, des templates récents avec un ancien CSS peuvent donner un dashboard sans style ou des textes collés. Reconstruire puis recharger le navigateur avec `Ctrl+F5`.

`composer db:sync` crée la base dev si elle manque, applique seulement les migrations manquantes puis vérifie le schéma. Les fichiers versionnés de `migrations/` sont la recette de construction des tables : aucun export SQL de données personnelles n'est nécessaire. Cette commande ne purge pas les tables ; relire toute nouvelle migration avant de l'appliquer. Elle ne prépare pas la base de test, gérée par `scripts/check.php`.

Les seeds/fixtures sont des données de démonstration facultatives. Aucune donnée métier initiale n'est requise actuellement : créer un compte par `/inscription`, confirmer via Mailpit, puis importer ses vidéos. Aucun compte à mot de passe partagé, faux compte social ou seed automatique n'est installé. Les fixtures des tests restent exclusivement dans `shortflow_test`.

Pour l'extension PostgreSQL de VS Code sur ce PC :

| Champ | Valeur locale |
| --- | --- |
| Hôte | `127.0.0.1` |
| Port | `5433` |
| Base | `shortflow` |
| Utilisateur | `shortflow` |
| Mot de passe | `localdev` |
| SSL | Désactivé pour ce serveur local |

Ces identifiants sont ceux de `compose.yaml`, exclusivement pour Docker local. Le port PostgreSQL est lié à loopback ; aucun accès réseau public n'est ouvert. Depuis PHP/worker, l'adresse reste `database:5432` : ne pas remplacer leur `DATABASE_URL` par l'adresse VS Code. Le volume `shortflow_database_data` conserve les données. Ne pas lancer `docker compose down -v` pour résoudre une migration manquante.

Pour vérifier : `docker compose exec php composer db:sync` peut être répété, puis recharger `/studio`. Doctrine mémorise les migrations exécutées dans `doctrine_migration_versions` ; une deuxième exécution ne recrée pas les tables.

### Réseaux sociaux sur un autre PC

Essais Instagram par tunnel HTTPS temporaire autorisé : [procédure dédiée](docs/instagram-test-https.md), entrée Docker sans debug, arrêt et limites. Le serveur de développement habituel ne doit pas être ciblé par le tunnel.

L’état « À configurer » indique que la configuration privée du réseau manque sur ce poste. Les identifiants OAuth (client ID/key et client secret), la clé de chiffrement des jetons et les URL de retour se configurent dans `.env.local`, ignoré par Git. Ils ne sont donc pas récupérés par un pull. Réutiliser l’application développeur existante, notamment celle de Google pour YouTube ; il n’est pas nécessaire d’en créer une par PC. Ne jamais copier les secrets dans le dépôt ou dans une conversation.

La configuration de l’application et l’autorisation d’un compte sont deux étapes différentes : une fois le réseau configuré, connecter son compte depuis **Réseaux**. Cette autorisation est enregistrée dans la base locale, elle aussi indépendante de Git. Si une base contenant des connexions est restaurée, conserver les clés de chiffrement d’origine ; les remplacer rend les jetons existants illisibles. Après modification de la configuration privée, vider le cache dev avec `docker compose exec php php bin/console cache:clear --no-debug`, puis redémarrer le worker. Les premiers essais Meta/TikTok restent soumis aux accès, à la configuration HTTPS et à l’autorisation explicite du propriétaire.

Les dépendances PHP (`php_vendor` → `/app/vendor`) et le cache Symfony (`app_cache` → `/app/var/cache`) sont conservés dans des volumes Docker natifs partagés par PHP et le worker. Cela évite les milliers d'accès aux fichiers Windows/OneDrive pendant chaque requête. Le code source reste modifiable depuis Windows et le mode debug reste actif. Après un changement de `composer.lock`, lancer `docker compose run --rm --no-deps php composer install`, puis `docker compose restart worker`. Utiliser Composer et les outils PHP dans Docker : un éventuel dossier `vendor` Windows n'est plus celui exécuté par les conteneurs. Pour rafraîchir le cache : `docker compose exec php php bin/console cache:clear`.

Un `git pull` ne reconfigure pas les conteneurs déjà démarrés : `docker compose restart` les relance avec leurs anciens montages. Après un changement de Compose, installer les dépendances dans les volumes prévus avec `docker compose run --rm --no-deps php composer install`, puis appliquer la configuration avec `docker compose up -d php worker`. Sur Windows/OneDrive, un ancien conteneur qui lit encore `vendor` et `var/cache` via le montage du projet peut ralentir chaque page de plusieurs secondes. `docker inspect vydflow-php-1 --format '{{range .Mounts}}{{.Destination}} {{.Type}}{{println}}{{end}}'` doit afficher `/app/vendor volume` et `/app/var/cache volume` en plus de `/app bind`.

La base PostgreSQL et les vidéos privées ne sont pas dans ces deux volumes : leur emplacement reste inchangé. Les commandes de démarrage ci-dessus initialisent aussi les volumes sur un nouveau clone ; aucune copie manuelle du cache n'est nécessaire.

Mailpit VydFlow utilise désormais http://localhost:8026 pour cohabiter avec un autre projet qui occupe déjà le port 8025 ; SMTP interne reste mailpit:1025. Le serveur VydFlow reste sur localhost:8000. Les ports restent exclusivement liés à loopback.

Les services `worker` et `background-worker` démarrent avec `docker compose up -d` : le premier traite les publications confirmées, le second les générations IA et statistiques ; `edit-worker` garde les montages. Aucun terminal de consommation séparé n’est nécessaire. Ils attendent les dépendances Composer et la table Messenger avant de consommer, puis Docker les relance après une sortie. Après un changement PHP ou de secrets dans `.env.local`, exécuter `docker compose restart worker background-worker edit-worker`. État : `docker compose ps` ; diagnostic : `docker compose logs --tail 30 worker background-worker`. Ces consommateurs utilisent exclusivement la base dev ; les tests gardent leur base séparée.

Ouvrir http://localhost:8000. Les interfaces HTTP de l’application et de Mailpit sont exposées uniquement sur loopback. PostgreSQL et SMTP restent sur le réseau Compose. Identifiants Compose strictement locaux. Arrêter sans supprimer les données : docker compose stop. Ne pas exécuter down -v si les données sont à conserver.

Le studio est sur http://localhost:8000/studio ; l’inscription sur http://localhost:8000/inscription. Il faut confirmer son email avant de pouvoir se connecter. La boîte email de développement Mailpit est sur http://localhost:8026, uniquement en loopback. SMTP n’est pas publié ; les emails ne sortent pas du réseau Docker. APP_PUBLIC_URL définit l’origine de confiance des liens (défaut local http://localhost:8000). Ne pas déployer Mailpit publiquement. Voir docs/accounts.md pour le parcours de test et l’explication Symfony.

Bibliothèque : http://localhost:8000/studio/bibliotheque. Après mise à jour du Dockerfile, relancer `docker compose build php` puis `docker compose up -d` pour installer ffprobe et les limites d’upload. Limites bêta : MP4 H.264/AAC ou WebM compatible 8 bits, 100 Mo/fichier, 180 secondes, 1 Go et 100 vidéos/compte. Stockage ignoré dans var/storage/videos/dev, jamais sous public ; sauvegarder les fichiers avec PostgreSQL. Lecture via route privée avec Range. Guide complet et pédagogie : docs/library.md.

Avec Node local : npm ci puis npm run build remplace le conteneur outils. Après chaque changement frontend, relancer npm run build et recharger la page. npm run dev n’est pas relié aux templates ; le mode courant utilise les fichiers compilés. Le dossier public/build est généré et ignoré. PHP local par défaut est 8.1 : utiliser le conteneur PHP 8.4 pour toutes les commandes applicatives.

## Vérifications

Publications permet aussi un aperçu repliable de la source privée. Le lot local de compteurs vues/j’aime/nombre de commentaires est préparé pour YouTube, Instagram et TikTok, avec API simulées et collecte réelle désactivée par défaut (réglages `YOUTUBE_STATISTICS_ENABLED`, `INSTAGRAM_STATISTICS_ENABLED`, `TIKTOK_STATISTICS_ENABLED` à `0`). Les relevés sont privés, datés et actualisés en arrière-plan à la demande. Lecture réelle YouTube autorisée et vérifiée sur ce poste via `.env.local` ; Instagram/TikTok restent désactivés. Accès supplémentaires et essais réels restent distincts ; limites Instagram et guide Symfony : [docs/statistics.md](docs/statistics.md).

Studio : vue d'ensemble avec compteurs réels et envois à traiter, historique commun YouTube/Instagram/TikTok filtrable et paginé, réseaux connectés et espace Mon compte (`/studio/mon-compte`) avec changement sécurisé du mot de passe. Les liens légaux généraux sont regroupés vers `/informations-legales`. Guide des composants, flux Symfony et tests : [docs/studio.md](docs/studio.md).

Publication multi-réseaux : sélectionner les vidéos, choisir une ou plusieurs destinations, adapter les textes puis vérifier un récapitulatif avant la confirmation unique. Une destination indisponible bloque la mise en file de tout le lot ; les envois ont ensuite des suivis indépendants. Préparation possible sans accès sociaux, sans faux compte ni publication simulée dans le studio. Guide, limites et pédagogie Symfony : [docs/publication-multireseaux.md](docs/publication-multireseaux.md).

Lot local du 18 octobre : **Brouillons** sauvegardés en base, même incomplets et sans compte social ; **Calendrier** mensuel/agenda mobile, date commune au lot, fuseau IANA, programmation ponctuelle confirmée et annulation avant départ. Le PC et le worker doivent fonctionner ; au-delà de deux minutes de retard, il faut reprogrammer. Aucun nouvel essai social réel ajouté. Parcours, composants Symfony et tests : [docs/calendrier.md](docs/calendrier.md).

```sh
docker compose exec php php scripts/check.php
npm run check
npm run test:ui
npm run build
docker compose exec php composer audit
npm audit
```

Sans Node local, utiliser docker compose --profile tools run --rm node pour le build et docker compose --profile tools run --rm node npm run check après installation des paquets dans le volume.
Le script PHP crée shortflow_test si nécessaire, applique les migrations, valide les schémas dev/test puis lance lints YAML/Twig/conteneur, PHPStan, PHP-CS-Fixer et PHPUnit. PHPUnit nettoie uniquement les tables nécessaires dans shortflow_test, après vérification de son nom ; ne jamais le pointer sur une base réelle. composer check seul suppose la base test prête. Les compteurs de test sont réinitialisés par cache:clear --env=test.

Pour une nouvelle modification de schéma : doctrine:migrations:diff dans le conteneur, relire le SQL, puis migrations:migrate. Ne pas utiliser schema:update --force pour gérer les évolutions.

## Fichiers à découvrir
- AGENTS.md : règles de contribution et pédagogie.
- compose.yaml et docker/php : environnement local.
- src/Controller/LandingController.php : route et orchestration HTTP.
- src/Form/WaitlistType.php : champs, contraintes et CSRF automatique.
- src/Service/WaitlistRegistration.php : inscription et doublons.
- src/Entity/WaitlistEntry.php et migrations : persistence PostgreSQL.
- templates/landing : HTML rendu par Twig.
- assets/controllers/studio_controller.js : scène progressive Three.js/GSAP via Stimulus.
- assets/styles/app.css : tokens, layout, responsive et états.
- assets/screen-composer.js : six écrans illustratifs et composition des montages.
- assets/styles/experience.css : compositions enrichies et version mobile dédiée.
- docs/landing-experience.md : fonctionnement, provenance des médias et guide pédagogique.
- docs : architecture, roadmap, design system, intégrations et décisions.
- docs/verification.md : résultats réellement exécutés et limites.

## Comprendre une requête Symfony
1. Le navigateur demande /. Le serveur transmet à public/index.php.
2. Le runtime initialise App\Kernel, qui charge les routes, services et configuration.
3. Le routeur associe GET / à LandingController::index, qui rend la landing bêta. Les CTA vont à l’inscription ou au studio selon la session.
4. Les formulaires d’inscription/import/renommage utilisent handleRequest pour lire les données, vérifier le CSRF et les contraintes. Un service métier coordonne Doctrine et, pour la vidéo, la sonde et le stockage privé. Le formulaire historique reste sur /liste-attente.
5. render appelle Twig avec les données. Twig produit du HTML échappé ; le navigateur reçoit une Response. Security contrôle l’accès au studio et le propriétaire est vérifié sur chaque vidéo.
6. Le navigateur charge CSS/JS compilés. Stimulus améliore les animations et la progression d’import ; les formulaires fonctionnent aussi sans JS. Les POST réussis répondent 303 vers une page GET. BinaryFileResponse gère la lecture privée et les requêtes Range.

Un controller coordonne une requête ; un service porte un comportement réutilisable ; une entité décrit les données persistées ; une migration versionne le schéma ; Twig représente la page. Cette séparation reste volontairement simple.

## Limites avant production
Le serveur PHP intégré et le debug sont locaux. Ne pas publier cette configuration. La confidentialité doit être finalisée (exploitant, contact, conservation) avant collecte publique. Aucun email de liste d’attente ni double opt-in de celle-ci ; les emails de compte restent dans Mailpit local. Le limiteur local est destiné à une instance et ne remplace pas une protection réseau de production. La landing inclut quatre montages de démonstration originaux, six visuels et six écrans narratifs : voir docs/landing-experience.md. Les licences des polices libres sont fournies dans node_modules/@fontsource ; aucun média de tiers utilisé.

Publication YouTube, Instagram Reels et TikTok implémentée avec traitement asynchrone et tests HTTP simulés : [YouTube](docs/youtube.md), [Instagram](docs/instagram-publication.md), [TikTok](docs/tiktok.md). Des essais réels ont réussi sur les comptes du propriétaire ; TikTok reste en Sandbox. Ces essais ne valident pas l'accès commercial pour tous les utilisateurs. Calendrier, IA BYOK et montage sont intégrés ; séries et abonnement restent à construire. La roadmap de référence est [docs/roadmap.md](docs/roadmap.md) ; l'ancien prompt V1 est archivé. Les générations sont payées directement au fournisseur, sans wallet VydFlow. Les accès et essais supplémentaires restent séparément autorisés.

Documents légaux locaux : /informations-legales. Coordonnées publiques centralisées dans config/packages/twig.yaml. Base de travail personnalisée micro-entreprise B2C/B2B, non finalisée avant lancement ; audit et points restants dans docs/legal-audit.md.


Studio IA BYOK : premier parcours local via Fal (Kling 2.5 Turbo Pro / Wan 2.5), accessible depuis Studio → Générer une vidéo et Connexions → IA et clés API. Préparation, estimation datée, confirmation, file asynchrone et copie privée sont testées avec HTTP simulé. `AI_TOKEN_KEY` reste privé dans `.env.local`, `AI_GENERATION_ENABLED=0` interdit les appels payants réels par défaut. Migration additive `Version20261019010000`. Guide et limites : [docs/studio-ia.md](docs/studio-ia.md). Extension locale : catalogue avec recherche/filtres, Kling 3 jusqu’à 15 secondes et Replicate BYOK (Wan / Veo 3.1 Fast). Migration complémentaire `Version20261019020000`, clés Fal existantes préservées ; chaque fournisseur possède sa clé. Premier essai Fal réel autorisé réussi : plan Kling 2.5 de 5 secondes, copie privée et lecture validées, estimation 0,35 USD (débit fournisseur non consulté). Activation globale toujours désactivée après cet essai. Autres modèles et montage de 30–60 secondes restent à faire ; aucun envoi social n’est déclenché par une génération. Voir aussi [le compte rendu](docs/ia-premier-essai.md).

Image vers vidéo : premier parcours Kling 2.5 Turbo Pro via Fal, image privée et aperçu local, 5/10 s, confirmation avant envoi. Migration additive `Version20261020010000`. [Guide et limites](docs/studio-image-video.md).

Extension image : 33 configurations compatibles, images de début/fin selon capacités, choix du format et cadrage prévisualisé. Migration `Version20261020020000`. [Matrice et guide](docs/studio-image-video-complet.md). Aucun essai payant réel ajouté.

Mini montage : Studio → Montage, imports et vidéos IA communs, séparation/coupes/ordre, sous-titres locaux gratuits en frais API, piste musicale privée et mixage exporté. [Guide et composants](docs/montage-sous-titres.md) ; [proposition séries/épisodes](docs/series-episodes-proposition.md). Après mise à jour, appliquer les migrations additives jusqu’à `Version20261021020000`.
