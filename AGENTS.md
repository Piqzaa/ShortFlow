# VydFlow — instructions de travail

VydFlow est un futur micro-SaaS français pour les créateurs de vidéos courtes. Positionnement : « Crée, prépare et publie tes vidéos courtes depuis un seul studio. » Internationalisation anglaise ultérieure.

## Périmètre actuel
Passage à la V1 autorisé le 14 octobre 2026. Lot courant : cadrage, comptes utilisateurs, vérification email, récupération de mot de passe et espace privé. Préserver la landing et la liste d’attente. Arrêt après ce lot pour bilan avant la bibliothèque.
La V1 cible ensuite bibliothèque/import privé, génération IA BYOK (un fournisseur/un modèle), préparation/calendrier, intégrations sociales officielles et abonnement logiciel. Créer ces fonctions uniquement dans leur lot, sans classes futures spéculatives. Aucun wallet ni revente de générations IA : l’utilisateur finance directement son fournisseur. Une offre hybride reste une possibilité future non autorisée pour l’implémentation actuelle.

## Stack et architecture
Monolithe Symfony 7.4 LTS ; PHP 8.4 Docker ; Doctrine ORM et PostgreSQL 16 ; Twig ; Stimulus ; Vite ; Three.js ; GSAP/ScrollTrigger. Aucun React, microservice, CQRS ou DDD complexe.
Controllers légers dans src/Controller ; formulaire/validation dans src/Form ; métier dans src/Service ; entités dans src/Entity ; migrations versionnées dans migrations ; templates dans templates ; contrôleurs Stimulus dans assets/controllers ; tokens/styles dans assets/styles ; documentation dans docs.
Messenger installé sans transport ni worker avant une véritable tâche asynchrone. Les repositories dédiés apparaîtront lorsque les requêtes métier le nécessitent.

## Conventions
PHP : types explicites, conventions Symfony, injection de dépendances, attributs de routes et mapping, formatage PHP-CS-Fixer. JavaScript : ES Modules. Twig : auto-échappement ; jamais raw sur une donnée utilisateur. Rester simple, éviter les dépendances et abstractions superflues. Mettre à jour documentation et tests concernés avec chaque changement.

## Sécurité
Secrets exclusivement dans .env.local ignoré ou variables d’environnement. Les identifiants Compose sont exclusivement locaux. Serveur sur loopback, base sans port public. Formulaires avec CSRF, validation serveur, réponses neutres pour doublons et limiteur IP/honeypot. Aucun email dans les logs applicatifs. Ne pas exposer debug publiquement.
À l’ajout d’authentification : Symfony Security, hashage password auto, autorisations et propriété des ressources. Futurs uploads privés : format réel, limite de taille, noms sûrs, prévention de traversée, autorisation propriétaire, arguments FFmpeg sécurisés, nettoyage temporaire. Futurs tokens OAuth chiffrés et absents des logs/front. Publications traçables et idempotentes, adaptateur par réseau.
Confirmation d’email obligatoire avant connexion ; contrôler l’état avec le UserChecker Symfony après validation du mot de passe et protéger le studio par ROLE_VERIFIED. Les tests reçoivent les emails dans Mailpit local. Une récupération de mot de passe ne confirme pas l’adresse.

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
