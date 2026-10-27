# R1 — Fiabilité du rédacteur : suivi du lot

## État courant — R1.2 terminé, 27 octobre 2026

**Le défaut reproduit en R1.1 est corrigé.** Les paragraphes R1.1 plus bas conservent la phase rouge historique ; ils ne décrivent plus l'état courant. Suite complète : 651 tests / 7 766 assertions réussis. Prochain point : **R1.3**, lisibilité des états et reprise dans l'interface. Le lot R1 reste en cours ; la vérification multi-processus et le bilan global R1.4/R1.5 ne sont pas cochés.

### Objectif et règles temporelles

L'âge de création ne sert plus à expirer une exécution qui vient de démarrer. `ClockInterface`, déjà configurée dans Symfony, est injectée dans le service ; les tests utilisent `MockClock` sans attente réelle. Les demandes créées par le service enregistrent cette même heure de référence.

| Limite technique initiale | Règle |
| --- | --- |
| Attente de démarrage | 3 600 s, soit une heure depuis la création ; message distinct si le travail n'a jamais pu démarrer |
| Phase d'exécution | 210 s depuis le début de la phase ; marge par rapport aux 180 s configurées pour un appel texte |
| Exécution entière | 420 s depuis le premier démarrage ; un changement de phase ne renouvelle pas ce délai |
| Admission | Une proposition en attente/exécution par propriétaire, dix propositions encore valides au total dans `StoryProposals` |

Ces valeurs sont des garde-fous de départ, **pas des quotas d'abonnement ni une garantie de débit/délai**. Elles restent à confronter à la capacité du poste et de la future cible R8. L'admission couvre les propositions manuelles de scénarios et de textes sociaux. Les préparations récurrentes utilisent également le worker de rédaction mais appellent directement le rédacteur : elles ne sont pas incluses dans ce compteur. Cette limite n'est donc pas une admission universelle de toute la file Messenger.

### Fichiers et responsabilités de R1.2

| Fichier | Rôle |
| --- | --- |
| `src/Entity/StoryProposal.php` | Dates de démarrage et de début de phase, nullables pour les anciennes lignes ; répéter une phase ne repousse pas son délai |
| `src/Service/StoryProposals.php` | Horloge, délais séparés, admission bornée, prise en charge et finalisation sous contrôle d'état |
| `migrations/Version20261027160000.php` | Deux colonnes nullables et un index d'état/date ; aucune suppression ni réécriture des préparations |
| `tests/Unit/StoryProposalQueueTest.php` | Les douze cas R1.1 conservés, maintenant à horloge figée, plus limites exactes, longue attente puis démarrage récent et anciennes lignes sans dates d'exécution |
| `tests/Functional/StoryProposalLifecycleTest.php` | Admission réelle PostgreSQL, reprise d'un message, phases, fin tardive et remplacement de demande avec clients texte simulés |
| `tests/Functional/AiStoryProposalTest.php` | Ancien cas d'expiration transformé en véritable exécution expirée, au lieu de figer le bug d'une attente de onze minutes |

Flux : création sous verrou du propriétaire → réutilisation d'une demande identique ou contrôle de la place disponible → message Messenger → prise en charge sous verrou court et enregistrement du démarrage → rédaction **hors transaction** → vérification sous verrou avant chaque phase/résultat → sauvegarde seulement si le travail est encore actif, dans ses délais et toujours le plus récent pour ce propriétaire/périmètre/type.

L'admission est sérialisée par un verrou transactionnel PostgreSQL consultatif. Les chemins existants verrouillent d'abord leur propriétaire et leurs propres demandes, puis prennent ce verrou d'admission ; la lecture globale ne verrouille ni n'hydrate les lignes des autres propriétaires. Aucun de ces verrous ne couvre l'inférence. Une demande identique retrouve son identifiant même si la file est devenue pleine ; un nouveau travail en surnombre reçoit une erreur explicite sans nouveau message.

Les propositions périmées sont exclues du comptage d'admission même avant une nouvelle consultation ; elles ne saturent pas définitivement la file. La purge d'un jour est limitée aux travaux terminés/en échec du propriétaire, jamais à une exécution encore active. Les anciennes exécutions sans nouvelles dates utilisent leur création comme repère conservateur : une lecture ne leur accorde pas un nouveau délai.

Un travail expiré/remplacé ne peut plus enregistrer une réussite ou écraser sa notice avec une erreur tardive. Si la première étape arrive trop tard, le contrôle de phase empêche le deuxième appel texte. Cela n'annule pas rétroactivement un calcul déjà transmis au moteur ; aucun nouveau calcul vidéo ni renvoi automatique payant n'est ajouté.

### Vérifications réellement exécutées en R1.2

- Nouveau jeu unitaire : **21 tests / 171 assertions**, tous réussis ; les douze cas rouges R1.1 sont verts.
- Première recette ciblée scénarios/éditorial/cycle : **44 tests / 455 assertions**, tous réussis.
- Suite complète dans une copie temporaire Linux, image `edit-worker`, après garde `shortflow_test`, migrations et reconstruction du cache : **651 tests / 7 766 assertions**, tous réussis en 4 min 02 s.
- Rejeu final après clarification du compteur HTTP du test fonctionnel (compteur natif du client pour la redélivrance) : **34 tests / 296 assertions**, tous réussis. Aucun correctif applicatif n'a changé après la suite complète.
- PHPStan niveau 5 sur l'application : aucune erreur. Analyse ciblée des nouveaux tests et migration vérifiée ; le fichier fonctionnel final est également sans erreur. CS-Fixer : zéro correction sur les six fichiers concernés.
- Conteneur Symfony valide, 19 fichiers YAML et 102 templates Twig valides, schéma Doctrine test synchronisé lors de la suite complète.
- Migration additive appliquée sur **test puis dev**, schéma dev synchronisé. Absence d'exécution réelle vérifiée avant actualisation (`running=0`) ; cache dev reconstruit et seul `story-worker` recréé. Page d'accueil locale : HTTP 200.
- Revue indépendante du diff : aucun blocage restant. La capture initiale de variables non initialisées a été corrigée avant les recettes ; aucun avertissement n'est laissé comme comportement attendu.

Pas d'Ollama, de génération vidéo payante ou d'envoi social dans les nouveaux tests : les réponses HTTP sont synthétiques et les autres clients refusent tout appel. La suite complète conserve son test de transcription locale hors ligne. Aucun nouveau test visuel ou build frontend pour ce changement backend sans modification des assets/templates ; R1.3 portera le suivi visible.

Les cas PostgreSQL sont des simulations séquentielles des courses avec des réponses retardées : la vraie concurrence multi-processus, la capacité sous charge, l'arrêt brutal d'un consommateur et le scénario de reprise complet restent à vérifier en R1.4/R8. La revue n'est pas une preuve de débit.

Contrôle supplémentaire hors du périmètre PHPStan configuré (`src`) : inclure aussi l'ancien `AiStoryProposalTest.php` signale son ternaire préexistant sur `$suspicious` capturé par référence, ligne 67 (`ternary.alwaysFalse`). Cette ligne n'est pas modifiée par R1.2 et le scénario fonctionnel correspondant passe ; l'alerte est conservée dans `artifacts/r1-2-phpstan-final.log`, sans suppression de règle. La commande finale sur le service, l'entité, les nouveaux tests et la migration passe (`r1-2-phpstan-final-scoped.log`). Ne pas présenter cette extension à l'ancien test comme une analyse sans erreur.

### Reprise et pédagogie Symfony

Sur un autre PC, récupérer le code puis appliquer les migrations additives selon le README, reconstruire le cache et recréer le worker de rédaction hors traitement actif. Ne pas supprimer la base ou les files pour résoudre une différence de schéma. Les colonnes nullables rendent l'évolution compatible avec les propositions déjà enregistrées.

À comprendre : Doctrine conserve les dates de cycle dans PostgreSQL ; l'horloge injectée permet de faire avancer ces dates dans les tests sans attendre. Un verrou de ligne sert à décider et écrire un état brièvement ; il doit être relâché pendant l'appel lent. La finalisation recontrôle la base car l'objet PHP peut être devenu obsolète entre-temps. Les tests vérifient aussi cette absence de transaction pendant les réponses HTTP simulées.

Reproduire les tests avec les commandes R1.1 ci-dessous : la première est désormais verte. Pour le cycle fonctionnel, sur `shortflow_test` migrée exclusivement :

```sh
docker compose exec -T -e APP_ENV=test -e APP_DEBUG=0 php php bin/phpunit --filter 'StoryProposalQueueTest|StoryProposalLifecycleTest'
```

Les journaux locaux ignorés `artifacts/r1-2-full.log` et `r1-2-final-focused.log` conservent les sorties. Ce bilan et les tests versionnés permettent la reprise sans ces fichiers.

**Prochain point R1.3 :** rendre les états d'attente, rédaction, préparation des plans/textes et erreur clairs, puis vérifier quitter/retrouver la page. Aucun essai payant n'est nécessaire pour cette suite.

---

## Historique de la phase rouge

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
