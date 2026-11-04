# R7.3a — premier sous-lot : fiches privées

4 novembre 2026 : création, portrait, vues supplémentaires, liste paginée, modification, archivage et restauration implémentés localement. Accès **Studio → Mes personnages**, également depuis la configuration d’une série ; aucune septième destination ajoutée, section Studio active.

**R7.3a reste en cours** : ce premier sous-lot ne sélectionne pas encore les personnages dans les recettes de séries ou les scènes. R7.2 et R7.3 restent ouverts pour les retours et corrections des testeurs. L’autorisation du propriétaire de développer les fiches succède au [cadrage](r7-personnages-cadrage.md) ; la migration additive a été appliquée en test puis en développement local pour rendre les écrans utilisables, sans déploiement de production.

## Utilisation

- Ajouter un personnage : nom, description et portrait obligatoire. Nom limité à 60 caractères, description à 2 000 ; validations techniques, sans nouvelle offre commerciale.
- Portrait et jusqu’à trois vues facultatives : stockage privé normalisé JPG, validation existante JPG/PNG/WebP, 10 Mo, dimensions/proportions existantes et garde partagée de 100 Mo par compte.
- Modifier sans joindre de fichier conserve les images. De nouveaux fichiers remplacent celles de la fiche. Une case permet de retirer les vues ; les anciens fichiers demeurent protégés pour leurs éventuels usages.
- Archiver retire la fiche de la liste active ; restaurer la remet à disposition. Aucun effacement physique exposé. Une fiche archivée est consultable mais non modifiable.
- Les erreurs conservent les textes, mais les fichiers doivent être sélectionnés à nouveau après un refus (fonctionnement natif du navigateur). Les écritures exigent un CSRF et les changements concurrents sont refusés par révision.

Les anciennes séries, scènes, générations, devis et programmations ne sont pas modifiés par ces opérations. La sélection effective de personnages dans ces parcours est le sous-lot suivant. Ni import, ni lecture, ni modification de fiche ne lance d’appel fournisseur.

## Architecture et flux Symfony

`AiCharacter` est la nouvelle entité : propriétaire, nom/description, portrait/vues courants, historique des noms d’images remplacées, révision, archivage et date de modification. Les noms de fichiers sont immuables ; modifier une fiche ne réécrit jamais les octets d’une ancienne référence.

`AiCharacterType` fournit les champs natifs et leurs contraintes. `AiCharacterController` vérifie l’accès au propriétaire, traite les formulaires et sert les images par route privée. Les réponses sont privées/no-store ; les écritures sont POST uniquement, l’archivage possède son CSRF, les IDs étrangers répondent 404. La protection ROLE_VERIFIED existante couvre `/studio/personnages`.

`AiCharacters` verrouille le propriétaire, recharge la fiche et vérifie sa révision avant import/écriture. Le même verrou est utilisé par la maintenance des références. Les fichiers créés dans une opération refusée sont nettoyés ; tous les nettoyages sont tentés, et un échec de suppression est signalé explicitement. Une panne réelle du stockage peut exiger une intervention, elle n’est pas présentée comme un rollback de fichiers garanti dans toutes les pannes matérielles.

`AiReferenceMaintenance` inventorie désormais aussi les fiches, leurs vues et leur historique, archivées comprises. Les images anciennes de ces fiches ne deviennent pas des orphelins à trente jours. Cette conservation consomme le stockage partagé ; elle ne contourne pas sa limite.

Les templates `character/index` et `character/edit` réutilisent le shell Studio et les formulaires accessibles. CSS limité aux nouvelles classes ; les liens contextuels sont ajoutés au Studio et à la configuration de série. Aucun JS nouveau, aucune dépendance ajoutée.

## Migration et contrôles

Migration `Version20261104010000` : nouvelle table `ai_character`, index de propriétaire/archivage et clé étrangère avec suppression liée au compte. Aucun ancien champ métier modifié. La migration descendante refuse de supprimer une table non vide.

Le premier contrôle test a identifié le nom de l’index de relation attendu par Doctrine : nom corrigé avant application en dev. Aller-retour descendant/ascendant testé **uniquement sur la nouvelle table test vide**, puis schémas test et dev validés. Dev avait exactement une migration en attente : celle-ci. Cache web dev actualisé, aucun worker redémarré.

- **4 tests fiches / 66 assertions** : création et images privées, remplacement et vues, refus après plusieurs fichiers avec conservation des anciennes images, CSRF, révisions obsolètes, archivage/restauration, propriété, pagination et maintenance au-delà de trente jours.
- **4 tests de non-régression / 36 assertions** : entretien historique des références, navigation du préparateur et erreurs de références communes. **8 tests ciblés / 102 assertions**, aucune suite PHP globale revendiquée.
- **195 frontend**, check et build verts ; dernier build après finition CSS. Avertissement de taille Three.js existant, non bloquant. Aucun changement JS.
- **122 Twig**, **19 YAML**, conteneur, PHPStan et style ciblé verts ; schémas dev/test verts.

Les tests de fiches utilisent des propriétaires synthétiques et une transaction exclusivement dans `shortflow_test`. Comparaison des compteurs de toutes les tables publiques (25), rollback, nettoyage des répertoires d’images de ces propriétaires et temporaires ; cinq clients HTTP remplacés par des refus, zéro HTTP fournisseur constaté. Les tests de non-régression gardent leurs propres conventions : ne pas étendre à l’ensemble de ceux-ci les garanties de rollback de cette nouvelle classe.

Réponses HTML et portrait synthétique sous `artifacts/characters`, captures et banc navigateur sous `output/playwright/characters-*` ; fichiers de recette ignorés par Git. Le manifeste versionné consigne les contrôles navigateur et empreintes. Relecture de six états : vide, création, liste, modification, archives, erreur, à 320/390/768/1280 sans JS et 390/1280 avec JS ; formulaires soumis réellement par BrowserKit, relecture navigateur distincte. Pas de nouvelle recette iPhone ou lecteur d’écran revendiquée.

Revue indépendante en lecture seule des propriétaires, confirmations CSRF, révisions, verrous et conservation des anciennes images. Ses renforcements sur le retrait explicite des vues et le signalement des erreurs de nettoyage sont intégrés. Résultats et limites finaux dans le manifeste ; le relecteur n’a pas exécuté les tests DB.

Pour vérifier : `docker compose exec -T php php vendor/bin/phpunit tests/Functional/AiCharactersTest.php`. Pour essayer : ouvrir le Studio local connecté, puis « Mes personnages », importer une image et modifier/archiver la fiche. Ce test ne demande aucun crédit IA.

## Suite

Prochain sous-lot technique : sélection commune des fiches dans Studio/Séries, puis présence par scène et résolution des références réellement utilisées dans les devis. Les formats anciens, confirmations et images épinglées doivent rester protégés. Aucun coût, appel fournisseur réel, génération, email réel, publication sociale, activation d’indexation, push ou déploiement pour ce sous-lot.

Bilan final : 36 vues sans débordement ni image cassée ; clavier sans JS à 390 px (évitement, champ nommé, retour bibliothèque). Après défilement, bas du bouton Enregistrer à 733,25 px pour une navigation commençant à 829 px. Six empreintes HTML et quatre rapports rollback vérifiés. Revue indépendante finale sans blocage identifié. [Manifeste de preuves](fixtures/personnages-fiches-20261104.json). Les premiers essais humains sont reportés par le propriétaire, sans validation déduite de ces contrôles.

Suite du même jour : [sélection commune, présence par scène et devis implémentés](r7-personnages-selection.md). Le présent document conserve les preuves et limites du premier sous-lot ; R7.3a reste ouvert pour la recette finale.
