# R5.3 — clavier et contrastes contrôlés, recette système à compléter

2 novembre 2026. **R5.3 reste ouvert pour l’écoute ciblée du lecteur d’écran et le changement réel de préférence système.** Les parcours clavier bornés décrits ci-dessous, les dialogues et les contrastes sur fonds unis ont pu être contrôlés après ouverture de l’export par le propriétaire. Le zoom natif Chrome à 200 % est confirmé sur le préparateur, sans matrice exhaustive de zoom. R5.2 reste ouvert pour téléphone réel. R5-D1/D3 et les limites globales restent à traiter en R5.5.

Reprise complémentaire le même jour : serveur d’exports opérationnel sur le PC, réponse GET 200, et contrôle structurel des 18 exports réussi. La navigation Chrome a de nouveau été arrêtée par Computer Use ; aucune nouvelle recette interactive n’est donc validée. Aucun changement applicatif ou nouvelle suite complète lors de cette reprise.

**Passage suivant, après « prêt » du propriétaire : blocage de navigation levé.** Douze parcours desktop et cinq mobiles jusqu’au bord du navigateur, préparation avec contrôles vidéo natifs examinée séparément, dialogues calendrier/export/projet et menu mobile vérifiés. Un lien de profil compact sans nom accessible a été corrigé. **22 PHP ciblés / 285 assertions**, 115 Twig et style verts ; 18 exports régénérés avec rollback. Le Narrateur a démarré avec retard, mais aucune écoute probante des champs/erreurs/états n’est obtenue. Les sections de premier passage ci-dessous restent des preuves historiques, complétées par le bilan interactif final.

## Défauts corrigés

| Gravité | Constat | Correction |
| --- | --- | --- |
| P2 | Le lien « Aller au contenu » défile mais laisse `document.activeElement` sur `BODY`, reproduit sur la connexion. | Les cinq gabarits de contenu possèdent `tabindex="-1"` sur `main#contenu`. Après correction, Entrée sur le lien donne `MAIN#contenu`, vérifié dans le navigateur intégré. Le contenu n’ajoute pas d’arrêt dans la tabulation ordinaire. |
| P2 | L’ouverture des panneaux invalides était assurée par le contrôleur de cartes de scènes, sans couverture commune des autres formulaires. | Un contrôleur commun ouvre les ancêtres `details` des erreurs serveur et des champs invalides avant le focus natif. Il n’enlève jamais les attributs `hidden`, les désactivations ou les gardes métier. |
| P2 | Le thème Symfony associe les erreurs dans `form_row`, mais certains champs composés manuellement utilisent directement `form_widget`. Le choix guidé cache aussi le sélecteur natif. | Le thème commun associe les erreurs aux widgets directs en conservant leurs descriptions existantes, sans identifiant dupliqué. Les sélecteurs visibles modèle/résolution reprennent les mêmes identifiants d’erreur. |
| P2 | Les champs de connexion ne décrivaient pas leur message d’échec commun. | Les deux champs référencent `login-error` et sont marqués invalides uniquement après refus. Le message reste neutre. |
| P2 | La règle de lecture du montage supprimait explicitement le contour des superpositions, même focalisées. | La suppression ne vise plus `:focus-visible` ; le contour existant reste disponible au clavier pendant la lecture. Recette visuelle du montage encore requise. |
| P3 | La règle commune de focus ne couvrait pas explicitement listes, zones de texte et résumés natifs. | Contour commun de 2 px sur `select`, `textarea`, `summary` et destinations de focus programmatiques. |
| P2 | À largeur compacte desktop, le texte du profil est masqué et son avatar est décoratif : le lien de compte perd son nom accessible, constaté dans Chrome à 200 %. | `aria-label` explicite « Mon compte · nom affiché ou email », échappé par Twig. Vérifié à 900 px : texte du profil `display:none`, nom toujours présent. Test fonctionnel avec un nom contenant `<création>` pour conserver l’échappement. |

Les listes d’erreurs portent désormais `role="alert"`. Cela expose leur sémantique ; cela ne prouve pas leur annonce effective dans chaque lecteur d’écran. Les régions de progression existantes ne sont pas réécrites : génération (détail poli/atomique), rédacteurs et montage (`status`), calendrier (`status`). Les noms, durées, devis, consentements, données enregistrées et protections serveur restent conservés.

## Vérifications et limites

| Axe R5.3 | Preuve obtenue | Ce qui reste à vérifier |
| --- | --- | --- |
| Clavier et focus | Avant/après du lien d’évitement ; douze parcours desktop et cinq mobiles bornés, dialogues et menu mobile, noms et contours de focus. | Autres états, détails non ouverts et contrôles internes du lecteur vidéo natif ; aucune conformité exhaustive déduite de ces passages. |
| Erreurs et libellés | Réponse réelle d’inscription 422, association champ/identifiant ; widget direct avec aide conservée ; sélecteurs modèle/résolution associés à leur erreur. | Lecture et correction des erreurs en situation, y compris sans JS (R5.4). |
| Annonces d’état | Revue des rôles/régions existants et ajout de la sémantique d’erreur commune. | Écoute des erreurs, changements de progression et annonces répétées avec un lecteur d’écran réel. L’arbre accessible seul ne vaut pas cette recette. |
| Contraste | Cinq couples centraux puis **499 éléments textuels rendus sur fonds unis**, onze vues, minimum 6,89:1 ; zéro résultat sous le seuil applicable dans ce périmètre. | Trois éléments à fond complexe exclus, images/dégradés, opacité de groupe, placeholders et autres états. Aucun certificat de conformité globale. |
| Zoom navigateur 200 % | Valeur 200 % explicitement indiquée par Chrome natif et captures de son affichage inspectées sur le préparateur ; observation en fenêtre étroite et agrandie, zoom remis à 100 %. | Parcours complet des autres écrans et états au zoom ; le viewport mobile reste une vérification distincte. |
| Mouvement système | Revue des media queries et des abonnements `matchMedia` du contrôleur landing. | Changement réel de préférence Windows, avant/après chargement, arrêt des animations et absence de contenu inaccessible. Les essais R5.1 restent historiques. |

Lors du premier passage, le navigateur intégré a permis de reproduire puis vérifier le lien d’évitement sur `http://127.0.0.1:8000/connexion`. L’ouverture de Chrome natif a ensuite été arrêtée par Computer Use, incapable de déterminer l’URL avec une confiance suffisante. Aucun contournement. Les observations interactives obtenues après ouverture manuelle de l’export figurent plus bas ; les images ont été inspectées dans l’outil, sans nouvel ensemble de captures archivé.

## Contrôles automatisés

- `npm run check` : réussi, avec le nouveau contrôleur explicitement inclus.
- `npm run test:ui` : **184 tests réussis**.
- `npm run build` : réussi après le dernier correctif CSS. Avertissements existants de taille du chunk Three.js et de ressources médias/polices résolues au runtime.
- `php scripts/check.php`, copie Linux isolée : schémas dev/test, YAML, **115 Twig**, conteneur, PHPStan et style réussis ; PHPUnit **815 tests / 8 975 assertions, 35 erreurs et 2 échecs**. Cette suite inclut le premier test d’accessibilité, pas son deuxième cas ajouté ensuite.
- Les 35 erreurs refusent un tarif trop ancien ; les deux échecs attendent un passage en file ou un diagnostic de capacité, désormais précédés par le même refus de tarif. `AiCatalog::CHECKED` vaut `2026-10-19` et la garde utilise une limite de quatorze jours. Aucun tarif ni garde n’est assoupli pour faire passer les tests.
- Comparaison sur le commit de départ `54423f8`, copie isolée distincte, sans suites DB concurrentes : **814 tests / 8 953 assertions, les mêmes 35 erreurs et 2 échecs**. Les 37 noms de cas en défaut sont identiques. L’écart d’assertions correspond au premier nouveau test, qui passe ; cela n’autorise pas à annoncer une suite verte.
- Rejeu final `AccessibilityTest` et `AccountTest` : **15 tests / 161 assertions réussis**, incluant les deux nouveaux tests, les widgets directs et les sélecteurs guidés. Style final réussi. Les scripts de recette passent `php -l`.
- Un premier lancement a été interrompu : le lien symbolique vers `vendor` ramenait aux sources montées Windows via l’autoload Composer. Dépendances copiées dans le dossier isolé, puis relance complète ci-dessus. Journal initial conservé séparément.

Les résultats finaux ciblés, la comparaison au commit de départ et les exports figurent dans [le manifeste de preuves](fixtures/r5-accessibilite-20261102.json). Ne pas présenter les **814 / 9 471** du 1er novembre comme des contrôles encore verts aujourd’hui.

Journaux locaux ignorés par Git : `artifacts/r53-full.log`, `r53-baseline.log`, `r53-targeted.log`, `r53-check.log`, `r53-build.log` et `r53-export.log`. Cache dev actualisé pour charger le thème Twig ; aucun worker redémarré.

## Recette synthétique réutilisable

`scripts/export-accessibility-fixtures.php` utilise exclusivement `shortflow_test`, refuse les cinq clients HTTP fournisseurs/rédacteur, exporte les réponses et annule les écritures dans `finally`. Onze compteurs métier/file sont comparés avant/après. Les séquences de la base test peuvent être consommées ; ni le rollback ni ces compteurs ne garantissent leur conservation. L’inscription invalide est le seul POST exporté ; aucune demande de génération, de publication ou de rédaction n’est soumise. Les états de génération/montage et les médias sont synthétiques.

`scripts/accessibility-router.php` sert ces exports et les assets publics sur loopback. Tous les POST sont refusés avec 405. Les vues sont disponibles par `/qa/<nom>` et les chemins du manifeste ; `?nojs` retire les scripts des exports. Ne pas utiliser ce serveur comme une application authentifiée ou une preuve de soumission réelle. Le serveur n’est pas démarré automatiquement.

Résultat : **18 exports, zéro HTTP fournisseur/rédacteur, onze compteurs concordants après rollback**, empreintes des 18 fichiers vérifiées. Premier export interrompu sur une fixture propriétaire détachée après les requêtes KernelBrowser ; rollback exécuté, propriétaire rechargé avant les écritures suivantes, puis export rejoué avec succès. Contrôle HTTP local du routeur : GET 200, POST 405, aucun script avec `?nojs` ; serveur temporaire arrêté dans `finally`. Ce contrôle HTTP n’est pas une recette navigateur.

Après la fin de toute autre suite utilisant la base test :

```text
APP_ENV=test php scripts/export-accessibility-fixtures.php
php -S 127.0.0.1:8021 -t public scripts/accessibility-router.php
```

Les tests PHP doivent tourner dans `edit-worker` (moteur de transcription disponible), de préférence avec une copie locale Linux de **sources et dépendances**, comme pour les lots précédents. Aucun redémarrage de worker ou activation réelle n’est nécessaire à cette recette.

## Comprendre le changement

1. **HTML/Twig** : `tabindex="-1"` rend la destination d’ancre focalisable ; les libellés restent en HTML. Le thème `templates/form/accessibility.html.twig` hérite du thème Symfony : il conserve son rendu et enrichit les erreurs plutôt que la validation.
2. **Configuration Symfony** : `config/packages/twig.yaml` déclare le thème commun, aussi utilisé par les formulaires rendus à la main. Un nouveau cache de conteneur est nécessaire pour prendre en compte cette configuration.
3. **Stimulus** : `assets/controllers/accessibility_controller.js`, enregistré dans `assets/app.js` et attaché au body, écoute `invalid` en capture, car cet événement ne remonte pas normalement. Il ouvre les panneaux avant que le navigateur tente de focaliser un champ. À la déconnexion, l’écouteur est retiré.
4. **CSS** : les styles rendent le focus visible, sans modifier la validation ou les routes. La règle du montage conserve le focus pendant la lecture.
5. **Flux** : Symfony produit une réponse avec erreurs et identifiants ; Twig les associe aux champs ; Stimulus révèle leurs panneaux ; le navigateur assure son focus natif. Sans JS, les associations HTML et les contrôles serveur restent présents ; l’ouverture de tous les panneaux sans JS doit encore être vérifiée en R5.4.

**Reste R5.3 : écoute ciblée et changement de préférence système**, avec les limites de zoom/lecteur natif conservées dans la grille globale. **Prochain travail indépendant possible : R5.4.** Clôture R5.5 subordonnée aux défauts R5-D1/D3, à la recette téléphone R5.2 et à la revue globale. Aucun push, coût, appel modèle réel, publication, activation ou migration applicative dans ce lot.

## Reprise des essais du 2 novembre

- Contrôle des **18 exports existants** avec `HTMLParser` : aucun identifiant dupliqué, aucune cible inexistante pour `aria-describedby`, `aria-labelledby`, `aria-controls` ou `label[for]` ; chaque `main#contenu` porte `tabindex="-1"`. Les empreintes des sources enregistrées correspondent encore aux fichiers actuels. Cela ne vérifie ni le nom accessible calculé ni la visibilité réelle du focus.
- Les premiers serveurs lancés dans des sessions shell ne répondaient pas au navigateur. Ils ont été arrêtés. Un seul serveur PHP hôte, lancé en arrière-plan avec fenêtre masquée et journaux séparés, répond **200** sur `http://127.0.0.1:8021/qa/creation`. Il conserve le routeur GET uniquement et reste disponible pour la reprise manuelle. Journaux locaux : `artifacts/r53-server-out.log` et `r53-server-error.log`.
- Chrome était réduit avec une ancienne page 500 sur `/studio/creation`. Fenêtre restaurée, adresse lue et adresse de recette saisie ; l’outil a de nouveau interrompu le contrôle à la navigation, faute d’URL déterminable avec assez de confiance. **Aucune interaction d’application après cet arrêt**, aucun contournement.
- L’exception historique indique un argument `$writer` non résolu sur `AiStudioController::index`. Inspection actuelle du service `LocalStoryWriter` et `lint:container --env=dev --no-debug` réussies ; OPcache CLI désactivé. Aucune nouvelle requête authentifiée n’a été rejouée : **la disparition de la 500 dans la session du propriétaire n’est pas certifiée**. Aucun service ou worker redémarré pour masquer ce problème.
- Les tests frontend et PHP chiffrés plus haut restent ceux du premier passage. Seuls le contrôle structurel et le lint du conteneur dev ont été ajoutés lors de cette reprise.

Pour préparer la prochaine tentative, ouvrir l’export de recette dans Chrome et vérifier que le préparateur synthétique est affiché, puis reprendre le contrôle dans un nouveau tour. Les formulaires de cet export ne peuvent pas lancer de génération ou de publication. Garder R5.3 décoché jusqu’aux essais réellement réalisés.

## Bilan du passage interactif après ouverture manuelle

### Parcours clavier

À **1280 × 720**, parcours depuis le lien d’évitement puis tabulations, jusqu’au passage vers les contrôles du navigateur. Seuls les contrôles applicatifs atteints sont comptés ; les panneaux repliés ne sont pas artificiellement rendus visibles.

| Vue | Arrêts de focus |
| --- | ---: |
| Montage | 43 |
| Calendrier | 57 |
| Studio neuf | 31 |
| Épisode à trois plans | 50 |
| Nouvelle série | 26 |
| Inscription refusée | 10 |
| Import | 14 |
| Profil | 22 |
| Connexions | 14 |
| Génération simulée | 15 |
| Vidéos | 20 |
| Publications | 16 |

Aucun contrôle applicatif sans nom ou contour de focus dans ces passages. Ce relevé ne prouve pas la perception du contour sur toutes les images ni la couverture de chaque état.

La préparation de publication a été reprise avec la tabulation native : **21 arrêts applicatifs et cinq arrêts du lecteur vidéo du navigateur**. Les contrôles internes du lecteur conservent `VIDEO` comme `document.activeElement` ; le contour de cet hôte n’est pas une mesure du focus interne. Les explorations interrompues sur un hôte vidéo répété ne sont donc ni un défaut de piège clavier ni une réussite de cycle complet.

À **390 × 780**, Studio neuf 29, épisode 48, calendrier 55, montage 36 et inscription refusée 10 arrêts ; tous atteignent la frontière du navigateur, avec noms et contours applicatifs présents. Largeur de document 375 px avec barre de défilement, pour 390 px de viewport : aucun débordement horizontal de ces cinq exports. Le passage mobile de la préparation avec lecteur natif reste partiel.

- Calendrier : Entrée sur le 03/11/2026 ouvre le dialogue, focus « Fermer », Tab atteint l’import ; Échap ferme et rend le focus au jour déclencheur.
- Montage : Entrée sur Exporter ouvre le dialogue, focus « Fermer », Tab atteint assemblage/confirmation/export ; Échap ferme et revient à Exporter. Aucun export soumis. Actions du projet : ouverture et fermeture clavier vérifiées, aucune suppression.
- Les dialogues natifs peuvent envoyer temporairement le focus vers l’interface du navigateur (`BODY` dans le document) ; aucun contrôle de la page derrière le dialogue n’a été atteint.
- Menu mobile : Entrée ouvre, Tab atteint Vidéos, Échap ferme et revient au `summary` Menu.
- Choix Kling 2.5 / 15 s / trois plans : trois cartes de 5 s créées côté navigateur. Les trois plans sont incompatibles à 5 s, options correctement désactivées ; l’essai a utilisé la durée compatible. Le POST de récapitulatif a reçu **405 du routeur de recette** : cela ne prouve pas l’ouverture native d’un champ invalide, toujours couverte ici par les tests de contrôleur et les assertions serveur.

Les premiers relevés de géométrie faits trop rapidement pendant le défilement fluide sont exclus des conclusions de visibilité. Aucun parcours de génération/publication authentifié n’est simulé comme réussi par ce banc GET.

### Contraste, zoom et profil compact

Onze vues, **499 groupes d’éléments textuels** : sRGB sur couleurs calculées et fonds unis des ancêtres, couleurs alpha compositées ; seuil 4,5:1 ou 3:1 selon taille/graisse. Les options de listes natives, éléments cachés, panneaux fermés, contrôles désactivés, images/dégradés et opacités de groupe sont exclus. Minimum observé **6,89:1**, trois éléments complexes de Publications laissés hors mesure. Ce calcul ne tient pas lieu d’audit de toutes les superpositions, de la landing ou des états dynamiques.

Chrome natif a explicitement indiqué **200 %** dans la barre et la bulle de zoom. Début du préparateur inspecté en fenêtre étroite puis agrandie, sans agrandissement CSS ; zoom restauré à **100 %**, confirmé par l’interface. L’étroite fenêtre initiale donne une largeur effective inférieure au plancher de recette 320 px : ne pas la confondre avec une preuve de reflow à 320 px. La matrice de zoom globale n’est pas certifiée.

Ce contrôle a révélé le profil latéral compact sans nom. Correction dans `templates/studio/shell.html.twig`, test ciblé ajouté ; le navigateur intégré confirme à 900 px que le texte est masqué et que le nom explicite reste présent. Aucun modèle, devis, consentement ou service métier changé.

### Narrateur et préférence système : preuves insuffisantes

SystemSettings n’a pas fourni de fenêtre pilotable ; `control.exe` a ouvert Outils Windows, sans accès au réglage visuel voulu. **Aucune préférence système changée.** La préférence réduite simulée ou une revue des media queries ne remplace pas cette vérification.

Le Narrateur Windows s’est lancé avec retard après un premier retour sans fenêtre cible. L’écran de nouveautés a été fermé au clavier ; une fenêtre d’accueil supplémentaire s’est ouverte. Le pilotage natif n’a pas permis de confirmer le focus de lecture sur les champs. Le propriétaire a répondu **« Je sais pas je suis sensé faire un truc pour l’entendre ? »** à la demande d’écoute : aucune réussite sonore des noms, erreurs ou annonces d’état ne peut être déduite de ce retour ou de l’arbre accessible.

L’accueil QuickStart a été arrêté, mais Windows a refusé l’arrêt du processus Narrator lancé pour cet essai (accès refusé). Le propriétaire a reçu la commande manuelle **Ctrl + Windows + Entrée** pour le désactiver. Aucune extinction du Narrateur n’est revendiquée sans confirmation. Les tentatives de lancement, la présence d’un processus et l’exposition des rôles ARIA ne valent pas recette au lecteur d’écran.

### Contrôles après le dernier correctif

**22 PHP / 285 assertions** réussis : AccessibilityTest, AccountTest et ValidationQueueTest, en copie Linux isolée avec dépendances copiées, sans suite DB concurrente. **115 Twig** et style du test réussis. Pas de nouvelle suite PHP complète ni de nouvelle suite/build frontend pour ce seul changement de libellé Twig ; leurs résultats précédents restent datés plus haut.

**18 exports régénérés**, empreintes vérifiées, zéro HTTP fournisseur/rédacteur et onze compteurs concordants après rollback. La preuve initiale des anciens exports est conservée séparément dans le manifeste. Journaux : `artifacts/r53-interactive-targeted.log` et `r53-interactive-export.log`. Les copies temporaires Linux et de configuration sont nettoyées après récupération des preuves.

## Retour ultérieur du propriétaire et reprise R5.4

Le propriétaire confirme ensuite : **« ok j’ai écouter le narrateur fonctionne bien »**. Cela confirme une écoute générale positive ; aucun parcours précis des erreurs ou annonces d’état n’a été décrit. R5.3 reste ouvert pour ces vérifications ciblées et la préférence système de mouvement. Le raccourci Ctrl + Windows + Entrée a été communiqué pour arrêter le lecteur ; son arrêt n’est pas déduit de cette demande. Les sources reprises en R5.4 ont évolué : les empreintes R5.3 ci-dessus sont historiques, le manifeste R5.4 conserve les empreintes suivantes. Voir [R5.4](r5-resilience.md).

## Retour ciblé sur l’inscription et ordre des descriptions

Le propriétaire entend « Indique une adresse email valide ». Il signale d’abord ne pas entendre « Les mots de passe doivent être identiques », puis précise : **« ah si ça me le dis plus tard en fait […] je me suis tromper »**. Ce retour confirme une lecture tardive ; **aucune disparition sonore de cette erreur n’est démontrée**. Le champ exact de cette observation n’a pas été précisé. Ne pas enregistrer cette correction de retour comme un échec avéré du Narrateur.

Inspection du HTML : sur le premier mot de passe, `aria-describedby` référençait l’aide de longueur avant l’erreur ; sur la confirmation, aucune référence à cette erreur commune. Amélioration de présentation conservée : le thème `templates/form/accessibility.html.twig` place les erreurs avant les aides et relie les deux champs d’une paire répétée aux erreurs que Symfony rend sur son premier champ. L’aide reste présente, les messages ne sont pas dupliqués et les contrôles de mot de passe restent identiques. Le rendu natif sans erreur conserve son aide et n’annonce pas de champ invalide. Les erreurs déjà associées ne sont pas ajoutées deux fois.

Symfony `RepeatedType` constitue une seule valeur à partir de deux saisies et rend ses erreurs sur le premier enfant ; le thème Twig construit les descriptions accessibles des widgets. Le changement concerne ces liens et leur ordre, sans modifier `RegistrationType`, les contraintes ou la soumission. Le test dans `AccessibilityTest` vérifie l’état initial puis les deux champs après désaccord ; le test des widgets directs vérifie aussi l’erreur avant une aide préexistante.

Deux tests rouges avant correction (**2 tests / 4 assertions, 2 échecs**) reproduisent l’ordre précédent. Après correction : **20 PHP / 205 assertions réussis** (AccessibilityTest, AccountTest, ResilienceTest), style PHP et **115 Twig** réussis. Le premier contrôle de style a manqué du répertoire public `docker/php` dans la copie isolée ; répertoire ajouté, contrôle final réussi. Pas de nouvelle suite complète PHP, frontend ou build : aucun JS/CSS modifié lors de ce complément. Les résultats complets rouges antérieurs restent historiques.

**21 exports régénérés**, empreintes et onze compteurs concordants après rollback, zéro HTTP fournisseur/rédacteur. La page corrigée est servie au même endroit sur 8023 ; un nouvel onglet de contrôle confirme les champs et le message unique dans l’arbre accessible. L’onglet du propriétaire n’a pas été rechargé automatiquement. [Manifeste et preuves](fixtures/r5-erreurs-formulaire-20261102.json). Les anciens manifestes gardent leurs empreintes historiques ; leurs exports locaux antérieurs restent distincts des nouveaux.

L’ordre sonore de la version modifiée reste à écouter après actualisation. Ce retour positif partiel ne clôture pas les annonces dynamiques, la préférence Windows de mouvement, le téléphone réel ou R5.5. Aucun compte créé, coût, appel réel, publication, activation, migration, worker redémarré, commit ou push.

## Confirmation suivante et enregistrement Git

Le propriétaire confirme ensuite **« la le narrateur c’est bon »**, en poursuivant la recette de `/qa/register-error`. L’écoute des erreurs d’inscription de la version modifiée est donc acceptée sur ce périmètre. Ce retour ne décrit pas le scénario dynamique de suivi indisponible ni une modification de la préférence Windows ; ces points et la recette téléphone restent ouverts. Aucun test sonore supplémentaire n’est inventé à partir de l’arbre accessible.

Les correctifs applicatifs et leurs tests sont enregistrés dans le commit local **`5fe6f0d`** (`fix: improve accessible forms, native fallbacks and responsive layouts`). Les outils et preuves sont enregistrés séparément. Les résultats chiffrés ci-dessus n’ont pas été rejoués pour l’opération Git ; ils restent les passages datés décrits, y compris la suite complète rouge historique. Aucun push. Les mentions « aucun commit » des passages précédents décrivent leur état à cette date et ne sont pas le statut actuel.
