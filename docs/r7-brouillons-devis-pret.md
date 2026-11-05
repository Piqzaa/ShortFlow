# R7.4 — quatre brouillons et devis prêts à relire

> État historique de préparation. Le propriétaire a ensuite autorisé les seuls huit clips, exécutés le 5 novembre : [résultats réels et limites](r7-essai-reel-resultats.md). Cet accord est consommé ; aucune génération supplémentaire autorisée.

5 novembre 2026. Le propriétaire demande de poursuivre la préparation, puis **confirme que la clé Fal déjà enregistrée dans VydFlow appartient au compte « Alex Berrel »**. Cette confirmation porte sur le rattachement, sans lancement vidéo. **Huit clips proposés, 3,20 USD estimés au total, plafond retenu 4 USD hors taxes/conversion ; aucune vidéo lancée. R7.4 reste ouvert pour l’accord distinct de lancement.**

## Préparations enregistrées

Quatre nouveaux brouillons privés sont enregistrés pour le profil Vyd, utilisateur local 2, par les vrais formulaires Studio (`intent=compose`, CSRF) et relus depuis PostgreSQL :

| Brouillon | Personnages dans chacun des deux plans | Format/durée | Estimation | Relecture locale |
| --- | --- | --- | --- | --- |
| 15 — Milo seul 1 | Milo | 9:16, 2 × 5 s | 0,80 USD | [Ouvrir](http://127.0.0.1:8000/studio/series/episodes/15?review=1) |
| 16 — Milo seul 2 | Milo | 9:16, 2 × 5 s | 0,80 USD | [Ouvrir](http://127.0.0.1:8000/studio/series/episodes/16?review=1) |
| 17 — Milo et Nox 1 | Milo, Nox | 9:16, 2 × 5 s | 0,80 USD | [Ouvrir](http://127.0.0.1:8000/studio/series/episodes/17?review=1) |
| 18 — Milo et Nox 2 | Milo, Nox | 9:16, 2 × 5 s | 0,80 USD | [Ouvrir](http://127.0.0.1:8000/studio/series/episodes/18?review=1) |

Modèle identique sur les huit plans : **Vidu Q1 Référence de personnage**, `fal-ai/vidu/q1/reference-to-video`, connexion API VydFlow. Le tarif public consulté à nouveau le 5 novembre reste **0,40 USD pour cinq secondes**, cohérent avec les quatre devis de l’application. Ce sont des estimations, pas des montants réellement débités. [Page officielle Fal](https://fal.ai/models/fal-ai/vidu/q1/reference-to-video).

Les références proviennent des fiches **Milo 1/révision 1** et **Nox 2/révision 1**. Les copies sont figées dans les recettes. Le cadrage des références est `contain` pour préserver l’image entière ; `mutePlans=true` coupe le son des plans au montage. Aucun test de voix ni réplique française n’est inclus. Les huit prompts finaux effectivement composés par l’application font de **843 à 1 415 caractères**, sous la limite du modèle de 1 500. Leurs textes et les quatre empreintes de devis sont conservés dans le manifeste.

Tous les épisodes sont `draft`, avec `consentHash=null` et aucun `AiGeneration` associé aux plans. La création de brouillons n’est pas une confirmation de coût. Aucun ancien brouillon n’a été modifié : **seuls `ai_episode` +4 et `ai_shot` +8**, tous les autres compteurs des 27 tables inchangés.

## Rattachement du compte

Le rattachement à Alex Berrel est une **confirmation du propriétaire dans le chat**, pas une preuve obtenue auprès d’une API d’identité Fal. La version de la clé enregistrée a été figée par SHA-256 du texte chiffré, calculé en base : ni texte chiffré ni clé en clair retournés, aucune clé déchiffrée. Un remplacement de clé avant l’essai doit entraîner une nouvelle vérification du compte, sans réutiliser silencieusement cette confirmation.

Le compte du connecteur Fal de l’assistant reste distinct du mécanisme de clé API VydFlow. Aucune équivalence technique n’en est déduite et aucun appel de génération via le connecteur n’a contourné les brouillons applicatifs. Solde, permissions réelles et facturation de ce compte n’ont pas été consultés.

## Préparation isolée et contrôles

Un script local ignoré par Git utilise un **kernel de préparation à cache séparé**, avec seulement les services nécessaires rendus accessibles dans ce banc et les cinq clients HTTP remplacés par des clients refusant tout accès réseau. Aucune configuration applicative ni visibilité de service du site n’a été changée. Le premier accès direct par le kernel dev normal a été refusé par la visibilité privée du service de test ; cette protection n’a pas été modifiée dans l’application.

**Écart constaté : le CLI dev résout actuellement `AI_GENERATION_ENABLED=true`, `PORTRAIT_GENERATION_ENABLED=false`.** Les deux valeurs sont forcées à `false` **uniquement dans le processus de préparation**, en plus du blocage HTTP. Aucun fichier d’environnement modifié, aucune activation globale nouvelle. Ce constat ne certifie pas la valeur chargée par le processus web ou par les workers existants ; les anciens repères affirmant une désactivation vidéo globale ne sont pas une mesure actuelle de ces processus.

Déroulement contrôlé :

1. Quatre préparations métier à blanc, puis rollback : les 27 compteurs reviennent exactement à leur valeur initiale. Des numéros de séquence PostgreSQL sont consommés, sans brouillons conservés.
2. Quatre POST natifs avec leurs GET de formulaire et de récapitulatif, sous transaction commune : seuls les quatre épisodes et huit plans attendus sont ajoutés. Zéro tentative HTTP fournisseur/local writer par le banc.
3. Après commit, relecture des quatre épisodes : devis strictement identiques, huit plans sans génération, états non confirmés et compteurs attendus.
4. Le nettoyage de la session native a initialement échoué parce que la sortie CLI était déjà envoyée. **Les brouillons étant déjà enregistrés, aucun nouvel import n’a été rejoué.** La session de banc a été identifiée uniquement par son `TestBrowserToken` et sa dernière préparation 18, puis retirée ; zéro session correspondante restante. Aucun identifiant de session ni contenu privé reproduit dans les preuves versionnées. Le script a été corrigé pour nettoyer avant sortie.
5. Contrôle de charge en lecture seule : file à zéro, aucun épisode actif du profil 2 ; les quatre anciennes générations restent deux `available`, une `prepared`, une `replaced`. Elles ne font pas partie de l’essai à autoriser et ne doivent pas être traitées par une consommation générale.

**24 vues de relecture**, quatre HTML réels : sans JS à 320/390/768/1280, avec JS à 390/1280, huit captures. Aucun débordement, ressource en échec, erreur JS ou image chargée cassée. GET locaux seulement ; aucun formulaire de génération soumis dans cette relecture. Les images différées dans des détails fermés ne sont pas déclarées cassées avant chargement. Les pages de ce banc reflètent son mode désactivé, pas une preuve de désactivation globale du site.

Aucune nouvelle suite PHP/frontend/build ni nouveau lint applicatif, migration ou revue indépendante : aucun code applicatif, formulaire, template, CSS, JS ou schéma n’a changé. Les contrôles ici portent sur le contenu préparé, la persistance et l’absence d’effet de génération. Les **36 PHP / 627 assertions de R7.3c restent historiques**. Aucun worker démarré/redémarré, cache web dev actualisé, upload Fal, email, publication, push ou déploiement.

## Accord encore attendu

La proposition de lancement est désormais concrète : **uniquement les huit plans des brouillons 15–18**, Vidu Q1 Référence de personnage, cinq secondes chacun, portraits/version/cadrage et devis ci-dessus, compte Fal désigné Alex Berrel avec clé applicative attestée par le propriétaire, **3,20 USD estimés et plafond de consentement 4 USD hors taxes/conversion**. Pas de nouvelle image, variante, modèle de secours, recharge de compte ou publication sociale.

Avant un futur traitement autorisé, relire les états/devis/empreinte de clé et la file. Ne pas démarrer un consommateur général qui traiterait l’ancienne génération `prepared` ou d’autres demandes. Toute réponse incertaine reste attachée à son identifiant ; aucune nouvelle soumission ne se déduit d’un échec de récupération. Aucun état `authorized_to_execute` ne passe à `true` avant l’accord distinct de lancement.

Le budget et la confirmation du compte obtenus jusqu’ici ne valent pas cet accord. Cette séparation est expressément prévue par le [cadrage R7.4](r7-essai-reel-cadrage.md) et le repère courant d’AGENTS.md : « recueillir l’accord distinct de lancement des vidéos ». R7.3c/R7.5, R7.2/R7.3 et grand R7 restent ouverts ; aucune qualité vidéo ni observation humaine inventée.

[Manifeste de préparation et devis figés](fixtures/r74-brouillons-devis-20261105.json).
