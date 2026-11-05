# R7.3b — confirmation, suivi privé et acceptation du portrait

5 novembre 2026. Deuxième sous-lot technique local, après les [brouillons et devis](r7-portraits-brouillons-devis.md). Le parcours est implémenté et vérifié avec Fal simulé. **La génération reste désactivée en dev ; aucun worker portrait démarré, aucun rendu IA réel ni coût. R7.3b reste ouvert pour sa recette finale avec réutilisation dans une scène.** R7.2 et grand R7 restent ouverts, les essais humains sont reportés.

## Parcours livré

Dans Mes personnages → Depuis une idée, enregistrer conserve toujours un brouillon sans lancer d’image. Avec la génération activée dans le seul processus du banc de test, le devis courant propose une confirmation distincte : une case explicite rappelle le coût image et la facturation Fal. Le POST exige propriétaire, CSRF, consentement, empreinte du devis, connexion courante et tarif encore valable. Aucun consentement vidéo n’est déduit de cette action.

La demande conserve ses propres textes, prompt exact, prix daté et version de clé. Modifier ensuite le brouillon ne change pas ce qui a été confirmé. La page de suivi privée permet une actualisation manuelle sans JavaScript. Le résultat prêt ne crée aucune fiche automatiquement : accepter crée une **nouvelle** fiche privée ; écarter ne crée rien et ne relance aucune image. Accepter deux fois renvoie la même fiche. Aucune fiche, série, scène ou ancienne référence existante n’est remplacée.

## Symfony, Doctrine et Messenger

`AiPortraitRequest` sépare le devis confirmé du brouillon et de `AiCharacter`. La contrainte unique `(draft_id, quote_hash)` et le verrou propriétaire rendent les confirmations répétées idempotentes. Une seule demande active par propriétaire est admise, avec douze portraits prêts à relire au maximum. Une demande incertaine compte encore comme active.

`AiPortraits` enregistre demande et message Doctrine dans la même transaction. Le traitement prend un verrou propriétaire, rafraîchit la demande et enregistre son état/jeton de traitement **avant** tout POST fournisseur. Les accès réseau se font hors de ce verrou ; seul le détenteur du jeton peut appliquer sa réponse. La clé est lue sous le verrou qui contrôle son empreinte, pour qu’un remplacement concurrent ne change pas le compte utilisé entre le contrôle et l’envoi.

La file dédiée `portrait_generation` a zéro tentative automatique Messenger et un délai de remise en circulation de 600 secondes. Le suivi connu est reprogrammé après dix secondes. Le handler est implémenté, **mais aucun consommateur de cette nouvelle file n’a été lancé ou redémarré**. Sa mise en exploitation appartient au travail d’exploitation ultérieur ; ne pas déduire une activation de la présence du code.

`PORTRAIT_GENERATION_ENABLED` a une valeur par défaut `0`, indépendante du drapeau vidéo. Le service métier et l’adaptateur HTTP la contrôlent. Le banc active uniquement son processus de test puis restaure sa valeur. La vérification du conteneur dev après actualisation du cache retourne `false` ; aucun fichier d’environnement local n’a été modifié.

## Pannes et reprise

Avant un premier envoi, changement/retrait de clé, expiration, ancienne date de tarif, ancien modèle ou ancien coût bloquent la demande sans POST. Une ancienne demande ne profite donc pas de la fraîcheur d’une future version du catalogue. Il faut relire un nouveau devis et confirmer explicitement une nouvelle demande.

Un envoi interrompu sans reçu exploitable passe en vérification, sans remise automatique en état « à envoyer ». Après interruption d’un traitement déjà revendiqué, une livraison ultérieure constate son ancienneté et le suspend aussi. L’utilisateur doit vérifier l’envoi incertain chez Fal avec le support ; ce lot ne fournit aucun bouton qui abandonnerait cette incertitude pour autoriser une nouvelle dépense.

Avec un reçu connu, une panne de suivi, de téléchargement, de normalisation ou un résultat refusé conserve ce reçu. Une reprise explicite consulte **cette même demande**, sans POST de génération. Elle peut utiliser la clé API actuelle après confirmation dédiée du propriétaire ; le devis et sa connexion d’origine restent figés. Le prix périmé empêche un nouvel envoi, pas la récupération d’un résultat déjà demandé.

Les messages d’erreur sont génériques et ne reprennent ni exception HTTP brute, clé ni URL fournisseur. Les métadonnées de diagnostic bornées restent côté serveur, jamais injectées dans le HTML.

## Fichiers et accès privés

`AiPortraitApi` applique le [contrat image documenté](r7-personnages-idee-contrat.md) : entrée figée, reçu limité à la queue Fal/application/identifiant attendus et utilisation des URL validées reçues, JSON plafonné à 64 Kio, exactement une image et une graine entière. Les résultats multiples ou inattendus sont conservés pour diagnostic sans choisir silencieusement un portrait.

Téléchargement HTTPS limité aux hôtes Fal Media autorisés ou au préfixe Google Storage prévu, sans redirection et **sans clé dans le GET média**, temporaire plafonné à 10 Mio. Les pixels réels doivent faire 1024 × 1024 ; le fichier passe ensuite les contrôles/normalisation JPEG existants. Son poids normalisé reste limité à 2 Mio et compte dans le quota partagé de 100 Mio. Le contrôle de capacité est commun aux imports et aux portraits, avant confirmation, avant envoi et lors du stockage.

L’acceptation réutilise le fichier déjà validé sans seconde copie. L’inventaire de maintenance protège les résultats prêts et acceptés, en plus des références historiques. Écarter retire la référence de la demande sans suppression immédiate : le nettoyage ne retire que les fichiers non référencés et âgés d’au moins trente jours. Une erreur d’enregistrement ne nettoie que le fichier créé par l’opération en cours.

Les routes de suivi, image et action contrôlent le propriétaire et ne reçoivent jamais de nom de fichier libre. Réponses `private, no-store`, JPEG `nosniff`. Le réglage public par défaut de `BinaryFileResponse` est désactivé ici et sur les images des fiches existantes. Le profiler est désactivé pour tout `/studio/personnages`, comme pour les autres parcours IA privés.

## Recette et limites

**29 tests PHP ciblés / 376 assertions**, 196 frontend/check/build, 126 Twig, 19 YAML, conteneur/PHPStan/style et schémas dev/test verts. Dix rapports transactionnels : 27 compteurs égaux après chaque rollback, 29 échanges HTTP du parcours simulés dont cinq POST. Les quatre tests unitaires de l’adaptateur bornent séparément les accès réseau ; leurs réponses sont aussi simulées. Aucun nouveau passage PHP global.

Le manifeste [portraits-suivi-20261105.json](fixtures/portraits-suivi-20261105.json) référence les résultats et empreintes. Les tests utilisent des utilisateurs, clés et réponses synthétiques, une image violette produite localement et la base `shortflow_test`. Aucune clé réelle n’a été consultée ou déchiffrée par le banc.

Couverture : confirmations natives répétées, messages rejoués, prompt figé malgré modification du brouillon, attente fournisseur, acceptation répétée, envoi ambigu, traitement interrompu, reprise sur reçu connu après changement de clé et expiration, résultats multiples/hôte refusé/image invalide, téléchargement interrompu/surdimensionné/redirigé, quota plein, maintenance, propriétaire/CSRF/consentement/état incorrect et refus de rollback non vide. Les transactions de test vérifient les compteurs de toutes les tables après rollback. Les séquences PostgreSQL consommées ne sont pas remises à zéro.

Les doublons sont exercés par deux requêtes HTTP successives et une livraison réentrante pendant l’envoi simulé. Les tests sont enveloppés dans une transaction externe de rollback : **ce n’est pas un essai de crash de processus ni de concurrence entre deux workers réels**. Le résultat ne certifie ni une facturation fournisseur, des permissions/crédits, un compte Fal réel ni la qualité du portrait.

Relecture des sept HTML réellement produits : 42 contextes, sans JS à 320/390/768/1280 et avec JS à 390/1280. Aucun débordement, image cassée, erreur JS ou réponse HTTP en échec ; six captures. Trois confirmations natives au clavier sans JS à 390 px, cases cochées et boutons visibles au-dessus du menu après stabilisation du défilement. Leurs POST sont **interceptés dans ce banc de relecture**, distinct des véritables mutations BrowserKit en base test.

Migration additive `Version20261105020000`, seule en attente en dev : appliquée en test, down/up sur table vide, refus non vide testé, puis application locale annoncée et effectuée en dev. Schémas dev/test synchronisés. Cache web dev actualisé, aucun worker redémarré. Aucune suite PHP globale, revue indépendante nouvelle ou recette humaine/matérielle revendiquée.

## Suite exacte

Finir R7.3b par une recette idée → demande simulée → portrait accepté → sélection dans une scène → devis vidéo avec référence figée ; relire l’ensemble des protections et consigner les limites avant clôture technique. R7.3c et les validations audiovisuelles réelles restent distinctes. Les tests humains R7.2 restent à organiser quand le propriétaire sera disponible. Aucun push, activation, génération réelle, email, publication ou déploiement.

Suite du 5 novembre : [recette finale et bilan R7.3b](r7-portraits-recette-finale.md), terminé sur le périmètre technique local simulé. Génération réelle désactivée, essais humains et qualité audiovisuelle distincts ; prochain point technique R7.3c.
