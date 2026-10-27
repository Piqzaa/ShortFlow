# R2 — Qualité des scénarios et textes : suivi

## État courant — R2.1 terminé, 28 octobre 2026

**R2 est en cours, pas livré.** R2.1 ajoute les reproductions automatisées ; il ne corrige pas encore le rédacteur. **15 cas / 90 assertions : 11 réussites et 4 échecs attendus**, code de sortie 1. Les quatre rouges sont des défauts applicatifs déjà capturés, pas des pannes d'installation. Aucun test exclu, ignoré ou marqué artificiellement vert. La suite par défaut inclut ce fichier et reste rouge jusqu'aux corrections correspondantes.

**Prochain point exact : R2.2**, contrat des scènes parlantes et capacité du chemin vidéo avant coût. Les corrections de langue/citations relèvent ensuite de R2.3, les packs sociaux de R2.4 et le contexte factuel de R2.5. Ne pas retirer les assertions pour passer ces étapes.

## Objectif et responsabilités

`tests/Unit/WriterQualityRegressionTest.php` est le seul fichier de code ajouté. Il utilise les services réels `LocalStoryWriter::review()`, `LocalStoryWriter::publication()` et `PublicationTexts::normalize()`, sans modifier leur fonctionnement. Le [corpus historique](fixtures/redacteur-audit-20261027.json) et le [rapport qualité](audit-global-20261027-qualite.md) sont conservés intacts.

| Composant | Rôle dans le test |
| --- | --- |
| `LocalStoryWriter::review()` | Relecture hors ligne des sorties sauvegardées ; moteur désactivé et client HTTP qui échoue si un appel est tenté |
| `PublicationTexts::normalize()` | Vraie validation du pack : réseaux, champs et séparation des hashtags ; elle ne vérifie pas la vérité des faits |
| `LocalStoryWriter::publication()` | Rejoue une enveloppe synthétique/capturée via `MockHttpClient` ; aucune connexion Ollama ni autre API |
| Nouveau test | Charge le corpus, distingue captures brutes et résultats normalisés, ajoute des témoins synthétiques et vérifie les protections attendues |
| README, AGENTS et roadmap | Repère de reprise, cases, état rouge explicite et prochaine tâche |

Flux : fixture versionnée → service réel avec dépendances remplacées → assertion ciblée → comparaison au comportement attendu. Aucune base n'est interrogée par ces tests unitaires. La préparation du lancement a cependant vérifié `shortflow_test` et appliqué les migrations avant PHPUnit, conformément au protocole du projet.

## Contrats et résultats finaux

| Cas | Résultat actuel | Attendu / suite |
| --- | --- | --- |
| Six scénarios sauvegardés | Six réussites : durées présentes, scènes non vides et relecture sans mutation ni HTTP ; pas d'alerte de paroles systématique sur les cinq cas sans dialogue | Préserver les préparations ; ces témoins ne certifient pas leur sens |
| Dialogue capturé, consignes vidéo françaises | **Rouge** : aucune alerte de langue | Alerte visant les prompts, distincte d'une alerte de paroles ; R2.3 |
| Deux répliques présentes dans l'idée/descriptions mais absentes des prompts | **Rouge** : aucune alerte de paroles perdues | Signaler le défaut avant coût ; contrat R2.2 puis fidélité R2.3 |
| Consignes anglaises et courte citation française historique | Réussite | Ne pas interdire les citations intentionnelles |
| Consignes anglaises et citation synthétique contenant plusieurs marqueurs français | **Rouge** : fausse alerte de langue | Distinguer citation parlée et direction technique ; R2.3 |
| Vraie réponse brute du diagnostic mug, hashtags dans la description | Deux réussites : refus par le normaliseur et par le rédacteur, un seul appel simulé | Conserver le validateur et l'absence de relance implicite ; R2.4 |
| Pack « Vacances 2026 » dérivé : conformité structurelle | Réussite : le pack est accepté par la validation de structure et reconstitue exactement la capture normalisée | La forme valide ne prouve pas la fidélité |
| Même pack inventé depuis le seul titre | **Rouge** : préparation/projets/absence de plan acceptés sans contexte | Refus actionnable pour manque d'informations ou sortie strictement neutre ; R2.5 |
| Pack témoin littéral neutre depuis le même titre | Réussite | Autoriser un texte neutre ou une demande de contexte, sans imposer l'invention |

Les alertes de langue et de paroles sont testées séparément. Le test de langue ne se contente pas d'un tableau d'alertes non vide : il exige une indication concernant les prompts et leur langue. Le témoin de citation dense évite un faux succès dû à la brièveté de la citation historique.

## Provenance et limites de sens

Le cas historique `dialogue.result` est utilisé tel quel pour la relecture ; il n'est pas présenté comme une réponse brute des deux appels du modèle, qui n'a pas été conservée. Les témoins de citations sont explicitement synthétiques. Seul `diagnosticResponse.message.content` fournit ici une vraie réponse éditoriale brute capturée et directement rejouable.

`text-import-minimal.result` contient déjà les hashtags concaténés par l'application. Le rejouer directement comme réponse brute ferait échouer sur des champs manquants/hashtags et masquerait l'invention. Le helper de test sépare donc le suffixe de hashtags dans une **fixture dérivée**, tout en conservant les phrases inventées. Un test de roundtrip prouve que sa normalisation redonne exactement le résultat capturé ; cette reconstruction ne prétend pas retrouver la réponse brute originale.

Pour le titre pauvre, l'oracle fini accepte un refus `PublicationException` expliquant le besoin de contexte, ou un pack complet dont les textes sont uniquement `Vacances 2026`, `Vidéo : Vacances 2026` ou `À découvrir : Vacances 2026`, et les hashtags uniquement issus de ce titre. Ce sont des variantes neutres de contrôle pour **ce cas précis**, pas un style éditorial général imposé au produit. Une adaptation future de cet oracle doit être justifiée par le contrat R2.5, sans réintroduire les faits inventés historiques. Les tableaux réseaux vides ne passent pas le test ; tous les champs sont requis.

Les tests ne déduisent pas la vérité de quelques mots-clés absents et ne prétendent pas comprendre tous les textes. Ils ne certifient ni fidélité narrative générale, ni mouvement filmable, ni voix, lipsync ou parole audible. L'évaluation humaine du corpus et ses répétitions restent en R2.6. Le cas d'import titre seul demeure exploratoire au niveau du service : R2.1 n'expose pas une nouvelle aide éditoriale dans l'interface des imports.

## Vérifications exécutées

| Contrôle | Résultat |
| --- | --- |
| Nouveau test, fichier final | **15 cas / 90 assertions, 11 réussites et 4 échecs attendus**, code 1 |
| Tests existants `LocalStoryWriterTest` et `PublicationTextsTest` | **17 tests / 60 assertions réussis**, code 0 |
| PHPStan sur le nouveau test | Aucune erreur |
| CS-Fixer final `--dry-run --diff` | Aucun fichier à corriger |
| Liens locaux, identifiants roadmap et `git diff --check` | Vérifiés avant commit |
| Revue indépendante ciblée | Contrats/provenance relus ; oracle de champs et témoin sans dialogue renforcés, aucun blocage restant dans cette étape de reproduction |

Base vérifiée `shortflow_test`, migration déjà à jour `Version20261027160000`, aucune migration ajoutée. Exécution dans la copie Linux temporaire du projet d'`edit-worker`, avec la fixture versionnée copiée également ; aucune suite DB concurrente. Aucun appel modèle réel, téléchargement, texte payant, vidéo, image, réseau social, récurrence ou changement des données de développement.

Pas de nouvelle suite complète, build ou recette navigateur : aucun code d'application, asset ou template ne change. **Dernière suite complète verte : R1, 667 tests PHP / 8 044 assertions et 152 frontend.** Ce résultat historique n'est pas une validation verte de R2.1. Les journaux locaux ignorés sont `artifacts/r2-1-final.log`, `r2-1-phpstan-final.log` et `r2-1-style-final.log` ; cette documentation et les tests suffisent à reprendre sans eux.

## Reproduire sur un autre PC

Installer selon le README, vérifier/migrer `shortflow_test` avant PHPUnit, puis :

```sh
docker compose exec -T -e APP_ENV=test -e APP_DEBUG=0 php php bin/phpunit tests/Unit/WriterQualityRegressionTest.php
docker compose exec -T -e APP_ENV=test -e APP_DEBUG=0 php php bin/phpunit --filter 'LocalStoryWriterTest|PublicationTextsTest' tests/Unit
docker compose exec -T php php vendor/bin/phpstan analyse tests/Unit/WriterQualityRegressionTest.php --no-progress --memory-limit=256M
docker compose exec -T php php vendor/bin/php-cs-fixer fix --dry-run --diff --config=.php-cs-fixer.dist.php tests/Unit/WriterQualityRegressionTest.php
```

La première commande échoue actuellement sur les quatre protections manquantes ; les autres passent. Ollama et les crédits fournisseur ne sont pas nécessaires.

## À comprendre en Symfony et PHPUnit

Un test unitaire peut appeler un vrai service Symfony directement, sans kernel, Doctrine ni worker. Remplacer seulement son client HTTP permet de rejouer une mauvaise sortie de façon déterministe. Cela prouve le comportement du code face à cette sortie, pas ce qu'un modèle produira lors d'un autre appel. La validation de structure protège les champs ; l'alerte métier protège une intention spécifique ; la relecture humaine reste nécessaire pour juger le sens global.

La phase rouge écrit d'abord le comportement attendu, puis les prochains points le rendent vert en corrigeant l'application. La suite n'est pas rendue verte en supprimant les tests, en désactivant le validateur de hashtags ou en ajoutant une alerte universelle. Le grand lot R2 aura sa revue globale après ses corrections et son évaluation, en R2.7.
