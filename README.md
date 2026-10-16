# VydFlow — bêta, comptes et bibliothèque privée

Micro-SaaS Symfony : landing immersive avec inscription, comptes confirmés, dashboard et bibliothèque vidéo privée. Import MP4/WebM, lecture, renommage et suppression. Le lot YouTube ajoute sélection multiple, connexion OAuth, préparation et suivi des envois Messenger, avec worker Docker automatique. Résultats des essais réels : docs/verification.md. Calendrier, IA, Instagram, TikTok et abonnement viennent ensuite. Aucun service cloud ni compte payant nécessaire pour explorer localement ; les essais YouTube réels demandent une application Google. La liste d’attente historique est conservée sur /liste-attente, hors parcours principal.

## Vision de lancement

Vision validée le 15 octobre 2026 : « Importe ou génère tes vidéos courtes, prépare-les et programme leur publication sur tes réseaux depuis un seul studio. » Deux parcours : vidéos importées avec montage facultatif et textes adaptés, puis publication immédiate ou calendrier ; séries IA BYOK préparées en avance avec montage, textes et programmation, validation activée par défaut ou automatisation explicitement choisie selon les possibilités des plateformes. Un fournisseur/un modèle, quelques recettes de montage, pause et limites de générations/dépense ; aucun éditeur vidéo complet. Calendrier, IA, montage et séries restent à construire. Cible et étapes dans docs/roadmap.md, sans autorisation implicite de commencer les lots suivants.

## Démarrage local
Prérequis : Docker Desktop démarré (conteneurs Linux), Docker Compose et Git. Node local est optionnel : un conteneur outils est prévu.

Depuis le dossier du projet :

```sh
docker compose build
docker compose run --rm --no-deps php composer install
docker compose --profile tools run --rm node
docker compose up -d
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

Créer .env.local avant le premier démarrage sur un nouveau clone : APP_SECRET doit être une chaîne aléatoire privée. Exemple de génération : docker compose run --rm --no-deps php php -r "echo bin2hex(random_bytes(32));". Le fichier est ignoré par Git ; une valeur locale a déjà été générée dans cet espace de travail.

Les dépendances PHP (`php_vendor` → `/app/vendor`) et le cache Symfony (`app_cache` → `/app/var/cache`) sont conservés dans des volumes Docker natifs partagés par PHP et le worker. Cela évite les milliers d'accès aux fichiers Windows/OneDrive pendant chaque requête. Le code source reste modifiable depuis Windows et le mode debug reste actif. Après un changement de `composer.lock`, lancer `docker compose run --rm --no-deps php composer install`, puis `docker compose restart worker`. Utiliser Composer et les outils PHP dans Docker : un éventuel dossier `vendor` Windows n'est plus celui exécuté par les conteneurs. Pour rafraîchir le cache : `docker compose exec php php bin/console cache:clear`.

La base PostgreSQL et les vidéos privées ne sont pas dans ces deux volumes : leur emplacement reste inchangé. Les commandes de démarrage ci-dessus initialisent aussi les volumes sur un nouveau clone ; aucune copie manuelle du cache n'est nécessaire.

Mailpit VydFlow utilise désormais http://localhost:8026 pour cohabiter avec un autre projet qui occupe déjà le port 8025 ; SMTP interne reste mailpit:1025. Le serveur VydFlow reste sur localhost:8000. Les ports restent exclusivement liés à loopback.

Le service `worker` démarre avec `docker compose up -d` et traite automatiquement les publications confirmées ; aucun terminal de consommation séparé n’est nécessaire. Il attend les dépendances Composer et la table Messenger avant de consommer, puis Docker le relance après une sortie. Après un changement de code PHP ou de secrets dans .env.local, exécuter `docker compose restart worker` pour que ce processus permanent les recharge. État : `docker compose ps` ; diagnostic : `docker compose logs --tail 30 worker`. Le worker utilise exclusivement la base dev ; les tests gardent leur base séparée.

Ouvrir http://localhost:8000. Les interfaces HTTP de l’application et de Mailpit sont exposées uniquement sur loopback. PostgreSQL et SMTP restent sur le réseau Compose. Identifiants Compose strictement locaux. Arrêter sans supprimer les données : docker compose stop. Ne pas exécuter down -v si les données sont à conserver.

Le studio est sur http://localhost:8000/studio ; l’inscription sur http://localhost:8000/inscription. Il faut confirmer son email avant de pouvoir se connecter. La boîte email de développement Mailpit est sur http://localhost:8026, uniquement en loopback. SMTP n’est pas publié ; les emails ne sortent pas du réseau Docker. APP_PUBLIC_URL définit l’origine de confiance des liens (défaut local http://localhost:8000). Ne pas déployer Mailpit publiquement. Voir docs/accounts.md pour le parcours de test et l’explication Symfony.

Bibliothèque : http://localhost:8000/studio/bibliotheque. Après mise à jour du Dockerfile, relancer `docker compose build php` puis `docker compose up -d` pour installer ffprobe et les limites d’upload. Limites bêta : MP4 H.264/AAC ou WebM compatible 8 bits, 100 Mo/fichier, 180 secondes, 1 Go et 100 vidéos/compte. Stockage ignoré dans var/storage/videos/dev, jamais sous public ; sauvegarder les fichiers avec PostgreSQL. Lecture via route privée avec Range. Guide complet et pédagogie : docs/library.md.

Avec Node local : npm ci puis npm run build remplace le conteneur outils. Après chaque changement frontend, relancer npm run build et recharger la page. npm run dev n’est pas relié aux templates ; le mode courant utilise les fichiers compilés. Le dossier public/build est généré et ignoré. PHP local par défaut est 8.1 : utiliser le conteneur PHP 8.4 pour toutes les commandes applicatives.

## Vérifications

Studio : vue d'ensemble avec compteurs réels et envois à traiter, historique commun YouTube/Instagram filtrable et paginé, réseaux connectés et espace Mon compte (`/studio/mon-compte`) avec changement sécurisé du mot de passe. Les liens légaux généraux sont regroupés vers `/informations-legales`. Guide des composants, flux Symfony et tests : [docs/studio.md](docs/studio.md).

```sh
docker compose exec php php scripts/check.php
npm run check
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

Publication YouTube disponible avec worker Docker automatique : docs/youtube.md. Lot courant autorisé : connexion Instagram directe, reconnexion et déconnexion, avec configuration privée et HTTP simulé en tests ; guide pédagogique dans docs/instagram.md. Le premier essai Instagram réel attend l’accès développeur Meta et ses identifiants. Le parcours Reels (préparation, worker, suivi et badges) est implémenté avec API simulée : docs/instagram-publication.md. Son essai réel attend Meta et une adresse HTTPS adaptée. TikTok et IA restent à construire. La roadmap de référence est docs/roadmap.md ; l’ancien prompt V1 est archivé. Les générations seront payées directement au fournisseur, aucun wallet VydFlow aujourd’hui.

Documents légaux locaux : /informations-legales. Coordonnées publiques centralisées dans config/packages/twig.yaml. Base de travail personnalisée micro-entreprise B2C/B2B, non finalisée avant lancement ; audit et points restants dans docs/legal-audit.md.
