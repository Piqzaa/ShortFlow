# VydFlow — V1, comptes et espace privé

Micro-SaaS Symfony : landing immersive, liste d’attente locale et premier lot V1 avec comptes utilisateurs, vérification email, récupération de mot de passe et dashboard privé. Bibliothèque, calendrier, génération IA BYOK, publication et abonnement seront livrés par lots suivants. Aucun service cloud ni compte payant nécessaire aujourd’hui.

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

Ouvrir http://localhost:8000. Les interfaces HTTP de l’application et de Mailpit sont exposées uniquement sur loopback. PostgreSQL et SMTP restent sur le réseau Compose. Identifiants Compose strictement locaux. Arrêter sans supprimer les données : docker compose stop. Ne pas exécuter down -v si les données sont à conserver.

Le studio est sur http://localhost:8000/studio ; l’inscription sur http://localhost:8000/inscription. Il faut confirmer son email avant de pouvoir se connecter. La boîte email de développement Mailpit est sur http://localhost:8025, uniquement en loopback. SMTP n’est pas publié ; les emails ne sortent pas du réseau Docker. APP_PUBLIC_URL définit l’origine de confiance des liens (défaut local http://localhost:8000). Ne pas déployer Mailpit publiquement. Voir docs/accounts.md pour le parcours de test et l’explication Symfony.

Avec Node local : npm ci puis npm run build remplace le conteneur outils. Après chaque changement frontend, relancer npm run build et recharger la page. npm run dev n’est pas relié aux templates ; le mode courant utilise les fichiers compilés. Le dossier public/build est généré et ignoré. PHP local par défaut est 8.1 : utiliser le conteneur PHP 8.4 pour toutes les commandes applicatives.

## Vérifications

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
3. Le routeur associe / à LandingController::index. Le conteneur injecte les services nécessaires : pas de construction manuelle de connexion SQL dans le controller.
4. Le controller construit le formulaire. Sur POST, handleRequest lit les données, vérifie le token CSRF et les contraintes. Le service métier enregistre l’email via Doctrine si les protections l’autorisent.
5. render appelle Twig avec le formulaire et les données. Twig produit du HTML échappé ; le navigateur reçoit une Response avec son code HTTP.
6. Le navigateur charge CSS/JS compilés. Stimulus améliore le smartphone ; le contenu reste lisible sans JavaScript. Après inscription réussie, Symfony répond 303 vers une page GET avec confirmation en session.

Un controller coordonne une requête ; un service porte un comportement réutilisable ; une entité décrit les données persistées ; une migration versionne le schéma ; Twig représente la page. Cette séparation reste volontairement simple.

## Limites avant production
Le serveur PHP intégré et le debug sont locaux. Ne pas publier cette configuration. La confidentialité doit être finalisée (exploitant, contact, conservation) avant collecte publique. Aucun email de liste d’attente ni double opt-in de celle-ci ; les emails de compte restent dans Mailpit local. Le limiteur local est destiné à une instance et ne remplace pas une protection réseau de production. La landing inclut quatre montages de démonstration originaux, six visuels et six écrans narratifs : voir docs/landing-experience.md. Les licences des polices libres sont fournies dans node_modules/@fontsource ; aucun média de tiers utilisé.

Le lot comptes s’arrête ici pour bilan. Prochain lot : bibliothèque et imports privés sécurisés. La roadmap de référence est docs/roadmap.md ; l’ancien prompt V1 est archivé. Les générations seront payées directement au fournisseur via BYOK, aucun wallet VydFlow aujourd’hui.
