# R7.3c — recette consolidée des personnages

5 novembre 2026. Le volet automatisé est consolidé en local avec fournisseurs simulés. **R7.3c reste ouvert pour les essais audiovisuels réels prévus en R7.4–R7.5 et la décision de périmètre R7.6.** R7.2/R7.3 restent ouverts : aucun retour de testeur n’est inventé. Aucun nouvel essai réel n’est autorisé par ce document.

## Ce qui est vérifié

Cette recette rapproche les personnages importés de R7.3a, les portraits acceptés de R7.3b, les scènes, les devis et les envois simulés. Les fiches sont des sources réutilisables ; une préparation conserve la version qu’elle a choisie. La présence par scène reste distincte du cadre de début et des vues d’identité propres à chaque modèle.

| Cas | Preuve automatisée |
| --- | --- |
| Import, propriété, archivage et fichiers privés | `AiCharactersTest` : formulaires/CSRF, accès étrangers, versions périmées, import refusé sans perte de texte, pagination, historique et maintenance. |
| Idée → portrait accepté | `AiPortraitDraftsTest`, `AiPortraitFlowTest`, `AiPortraitApiTest` : brouillon/devis distincts, clé/tarif, consentement, limites réseau/fichier/quota, résultat privé, acceptation/rejet, doublons, pannes et reprise sans nouvel envoi. |
| Portrait accepté → scènes | Le scénario de bout en bout R7.3b réutilise le fichier accepté dans deux scènes ; une nouvelle version de fiche n’altère pas les devis déjà préparés, même après archivage. |
| Personnages présents seulement | `CharacterJourneyTest` contrôle les corps des envois Vidu pour Milo seul, Lea seule et les deux ; Kling distingue cadre de début, éléments/vues groupées et scène sans personnage. Empreintes des fichiers encodés vérifiées. |
| Nouvelle version / anciens épisodes | Copies rechargées depuis PostgreSQL, deux épisodes avant/après modification de fiches et de série, modification postérieure à confirmation sans réécriture des références confirmées. |
| Changement de modèle, nouveau scénario | Passage Vidu → Kling : le brouillon est conservé mais le devis est refusé tant que les vues Kling manquent. Après ajout volontaire d’une vue et choix explicite de la version 2, le nouveau devis inclut cette vue ; l’ancien hash ne confirme rien. Aucun objet de génération créé par ce test. |
| Anciennes données, nouveau scénario | Préparation historique sans `characterPool` ni `characterIds` : ancien cadre conservé après relecture DB. Une liste commune explicitement vide exige une présence de scène explicite ; `characterIds=[]` reste vide, sans ajout automatique d’une autre fiche du compte. |
| Anciennes saisies et confirmations | Formulaires/autosaves anciens sans champs gérés préservent les présences existantes ; anciens devis, présences modifiées et références incompatibles sont refusés. |
| Panne et ambiguïté | Reprise 503 sur le même reçu ; un envoi incertain ne produit ni nouveau POST automatique ni lancement du plan/personnage suivant. Les erreurs de résultat image n’inventent pas une fiche utilisable. |

## Deux nouveaux scénarios et relecture du banc

Le scénario de changement de modèle traverse les vrais formulaires Studio. Le premier essai du banc attendait à tort un POST 422 : le comportement existant est de sauvegarder la préparation, puis de revenir au formulaire lorsque le devis détecte les vues manquantes. Le test a été corrigé pour contrôler ce comportement, le refus explicite du devis, la conservation des références, puis le refus de l’ancien consentement. Aucune validation applicative n’a été assouplie.

Le scénario historique recharge trois états depuis la base : champs de personnages absents, liste commune présente avec présence de scène absente, puis présence explicitement vide. Il exerce le résolveur effectivement utilisé pour les devis, sans remplir les champs manquants depuis la bibliothèque actuelle.

L’analyse ciblée de l’ancien fichier de tests a signalé deux assertions `assertTrue(true)` préexistantes. Elles sont remplacées par la vérification des messages attendus : fiche étrangère/obsolète/doublon et fichier gelé manquant. Ces deux tests sont rejoués après renforcement ; ils ne constituent pas des scénarios supplémentaires à additionner au total de la campagne.

## Périmètre et reproduction

Les cinq groupes concernés sont exécutés ensemble :

```powershell
docker compose exec -T php php vendor/bin/phpunit tests/Functional/AiCharactersTest.php tests/Functional/AiPortraitDraftsTest.php tests/Functional/AiPortraitFlowTest.php tests/Unit/AiPortraitApiTest.php tests/Functional/CharacterJourneyTest.php
```

`KernelBrowser` traverse les routes, formulaires et validations Symfony. `AiCharacterSelection` copie les versions retenues ; `AiPlanInputs` résout présence et références selon le modèle ; `AiEpisodeFlow` prépare et contrôle le devis ; les adaptateurs HTTP reçoivent uniquement des réponses synthétiques. Doctrine est vidé puis relu lorsque la persistance est pertinente. Les transactions de test sont annulées et les fichiers synthétiques privés nettoyés. Les quatre scénarios de parcours vérifient aussi la restauration du stockage vidéo.

Les images et clips violets/bleus sont produits localement. Les traitements FFmpeg et assemblages de ces fixtures sont réels ; **ils ne sont pas des rendus IA**. Les prix vidéo utilisent l’horloge synthétique existante du 30 octobre, sans nouvelle vérification des tarifs publics. Les activations temporaires de traitement sont limitées aux processus de test et restaurées ; aucun worker réel ne traite les demandes du banc.

**36 tests ciblés / 627 assertions**, comprenant les deux nouveaux scénarios ; **3 reprises ciblées / 54 assertions** après renforcement du banc, avec chevauchement, à ne pas additionner. PHPStan et style du fichier de tests modifié verts. **32 rapports transactionnels**, 27 compteurs identiques après chaque rollback ; quatre rapports contrôlent explicitement la restauration du stockage vidéo privé. **79 échanges HTTP fonctionnels simulés dont 18 POST** (les réponses des quatre tests unitaires de l’adaptateur sont hors de ce décompte). **39 HTML et 29 médias des parcours vidéo** vérifiés par empreintes, plus les fichiers normalisés conservés pour les recettes image/modèle.

**12 vues de relecture** pour les deux nouveaux écrans de changement de modèle : sans JS à 320/390/768/1280, avec JS à 390/1280, quatre captures. Aucun débordement, ressource en échec, erreur JS ou image chargée cassée. Les images différées dans des détails fermés ne sont pas déclarées cassées avant chargement. GET locaux uniquement, aucun formulaire de génération soumis par cette relecture. Les 37 autres HTML frais de la campagne sont vérifiés par empreintes, sans nouvelle matrice navigateur revendiquée pour eux.

Les résultats, rapports et empreintes de la campagne sont conservés dans [le manifeste](fixtures/personnages-consolides-20261105.json). Les résultats d’anciennes campagnes ne sont pas présentés comme des nouveaux passages. Aucun code applicatif, schéma, configuration, Twig, CSS ou JavaScript modifié dans ce lot ; aucune suite PHP globale ni nouveau frontend/build, migration, cache web dev actualisé ou worker démarré/redémarré. Pas de nouvelle revue indépendante : celle du grand R7 reste requise avant sa clôture.

## Ce qui reste à observer réellement

Lors d’un essai autorisé et budgété, comparer plusieurs épisodes avec un personnage, puis deux personnages ensemble : visage, tenue, proportions, séparation des identités, présence/absence selon le plan et continuité des gestes. Évaluer séparément son, voix, langue, répliques et assemblage selon les capacités réellement offertes par le modèle. Une référence correctement transmise n’est pas une preuve que le fournisseur l’a respectée.

Consigner chaque résultat avec son modèle, compte, paramètres, coût constaté, fichiers et observation humaine. Si le budget ou le rendu ne permet pas l’essai, laisser cette qualité **non validée** et décider avec le propriétaire ce qui pourra être présenté en R7.6. Aucun achat, compte réel ou appel payant ne découle de la réussite des simulations.

**Suite exacte : cadrer R7.4 avec le propriétaire** (modèle/endpoint à revérifier, compte facturé, durée, nombre de plans, plafond de dépense ou vidéo existante). Ne rien lancer sans cet accord distinct. Les observations humaines R7.2 peuvent toujours être faites ultérieurement ; grand R7 n’est pas clôturé.

Suite du 5 novembre : [protocole R7.4 préparé avec tarif public revérifié](r7-essai-reel-cadrage.md). Budget, compte et références encore à confirmer, aucune génération ni activation.
