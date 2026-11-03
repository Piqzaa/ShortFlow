# R7.1 — recette produit avec API simulées

**R7.1 terminé le 4 novembre 2026**, grand R7 en cours. [Manifeste final](fixtures/r71-recette-20261104.json). Prochain point exact : **R7.2**, observations de testeurs autorisés à organiser avec le propriétaire.

Recette locale des jonctions import/montage/publication et série/assemblage/programmation. Les formulaires réels sont soumis par BrowserKit dans Symfony ; les traitements sont déclenchés explicitement dans le processus test, sans démarrer de worker. Les fournisseurs et réseaux sont remplacés par MockHttpClient. Aucun envoi réel, crédit, inférence locale ou activation n’est autorisé par ce banc.

## Trois scénarios intégrés

| Scénario | Opérations exécutées | Protections vérifiées |
| --- | --- | --- |
| Import avec montage facultatif | Véritable import MP4/FFprobe par formulaire, préparation YouTube avec texte/date, entrée montage depuis cette préparation, coupe 0,1→0,7 s, véritable export local FFmpeg, application du résultat, confirmation de programmation, départ au créneau, transfert et traitement YouTube simulés, suivi | Original et source fixture inchangés, sortie montée réellement utilisée, texte/compte/date conservés, aucune tâche sociale avant confirmation, rejouage de confirmation/départ/handler sans doublon |
| Deux vidéos indépendantes | Deux imports réels par le service de bibliothèque, deux comptes YouTube synthétiques, titres/descriptions et dates différents, confirmation de la seule préparation A | A sur sa chaîne et le 4 novembre à 14 h Paris ; B sur une autre chaîne et le 5 novembre à 16 h Paris, révision inchangée et toujours brouillon, tentative de dispatch de B sans armement inopérante |
| Série complète | Création par formulaire, saisie de deux scènes de 5 s et ouverture du récapitulatif par formulaire, devis et confirmation par formulaire, deux générations simulées, véritable assemblage FFmpeg, validation de la version par formulaire, ouverture de sa préparation, confirmation de programmation, calendrier et état de série | Confirmation de génération distincte de celle de publication ; aucun accès préparation avant validation ; source programmée égale à la version assemblée et approuvée ; aucun job social créé avant le créneau |

Le troisième scénario fixe deux prompts anglais manuels pour isoler le raccordement technique ; il n’exécute pas le rédacteur. Les deux appels simulés retournent le même clip violet synthétique : ni identité des personnages, continuité narrative, paroles, voix ou rendu fournisseur ne sont validés. Le montage facultatif n’est pas imposé aux autres parcours ; le test d’import sans montage existant est rejoué séparément.

## Suivi et reprise

Les tests existants ciblés complètent ces jonctions : transfert YouTube interrompu puis repris dans la même session ; redelivery ignorée ; résultat Instagram incertain récupéré sans nouveau POST ; absence d’identifiant distant qui interdit une relance TikTok ; correction d’un refus par nouvelle préparation limitée à la destination échouée ; anciens messages de programmation et annulation qui ne réarment pas une version obsolète.

Il faut distinguer une reprise de transfert/suivi d’une nouvelle tentative éditoriale après refus. Une préparation corrigée ne vaut jamais autorisation de renvoi. La recette d’indépendance confirme une seule préparation et vérifie que l’autre ne part pas.

## Isolation et preuves

`tests/Functional/ProductJourneyTest.php` exige `shortflow_test`, démarre une transaction sans supprimer les données existantes, puis annule en teardown. Les compteurs des **24 tables publiques** sont comparés avant/après chaque scénario. Le nettoyage des fichiers privés test et temporaires est dans un `finally` indépendant des assertions ; les variables modifiées sont restaurées. Le rapport de chaque scénario est initialisé à `completed=false` pour ne pas conserver une ancienne réussite en cas d’interruption.

Les copies de vidéos conservées sous `artifacts/r71` sont exclusivement des fixtures et sorties synthétiques. Leur empreinte correspond aux fichiers privés produits pendant le test, avant suppression de ceux-ci. Les réponses HTML réellement obtenues et leurs empreintes sont également conservées ; aucun compte de développement réel n’est utilisé. Les séquences de la base test peuvent être consommées et ne sont pas remises à zéro.

Les échanges MockHttpClient doivent être nommés **HTTP simulé** : le scénario import comporte quatre échanges YouTube simulés (POST, PUT, PUT, GET) ; celui de série comporte deux soumissions et six lectures/téléchargements simulés. Les clients non attendus refusent tout appel. Ce ne sont pas douze appels réseau réels.

Les autres tests fonctionnels historiques utilisent leurs propres nettoyages dans `shortflow_test` ; la comparaison transactionnelle ci-dessus concerne les trois nouveaux scénarios, pas la suite ciblée entière. Aucune suite DB concurrente n’est lancée.

## Pédagogie Symfony

Ce lot ajoute un banc de recette, sans fonctionnalité métier. BrowserKit traverse le routeur, les formulaires/CSRF, les contrôleurs et les services de l’application. Les services AiEpisodeFlow et VideoEdits traitent les étapes longues dans le processus test ; le handler YouTube traite ses messages explicitement. Les messages en file restent dans la transaction et ne sont pas consommés par les workers de développement.

L’injection de clients HTTP simulés permet de vérifier confirmations, persistance et reprise tout en gardant les données de fournisseur fictives. Réutiliser les vrais formulaires vérifie les raccords entre les étapes ; injecter seulement un état final ne suffirait pas à prouver ce parcours.

Commande du banc : `docker compose exec -T php php vendor/bin/phpunit tests/Functional/ProductJourneyTest.php`. Les journaux de la recette ciblée et le manifeste final donnent les résultats exacts. Les fichiers navigateur sous `output/playwright/r71-*` servent à la relecture en lecture seule des réponses capturées et de leurs médias synthétiques ; les parcours fonctionnels eux-mêmes sont exécutés par PHP/BrowserKit.

## Résultats et décision

- **87 tests ciblés / 1 242 assertions**, verts : ProductJourney, PublicationComposer, PublicationBatch, PublicationSchedule, EpisodePublications, YouTubePublication, InstagramPublication et TikTok. Ce passage comprend une première version verte des trois nouveaux scénarios.
- **Dernière version des trois nouveaux scénarios : 3 tests / 158 assertions**, verts après renforcement du nettoyage, des rapports, des imports indépendants et de la saisie des scènes par formulaire. Ces trois tests se chevauchent avec les 87 précédents ; ne pas les additionner comme des tests distincts.
- **4 tests / 76 assertions** de reprise de série verts : soumission ambiguë sans renvoi ni plan suivant, pause/reprise sans double génération, arrêt des étapes futures, remplacement d’un seul plan avec invalidation de l’approbation et conservation des autres résultats.
- **18 réponses HTML**, sept copies de médias synthétiques, empreintes vérifiées ; rollback et **24 compteurs égaux** par nouveau scénario, fichiers privés test restaurés. Les trois rapports finaux portent `completed=true`.
- **36 vues Chromium** : relecture des 18 réponses capturées à 390/1280, JavaScript désactivé, ressources et médias servis localement par interception. Aucun débordement, aucune ressource HTTP en échec ou requête bloquée dans le passage final. Captures préparation après montage mobile, épisode approuvé desktop et autres états conservées sous `output/playwright`. Il s’agit d’une relecture de véritables réponses de tests ; aucune soumission BrowserKit n’est présentée comme une action utilisateur dans Chromium. La recette clavier/accessibilité de R6 n’est pas revendiquée comme rejouée.
- Schéma test valide, syntaxe et style du nouveau fichier, PHPStan verts. Aucun code applicatif, Twig, JS ou CSS modifié : pas de nouvelle suite PHP globale/frontend/build. Les chiffres de R6 restent historiques pour ces contrôles non rejoués.

Les premiers passages ont permis de corriger le banc, sans correctif applicatif : entité Doctrine à recharger après requête, fabrique d’un mock déjà initialisé à réutiliser, sélection du vrai bouton de confirmation, et horloge avancée au créneau exact plutôt qu’une heure trop tard (refus protecteur attendu). Le premier replay navigateur révélait deux assets indépendants sans fichier dans la fixture ; ils ont été remplacés par de véritables imports, puis les 36 vues finales ont été rejouées sans requête manquante.

La revue indépendante en lecture seule a cartographié la couverture, relu les jonctions et renforcements du banc ; aucun blocage restant identifié. Elle a demandé le nettoyage dans `finally` et un rapport initialisé à `completed=false`, tous deux intégrés. Le relecteur n’a pas exécuté lui-même les tests/DB. Les tarifs de production n’ont pas été actualisés ni leurs gardes assouplies ; les horloges synthétiques de tests restent distinctes.

R7 demeure ouvert. Prochain point après validation R7.1 : **R7.2**, observation de trois à cinq créateurs ou testeurs autorisés, accompagnés, sans ouverture publique du poste. Ces observations humaines restent à organiser avec le propriétaire ; les tests automatisés ne les remplacent pas. Les essais IA payants et publications réelles gardent leurs portes d’autorisation distinctes. Le retour humain Résonance PC après Ctrl+F5 demeure également non obtenu.
