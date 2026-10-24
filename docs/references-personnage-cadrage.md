# Référence de personnage, premières images et cadrage

Lot local du 24 octobre 2026. Aucune génération payante, aucun envoi de photo au fournisseur et aucune modification des séries du propriétaire pendant les vérifications.

## Objectif et fonctionnement

« Accepte une image » ne signifie pas « utilise une référence de personnage ». Les entrées image-vers-vidéo existantes utilisent principalement une première image, éventuellement une dernière. Avec plusieurs scènes indépendantes, réutiliser ces images communes demande donc le même début et la même fin pour chaque clip. En mode prolongement, le début du plan suivant est extrait du précédent.

Le choix « Garder un personnage de référence » est distinct. Le premier endpoint intégré est `fal-ai/vidu/q1/reference-to-video`, sous l’identifiant durable `vidu-q1-subject`. Son champ `reference_image_urls` reçoit l’image entière du personnage. Il ne reçoit aucun champ de première ou dernière image, et conserve le format de sortie choisi. Une image commune peut guider l’apparence dans plusieurs scènes sans en figer les cadrages. La fidélité reste une capacité du modèle, pas une garantie du logiciel. Les raccords par extraction de dernière image sont refusés pour cet usage afin de ne pas remplacer la référence d’identité.

Vérification en lecture seule le 24 octobre : schéma officiel Fal via le plugin, et [fiche du modèle](https://fal.ai/models/fal-ai/vidu/q1/reference-to-video). Clip fixe de 5 secondes, formats 9:16/16:9/1:1, prompt limité à 1 500 caractères. La fiche affiche 0,40 $ par clip de 5 secondes ; l’outil de prix renvoyait une unité « credits » à 0,05 $, sans conversion exploitable. L’estimation locale reprend donc le prix explicite de la fiche, avec vérification proposée avant confirmation. Aucun tarif d’un modèle existant n’est changé et aucun modèle n’est substitué automatiquement.

La séparation texte/image est conservée ; les modèles image sont désormais distingués selon le rôle de l’image. Dans la préparation commune Studio/Séries, le libellé et l’aperçu expliquent le rôle du modèle sélectionné.

## Retrait et aperçu

Chaque image affiche un aperçu local. Changer le format ou le réglage recadrage/bandes met à jour les premières/dernières images. Les proportions, le centrage et les bandes noires correspondent au traitement existant de `AiReferenceStorage`. Pour une référence de personnage, l’image reste entière : aucun cadrage de premier plan n’est imposé.

« Retirer l’image » vide le fichier sélectionné, masque l’aperçu et demande le retrait de la configuration au prochain enregistrement. Sans JavaScript, une case fournit la même demande. Les images des préparations déjà figées restent disponibles ; retirer une référence de configuration n’efface pas un fichier encore utilisé par une vidéo préparée. Une configuration existante peut enregistrer ce retrait, mais les nouvelles préparations et la récurrence sont bloquées s’il manque une image requise. Une création de série image sans référence reste refusée.

## Composants Symfony et navigateur

- `AiCatalog` déclare la capacité `subjectReference`, le prix et les contraintes. `validateReferences` empêche les préparations incomplètes.
- `FalVideoApi` construit `reference_image_urls` pour le personnage, sans recadrage ni champs de début/fin. L’accès API et l’accès MCP réutilisent ce même constructeur.
- `AiSeriesType` ajoute le choix du rôle et les demandes de retrait. `AiSeriesStudio` applique ces demandes sous verrou et contrôle de révision, sans modifier les préparations existantes.
- `AiEpisodeFlow` et `SeriesRecurrences` conservent les contrôles avant préparation et refusent de confondre référence d’identité et raccord de cadres.
- `series_settings_controller.js` filtre explicitement les capacités. `series_reference_controller.js` gère aperçus, retrait, changements de cadrage et libération des URL locales ; `series/_reference.html.twig` fournit les champs et leur repli sans JavaScript.
- Le contrôleur commun `creation_controller.js` et le récapitulatif affichent le rôle correct pour une référence de personnage.

Flux : choix explicite du rôle/modèle → sélection ou conservation de l’image → aperçu/retrait local → formulaire Symfony avec CSRF et validation → recette privée enregistrée → préparation figée → estimation et consentement existants → construction de la requête fournisseur. Ni la sélection d’un fichier ni sa sauvegarde ne génèrent de vidéo.

À comprendre en Symfony : le formulaire transporte une intention de retrait ; le service décide de son effet sur la recette. Stimulus n’est responsable que de l’aperçu. Le rôle effectif provient du modèle validé côté serveur, jamais d’un simple libellé JavaScript. Aucun changement de schéma Doctrine n’est nécessaire.

## Vérifier manuellement sans dépense

Contrôles exécutés : 133 tests PHP / 2 317 assertions réussis sur les séries, le moteur des épisodes, la récurrence et les contrats image ; après le dernier ajustement du retrait, quatre tests ciblés ont été rejoués avec 45 assertions réussies. Les 112 tests frontend, la vérification syntaxique JS, le build Vite, PHPStan, CS-Fixer, le lint du conteneur test et les 88 templates Twig passent. Les appels fournisseur sont simulés. Un premier échec de rendu a révélé un cache Twig test périmé avec `APP_DEBUG=0` ; il a été renouvelé avant la vérification complète réussie.

Le navigateur a vérifié une configuration synthétique, avec une image unie de test : changements de cadrage, retrait de fin, rôle personnage sans fin, console sans erreur et absence de débordement horizontal à 390 px. Les captures ignorées sont dans `artifacts/series-framing-preview.png` et `artifacts/series-character-mobile.png`. Aucun formulaire de configuration réel du propriétaire n’a été soumis.

1. Recharger la configuration puis sélectionner « Garder un personnage de référence » et le modèle dédié. Vérifier qu’aucune image de fin ni option de cadrage de premier cadre n’est présentée.
2. Importer une image de personnage : vérifier son aperçu entier. La retirer puis vérifier la disparition de l’aperçu. Enregistrer seulement les changements désirés.
3. Avec un modèle image de début, comparer 9:16 et 16:9, recadrage et bandes, puis retirer l’image de fin. Les vidéos déjà préparées restent inchangées.
4. Vérifier le récapitulatif et le coût avant tout essai réel ; aucun essai vidéo supplémentaire n’est inclus dans ce lot.
