# R1 — Fiabilité du rédacteur : suivi du lot

## R1.1 terminé — reproduction, 27 octobre 2026

Objectif : prouver automatiquement qu'une demande encore en attente peut être déclarée expirée avant son premier traitement. Cette étape ajoute le test du comportement attendu ; **elle ne corrige pas le moteur**. Les six échecs décrits ci-dessous sont attendus et restent présents jusqu'à R1.2. Le lot R1 est en cours, pas livré ; ce point de contrôle n'est pas une version entièrement verte.

### Fichiers et responsabilités

| Fichier | Rôle |
| --- | --- |
| `tests/Unit/StoryProposalQueueTest.php` | Nouveau test du contrat : attendre dans une file ne doit pas être confondu avec une exécution bloquée |
| `src/Service/StoryProposals.php` | Service réel testé, inchangé ; `owned`, `latest` et `process` appellent actuellement `expire()` sur les demandes `queued` |
| `src/Entity/StoryProposal.php` | Entité réelle, inchangée ; porte statut, entrée, résultat et date de création |
| `docs/roadmap.md`, `README.md`, `AGENTS.md` | Avancement et repère de reprise R1.2 |

### Comment le test fonctionne

Le fournisseur de cas PHPUnit combine deux âges (0 et 11 minutes), deux types (`story`, `editorial`) et trois entrées publiques (`owned`, `latest`, `process`) : douze cas.

Chaque cas construit une demande `queued` en mémoire. La fixture est antidatée avec `DateTimeImmutable('-11 minutes')` et Reflection lorsque nécessaire : aucune attente de onze minutes, aucune modification de l'horloge du poste. Le service utilise encore l'heure système, donc ce test ne prétend pas figer son horloge ni tester la frontière exacte des dix minutes. L'horloge explicite et la distinction attente/exécution appartiennent à R1.2.

Doctrine, son repository, sa connexion et le bus Messenger sont des doubles PHPUnit ; aucune base n'est interrogée par ces tests unitaires. Le bus ne doit jamais envoyer de nouveau message. Le vrai `LocalStoryWriter` reçoit un `MockHttpClient` : ses réponses synthétiques valides permettent de vérifier qu'un travail récent peut réellement traverser le service de rédaction sans contacter Ollama. Aucun client vidéo ou social n'est utilisé.

Flux de reproduction : fixture en attente → appel public du service → `expire()` se base sur `createdAt` → passage prématuré à `failed` → aucune requête texte, même simulée. Un simple affichage via `owned/latest` peut donc faire échouer la demande ; le traitement via `process` la refuse aussi avant la rédaction.

Le contrat attendu est `queued` après lecture et `ready` après traitement simulé réussi, quel que soit l'âge de file de 0 ou 11 minutes. Les cas récents vérifient aussi résultat, identité de préparation et absence d'erreur. L'entrée du travail est normalement nettoyée après succès par l'entité : le test respecte ce fonctionnement, sans le confondre avec la conservation des textes humains dans leurs préparations.

### Résultats finaux exécutés

Base de test vérifiée avant PHPUnit : `SELECT current_database()` renvoie `shortflow_test`. Migration test exécutée, déjà à jour sur `Version20261026200000`. Aucun changement de schéma ajouté.

| Contrôle | Résultat |
| --- | --- |
| Nouveau test de file | **12 cas, 98 assertions, 6 réussites et 6 échecs attendus**, code de sortie 1 |
| Cas récents, 0 minute | Les six passent, pour les deux types et les trois entrées |
| Cas en attente de 11 minutes | Les six échouent : `failed` observé au lieu de `queued` en lecture ou `ready` après traitement simulé ; zéro requête texte dans chacun de ces cas |
| Tests existants `LocalStoryWriterTest` et `PublicationTextsTest` | **17 tests, 60 assertions, tous réussis**, code 0 |
| PHPStan sur le nouveau fichier | Aucune erreur |
| CS-Fixer en lecture seule sur le nouveau fichier | Aucun fichier à corriger |
| `git diff --check` | Réussi |

Pas de nouvelle suite PHP complète, de build frontend ou de recette navigateur dans cette étape : aucun code d'application, template, asset ou migration n'est modifié. La suite complète inclut désormais ce nouveau fichier et ne doit **pas** être annoncée verte tant que R1.2 n'est pas livré. Aucun test n'est masqué, exclu ou marqué comme réussi artificiellement.

Les journaux détaillés locaux sont ignorés dans `artifacts/r1-1-reproduction.log`, `r1-1-existing-tests.log`, `r1-1-style-final.log` et `r1-1-phpstan-final.log`. Ce document, le test et les commandes suffisent à reproduire sur un autre PC.

### Reproduire

Depuis un poste installé selon le README, après vérification/migration de `shortflow_test` :

```sh
docker compose exec -T -e APP_ENV=test -e APP_DEBUG=0 php php bin/phpunit tests/Unit/StoryProposalQueueTest.php --testdox
docker compose exec -T -e APP_ENV=test -e APP_DEBUG=0 php php bin/phpunit --filter 'LocalStoryWriterTest|PublicationTextsTest' tests/Unit
docker compose exec -T php php vendor/bin/phpstan analyse tests/Unit/StoryProposalQueueTest.php --no-progress --memory-limit=256M
docker compose exec -T php php vendor/bin/php-cs-fixer fix --dry-run --diff tests/Unit/StoryProposalQueueTest.php
```

La première commande doit actuellement échouer sur les six cas âgés : c'est la preuve du défaut R1.1, pas une erreur d'installation. Les autres commandes passent. Aucun modèle local ni crédit Fal requis.

### À comprendre en Symfony et PHPUnit

Un test unitaire peut utiliser le vrai service et la vraie entité, tout en remplaçant leurs dépendances Doctrine/Messenger/HTTP : il prouve la décision métier sans démarrer le kernel ni un worker. Le fournisseur de données applique le même contrat aux différents chemins d'entrée. Il ne reproduit pas une charge réelle de plusieurs utilisateurs ni une transaction PostgreSQL ; ces contrôles complémentaires relèvent de R1.4.

La phase rouge du développement par les tests écrit d'abord le comportement voulu. Ici, ajouter un test qui attendrait simplement `failed` figerait le bug au lieu de préparer sa correction. R1.2 doit rendre les cas verts en corrigeant la durée de vie des travaux, sans retirer ou ignorer ces assertions.

### Prochain point exact : R1.2

Séparer l'âge de la file et celui de l'exécution/phase, avec une horloge testable, des délais et une admission explicites. Relire aussi le cas historique de `tests/Functional/AiStoryProposalTest.php` : il attend actuellement `failed` pour une demande `queued` antidatée de onze minutes. Le mettre en cohérence avec le nouveau contrat tout en conservant une vraie couverture des exécutions expirées.

Ne pas corriger en allongeant seulement dix minutes, ne pas supprimer les délais des travaux réellement bloqués, ne pas lancer de génération vidéo pour reprendre un texte, et préserver le refus des anciens résultats après nouvelle demande. Aucun essai manuel payant ou social réel n'est nécessaire pour R1.1 ou sa correction temporelle.
