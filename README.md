# ShortFlow — Phase 1

Fondations d’un micro-SaaS Symfony, landing immersive et liste d’attente locale. Aucune bibliothèque métier, authentification, publication sociale, facturation ou génération IA. Aucun service cloud ni compte payant nécessaire.

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

Ouvrir http://localhost:8000. Seul le port HTTP est exposé et uniquement sur loopback. PostgreSQL est accessible depuis le réseau Compose, pas depuis Internet. Identifiants Compose strictement locaux. Arrêter sans supprimer les données : docker compose stop. Ne pas exécuter down -v si les données sont à conserver.

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
Le script PHP crée shortflow_test si nécessaire, applique les migrations, valide les schémas dev/test puis lance lints YAML/Twig/conteneur, PHPStan, PHP-CS-Fixer et PHPUnit. PHPUnit vide uniquement la table de la base de test ; ne jamais le pointer sur une base réelle. composer check seul suppose la base test prête. Les compteurs de test sont réinitialisés par cache:clear --env=test.

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
Le serveur PHP intégré et le debug sont locaux. Ne pas publier cette configuration. La confidentialité doit être finalisée (exploitant, contact, conservation) avant collecte publique. Aucun email automatique ni double opt-in. Le limiteur local est destiné à une instance et ne remplace pas une protection réseau de production. Le fichier de vidéo personnelle est facultatif : voir docs/design-system.md. Les licences des polices libres sont fournies dans node_modules/@fontsource ; aucun média de tiers utilisé.

La phase 1 s’arrête ici. Prochaine étape recommandée après validation : authentification et parcours du studio, avant les uploads privés.
