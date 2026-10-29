# R3.3 — Choisir le nombre de plans avant le scénario

29 octobre 2026. Complément demandé après essai du [plan unique et du guidage des durées](r3-plan-unique.md). Le prochain point de roadmap reste R3.4 ; cette correction ne livre pas R3.4.

## Objectif et comportement

En « Plusieurs scènes » et « Raccord depuis la dernière image », le formulaire demande **combien de plans** préparer, de deux à huit. Une préparation existante reprend le nombre de ses cartes ; revenir d'un clip unique à plusieurs scènes demande un choix explicite. Le mode unique conserve ses propres limites et un seul clip.

Changer explicitement le nombre ou la durée totale répartit celle-ci en clips acceptés par le modèle, aussi régulièrement que possible. Avec Kling 3 : 30 s / trois plans → 10 + 10 + 10 ; 30 s / quatre plans → 8 + 8 + 7 + 7, pas quatre clips non pris en charge de 7,5 secondes. Avec un modèle limité à cinq ou dix secondes : 30 s / quatre plans → 10 + 10 + 5 + 5. Le total est exact ; il n'est jamais arrondi. Les nombres impossibles sont désactivés, une ancienne valeur devenue incompatible est conservée et expliquée, et des totaux voisins sont indiqués. Si aucun découpage multiple n'est possible, l'aide propose d'augmenter le total ou de garder un plan unique.

Les cartes restent modifiables. Un découpage manuel valide, avec le bon nombre de plans et le bon total, est transmis tel quel au rédacteur ; il n'est pas rééquilibré à chaque demande. L'ajout/retrait manuel d'une carte actualise aussi le nombre demandé. « Proposer mon scénario » vérifie ou prépare les cartes avant de transmettre **nombre + durées exactes** ; le serveur valide les deux avant de créer la demande asynchrone. Les anciennes pages sans nombre explicite gardent leur protocole historique, sans autorisation vidéo supplémentaire.

Les textes, résumés, identités traduites, empreintes et références restent associés à leurs cartes, dans l'ordre. Ajouter des cartes conserve les anciennes données et laisse les nouvelles scènes à préparer. Réduire le nombre est refusé si une carte retirée contient un texte, une image, une consigne, un contexte traduit ou même un cadrage `contain` choisi sans image. L'explication reste près du sélecteur après les événements de saisie ; aucune suppression ou concaténation de plans rédigés n'est effectuée par cette redistribution. L'import d'images en cours bloque le redécoupage.

Le nouvel état d'un épisode est provisoire jusqu'au récapitulatif, avec avertissement de départ ; pour une idée neuve, il est conservé dans la session. Sans JavaScript, « Appliquer ce découpage » prépare les cartes via Symfony avec les mêmes contrôles ; l'inférence locale reste un parcours JS comme auparavant. La compatibilité du modèle et des références pour un raccord reste vérifiée séparément, avant vidéo. Choisir le nombre de plans ne garantit ni raccord réel, ni fidélité narrative, ni paroles/audio.

## Composants et flux Symfony

- `AiIdeaType.php` et `templates/ai/_plan_count.html.twig` : champ facultatif entier et accessible, aide, état provisoire et action serveur. `studio.html.twig` le place près du mode de tournage.
- `assets/preparation-plan.js` : proposition native avec un nombre exact optionnel, ou diagnostic de total impossible. Le mode automatique historique reste disponible aux anciens appelants.
- `preparation_controller.js` : choix compatibles, redistribution après choix explicite, protection du contenu, précontrôle du scénario, état et focus. `shot_editor_controller.js` signale les ajouts/retraits manuels.
- `AiEpisodeFlow::durations()` : calcule le découpage exact côté serveur pour un nombre demandé. `prepare()` refuse un nombre annoncé qui ne correspond pas aux cartes. Aucun devis ou consentement n'est déduit du calcul.
- `AiStudioController.php` : aller-retour des cartes, nombre repris à l'ouverture, session du Studio neuf et état provisoire d'épisode. Une nouvelle carte ne copie pas une image d'une autre scène.
- `story_writer_controller.js` : nombre inclus dans le payload et l'empreinte ; précontrôle avant demande. `AiStoryProposalController.php` vérifie nombre, total, durées natives et éventuel découpage manuel avant Messenger.

Flux : choix mode / durée / nombre → proposition exacte → cartes conservées ou refus explicite → proposition locale facultative sur ce découpage → application volontaire → récapitulatif/enregistrement → devis → confirmation vidéo distincte. Un choix de formulaire n'est pas une dépense. Symfony/Doctrine gardent propriété, CSRF, révision et verrou ; Stimulus fournit le guidage. Le nombre n'ajoute pas de colonne : le brouillon durable possède déjà ses cartes.

## Vérifications et limites

Tests ciblés de redistribution/protocole : **4 tests / 46 assertions** réussis après correction d'une fixture dont le rédacteur synthétique n'avait pas été activé ; aucune inférence réelle. **163 tests frontend**, syntaxe JS et build réussis. Les tests vérifient le corps exact `planCount=4`, `layout=8+8+7+7`, le refus sans HTTP d'une préparation non compatible, les répartitions natives, la protection des textes/cadrages et l'état de relecture.

Recette synthétique navigateur desktop 1440 × 900 et mobile 390 × 844 : unique → plusieurs scènes → 30 s → trois plans de dix ; quatre plans 8/8/7/7 ; réduction à deux refusée avec le troisième prompt humain intact ; retour à trois en raccord ; ajout manuel actualisant le nombre. Tab du nombre vers l'action suivante, aide lisible, largeur utile/scroll 375/375 sur mobile, aucune erreur console capturée. Les pages sont des réponses Symfony de test et les assets compilés, servis sur loopback ; aucune DB ni inférence/fournisseur dans le navigateur. Les requêtes asynchrones sont contrôlées par les tests frontend et fonctionnels simulés, pas par un essai réel de Qwen. Onglets fermés, viewport restauré, serveur temporaire arrêté.

Premier contrôle complet : **779 tests / 8 685 assertions, dix erreurs et un échec**. Les dix erreurs venaient de la date de retrait Pika déjà configurée, atteinte pendant le passage au 29 octobre ; un test attendait encore « total exact » dans le diagnostic. Ce libellé est conservé dans le diagnostic détaillé, sans changer le contrôle. Aucun test exclu.

### Déterminisme des contrats historiques au changement de date

`AiCatalog` utilise maintenant `ClockInterface`, par défaut l'horloge réelle UTC, au lieu de construire sa date dans le contrôle de retrait. Les dates de retrait, identifiants, endpoints et tarifs restent inchangés. Les tests historiques de contrats Pika injectent une `MockClock` au 28 octobre ; toutes leurs assertions de paramètres et de nombre de POST simulés sont conservées. Un nouveau test traverse 23:59:59Z → minuit et vérifie le refus par le catalogue. Il ne réactive pas Pika dans l'application. Revue indépendante ciblée sans levée de garde identifiée ; **118 tests / 2 424 assertions** réussis pour les contrats et la frontière temporelle.

Contrôle complet final : **780 tests PHP / 8 926 assertions**, code 0, 2 min 40 s, 78,5 Mio, sans avertissement PHPUnit ; PHPStan/style/lints/schémas verts. **163 tests frontend**, `npm run check`, `npm run test:ui` et build réussis. Les suites DB utilisent exclusivement `shortflow_test`, migrations vérifiées avant exécution, jamais simultanément. Les contrôles complets utilisent une copie Linux temporaire des sources/dépendances et vérifient le chemin du Kernel ; avertissements préexistants de métadonnées du montage Windows, volume historique/containers Compose et build Three.js/polices conservés.

## Comment essayer

Recharger le Studio ou un épisode en préparation. Choisir « Plusieurs scènes », une durée totale de trente secondes puis trois plans : les cartes passent à dix secondes chacune. Choisir quatre plans avec Kling 3 affiche huit, huit, sept, sept. Demander ensuite le scénario : la demande porte ce nombre et ces durées. On peut retoucher manuellement les durées si le total et le modèle restent compatibles. Aucun lancement vidéo n'est nécessaire pour vérifier ce parcours.

Revues indépendantes du parcours et de l'horloge effectuées, sans blocage restant identifié. Cache dev et quatre workers actualisés après garde à zéro message et zéro traitement actif. Aucune migration, installation de modèle, appel texte réel, vidéo, dépense, publication, activation ou push. Commits locaux `fix: make provider retirement checks clock driven` et `feat: choose scene count before writing stories`. Prochain point exact : **R3.4**, grand R3 en cours, revue globale R3.7 à faire.
