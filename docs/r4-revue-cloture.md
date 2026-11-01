# R4 — revue globale performance et volumes

1er novembre 2026. **Grand R4 terminé, R4.1–R4.5 validés. Prochaine tâche : R5.1.** Cette décision porte sur tout le lot, après revue indépendante globale ; elle ne se limite pas à additionner les revues ciblées.

## Grille de clôture

| Tâche | Résultat et preuve |
| --- | --- |
| R4.1 | Banc petit/grand volume, SQL calibré, temps Symfony séparé du Crawler, HTML brut ; attente Doctrine et réponses de rédaction simulées séparées. [Protocole et limites](r4-mesures-performance.md). |
| R4.2 | Épisodes et historique à 12 par page, trois créneaux récents sans épisode, accès aux anciens conservé. Formulaire d'ancien onglet construit seulement sur POST ciblé ; retours et refus protégés. [Pagination et mesures](r4-series-paginees.md). |
| R4.3 | Environ 57 ko HTML retirés du Studio et de la préparation d'épisode en supprimant une sérialisation du catalogue. Ancienne valeur compatible. Aucun découpage d'assets justifié par les mesures. [Correctif et campagnes](r4-autres-lenteurs.md). |
| R4.4 | 500 épisodes/505 créneaux, huit états de publication hors première page, ancienne version conservée, refus des accès étrangers, douze tables métier/file inchangées après lectures. Recette locale desktop/mobile/clavier/sans JS. [Contrôles et limites](r4-lectures-reprise.md). |
| R4.5 | Relecture globale des sources et preuves, vérification mécanique des campagnes, synthèse avant/après et décision de sortie dans ce document. [Empreintes et résultats de vérification](fixtures/r4-cloture-20261101.json). |

**Porte satisfaite :** lectures et rendu des séries bornés, historique accessible, résultats comparables et limitations explicites. Aucun blocage fonctionnel ou méthodologique restant identifié par la revue indépendante. Aucun correctif applicatif supplémentaire nécessaire en R4.5.

## Résultat mesuré, sans extrapolation

Les chiffres suivants proviennent des campagnes déjà réalisées, **pas de nouvelles mesures R4.5**. A/B désignent deux campagnes du même protocole pour chaque comparaison ; ne pas additionner ni comparer les temps de sessions différentes comme une promesse.

| Comparaison | Avant | Après | Conclusion limitée |
| --- | --- | --- | --- |
| Détail de série, 120 épisodes, R4.1 → R4.2 | 151 006 octets HTML (B), 36,27 / 31,69 ms serveur (A/B) | 55 172 octets (B), 14,68 / 14,63 ms (A/B) | Moins de données rendues : 12 épisodes par page, accès aux autres conservé. |
| Même route, requêtes SQL petit/grand jeu | 13 / 13 | 17 / 17 | Coût des comptages/historique ; nombre stable avec ce volume, pas de requête par épisode ajoutée. |
| Petit détail de série, 3 épisodes | 10,33 / 9,99 ms | 13,94 / 13,32 ms | La pagination ajoute un coût sur le petit jeu ; ce recul n'est pas masqué. |
| Studio, comparaison propre à R4.3, grand jeu | Environ 279 ko HTML | Environ 222 ko | Environ 57 ko retirés, pas de gain stable de temps serveur démontré. |
| Préparation épisode, comparaison R4.3, grand jeu | Environ 309 ko HTML | Environ 252 ko | Même réduction ; SQL 7 → 7, aucune garantie de délai navigateur. |

Sources brutes : [R4.1](fixtures/r4-baseline-20261030.json), [R4.2](fixtures/r4-pagination-20261030.json), [R4.3](fixtures/r4-catalogue-20261101.json). Les comptes de tables retrouvent leurs valeurs après rollback, mais les **séquences de test consommées ne sont pas restaurées**. Les gains de mémoire R4.2 sont des pics du processus PHP persistant du banc, pas une mesure RSS de production.

Les 500 épisodes de R4.4 servent à vérifier les bornes/navigation/intégrité ; ils ne sont pas présentés comme une nouvelle campagne de performance. Les assets lourds de la landing restent conditionnels ; ni animations, ni reduced motion, ni fallback n'ont été modifiés par ce lot.

## Revue indépendante globale

Un agent en lecture seule a confronté R4.1–R4.4 à leurs objectifs, relu le diff applicatif depuis la clôture R3 `6002695`, les projections héritées de `d3e4700`, le benchmark, les trois fichiers de mesures et les logs finaux R4.4. Il n'a lancé aucune suite DB et n'a modifié aucun fichier.

Constats concordants avec la revue principale :

- Propriété de la série contrôlée avant pagination, requêtes contraintes à cette série/récurrence et projections de couverture/publication toujours groupées. Le lien épisode/occurrence est OneToOne : douze occurrences suffisent pour les douze épisodes visibles.
- Anciennes soumissions ciblées gardent CSRF, révisions, états, textes échappés et refus récupérables. Le marqueur de refus empêche la reprise d'une ancienne ancre de remplacer une réponse avec saisie humaine. Les nouveaux retours désignent l'épisode, sans lire un fragment côté PHP.
- Devis et consentements de génération/publication conservés. Aucun nouveau chemin d'appel externe sur GET ; les tests R4.4 font échouer tout HTTP applicatif.
- Optimisations antérieures conservées : projections groupées, comptes/titres sans lecture de secrets inutiles, récents Studio limités, absence de préchargement vidéo ajouté. Imports dynamiques de GSAP/Three.js et comportement landing inchangés.
- Les campagnes distinguent calcul GET, attente contrôlée et réponses texte simulées ; aucun résultat d'inférence réelle ou de capacité worker revendiqué.

**Décision du relecteur : aucun blocage identifié pour clôturer le grand R4.** Deux précisions de preuve sont intégrées ci-dessous : cache dédié du benchmark et fins de ligne des empreintes. Ce ne sont pas de nouveaux défauts applicatifs.

## Contrôles de cette clôture et preuves conservées

Vérification locale R4.5, sans DB ni HTTP : `node artifacts/r45/verify.cjs`. **8 campagnes, 240 GET mesurés et 80 préchauffages**, même SHA du programme, calibration SQL à deux exécutions, trois échantillons par route, **80 triplets min/médiane/max recalculés identiques**, onze comptages avant/après identiques et zéro HTTP interdit pour chaque campagne. Les 80 empreintes de sources concordent avec les commits annoncés : 64 identiques aux blobs Git bruts, 16 différences explicables uniquement par CRLF ou fins de ligne mixtes. Le contrôle principal compare les variantes LF/CRLF et, pour le template mixte actuel, son empreinte exacte et son contenu normalisé au commit. Ne pas lire ce résultat comme 80 égalités avec les blobs bruts. Le rapport et les empreintes des fixtures sont versionnés dans [le relevé de clôture](fixtures/r4-cloture-20261101.json) ; le script local n'est pas nécessaire pour reprendre R5.

Contrôle CLI R4.5 dans la copie Linux du conteneur edit-worker : `php -l scripts/benchmark-studio.php` puis `php scripts/benchmark-studio.php` **sans `--run`** : syntaxe et manifeste réussis ; ce mode ne démarre pas le Kernel et n'accède pas à la base. `git diff --check` réussi sur les changements de clôture.

Les sources applicatives/tests n'ont pas changé depuis R4.4 `ac3bcac`. Les derniers contrôles complets restent donc ceux de R4.4, **non rejoués en R4.5** :

- `php scripts/check.php` dans la copie Linux : **813 tests PHP / 9 437 assertions**, schémas dev/test, migrations test à jour, lints YAML/Twig/conteneur, PHPStan et style verts.
- `npm run test:ui` : **183 tests** ; `npm run check` et `npm run build` réussis. Avertissements Three.js et chemins médias/polices au runtime déjà documentés.
- Recette Playwright locale R4.4 : desktop 1280 × 900, mobile 375 × 812, clavier et sans JS ; retour épisode 500, historique indépendant, refus conservé, modèles/durées et absence de débordement sur les écrans contrôlés. La limite navigateur R4.3 a été levée en R4.4. Aucune nouvelle recette visuelle revendiquée en R4.5.

Les incidents R4.1–R4.4 restent décrits dans leurs bilans, sans suppression des essais refusés. **Le cache `var/cache/studio-benchmark` est distinct du cache test** : partir d'un cache benchmark neuf à tout changement de sources avant de comparer. Les premières mesures R4.3 sur ancien Twig et le rejeu exploratoire chevauchant PHPUnit sont exclus ; les campagnes retenues ont été relancées sans concurrence DB.

## Limites et suite

Ce banc local chaud et synthétique ne mesure ni hébergement cible, concurrence réelle, réseau/TLS, compression HTTP effective, rendu GPU, miniatures réelles, décodage ou qualité audiovisuelle. La file et les réponses du rédacteur sont contrôlées/simulées ; aucune capacité Qwen ou garantie de délai n'en découle. Les exports navigateur sont synthétiques et leur serveur refuse les POST ; la sauvegarde automatique Studio y est simulée. Les snapshots R4.4 couvrent douze tables métier/file, pas toute la base ni les caches/sessions.

Le comptage et les offsets SQL ne sont pas de coût constant ; la pagination borne l'hydratation/rendu, sans démontrer une tenue illimitée. La capacité et les délais acceptables sur la cible restent en **R8/R11**, avec le budget et les exigences de charge consignés dans la roadmap. Aucune qualité narrative autonome, connexion fournisseur réelle ou fidélité audio/vidéo n'est certifiée par R4.

**Prochain point exact : R5.1.** Vérifier les écrans et états à 320, 390, 768 et 1280 px, notamment la landing mobile signalée par le propriétaire (téléphone/texte/CTA), animations, état statique et reduced motion. R5.2–R5.5 gardent navigation, clavier, zoom, erreurs et fallback. Rien de R5 n'est déclaré réalisé par la recette ciblée R4.

Pédagogie : borner en SQL évite d'hydrater tous les épisodes, tandis qu'une pagination uniquement en JavaScript laisserait le coût initial. Un nombre SQL plus élevé peut accompagner moins de travail total ; il faut regarder aussi le volume, le temps et la mémoire, avec leurs conditions. Le catalogue partagé réduit le HTML, sans garantir un parsing JSON unique dans Stimulus.

Commits locaux du lot : `aca2424` (banc), `195ff4e` (pagination), `6fa3017` (mesures), `6a8bbb4` (catalogue), `003e754` (bilan catalogue), `ac3bcac` (vérifications R4.4), puis commit documentaire de cette clôture. Aucune publication publique des mesures : « publier » R4.5 signifie les consigner dans Git local. Aucun appel IA/fournisseur/social réel, coût, activation, migration applicative, déploiement ou push. Aucun changement applicatif R4.5 : pas de cache dev/worker redémarré ni de nouveau serveur/onglet de recette ouvert.
