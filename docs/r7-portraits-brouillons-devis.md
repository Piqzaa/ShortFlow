# R7.3b — premier sous-lot : brouillon privé et devis image

5 novembre 2026. Implémentation locale du premier sous-lot du [contrat image](r7-personnages-idee-contrat.md). **R7.3b reste ouvert** : aucune soumission fournisseur, génération d’image, récupération ou création de fiche depuis un résultat n’est implémentée ici. R7.2/R7.3 restent ouverts et les essais humains sont reportés.

## Objectif et utilisation

Depuis **Studio → Mes personnages → Depuis une idée**, saisir le nom, l’idée, l’apparence et le style. « Enregistrer le brouillon » conserve les textes et une estimation séparée de la vidéo. « Mes idées de portraits » donne accès à la liste privée, paginée par douze. Les six destinations principales du Studio restent inchangées.

Le prompt est construit localement à partir des champs, sans rédaction IA ni traduction. Un prompt complet facultatif remplace ce texte calculé ; son aide indique explicitement que les autres champs ne sont alors pas ajoutés au prompt. Le texte exact est relisible après sauvegarde, y compris lorsque le tarif est périmé. Aucun portrait fictif ou image de remplacement n’est ajouté à la bibliothèque.

Le devis fixe **une image JPEG 1024 × 1024, FLUX.2 Pro chez Fal, accès par clé API**, avec le tarif documentaire du contrat. Il distingue une clé enregistrée d’un compte facturé, de permissions et de crédits réellement vérifiés. La connexion par navigateur n’est pas proposée pour ce parcours.

La page annonce que la génération n’est pas disponible. Il n’y a ni bouton de confirmation payante, ni endpoint de lancement, ni message Messenger de génération. Le repli vers l’import manuel actuel reste accessible.

## Fichiers et responsabilités Symfony

- `AiPortraitDraft` : entité distincte de `AiCharacter`, contenant propriétaire, textes, révision, date et snapshot du devis. Aucune image requise ni fiche de personnage créée par sa sauvegarde.
- `AiPortraitDraftType` : champs natifs, longueurs locales (nom 60, idée 1 000, apparence 2 000, style 300, prompt personnalisé 4 000), révision cachée et CSRF Symfony. Ces limites sont celles du brouillon, pas des capacités fournisseur nouvellement certifiées.
- `AiPortraitDrafts` : accès propriétaire, verrou du compte, rechargement et refus d’une révision obsolète, validation des textes et écriture transactionnelle. Le brouillon reçoit son identifiant avant le calcul du devis ; les deux écritures restent dans la même transaction.
- `AiPortraitQuotes` : prompt local, contrat fixe, tarif daté, durée de validité et empreinte. Aucun client HTTP ni bus de messages injecté.
- `AiPortraitDraftController` : liste, création et édition sous `/studio/personnages/idees`, réponses privées/no-store, rate limiter de sauvegarde et traitement des erreurs. La protection ROLE_VERIFIED existante du Studio s’applique.
- `character/ideas` et `character/idea` : liste, formulaire, erreurs et dernier devis enregistré. Le lien est ajouté à `character/index`, le CSS reste limité aux classes du devis et du prompt.
- `Version20261105010000` : nouvelle table `ai_portrait_draft`, clé étrangère propriétaire avec cascade et index. Aucun ancien champ métier modifié.

Le flux est : formulaire → contrôle propriétaire/CSRF/révision → sauvegarde transactionnelle → devis calculé côté serveur → redirection et relecture. Un coût ou modèle injecté dans le POST est refusé comme champ supplémentaire. Le navigateur ne décide pas des paramètres facturables.

## Validité du devis et conservation

L’empreinte comprend identifiant du brouillon, propriétaire, révision, textes, prompt exact, modèle, paramètres, quantité, tarif, devise, date de vérification et empreinte de la connexion API. Deux brouillons identiques ont donc des empreintes distinctes. Cette empreinte n’est pas un consentement de génération.

Le tarif est daté du 5 novembre. La politique locale refuse un tarif futur et expire à quatorze jours calendaires depuis minuit **Europe/Paris**, fuseau de cette vérification documentaire. Cela évite de traiter le début du 5 novembre en France comme une vérification future parce que la date UTC est encore le 4. La frontière exacte est testée à une seconde près. Cette fenêtre ne garantit pas que le fournisseur conserve son prix ; aucune vérification de prix authentifiée ou permission réelle n’est effectuée pendant la sauvegarde.

Un GET ne réécrit pas le devis. Un changement de clé, de révision ou de données rend son empreinte obsolète. Une clé absente est indiquée, sans bloquer l’enregistrement du texte et de l’estimation. Un tarif expiré conserve le brouillon mais n’émet plus de nouveau devis : une sauvegarde enlève l’ancienne estimation et garde le prompt relisible. La future étape de confirmation devra exiger un devis courant et un consentement image propre ; rien ne peut être lancé dans ce lot.

Après un refus de sauvegarde, les champs soumis restent affichés. Le récapitulatif est alors explicitement intitulé « Dernier devis enregistré » : il ne mélange pas la saisie refusée avec les données sauvegardées.

## Migration locale et validation

Migration appliquée en test, schéma validé, retour arrière puis réapplication vérifiés sur la nouvelle table test vide. Le refus d’un rollback sur table non vide est aussi testé, sans exécuter son DROP. Avant passage local, statut dev vérifié : 40 migrations exécutées, 41 disponibles, seule cette version en attente. Son application en dev et l’actualisation du cache web ont été annoncées explicitement après les tests ; aucun worker redémarré, aucune migration de production.

**15 tests PHP ciblés / 230 assertions**, dont les six scénarios nouveaux de `AiPortraitDraftsTest` et les neuf tests des fiches existantes. Couverture : formulaires natifs, propriétaire et ID zéro, CSRF, révisions obsolètes, champ de coût falsifié, absence de fiche/job/envoi, connexion remplacée/retirée, prompt personnalisé, dates de tarif, identité du brouillon, pagination, échappement et rollback protégé.

La nouvelle classe compare les compteurs des **26 tables publiques** avant/après rollback dans `shortflow_test`, avec cinq clients fournisseur remplacés par des refus et zéro appel constaté. Les neuf tests de fiches sont rejoués avec leurs propres nettoyages d’images synthétiques. Aucune suite PHP globale revendiquée.

**196 frontend/check/build**, **125 Twig**, conteneur/PHPStan et style ciblé verts ; schémas dev/test verts. Le build conserve l’avertissement Three.js existant. Aucun JS nouveau ni dépendance ajoutée ; pas de lint YAML rejoué, configuration inchangée.

Relecture : neuf réponses HTML (dont le refus 422 attendu), avec quatre largeurs sans JS et deux avec JS, soit **54 vues** ; deux stress DOM distincts avec 4 000 caractères sans espace. Aucun débordement, image chargée cassée, erreur JS, requête extérieure ou réponse HTTP inattendue en échec. Clavier sans JS : évitement, champs, accordéon, prompt personnalisé et soumission native ; le POST du banc navigateur est intercepté, la vraie sauvegarde applicative est vérifiée séparément par BrowserKit. Après stabilisation du défilement, le bouton est au-dessus de la navigation basse.

Résultats et empreintes : [manifeste](fixtures/portraits-brouillons-20261105.json). Captures et logs synthétiques sous `artifacts/portrait-drafts` et `output/playwright/portrait-draft-*`, ignorés par Git. Relecture locale du périmètre ; aucune nouvelle revue indépendante ni recette matérielle/lecteur d’écran revendiquée pour ce sous-lot.

Pour rejouer : `docker compose exec -T php php vendor/bin/phpunit tests/Functional/AiPortraitDraftsTest.php tests/Functional/AiCharactersTest.php`. Pour essayer sans coût : ouvrir la bibliothèque locale connectée, choisir « Depuis une idée », sauvegarder et relire le prompt et le statut du devis.

## Suite et limites

Prochain sous-lot R7.3b : confirmation distincte, demande asynchrone avec fournisseur simulé, récupération idempotente, stockage privé borné des résultats non acceptés et validation explicite vers une fiche. Le quota et la maintenance de ces futurs résultats doivent être traités avant toute récupération d’image. Les épisodes et devis vidéo déjà préparés restent intacts.

Aucun coût, appel fournisseur réel, clé réelle consultée, email réel, activation, publication, push ou déploiement. Le générateur de portraits reste non implémenté et aucune qualité de rendu n’est déduite de ce brouillon.
