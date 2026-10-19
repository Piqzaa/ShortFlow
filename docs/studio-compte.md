# Studio et compte personnel — 18 octobre 2026

## Objectif et périmètre

Lot demandé avant les essais TikTok réels : rapprocher le studio de la direction visuelle de la landing et réduire le parcours de préparation. Le compte regroupe les informations personnelles, la photo, les préférences, la sécurité et l’accès aux réseaux. L’abonnement possède un emplacement « À venir », sans paiement, carte bancaire, facture fictive ni nouvelle entité de facturation.

La landing et ses animations sont conservées. Le moteur social, les contrôles de propriété, les confirmations, les limites de format, les verrous, la programmation Messenger et les protections contre les doubles envois sont réutilisés. Aucun nouvel essai social réel n’est effectué pour ce lot.

## Utilisation

1. Ouvrir `/studio` : galerie privée, sélection multiple, import et aperçu des prochains départs. Les brouillons occupent un onglet ; l’ancien tableau de suivi reste accessible par « Activité ».
2. Cliquer sur une vidéo ou sélectionner plusieurs vidéos puis « Préparer la sélection ». Un import depuis ce parcours ouvre directement sa préparation.
3. Sur un même écran, choisir les destinations, modifier leurs textes et réglages, puis choisir maintenant ou une date et son fuseau. Les options moins fréquentes restent dans des sections dépliables.
4. « Enregistrer le brouillon » accepte une préparation incomplète. « Vérifier avant de confirmer » contrôle les champs obligatoires et conduit au récapitulatif. Aucun de ces boutons ne publie.
5. La case et le bouton du récapitulatif déclenchent seuls la publication ou la programmation. Une préparation programmée doit être désarmée avant modification ; ses anciens messages deviennent inopérants.

Le calendrier permet de choisir un jour, ou de saisir une date sur mobile, avant de sélectionner les vidéos. Son fuseau de consultation est conservé dans ce parcours. Le fuseau par défaut du compte s’applique aux nouvelles préparations ; changer cette préférence ne déplace jamais un créneau existant. Une date invalide conserve les textes sauvegardés et demande une correction. Les textes des destinations décochées sont conservés dans le brouillon pour une reprise ultérieure.

Le compte, à `/studio/mon-compte`, contient :

- **Profil** : nom affiché facultatif, photo et retrait de la photo ; email confirmé, date d’inscription et occupation de la bibliothèque en lecture.
- **Préférences** : fuseau horaire par défaut. L’interface reste française.
- **Sécurité** : changement du mot de passe existant, vérification du mot de passe courant et déconnexion après succès.
- **Réseaux connectés** : accès aux connexions sociales existantes.
- **Abonnement** : emplacement explicitement annoncé à venir.

## Fichiers et composants

| Composant | Rôle |
| --- | --- |
| `WorkspaceController`, `studio/workspace.html.twig` | Galerie principale, onglets, pagination propriétaire, prochains départs. |
| `PublicationComposerController`, `PublicationComposerType`, `publication/composer.html.twig` | Écran de préparation commun et état temporaire de session, sauvegarde durable via `PublicationDrafts`. |
| `PublicationDraftController`, vues `draft/` | Confirmation et cycle de vie existants, calendrier avec entrée par date, reprise dans le nouveau parcours. |
| `ProfileController`, `ProfileType`, `ProfilePreferencesType`, `studio/account.html.twig` | Formulaires distincts avec CSRF, onglet actif, validation serveur et messages. |
| `ProfileSettings` | Modification du seul profil concerné, sous verrou et transaction. |
| `ProfileAvatarStorage` | Validation et copie normalisée des images, stockage privé et suppression de l’ancienne photo. |
| `User`, migration `Version20261018164832` | Trois colonnes : nom affiché, fuseau et nom privé de la photo. Valeurs par défaut pour les comptes existants. |
| `studio/shell.html.twig`, `assets/styles/workspace.css` | Navigation commune et styles anthracite/violet, grille adaptative et focus clavier. `studio/base.html.twig` garde le point d’héritage des vues existantes. |
| `composer_controller.js` | Actualisation des destinations par soumission native et affichage de la date ; les contrôles restent utilisables sans JavaScript. |
| `VideoController`, vues bibliothèque/publications | Liens vers le nouveau parcours et retour direct après import. |

## Flux Symfony à comprendre

Une route choisit le contrôleur. Celui-ci charge uniquement les ressources du propriétaire, construit un formulaire Symfony et transmet ses erreurs à Twig. Les contrôleurs ne réalisent pas les transferts sociaux : ils utilisent `PublicationDrafts`, qui conserve les validations et transactions existantes. La préparation en session porte une révision pour refuser une soumission ancienne ; le brouillon en base possède également sa propre révision pour gérer deux onglets ou une modification concurrente.

Après une sauvegarde réussie, une redirection HTTP 303 ouvre une nouvelle requête GET. Cela évite de renvoyer le formulaire en rafraîchissant. Le récapitulatif reste une étape distincte pour l’action effective. Messenger attend le créneau confirmé puis recontrôle les destinations avant l’envoi.

Pour la photo, Symfony vérifie le fichier ; le service contrôle aussi le contenu réel, la taille et les dimensions. FFmpeg crée un JPEG carré de 256 pixels, sans métadonnées. JPG, PNG et WebP sont acceptés jusqu’à 2 Mo et 4 096 pixels par côté. Le fichier aléatoire est stocké dans `var/storage/avatars/{environnement}`, hors de `public`. La route de lecture vérifie que son nom appartient au compte connecté et répond sans cache public. La photo n’est pas transmise aux réseaux sociaux. Les tests utilisent un stockage séparé de celui du développement.

## Ajustements ergonomiques après retour du propriétaire

Connexions est accessible directement dans la navigation principale. La déconnexion reste visible sous forme de bouton à icône, aligné avec le profil sur ordinateur et dans l’en-tête sur mobile. Le lien Mon compte ouvre directement le profil. À terme, Connexions pourra séparer les réseaux sociaux et les fournisseurs IA BYOK ; aucune gestion de clé IA n’est ajoutée dans ce lot. Les boutons des connexions partagent largeur, alignement et hauteur. La barre de sélection présente directement « Supprimer la sélection ». Les brouillons du studio ont un lien de suppression indépendant du lien de reprise ; le formulaire existant conserve confirmation, CSRF, révision et isolation propriétaire, sans supprimer la vidéo source. Après confirmation de suppression, une redirection HTTP 303 revient dans l’onglet Brouillons du studio ; abandonner la suppression y ramène également.

Le calendrier calcule seulement les semaines nécessaires au mois (28, 35 ou 42 cases), replie les filtres et adapte ses lignes à la hauteur de la fenêtre. Les jours vides restent disponibles sur mobile. Un clic dans une case ouvre un `dialog` natif avec des cartes de vidéos privées : aperçu, titre, durée et format. Un bouton radio natif assure la sélection au clavier ; les aperçus chargent leurs métadonnées seulement à l’ouverture et à proximité de la zone visible, puis l’observateur est nettoyé à la fermeture. La date et le fuseau partent vers le compositeur en mode programmé. Aucun envoi n’est armé par cette sélection. Sans JavaScript, le lien de date conserve le parcours via la galerie. `calendar_controller.js` gère ouverture, fermeture et transfert du jour ; les contrôles de publication restent côté serveur. Les cellules chargées peuvent défiler localement et les petites fenêtres restent défilables.

Les compteurs d’une publication publiée sont toujours visibles : le service existant fournit les valeurs datées, ou « — » si elles manquent. Le bouton Instagram passe par `InstagramPublicationController::view`, qui contrôle le propriétaire, le compte et l’état publié, puis demande `id,username,permalink` à Meta. `InstagramApi::permalink` valide l’identité et une URL HTTPS Instagram de publication avant la redirection privée ; aucun lien n’est déduit de l’identifiant numérique. YouTube et TikTok conservent leurs liens vidéo existants.

Le propriétaire a autorisé la collecte Instagram réelle : activation locale de `INSTAGRAM_STATISTICS_ENABLED=1`, premier relevé réussi et lien direct vérifié. La commande `app:instagram:statistics:refresh <id> --now --link` permet un contrôle explicite d’un Reel déjà publié ; elle ne publie rien. Messenger réutilise `PublicationStatistics` pour les prochains relevés. Voir `statistics.md` pour les autorisations et la distinction entre zéro et donnée manquante.

## Tester localement

Après récupération du code : `docker compose exec php composer db:sync`, reconstruire les assets avec `docker compose run --rm node`, puis recharger le navigateur. La migration ajoute trois colonnes et ne supprime aucune donnée.

Les nouveaux tests `PublicationComposerTest` couvrent l’isolation des vidéos/brouillons, le transport du jour et du fuseau, les champs obligatoires, la reprise entre sessions, les textes décochés, les révisions, le CSRF, la correction d’une date et la confirmation finale. `ProfileSettingsTest` couvre le nom échappé, les préférences, un véritable transcodage de photo, son accès privé, son retrait et le rejet d’une fausse image. Toutes les écritures de test restent dans `shortflow_test` ; les clients sociaux sont simulés.

Commandes de vérification :

```sh
docker compose exec -T -e SHELL_VERBOSITY=-1 php php scripts/check.php
docker compose run --rm node sh -c "npm run check && npm run test:ui && npm run build"
```

Pour obtenir le détail d’un échec PHPUnit, lancer directement `docker compose exec -T -e SHELL_VERBOSITY=-1 php php bin/phpunit` après la préparation de la base de test par le script ci-dessus.

Contrôle manuel : tester la sélection et le menu au clavier, les onglets du compte, une photo, les textes par réseau, une programmation et son récapitulatif. Une publication réelle reste une action explicite du propriétaire. Les rendus de contrôle visuel peuvent utiliser des objets de démonstration en mémoire ; ne pas créer de faux comptes sociaux dans la base de développement.


## Recherche dans le calendrier et pied du menu

Objectif : retrouver une vidéo dans toute la bibliothèque sans faire défiler une longue liste, et conserver une déconnexion accessible dans un pied de navigation compact.

`VideoAssetRepository::search` filtre par propriétaire et par nom, sans tenir compte des majuscules. La recherche traite `%` et `_` comme des caractères du nom, pas comme des jokers. Les résultats sont triés par date d’import puis identifiant décroissants, avec 12 vidéos par page. La route GET privée `PublicationDraftController::videos` retourne un fragment Twig et les informations de pagination ; le calendrier charge seulement les 12 vidéos initiales. L’ancienne limite globale de 100 vidéos est retirée.

`templates/draft/_video_choices.html.twig` centralise les cartes et l’état sans résultat. Twig échappe les titres. `calendar_controller.js` attend 250 ms après la saisie, annule la requête précédente, remplace les résultats et ajoute les pages avec « Afficher plus ». La fermeture annule les requêtes encore actives. Le jour, le fuseau et le reste de la page ne changent pas. Un échec propose « Réessayer » ; une recherche vide de résultats désactive la préparation. Les vidéos restent privées et aucun appel social n’est effectué.

`templates/studio/shell.html.twig` conserve la déconnexion en formulaire POST protégé par CSRF. `workspace.css` aligne profil et bouton à icône au bas de la barre latérale ; le nom accessible et l’infobulle restent « Se déconnecter ». Sur mobile, le bouton à icône reste dans l’en-tête, à côté de Mon compte. Sur grand écran, le panneau latéral mesure 220 px ; la passe responsive ci-dessous précise les autres dispositions.

À comprendre en Symfony : une petite route de lecture peut servir des résultats à Stimulus sans recharger la page ; les vérifications de connexion et de propriété restent côté serveur, dans Security et le repository. Le JavaScript améliore l’interaction sans remplacer ces contrôles.

Pour tester : cliquer sur une date, rechercher un nom partiel, vider la recherche, charger une page supplémentaire, sélectionner une vidéo puis vérifier date et fuseau dans la préparation. Vérifier aussi un nom inexistant, Échap et le parcours au clavier. Aucun envoi n’est effectué avant la confirmation du parcours existant.


## Studio sur téléphone et tablette

La navigation et la densité disposent désormais de trois dispositions. À partir de 1 200 px, le menu complet reste affiché. Entre 768 et 1 199 px, une barre latérale de 96 px conserve les quatre destinations avec icône et libellé. Sous 768 px, le menu devient une navigation fixe en bas ; profil et déconnexion restent en haut. Le bas du contenu réserve la place du menu et des zones de sécurité du téléphone. Une fenêtre native de choix vidéo reste au-dessus de cette navigation.

`templates/studio/shell.html.twig` contient les liens réels et les états `aria-current`, ainsi que le formulaire de déconnexion CSRF. `assets/styles/workspace-responsive.css`, importé en dernier dans `assets/app.js`, centralise les dispositions par taille. Les templates de page conservent les mêmes contrôles métier : la navigation responsive ne crée aucune route ni requête de publication.

Sur téléphone, la bibliothèque garde deux colonnes et masque les deux actions de sélection tant qu’aucune vidéo n’est choisie. Le compositeur place l’aperçu compact au-dessus des champs ; date et fuseau occupent chacun leur ligne et la vérification finale reste le bouton principal. Les connexions utilisent des boutons pleine largeur ; les statistiques restent groupées par réseau. Sur téléphone, les rubriques du compte se choisissent dans un menu vertical dépliable, sans défilement horizontal.

Le calendrier conserve ses jours cliquables et ajoute une liste « Au programme » sous la grille sur téléphone. Les titres, états et réseaux y restent lisibles ; la grille garde le repère visuel des dates. Sur les petits écrans, défiler verticalement reste possible et nécessaire pour la liste des publications.

À tester : Studio → choisir une vidéo → préparation → choisir une date ; puis Calendrier → rechercher une vidéo. Vérifier aussi le profil, les connexions, les compteurs, la navigation au clavier et le bas des pages. Les contrôles visuels sont réalisés en navigateur avec des tailles simulées ; ils ne remplacent pas un essai sur appareils iOS/Android réels.


### Changement de réseau sans fausse alerte de départ

Lorsqu’un réseau change, le compositeur actualise le formulaire côté serveur en conservant les champs. La soumission automatique est déclenchée avec `setTimeout(..., 0)` après la propagation complète de l’événement, puis `unsaved#submit` remet le garde de sortie à zéro. Une microtâche était trop précoce : un navigateur peut l’exécuter entre deux écouteurs, avant que le même `change` ne marque à nouveau le formulaire comme modifié. Le timer est annulé à la déconnexion du contrôleur. Quitter réellement une page avec du texte non enregistré déclenche toujours l’avertissement.

Pour TikTok, `TikTokPublishing::readinessIssue` distingue compte absent, autorisation expirée, configuration OAuth et disponibilité du téléchargement des vidéos. `TikTokMediaLink` explique séparément envoi désactivé, URL non vérifiée et HTTPS mal configuré. Le récapitulatif utilise ce diagnostic au lieu de demander une reconnexion lorsque le compte est déjà connecté.


### Valeurs de départ YouTube — 19 octobre 2026

À la demande explicite du propriétaire, les nouvelles préparations YouTube proposent `public` et audience `yes` (« Oui, conçue pour les enfants »). Ces choix sont modifiables et restent visibles dans le récapitulatif avant confirmation. Les contrôleurs `PublicationComposerController`, `MultiNetworkPublicationController` et `PublicationController` initialisent seulement les nouvelles lignes ; ils ne changent aucune donnée de brouillon déjà enregistrée ni les options soumises par l’utilisateur. Une ancienne ligne sans choix reste à compléter, sans valeur ajoutée lors de la validation serveur.

Le formulaire Symfony continue de valider les valeurs disponibles. Le test fonctionnel couvre la préselection, sa modification en privé/non, la sauvegarde, la reprise dans une autre session et la conservation lors du retrait/retour d’un réseau. TikTok conserve sa visibilité vide : [ses règles d’interface](https://developers.tiktok.com/docs/en/content-sharing-guidelines) imposent un choix manuel même si seul `SELF_ONLY` est autorisé. Aucun changement aux droits sociaux ni à la confirmation finale, aucun nouveau transfert lancé.


### Corrections téléphone et brouillons — 19 octobre 2026

Objectif : conserver un calendrier lisible et des brouillons réellement à terminer. Sous 768 px, les cases du mois affichent uniquement le jour et le nombre de publications, sans heure ni barre de défilement interne. La liste « Au programme » conserve les titres, horaires et liens. Le jour reste cliquable pour préparer une vidéo. `calendar.html.twig` produit le compteur et son libellé accessible ; `workspace-responsive.css` adapte seulement la présentation mobile. Sur ordinateur, les entrées détaillées restent dans la grille.

`account.html.twig` utilise un `details/summary` natif pour choisir les rubriques sur téléphone ; les liens sont verticaux, avec des cibles de 48 px. La navigation habituelle reste visible sur tablette et ordinateur. Aucun nouveau contrôleur JavaScript ni formulaire de réglage n’est ajouté.

L’ancienne route `/studio/brouillons` affichait toutes les préparations, y compris celles déjà envoyées. `PublicationDraftController::drafts` redirige désormais vers `/studio?tab=drafts`, qui filtre le statut `draft`. La liste complète possède une adresse explicite `/studio/preparations` ; les liens de suivi et d’alerte utilisent cette route. Une publication confirmée immédiatement passe à `dispatched`, une programmation confirmée à `scheduled` : elles ne sont plus des brouillons. Une préparation sauvegardée ou abandonnée avant confirmation reste un brouillon, même si la vidéo source a déjà servi à un autre envoi. Aucun effacement des données existantes.

Flux Symfony : le routeur choisit la liste ; le contrôleur et le repository conservent la vérification du propriétaire ; Twig rend les mêmes données avec une présentation adaptée à la largeur. L’historique métier reste nécessaire au suivi et à l’idempotence : on filtre l’affichage au lieu de supprimer les préparations après envoi.

Pour tester : ouvrir un mois avec publications sur téléphone, vérifier le nombre dans une case et ouvrir sa date ; ouvrir « Mon compte », déplier les rubriques au toucher et au clavier. Enregistrer une préparation puis la confirmer avec les API de test : elle doit quitter Brouillons et rester dans Préparations/Publications. Le test fonctionnel de régression vérifie cette séparation sans envoi réel.


## Vidéos et Studio : deux usages distincts — 19 octobre 2026

Objectif : rendre le produit cohérent avec sa chaîne import/génération → montage facultatif → préparation → publication, puis séries récurrentes. `/studio` reste la bibliothèque et porte désormais le nom **Vidéos**. Le **Studio** est directement accessible dans la navigation via `/studio/creation`. Les cinq destinations disponibles restent accessibles en permanence sur téléphone : Vidéos, Studio, Calendrier, Publications, Connexions. Les séries sont annoncées « À venir » dans la barre latérale, sans fausse action ; elles ne prennent pas un sixième emplacement mobile.

Le Studio contient aujourd’hui la création IA. Le montage guidé possède un emplacement explicitement annoncé à venir et une explication ; aucune transformation ni série automatique n’est ajoutée dans ce lot. La séparation évite d’assimiler l’ensemble du Studio au seul choix des modèles. Une fois disponible, le montage doit accueillir aussi bien les vidéos importées que générées.

### Fichiers, responsabilités et flux

`templates/studio/shell.html.twig` partage une liste de liens entre ordinateur et mobile et calcule séparément les états actifs Vidéos/Studio/Connexions. La route historique `/studio/ia` reste utilisable ; `AiStudioController::index` reçoit aussi la nouvelle route `/studio/creation` pour réutiliser exactement le même formulaire, la sauvegarde et le moteur IA. Le retour après configuration d’une clé et les liens depuis le récapitulatif utilisent la nouvelle adresse. `AiPrivacyListener` inclut cette adresse pour maintenir la désactivation du profiler sur les idées privées.

`templates/ai/studio.html.twig` place l’idée avant les réglages, replie le nom et la comparaison des modèles dans des `details` natifs, et montre la dernière création ou un état vide explicite. Si une vidéo existe, son aperçu privé est contrôlable sans lecture automatique et un lien mène au compositeur existant. Le résultat affiché est identifié comme la dernière création, pour ne pas le confondre avec un nouveau prompt en cours de saisie. `ai-studio.css` et `workspace-responsive.css` assurent les dispositions, les cinq liens tactiles et l’alignement des contrôles.

`AiIdeaType` rend le nom facultatif et refuse un prompt constitué uniquement d’espaces. Si le nom manque, le contrôleur dérive un nom court du prompt, sans changer l’idée originale ni les créations existantes. Les paramètres, le coût et la confirmation restent contrôlés côté serveur.

`creation_controller.js` actualise uniquement l’estimation indicative et la description audio à partir du modèle et de la durée. Il ne soumet aucun formulaire et n’effectue aucune requête fournisseur. Sans JavaScript, le formulaire et le coût serveur du récapitulatif restent disponibles ; un texte de remplacement le précise. Le garde d’édition `unsaved` existant reste attaché au formulaire. Aucun nouveau stockage, transport ou fournisseur ajouté, aucune activation de génération payante.

À comprendre en Symfony : deux noms de routes peuvent mener à la même action pour conserver les anciens liens pendant une évolution du vocabulaire. Le template indique la section active ; Stimulus améliore le retour immédiat, tandis que la validation et le calcul faisant foi restent dans le formulaire et les services PHP existants.

Pour tester : Vidéos → Studio dans les deux navigations, changer modèle/durée et vérifier l’estimation, préparer une idée sans nom puis vérifier le nom proposé et le coût au récapitulatif. Ouvrir les réglages et comparaisons au clavier, revenir après configuration de clé, ouvrir aussi l’ancien lien `/studio/ia`. Vérifier les états sans création, création en attente et vidéo disponible, puis le lien de préparation de publication. Montage et Séries doivent rester clairement indisponibles.
