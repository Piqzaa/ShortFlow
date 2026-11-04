# R7.3a — recette finale des personnages importés

4 novembre 2026. Recette technique locale des [fiches privées](r7-personnages-fiches.md) et de leur [sélection par scène](r7-personnages-selection.md). Elle complète les critères du [cadrage](r7-personnages-cadrage.md). Les observations humaines R7.2 restent reportées par le propriétaire ; aucune compréhension humaine ni qualité audiovisuelle IA n’est déduite des simulations.

## Ce que la recette vérifie

`tests/Functional/CharacterJourneyTest.php` ajoute quatre parcours dans le vrai noyau Symfony. Les fichiers d’images sont de véritables JPEG synthétiques normalisés par le stockage privé ; les vidéos sont des clips violets synthétiques de cinq secondes. Les appels fournisseur passent par les services applicatifs et l’adaptateur Fal existants, mais le client HTTP est intégralement remplacé par un `MockHttpClient` fermé.

| Scénario | Vérification |
| --- | --- |
| Deux épisodes Vidu, trois scènes chacun | Milo seul, Lea seule, puis les deux : les empreintes des octets réellement présents dans les requêtes correspondent exactement aux portraits attendus, dans leur ordre |
| Modification de bibliothèque et de série | Épisode 1 conserve Milo révision 1 ; épisode 2 reprend la copie révision 2 enregistrée dans sa série. La révision 3 créée après confirmation du premier épisode n’est injectée dans aucun des deux |
| Archivage de Lea | La nouvelle sélection actuelle disparaît ; la copie déjà détenue par la série reste utilisable dans ses préparations. L’archivage ne remplace ni n’efface son portrait figé |
| Confirmation et rechargement Doctrine | Confirmer deux fois n’envoie rien immédiatement. Après vidage de l’EntityManager et rechargement depuis la base, le traitement utilise encore les bons portraits et les prompts confirmés |
| Assemblage | Les trois clips du premier épisode sont assemblés par le moteur réel en une vidéo de quinze secondes. Elle reste non approuvée : aucune validation éditoriale ou programmation inventée |
| Échec de suivi HTTP 503 simulé | L’épisode se suspend, le plan suivant n’est pas créé. La reprise récupère le même identifiant distant, sans nouveau POST du premier plan, puis envoie uniquement le personnage de la scène suivante |
| Kling 3 image | Le cadre 1080 × 1920 est distinct des portraits et vues. L’ordre `@Element1`/Lea et `@Element2`/Milo correspond à l’ordre des éléments envoyés. Une scène sans personnage ne reçoit aucun élément |
| Ancien devis et envoi ambigu | Changer la présence invalide l’ancienne empreinte avant tout envoi. Un POST dont la réponse est incertaine ne provoque ni renvoi automatique, ni départ du personnage suivant |

Les requêtes sont enregistrées sous forme d’URL autorisée, de méthode, de prompt synthétique et d’empreintes/dimensions des images. Aucun en-tête d’autorisation ni secret n’est exporté. Toute URL ou méthode absente de la liste simulée fait échouer le test. Les clients sociaux et de rédaction sont remplacés par des refus.

Le banc conserve des copies synthétiques des images, clips et assemblage dans `artifacts/character-journey`, hors stockage applicatif. Le manifeste vérifie leurs empreintes et celles des HTML ; ces fichiers de recette sont ignorés par Git. Les noms et révisions réellement employés restent vérifiables dans les requêtes enregistrées et dans le code des assertions.

## Périmètre de validation R7.3a

| Critère initial | Preuve combinée |
| --- | --- |
| Propriétaire, accès aux images, CSRF, import refusé sans perte ni orphelin | `AiCharactersTest`, rejoué avec la recette finale |
| Fichiers remplacés/archivés protégés de la maintenance | Tests de maintenance des fiches, complétés par les envois après modification/archivage |
| Réserve commune différente de la présence d’un plan | Matrices exactes Vidu et Kling sur le contenu HTTP simulé |
| Liste vide différente de l’absence historique du champ | Tests de résolution, anciens POST/autosave et scène Kling sans élément |
| Modèle incompatible, vues insuffisantes ou image manquante | Refus avant devis dans les tests de sélection ; envoi positif des vues groupées Kling dans la recette finale |
| Devis et préparations anciennes conservés | Révisions et anciennes empreintes testées, recettes d’épisodes réhydratées ; aucun chemin de programmation modifié |
| Interface sans scripts et clavier | Preuves antérieures des fiches et sélecteurs, complétées par la relecture des états de résultat, devis et suspension de cette recette |
| Aucune dépense ou publication réelle | HTTP fermé, credentials synthétiques, transport de test et nettoyage transactionnel ; aucun worker de développement exécuté par le banc |

L’absence de modification des services applicatifs dans ce lot est volontaire : aucun défaut applicatif restant n’a été identifié par ces parcours. Les deux réserves de la revue indépendante concernaient la qualité du banc : nettoyage dans un `finally` indépendant du rollback, et vérification HTTP de chaque réponse avant export HTML. Elles sont intégrées.

## Comment fonctionne le test Symfony

`WebTestCase` démarre les services réels. `KernelBrowser` ouvre et soumet les formulaires existants avec leur CSRF. La préparation enregistre la recette ; `AiEpisodeFlow::confirm()` conserve le consentement ; `advance()` et `AiGenerations::process()` exécutent les étapes normalement appelées par les handlers. Ils ne démarrent pas de worker réel dans cette recette. L’adaptateur construit les images encodées ; le mock décode leur contenu et compare les empreintes aux fichiers privés attendus.

Pour les reprises, le mock renvoie volontairement une erreur connue ou une réponse ambiguë. Le test contrôle l’état conservé, le nombre de POST et l’absence de départ prématuré du plan suivant. Il ne suffit donc pas qu’un écran semble correct : les requêtes et l’état métier doivent aussi correspondre.

Chaque scénario ouvre une transaction exclusivement dans `shortflow_test`. Après rollback, les compteurs des 25 tables publiques sont comparés, les fichiers privés et temporaires sont nettoyés et la liste initiale des vidéos est restaurée. Les séquences de la base de test peuvent consommer des identifiants, comme dans les autres recettes. Le drapeau de génération est activé uniquement dans les variables du processus de test puis restauré ; aucune activation du poste applicatif n’est effectuée.

Pour rejouer le périmètre :

```text
docker compose exec -T php php vendor/bin/phpunit tests/Functional/CharacterJourneyTest.php tests/Functional/AiCharactersTest.php tests/Unit/AiShotTypeTest.php tests/Unit/AiShotPromptsTest.php
```

Les résultats finaux et les limites de relecture sont consignés dans [le manifeste](fixtures/personnages-recette-20261104.json). Pas de suite PHP globale revendiquée. Aucun frontend, template ou CSS modifié ici ; les 196 tests frontend/check/build et les contrôles statiques applicatifs du sous-lot précédent restent des résultats antérieurs, non rejoués pour cette recette sans modification applicative.

## Limites et suite

Les passages finaux et les empreintes sont vérifiés : **R7.3a est terminé sur le périmètre technique local simulé**. Elle ne prouve pas qu’un fournisseur conservera réellement le visage, la tenue, les proportions, le mouvement ou les voix : les vidéos violettes sont des fixtures. La relecture navigateur n’est pas une nouvelle recette iPhone, lecteur d’écran ou observation de testeur.

R7.2/R7.3 restent ouverts. R7.3b (portraits depuis une idée), R7.3c (recette consolidée des personnages, dont les essais audiovisuels distincts) et R7.4–R7.6 restent ouverts. Les présentes simulations peuvent être réutilisées comme preuves techniques de R7.3c, sans cocher ce lot. Le prochain chantier technique est le cadrage de R7.3b : choix du modèle image, connexion, validation des portraits et devis distinct, sans nouvel appel payant autorisé implicitement.

Aucun push, coût, appel fournisseur réel, email réel, publication, activation, migration, cache dev actualisé, worker redémarré ou déploiement dans cette recette.

Bilan final : **22 tests ciblés / 322 assertions**, dont les quatre nouveaux scénarios (ne pas les additionner). Style et PHPStan du nouveau banc verts. **13 rapports transactionnels** comparant chacun les 25 tables après rollback, dont quatre vérifient aussi explicitement la restauration du stockage vidéo privé et le retrait du dossier de références du propriétaire synthétique. **46 échanges HTTP simulés / 12 POST**, 16 médias, cinq nouveaux HTML et leurs images vérifiés par empreintes ; **20 vues de relecture** à 390/1280 avec et sans scripts, sans débordement ni erreur JS, HTTP ou image chargée en échec. Les anciens 36 contextes des sélecteurs restent des preuves antérieures distinctes. Revue indépendante sans blocage après correction des deux réserves du banc ; le relecteur n’a pas exécuté de tests DB. Les tarifs utilisent l’horloge synthétique existante du 30 octobre : aucun tarif fournisseur actuel n’a été revérifié.

Contrôle indépendant final des preuves : quatre rapports terminés, 25 compteurs égaux et stockage restauré, cinq HTML, seize médias et treize références d’images conformes à leurs empreintes ; code du banc et journal final conformes au manifeste, aucune incohérence bloquante. Ce contrôle de fichiers n’a exécuté aucun test DB supplémentaire.
