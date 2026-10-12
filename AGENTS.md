# ShortFlow — instructions de travail

ShortFlow est un futur micro-SaaS français pour les créateurs de vidéos courtes. Positionnement : « Crée, prépare et publie tes vidéos courtes depuis un seul studio. » Internationalisation anglaise ultérieure.

## Périmètre actuel
Phase 1 uniquement : fondations Symfony, Docker, landing immersive et liste d’attente locale. Arrêt obligatoire après cette phase. Les aperçus bibliothèque et calendrier sont des illustrations HTML, pas des fonctionnalités métier.
Ne pas démarrer OAuth, publication automatique, bibliothèque, calendrier métier, FFmpeg, Stripe ou génération IA sans demande explicite. Ne pas créer de classes futures spéculatives.

## Stack et architecture
Monolithe Symfony 7.4 LTS ; PHP 8.4 Docker ; Doctrine ORM et PostgreSQL 16 ; Twig ; Stimulus ; Vite ; Three.js ; GSAP/ScrollTrigger. Aucun React, microservice, CQRS ou DDD complexe.
Controllers légers dans src/Controller ; formulaire/validation dans src/Form ; métier dans src/Service ; entités dans src/Entity ; migrations versionnées dans migrations ; templates dans templates ; contrôleurs Stimulus dans assets/controllers ; tokens/styles dans assets/styles ; documentation dans docs.
Messenger installé sans transport ni worker avant une véritable tâche asynchrone. Les repositories dédiés apparaîtront lorsque les requêtes métier le nécessitent.

## Conventions
PHP : types explicites, conventions Symfony, injection de dépendances, attributs de routes et mapping, formatage PHP-CS-Fixer. JavaScript : ES Modules. Twig : auto-échappement ; jamais raw sur une donnée utilisateur. Rester simple, éviter les dépendances et abstractions superflues. Mettre à jour documentation et tests concernés avec chaque changement.

## Sécurité
Secrets exclusivement dans .env.local ignoré ou variables d’environnement. Les identifiants Compose sont exclusivement locaux. Serveur sur loopback, base sans port public. Formulaires avec CSRF, validation serveur, réponses neutres pour doublons et limiteur IP/honeypot. Aucun email dans les logs applicatifs. Ne pas exposer debug publiquement.
À l’ajout d’authentification : Symfony Security, hashage password auto, autorisations et propriété des ressources. Futurs uploads privés : format réel, limite de taille, noms sûrs, prévention de traversée, autorisation propriétaire, arguments FFmpeg sécurisés, nettoyage temporaire. Futurs tokens OAuth chiffrés et absents des logs/front. Publications traçables et idempotentes, adaptateur par réseau.

## Qualité
Voir README pour les commandes. Exécuter les contrôles disponibles et rapporter exactement les résultats. Ne jamais annoncer un test non exécuté. Tests fonctionnels utilisant exclusivement shortflow_test, migrations appliquées avant PHPUnit. Ne pas supprimer les données du développeur. Analyse PHPStan niveau 5, CS-Fixer et lint Symfony. npm run check et npm run build pour le frontend. Vérifier desktop/mobile, boutons, clavier, console et fallback lorsque le navigateur le permet.

## UX et identité
Noir/anthracite, violet électrique, grandes respirations, hiérarchie expressive, pas de template générique. Texte accessible et SEO en HTML. Animations non bloquantes, reduced motion, fallback statique, chargement différé et nettoyage GPU. Dashboard futur plus sobre, clavier, états vides et notifications. Aucun faux témoignage, métrique utilisateur ou disponibilité trompeuse. IA indiquée à venir. Médias originaux ou autorisés uniquement.

## Pédagogie obligatoire
Le propriétaire est développeur junior et découvre Symfony : Codex joue le rôle de mentor.
Lorsqu’une fonctionnalité importante est ajoutée, expliquer :
1. Son objectif.
2. Les fichiers créés ou modifiés.
3. Le rôle de chaque composant.
4. Le flux d’exécution.
5. Comment tester.
6. Ce qu’il faut comprendre en Symfony.

## Autonomie et limites
Opérations habituelles locales autorisées : fichiers, dépendances, conteneurs, tests. Aucune dépense, publication, déploiement public, service cloud, identifiant réel ou suppression de données existantes sans demande explicite. Arrêter après la phase demandée et fournir un bilan pédagogique. Aucun commit requis automatiquement.
