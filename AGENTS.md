# VydFlow — instructions de travail

VydFlow est un futur micro-SaaS français pour les créateurs de vidéos courtes. Positionnement : « Crée, prépare et publie tes vidéos courtes depuis un seul studio. » Internationalisation anglaise ultérieure.

## Périmètre actuel
Passage à la V1 autorisé le 14 octobre 2026. Comptes terminés. Lot bibliothèque/import privé autorisé le 15 octobre 2026 : lecture, renommage, suppression, isolation propriétaire et parcours landing en mode bêta vers inscription/studio. Préserver les animations de la landing et les anciennes données de liste d’attente ; cette dernière sort du parcours principal. Comptes et bibliothèque terminés. Décision du 15 octobre : nettoyage des mentions inutiles, puis priorité à la publication immédiate d’une ou plusieurs vidéos sur TikTok, YouTube et/ou Instagram au choix, avant le studio IA. Cadrage dans docs/publication.md ; Lot YouTube autorisé après validation du cadrage : connexion OAuth, sélection multiple, préparation, publication asynchrone et suivi. Pas de publication réelle, service cloud ou compte social connecté sans les accès et essais explicitement autorisés. Arrêt après YouTube avant Instagram/TikTok/IA.
Vision de lancement élargie et validée le 15 octobre : vidéo importée ou générée en BYOK → montage automatique facultatif et guidé → textes adaptés/modifiables par réseau → publication immédiate ou calendrier. Inclure au lancement des séries récurrentes (thème/prompt, un fournisseur/un modèle BYOK, recette de montage, destinations/fréquence), préparées en avance, validation avant publication activée par défaut, mode automatique explicitement choisi selon les capacités autorisées des plateformes, pause et limites de générations/dépense. Calendrier commun et suivi par destination. Roadmap de référence : docs/roadmap.md. Abonnement logiciel uniquement, aucun éditeur vidéo complet au lancement. Cette vision ne lève pas l’arrêt après YouTube : créer chaque fonction uniquement dans son lot, sans classes futures spéculatives. Aucun wallet ni revente de générations IA : l’utilisateur finance directement son fournisseur. Une offre hybride reste une possibilité future non autorisée pour l’implémentation actuelle.

Mise à jour du 16 octobre 2026 : premier transfert YouTube réel autorisé et réussi, worker Docker automatique. Repère des publications réussies autorisé dans la bibliothèque. Le propriétaire choisit pour la suite de passer son compte Instagram personnel en Créateur puis d’intégrer Reels avant TikTok ; cela autorise le prochain lot Instagram au-delà de l’arrêt YouTube précédent. La conversion réelle du compte, les accès Meta et les essais restent à confirmer séparément ; aucune activation implicite de TikTok, calendrier ou IA.

Mise à jour du 17 octobre 2026 : le propriétaire autorise la préparation locale complète Instagram/TikTok sans créer ses comptes développeur ce soir. Lot TikTok : OAuth, préparation, publication asynchrone et suivi avec API simulée. Accès réels, HTTPS public et premiers essais sociaux restent à autoriser séparément. Calendrier, IA et statistiques restent hors du lot. Voir docs/tiktok.md.

Mise à jour du 17 octobre 2026 : lot statistiques de base autorisé localement pour les trois réseaux avec API simulées : vues, j’aime, nombre de commentaires et aperçu de la vidéo source dans Publications. Les compteurs sont distincts par destination, datés et collectés en arrière-plan ; aucune donnée manquante remplacée par zéro. La collecte réelle et les autorisations sociales supplémentaires restent à activer séparément. Aucun texte de commentaire, réponse, modération, historique analytique, calendrier ou IA ajouté dans ce lot. Voir docs/statistics.md.

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

Mise à jour du 17 octobre 2026 : lecture réelle des statistiques YouTube explicitement autorisée et premier relevé réussi, sans nouvel envoi ; Instagram/TikTok restent désactivés pour les statistiques réelles. Suppression multiple de la bibliothèque autorisée, confirmation explicite dans l’interface et blocage de tout le lot si un envoi est actif. Plusieurs commits locaux demandés ; aucun push demandé.
