# R3 — revue globale du parcours Studio et Séries

Revue du 30 octobre 2026. **R3.1–R3.7 terminés et grand R3 clôturé**, après correction des deux blocages trouvés par la revue globale. Ce bilan confronte tout le lot à sa porte de sortie ; il complète les revues ciblées des sous-tâches.

## Résultat examiné

| Tâche | Résultat actuel et preuves |
| --- | --- |
| R3.1 | Parcours commun idée → scènes → récapitulatif sur un écran, sans assistant imposé. [Cadrage](r3-parcours-commun.md). |
| R3.2 | Cartes centrées sur le prompt complet éditable, durée et outils image accessibles. À la demande du propriétaire, résumé français conservé dans les données et l'aperçu du scénario ; aucune édition concurrente du résumé dans les cartes. [Cartes](r3-cartes-scenes.md), [amendement prompt](r3-relecture-prompts.md), [contexte intégré](r3-reglages-compacts.md). |
| R3.3 | Durées natives et nombre de plans choisis avant proposition ; passage au plan unique effectif si compatible, textes/références incompatibles conservés avec refus explicite. [Plan unique](r3-plan-unique.md), [nombre de plans](r3-nombre-plans.md). |
| R3.4 | Modèle/résolution et connexion communs Studio/Séries ; rédaction locale distincte de l'accès vidéo API/navigateur. [Choix et connexions](r3-choix-modeles-connexions.md). |
| R3.5 | Coût total, modèle/connexion, plans et références exactes accessibles avant confirmation. Corrections refusées récupérables, aucun lancement implicite. [Récapitulatif](r3-recapitulatif-accessible.md). |
| R3.6 | Navigation après forward, validation des références sans libellé résiduel ni 500, comptes enregistrés par vidéo/occurrence, disponibilité et créneaux manqués expliqués. [Navigation et reprise](r3-navigation-comptes-reprise.md). |
| Compléments | Fin masquée en référence de personnage ; raccords issus de la dernière image décodée, fin commune uniquement au dernier plan. Texte/personnage non compatibles avec le raccord du parcours actuel à modèle unique. [Raccords et limites](raccords-verification-20261029.md). |

## Revue indépendante et corrections de clôture

Deux agents en lecture seule ont relu l'ensemble R3.1–R3.6, ses contrats et ses protections, conformément à la règle 9 de la roadmap. Ils n'ont pas exécuté les suites ni modifié les sources. Deux défauts concrets ont été trouvés et corrigés avant décision de sortie :

1. **Remplacement d'un plan terminé bloqué par l'ancien prompt.** `AiEpisodeFlow::stageRepair()` contrôlait l'ancien devis avant de lire la nouvelle saisie. Un ancien prompt français pouvait donc empêcher sa correction. Une réparation omettant une citation pouvait aussi être enregistrée puis bloquer toutes les corrections suivantes. Le devis valide désormais le candidat en mémoire, y compris langue, citations, longueur, état, références et coût, avant toute écriture. Le GET de remplacement conserve le champ même si l'ancien devis échoue ; aucun bouton de confirmation sans devis. Une correction valide prépare un nouveau devis et change sa révision ; le clip existant et l'exigence de confirmation restent conservés.
2. **Sauvegarde désarmée après refus d'import actif.** Le parent annulait la soumission après que le formulaire avait arrêté l'autosauvegarde ou retiré l'alerte de départ. La garde intervient désormais en capture et arrête la propagation ; une soumission refusée laisse les contrôleurs de sauvegarde et d'alerte actifs. Une soumission acceptée garde le parcours existant.

Fichiers : `src/Service/AiEpisodeFlow.php`, `src/Controller/AiEpisodeController.php`, `templates/ai/_repair_prompt.html.twig`, `templates/series/episode.html.twig`, `templates/ai/studio.html.twig`, `assets/controllers/preparation_controller.js`. Régressions dans `tests/Functional/AiEpisodeFlowTest.php` et `tests/Frontend/preparation.test.js`.

En Symfony, le contrôleur fournit le contexte de correction à Twig même lorsqu'un service refuse le devis. Le service valide une proposition avant de la persister sous verrou ; la validation ne doit pas dépendre du contenu qu'elle remplace. Dans le navigateur, la capture d'un événement traverse le parent avant les handlers du formulaire : l'ordre est nécessaire pour préserver l'autosauvegarde lorsqu'on annule la soumission.

## Vérification et limites

Les tests utilisent exclusivement `shortflow_test`, avec migrations vérifiées et aucune suite DB concurrente. HTTP des fournisseurs simulés ou refusés. Premier contrôle global avant corrections : **802 tests PHP / 9 207 assertions**, **177 frontend**, schémas/lints/PHPStan/style/check/build verts. Ces chiffres ne sont pas le passage final après correction.

La recette navigateur utilise des GET authentifiés Symfony avec utilisateurs, références, clips et comptes synthétiques, exportés puis servis sur boucle locale avec les assets construits. Aucun POST vidéo, social ou d'activation n'est accepté par le serveur de recette. Les erreurs, sauvegardes durables, révisions et confirmations sont vérifiées dans les tests fonctionnels ; la recette vérifie rendu et interactions navigateur.

Contrôles finaux du 30 octobre : **804 tests PHP / 9 227 assertions**, **178 frontend**, schémas dev/test, YAML/Twig/conteneur, PHPStan/style, syntaxe JS et build réussis. Commandes : `php scripts/check.php` dans un edit-worker temporaire test avec copie Linux des sources/vendor et contrôle du chemin du Kernel ; `npm run test:ui`, `npm run check`, `npm run build`. Avertissement Vite Three.js connu. Aucune exclusion de test. Premier ciblage sur montage Windows : 5 / 81, un échec de rendu avec ancien cache Twig ; cache test régénéré, **5 / 87 verts** sans modifier les attentes, puis suite complète finale ci-dessus.

| Recette synthétique finale | Observation |
| --- | --- |
| Studio neuf et création de série | GET authentifiés exportés, réglages modifiables, aucun lancement ; création de série en 375 px sans débordement. |
| Import actif et sauvegarde | Marqueur d'import simulé ajouté au vrai HTML ; clic récapitulatif refusé avec message, URL conservée et aucun POST applicatif. Après fin simulée, nouvelle édition envoyée au récepteur d'autosauvegarde simulé (séquence 6), preuve que le contrôleur reste actif. L'alerte de départ partage la même protection par capture, relue et couverte au niveau propagation. |
| Épisode enregistré | Deux prompts anglais conservés, menu Séries actif sur trois surfaces, proposition locale désactivée sans empêcher l'accès manuel. |
| Huit plans / 60 secondes | Modèle, coût et connexion visibles, prompts exacts et image repliés ouvrables ; image effectivement chargée. Liens clavier donnent le focus à `review-plans` et `story-quote`. Confirmation désactivée sans connexion. |
| Ancienne génération individuelle | Coût, connexion et confirmation conservés ; bouton désactivé sans clé ; mobile sans débordement. |
| Série avec brouillon et créneau manqué | Compte enregistré nommé dans la préparation et l'occurrence, liens clavier vers ajout manuel et futurs réglages ; focus correct et volet ouvert. Aucun rattrapage automatique. |

Desktop 1280 px et mobile 375 × 812 ; largeur du document respectivement 1265 et 360 px. Capture mobile du devis examinée : texte lisible, pas de chevauchement du contenu visible, navigation basse accessible. Captures locales `artifacts/r3-light/r37-review-desktop.png` et `r37-review-mobile.png`. Première ouverture locale refusée par délai réseau du serveur sandboxé ; serveur relancé sur boucle locale, sans exposition publique. Une première insertion de fixture d'import dans un prototype a produit une erreur de script de recette ; insertion déplacée dans la section visible, recette rejouée avec succès. Aucune erreur console applicative supplémentaire relevée ; cette erreur initiale de fixture n'est pas cachée.

Après relecture des corrections, les deux agents indépendants ne signalent plus de blocage global identifié, sous réserve des contrôles finaux maintenant passés. **Décision de sortie : porte R3 satisfaite dans le périmètre local/simulé autorisé.** Les limitations ci-dessous restent explicites ; elles ne deviennent pas des garanties de rendu ou de production.

Les contrôles de langue/citations restent heuristiques ; les résumés ne sont pas régénérés à chaque édition du prompt. Aucun test simulé ne certifie la fidélité narrative, la voix, les lèvres, un raccord parfaitement continu, un compte réel ni la disponibilité d'un modèle local. L'onglet et les données personnels du propriétaire ne sont pas utilisés dans la recette. Aucun appel IA réel, dépense, publication, activation, migration applicative ou push.

La porte de sortie exige conservation des préparations et consentements, accès aux scènes, récapitulatif compréhensible et absence de blocage identifié ; la réduction de hauteur seule ne suffit pas. Prochaine tâche après clôture : **R4.1**, jeux synthétiques bornés et mesures séparées SQL/HTML/temps serveur/attente de rédaction. Aucun travail R4 lancé ici.

Cache dev régénéré et quatre workers relancés après contrôle à zéro traitement actif ; fonctionnement des quatre services vérifié. Serveur et onglet de recette fermés, viewport restauré. Commit local `fix: close R3 review and preserve correction recovery`, aucun push.
