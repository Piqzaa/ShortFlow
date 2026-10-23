# Vidéos à plans jusqu’à 60 secondes — studio et séries

Parcours actualisé : [préparation commune et essai local](preparation-unifiee-essai-local.md). Le studio et les épisodes partagent maintenant le même formulaire ; modèle et références peuvent être modifiés avant lancement. La rédaction automatique et la récurrence restent à venir.

## Objectif et parcours livré

Une vidéo peut contenir **1 à 8 plans**, avec une durée totale au choix jusqu’à 60 secondes, des scènes manuelles et un assemblage automatique. La durée native de chaque plan dépend du modèle. Les choix fixes 30/45/60 et le minimum de 30 secondes sont retirés.

- **Studio** : décrire le contexte, choisir le modèle et ses références → « Composer une vidéo jusqu’à 60 s » → saisir la durée totale → proposer et choisir un découpage → personnaliser les scènes → estimation totale → confirmation → génération ordonnée et assemblage → validation → préparateur/calendrier existants.
- **Séries** : ouvrir un épisode à préparer **ou à confirmer**, puis choisir le parcours à plans. Une ancienne préparation encore non confirmée est conservée comme « Préparation remplacée » ; son estimation ne peut plus déclencher de demande. Un traitement déjà confirmé/en cours n’est pas converti.

Les propositions comprennent plusieurs répartitions compatibles : par exemple 2 × 15 s et 3 × 10 s pour un modèle proposant ces durées. Un total impossible n’est pas arrondi silencieusement : des totaux proches sont proposés et doivent être choisis explicitement. Les propositions affichent le total réel. La limite de huit sources vient du montage existant ; un modèle limité à cinq secondes par clip ne propose donc pas soixante secondes dans ce parcours. Aucun changement automatique de modèle ou de facturation.

Avant confirmation, on peut ajouter, retirer et réordonner les plans, changer leur durée native et écrire chaque scène : 5 + 15 + 10 s est accepté si le modèle le permet. Le total et son prix indicatif s’actualisent dans le navigateur ; le serveur recalcule et valide tout. Les boutons fonctionnent aussi sans JavaScript, avec enregistrement/rechargement. Au-delà de 60 secondes ou de huit plans, l’enregistrement/lancement est refusé et la saisie reste affichée.

Le contexte commun est ajouté au début de chaque prompt. Les scènes sont initialement vides : aucune rédaction IA simulée. Les confirmations affichent les scènes enregistrées en lecture seule ; une modification demande une nouvelle estimation. Les créations indépendantes restent accessibles depuis le studio et une liste paginée dans l’historique.

## Références et continuité

- **Scènes distinctes** : les modèles texte restent utilisables. Avec un modèle image, les images communes de la recette sont réutilisées selon ses capacités.
- **Action continue** : nécessite un modèle image vers vidéo, une image de départ privée et un modèle sans image de fin obligatoire. Après le premier résultat, FFmpeg extrait une image proche de sa fin ; cette image devient le départ du plan suivant. Les générations attendent donc le résultat précédent.
- Une image de fin facultative de la préparation s’applique au dernier plan. Pour les modèles qui exigent une fin, le mode scènes distinctes utilise la référence finale commune pour chaque plan.
- Le personnage, le décor et le style ne sont jamais garantis parfaitement identiques. L’extraction de la fin du clip aide les raccords ; elle peut aussi transmettre un défaut visuel au plan suivant. Les références de personnages avancées propres à certains fournisseurs ne sont pas ajoutées dans ce lot.

La recette vidéo est copiée lors de la préparation des plans. Une conversion reprend les réglages de la génération non confirmée, sans les remplacer par une nouvelle configuration de série. Le studio enregistre directement le propriétaire et la recette, sans créer de série fictive. Un changement ultérieur de série ne modifie pas les modèles, images, durées ou accès déjà choisis pour cet épisode. Choisir le modèle image et ses références dans la configuration de série avant de commencer un épisode avec raccords.

## Coût, stockage et sécurité

La confirmation affiche le total indicatif en dollars US. Elle ne contacte aucun fournisseur : elle enregistre le consentement et un message local. Les demandes payantes sont ensuite confiées au moteur IA existant, une par une. Les prix doivent correspondre au catalogue et avoir été vérifiés depuis moins de 14 jours ; un changement de tarif bloque les prochains plans. En connexion navigateur Fal, le compte accepté est conservé et revérifié avant les nouveaux plans.

Les clés restent dans `AiKeys`, chiffrées ; aucune copie dans la série, le plan, le consentement ou le navigateur. Les références restent dans le stockage privé existant et les commandes FFmpeg utilisent des arguments séparés, sans shell composé à partir des données utilisateur.

Le contrôle réserve une place et **100 Mo par plan restant**, plus une place et 100 Mo pour la vidéo assemblée. Les imports et les exports tiennent compte des réservations des épisodes actifs/suspendus. L’import d’un résultat consomme sa réservation, sans compter deux fois la même vidéo. Les limites générales restent 1 Go / 100 vidéos par compte ; aucun quota Gratuit/Creator ni paiement ajouté.

Les routes exigent un compte confirmé, vérifient le propriétaire et utilisent des formulaires/jetons CSRF. Les formulaires de scènes utilisent une révision pour éviter d’écraser un autre onglet. Les actions de coût comparent une empreinte du contenu, du modèle, des références, des durées et des prix sous verrou du propriétaire. Les liens directs vers une génération gérée par un plan renvoient vers l’épisode ; leur POST ne peut pas contourner ce parcours.

## Traitement, erreurs et reprises

`AiEpisodeFlow` fait avancer l’épisode sur la file `background`. Il attend la réussite du plan courant avant de préparer le suivant. Il réutilise `AiGenerations` et ses messages pour les demandes et les téléchargements : aucun second adaptateur fournisseur ni moteur générique de workflows.

Une redélivrance de message retrouve le même plan et la même génération. Le statut `submitting` est enregistré avant le POST fournisseur. Une réponse incertaine sans identifiant entraîne une pause, sans renvoi automatique. Si un identifiant distant est connu, la reprise récupère le même résultat. Une clé absente, un tarif modifié, un clip trop court ou une référence inaccessible suspend les étapes suivantes avec une explication.

La pause et la transition avant le POST partagent le verrou du propriétaire : un plan encore en file n’est pas envoyé après une pause. Une demande déjà transmise peut continuer et être facturée. La reprise des étapes restantes demande un accord explicite ; elle conserve le coût accepté et ne recrée pas les plans réussis.

Un arrêt explicite libère les réservations des étapes restantes et interdit leur lancement. Il conserve les résultats et n’annule pas une demande déjà transmise. Un épisode arrêté ne se relance pas automatiquement. Les clips utilisés par un épisode actif/suspendu sont protégés contre une suppression depuis la bibliothèque.

Un plan terminé et encore disponible peut être refait avec **le même prompt**, après nouvelle estimation et confirmation de son coût. Les autres clips et les anciennes générations sont conservés. Pour une continuité avec image de fin compatible, la fin du clip remplacé sert de cible au nouveau clip. Les raccords doivent être revérifiés. Une réparation retire la validation précédente et crée un nouveau montage ; l’ancienne vidéo finale reste dans la bibliothèque. Une demande incertaine n’est jamais transformée en remplacement payant automatique.

## Assemblage et validation

Lorsque tous les plans sont disponibles, `VideoEdits::assembleEpisode()` crée un projet de montage en réutilisant les clips privés dans leur ordre. Les excédents sont coupés aux durées prévues. Un clip manifestement trop court suspend l’épisode ; un déficit de quelques images reste possible, la durée réelle du résultat est affichée. Le format provient de la recette ; pour un format automatique, il est choisi selon l’orientation du premier clip.

Le rendu passe par la file `video_edits` et `VideoEditRenderer` existants. Il attend un autre export actif au lieu de multiplier les rendus. Une erreur de montage suspend l’épisode ; la reprise explicite relance l’export sans régénérer les clips. Le résultat rejoint la bibliothèque privée.

Par défaut, « Couper le son des plans au montage » est décoché : le son disponible dans les clips est conservé. L’utilisateur peut choisir de le couper avant le découpage. Les choix déjà enregistrés restent inchangés. La musique, les textes, les sous-titres et une narration importée peuvent ensuite être ajoutés dans le montage existant en modifiant/exportant une copie. Ni voix IA ni rédaction automatique de scènes ajoutées.

La vidéo assemblée attend une validation. Le créateur peut choisir la version assemblée ou une version montée de sa bibliothèque, puis la valider. Le bouton suivant ouvre le préparateur existant pour choisir réseaux, descriptions, date et fuseau. Ce lot ne crée aucun ordonnanceur social ni publication autonome ; les publications ne sont pas encore rattachées aux épisodes pour un suivi agrégé par réseau.

## Fichiers et responsabilités Symfony

| Composant | Rôle |
| --- | --- |
| `src/Entity/AiEpisode.php` | Conserve le parcours à plans facultatif (épisode ou création privée du studio), sa recette, le consentement, l’état d’assemblage et la version validée. |
| `src/Entity/AiShot.php` | Position, scène, durée, génération actuelle, anciennes tentatives et ancre de fin éventuelle. |
| `src/Form/AiStoryStartType.php`, `AiStoryType.php`, `AiShotType.php` | Champs natifs, validation des durées/textes, CSRF et révisions des formulaires. |
| `src/Controller/AiEpisodeController.php` | Autorisations HTTP, traitement des formulaires, actions explicites et lecture privée des états. |
| `src/Service/AiEpisodeFlow.php` | Découpage, estimation, consentement, génération ordonnée, pauses, arrêt, reprises et assemblage. |
| `src/Message/AdvanceAiEpisode.php`, `src/MessageHandler/AdvanceAiEpisodeHandler.php` | Message asynchrone local et appel au service de parcours. |
| `src/Service/AiFrameExtractor.php` | Extraction locale du raccord, puis validation/réencodage via `AiReferenceStorage`. |
| `src/Service/EpisodeReservations.php` | Comptage commun des futurs clips et exports d’épisodes ; crédits lors des imports de résultats. |
| `AiGenerations.php`, `VideoLibrary.php`, `VideoEdits.php` | Réutilisation des moteurs existants ; contrôle de pause avant le POST, import protégé et assemblage automatique. |
| `AiSeriesController.php`, `AiStudioController.php`, `AiSeriesStudio.php` | Accès depuis les séries, conservation du parcours court et prévention des contournements. |
| `templates/series/episode.html.twig`, `show.html.twig` | Scènes, estimation, suivi, résultat et validation, avec rechargement manuel toujours disponible. |
| `assets/controllers/episode_watch_controller.js`, `assets/styles/episode.css`, `assets/app.js` | Suivi léger des changements serveur, arrêt après erreurs répétées, interface responsive. |
| `migrations/Version20261023115349.php` | Nouvelle table de plans et champs facultatifs de l’épisode, sans supprimer de données. `Version20261023195047` autorise un propriétaire direct sans série et change uniquement le défaut du son pour les nouveaux enregistrements. Retour arrière bloqué si des épisodes longs existent. |

À comprendre en Symfony : le Controller ne génère pas la vidéo pendant la requête HTTP. Le Form valide, Doctrine enregistre le scénario et le consentement dans une transaction, Messenger transporte les étapes longues, et les services métier réutilisent les moteurs IA/FFmpeg. Le suivi lit des états réels ; aucun pourcentage fictif ni appel fournisseur depuis un GET. Les deux workers restent des processus du même monolithe.

## Tester et mettre à jour

1. Lire et appliquer la migration additive sur le poste ; ne pas utiliser `schema:update --force` et ne pas vider les files. Actualiser les caches web et les workers hors traitement actif, puis reconstruire les assets avec `npm run build`.
2. Dans le studio ou une série, choisir une création à préparer ou un épisode à confirmer, saisir par exemple 25/32/37 secondes et remplir les scènes. Enregistrer ne doit créer aucune requête fournisseur. Vérifier le contexte commun et le total avant confirmation.
3. Tester le modèle image avec une référence autorisée pour les raccords. Les plans suivants doivent attendre le précédent ; une pause avant envoi doit empêcher le POST. Les essais payants réels restent à autoriser séparément.
4. Vérifier les résultats simulés de 30 et 60 secondes, l’assemblage unique, une reprise de récupération, une nouvelle génération ciblée et l’invalidation de l’ancienne validation.
5. Vérifier la sélection de la version finale et l’ouverture de la préparation/calendrier existants, sans confirmer de publication réelle pendant une simple recette locale.

Automatisation : `tests/Functional/AiEpisodeFlowTest.php` utilise exclusivement `shortflow_test` après migrations, avec client HTTP simulé et FFmpeg local ; `tests/Frontend/episode_watch.test.js` couvre le suivi et ses erreurs. Résultats exacts dans [verification.md](verification.md).

Limites conservées : rédaction manuelle, identité imparfaite selon le modèle, trois épisodes initiaux par série, jusqu’à huit plans, réglages vidéo figés lors du découpage, pas de mémoire narrative, pas de lancement simultané de plusieurs épisodes, pas de récurrence automatique. La régénération ciblée reprend le même prompt ; la correction d’un prompt après lancement et les références avancées de personnages restent des améliorations distinctes.

## Pédagogie des modifications de durée

- `AiStoryStartType` reçoit un entier de 1 à 60 et un découpage proposé validé côté serveur. `AiStoryType` utilise une collection Symfony autorisant ajouts/suppressions ; les durées restent des choix du catalogue.
- `AiEpisodeFlow::suggestions()` calcule des répartitions représentatives avec une recherche bornée. `save()` valide le total, réutilise les positions existantes et ajuste les lignes uniquement en brouillon. Après confirmation, ces lignes ne peuvent plus être réordonnées.
- `shot_editor_controller.js` améliore la collection dans le navigateur : déplacement des champs, réindexation des noms/labels, total et prix indicatifs, suivi des saisies non enregistrées. Les actions serveur assurent le fallback sans JavaScript.
- `AiEpisode::getOwner()` donne le propriétaire de la série ou celui de la création indépendante. Les contrôles de propriété, les réservations de stockage et le rendu utilisent ce point commun.
- La migration ne modifie pas les choix de son existants et interdit un retour arrière qui ferait perdre les créations du studio.

Tester le point délicat : ouvrir une ancienne estimation dans un second onglet, convertir l’épisode en plans dans le premier, puis tenter l’ancienne confirmation. Elle doit être refusée et ne créer aucune demande fournisseur. Tester aussi 5 + 15 + 10, suppression puis ajout, ordre des prompts, total supérieur à 60 et lecture privée avec un autre utilisateur.
