# R4.1 — mesures de référence avant optimisation

30 octobre 2026. **R4.1 terminé ; grand R4 en cours. Prochaine tâche : R4.2**, borner les épisodes, occurrences et formulaires d'une série. Aucun correctif de performance applicatif n'est inclus dans R4.1.

## Ce que la mesure montre

Le détail d'une série conserve **13 exécutions SQL** entre 3 et 120 épisodes dans ce jeu mixte. En revanche, son HTML passe d'environ **42 ko à 151 ko** et son traitement Symfony d'environ **10 ms à 32–36 ms** dans ce banc local préchauffé. Le problème observé porte donc sur le volume chargé et rendu, sans augmentation du nombre de requêtes dans ce scénario. Ce constat ne mesure pas la capacité d'un hébergement ni un délai garanti à un utilisateur.

Les gains des projections de couvertures et publications restent visibles : pas de requête supplémentaire par épisode dans cette série mixte. La suite doit borner les lectures et le rendu, en gardant l'accès aux épisodes et à l'historique. On ne déduit pas de ces mesures qu'un découpage supplémentaire des assets serait nécessaire.

## Programme et protocole reproductible

- Programme versionné : `scripts/benchmark-studio.php`. Sans `--run`, il affiche uniquement le protocole ; aucun Kernel ni accès DB. Avec `--run --commit=<SHA complet>`, environnement test forcé et refus si la base effective n'est pas exactement `shortflow_test`.
- Sources applicatives mesurées : `6002695372b33cc5dea091bcc65cdadacbf0cda6` (clôture R3). Le programme et dix fichiers de sources/dépendances portent aussi leur SHA-256 dans les résultats ; les empreintes des copies Linux ont été comparées à celles du workspace, identiques lors des deux passages finaux.
- [Résultats bruts des deux passages](fixtures/r4-baseline-20261030.json), avec chaque échantillon, les cinq SQL les plus répétés, les métriques de rédaction et le nettoyage. Aucun paramètre SQL, secret ou donnée personnelle conservé.
- Deux propriétaires synthétiques distincts. Petit volume : 2 séries, dont une série de 3 épisodes et 3 occurrences. Grand volume : 25 séries, dont une série de 120 épisodes et 120 occurrences. Pour la série principale : tiers sans plans, tiers avec trois plans de 10 s, tiers validé avec brouillon de publication YouTube. Chaque occurrence est terminale, la récurrence est en pause. Les autres séries sont vides : ce jeu ne simule pas 25 séries de 120 épisodes.
- Vidéos : métadonnées synthétiques seulement, aucun fichier média ni appel IA. Ce protocole mesure les pages HTML, pas le décodage vidéo, les miniatures, le GPU ou les téléchargements.
- GET : Studio, liste des séries, détail de série, épisode en préparation, récapitulatif de trois plans. Un préchauffage écarté puis trois échantillons par route et volume, deux passages complets : **60 GET mesurés + 20 préchauffages**. ORM vidé avant chaque requête ; les caches Twig/SQL/OS restent chauds. Authentification et fixtures hors chronomètre/compteur.

Conditions : Docker Linux sur ce PC Windows, copie Linux temporaire des sources et vendor pour éviter la lenteur du montage Windows ; conteneur `edit-worker` limité à 2 CPU et 2 Gio, PHP **8.4.27**, PostgreSQL **16.15**, OPcache CLI désactivé, Kernel test sans debug/profiler. Services locaux habituels présents, sans charge utilisateur synthétique concurrente. Base de test migrée avant exécution, aucun autre test DB en parallèle. Pas de production ni simulation de plusieurs utilisateurs simultanés.

### Séparation des coûts

`StudioBenchmarkBrowser::doRequest()` chronomètre `handle`, le contenu de réponse et `terminate` de Symfony, **avant l'analyse DOM de BrowserKit**. `browserKitRequestMs` conserve séparément le coût de la requête interne complète et de son Crawler. Il ne s'agit dans aucun cas d'une mesure TCP, TLS, navigateur réel ou temps d'affichage.

Le middleware DBAL compte les exécutions, y compris les statements préparés. Calibration : un `prepare`, deux `execute` donnent exactement deux exécutions. Les fixtures, login et nettoyage ne sont pas comptés. Les temps SQL individuels ne sont pas mesurés ; le total serveur les inclut. Le nombre de requêtes seul ne mesure pas leur taille ni le coût d'hydratation.

`htmlBytes` est la longueur UTF-8 brute, sans compression ni assets externes. Les formulaires/champs sont comptés dans le HTML après la fenêtre serveur. Les pics PHP absolus et au-dessus du début de `doRequest` utilisent `memory_get_peak_usage(false)` réinitialisé par échantillon ; ils restent ceux d'un processus BrowserKit persistant, avec objets du passage précédent éventuellement présents au départ, et ne sont pas un RSS de worker neuf.

### Résultats HTTP

Médianes serveur en ms, arrondies ; les minima/maxima des trois échantillons restent dans le JSON. A et B sont deux passages du même programme avec les mêmes empreintes.

| Page | Petit A / B | Grand A / B | SQL petit / grand | HTML petit / grand, passage B |
| --- | ---: | ---: | ---: | ---: |
| Studio | 10,21 / 10,28 | 10,29 / 9,86 | 5 / 5 | 278 617 / 278 567 octets |
| Liste des séries | 3,45 / 3,42 | 3,62 / 3,45 | 4 / 4 | 5 607 / 9 053 octets |
| Détail de série | 10,33 / 9,99 | 36,27 / 31,69 | 13 / 13 | 41 980 / 151 006 octets |
| Épisode en préparation | 14,02 / 13,62 | 20,39 / 13,85 | 7 / 7 | 308 840 / 308 720 octets |
| Récapitulatif | 10,87 / 10,36 | 12,36 / 10,81 | 12 / 12 | 10 775 / 10 760 octets |

Le détail de série contient **quatre formulaires HTML** dans les deux volumes. Distinction importante : le contrôleur construit quand même un formulaire PHP pour chaque épisode sans plans, soit **1 contre 40** d'après les branches et fixtures, bien que le template actuel n'affiche pas ces formulaires. C'est un constat de sources, pas un comptage de quarante formulaires HTML. La page Studio et l'épisode contiennent chacun 41 formulaires rendus, notamment le catalogue ; leur volume reste presque constant ici. Aucune optimisation de ces formulaires ajoutée avant R4.2/R4.3.

## Attente et rédaction, mesurées séparément

Une vraie entrée est déposée dans le transport Doctrine de test via `StoryProposals::create()`. Après une attente volontaire de **250 ms**, le programme retrouve uniquement le message portant son UUID, vérifie son type/identité, appelle le traitement applicatif puis acquitte ce seul message. Les deux réponses de `LocalStoryWriter` sont simulées, avec **20 ms de délai contrôlé chacune**, et produisent un scénario normalisé terminé. Tous les autres HTTP sont refusés.

| Étape | Passage A | Passage B |
| --- | ---: | ---: |
| Création et dépôt de la demande | 7,987 ms | 7,944 ms |
| Attente contrôlée après dépôt et lecture du message | 251,648 ms | 251,771 ms |
| Traitement applicatif total, réponses simulées incluses | 47,780 ms | 47,941 ms |
| Réponse simulée 1, incluse ci-dessus | 20,095 ms | 20,091 ms |
| Réponse simulée 2, incluse ci-dessus | 20,093 ms | 20,087 ms |
| Inférence d'un vrai modèle local | non mesurée | non mesurée |

Ces durées imbriquées ne s'additionnent pas toutes. Cette expérience prouve la séparation des étapes et le chemin de traitement, pas une attente normale en file, une capacité de worker ou un temps réel Qwen. Le message est traité manuellement **dans la transaction du programme** : visibilité après commit et concurrence d'un worker ne sont pas testées ici. Les horodatages métier ont une résolution seconde ; les durées ci-dessus proviennent du compteur monotone du programme. Les mesures réelles de rédaction R2 restent historiques et ne sont pas présentées comme rejouées.

## Vérifications, incidents et limites

Le programme interdit les clients HTTP applicatifs, valide les réponses 200 et l'activité du compteur SQL, contrôle le statut final `ready` et les deux appels simulés. Toute la donnée synthétique est placée dans une transaction annulée en `finally`, même en cas d'erreur. Les comptages avant/après sur **onze tables** concordent dans les deux passages finaux, zéro HTTP interdit. Les **séquences PostgreSQL consommées ne reviennent pas en arrière** ; aucun identifiant existant ni ligne du développeur n'est supprimé, aucune queue n'est purgée globalement. Les caches et sessions du banc sont dans la copie Linux temporaire retirée avec le conteneur.

Deux premières constructions de fixture ont échoué correctement : plafond de sauvegarde de brouillons par le service public, puis récurrence sans réglages requise par le template. Fixtures construites directement par les entités pour le volume et récurrence synthétique dotée de réglages puis mise en pause ; aucune garde applicative changée. Un premier chronométrage englobait le Crawler : écarté, remplacé par le chronomètre `doRequest`, puis deux campagnes finales distinctes. Les premiers essais ne sont pas mélangés à la référence versionnée.

Revue indépendante du programme et de la méthode en lecture seule : ces points corrigés, aucun blocage méthodologique restant identifié. Le relecteur n'a exécuté ni benchmark ni test DB. R4.1 ne modifie aucun service, schéma, template ou asset applicatif ; pas de nouvelle recette visuelle ni suite PHP complète. Les **804 tests PHP / 9 227 assertions et 178 frontend** restent les derniers contrôles globaux de R3, historiques. Le programme R4.1 est exécuté réellement, syntaxe/mode manifeste et résultats/empreintes vérifiés séparément.

## Rejouer et poursuivre

Depuis une copie Linux du projet avec vendor, dans un conteneur local relié à la base de test :

```sh
APP_ENV=test APP_DEBUG=0 php bin/console doctrine:migrations:migrate --env=test --no-interaction
php scripts/benchmark-studio.php
php scripts/benchmark-studio.php --run --commit=6002695372b33cc5dea091bcc65cdadacbf0cda6 > baseline.json
```

Le manifeste seul n'accède pas à la base. Pour comparer après R4.2, remplacer le SHA par celui des sources réellement utilisées, garder volumes/versions/limites/cache identiques et consigner tout écart. Ne jamais exécuter en même temps qu'une suite utilisant `shortflow_test`. La commande directe sur montage Windows reste fonctionnelle mais ses temps ne sont pas comparables à cette copie Linux.

R4.2 doit maintenant borner la série et ses occurrences, éviter la construction des formulaires inutilisés et préserver la navigation/historique ainsi que les épisodes liés au calendrier. Aucun correctif, activation réelle, modèle réel, coût, publication, migration applicative, déploiement ou push dans R4.1. Commit local documentaire et programme de mesure ; revue globale de tout R4 encore à faire en R4.5.
