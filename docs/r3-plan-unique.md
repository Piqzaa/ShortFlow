# R3.3 — Choisir et préparer un plan unique

28 octobre 2026. R3.3 terminé dans le parcours commun Studio/épisodes. Grand R3 en cours ; prochaine étape R3.4, revue globale en R3.7.

## Objectif et comportement

Le bouton « Préparer un plan unique » se trouve désormais sous « Comment filmer cette vidéo ? ». Avec JavaScript, il apparaît quand le mode unique est sélectionné ; le nombre actuel de plans indique que le choix seul ne transforme pas encore les scènes. Sans JavaScript, le bouton reste disponible au même endroit. Il soumet la saisie courante au serveur et ne demande aucune génération.

La transformation réunit les prompts dans leur ordre, séparés par des retours de ligne, et les résumés français avec des espaces compatibles avec leur champ sur une ligne. La durée finale doit être native pour le modèle déjà choisi et égale au total actuel des plans. Exemple : trois plans de cinq secondes deviennent un plan de quinze secondes si le modèle accepte quinze secondes. Le modèle, le fournisseur, la connexion, le format, le brief et les réglages communs restent ceux du formulaire ; aucun remplacement automatique.

Le serveur refuse la conversion si des images ou consignes d'images sont affectées à plusieurs plans, si les contextes traduits/cadrages diffèrent, si la durée n'est pas native ou diffère du total, ou si les textes réunis dépassent 1 500 caractères de prompt / 300 de résumé ou la limite propre au modèle. Les champs restent présentés avec une explication, sans troncature ni suppression. Un plan déjà unique garde ses données, références et durée. Les identités traduites et empreintes communes sont conservées ; les gardes de langue/source/citations du devis restent applicables.

Pour un épisode existant, la transformation est provisoire jusqu'à « Voir le récapitulatif ». Un message l'indique et l'alerte de départ est active dès le chargement de la réponse, puis encore après un récapitulatif refusé. Sans JavaScript, le message reste visible mais il n'y a pas d'alerte navigateur : il faut enregistrer avant de partir. Pour une nouvelle idée du Studio, la conversion est aussi conservée dans la session ; ce n'est pas un brouillon durable hors session. Le devis et la confirmation du coût restent séparés. La transformation n'atteste ni cohérence narrative ni prise continue réelle du fournisseur.

## Fichiers et rôle des composants

- `src/Service/AiEpisodeFlow.php` : `singlePlan()` vérifie les compatibilités et construit le plan réuni, sans DB ni appel fournisseur dans cette méthode. Les méthodes existantes de préparation, propriété, révision, devis et confirmation continuent à encadrer l'enregistrement et la génération.
- `src/Controller/AiStudioController.php` : appelle cette transformation depuis le formulaire commun, conserve les erreurs, actualise la session du Studio neuf et transmet l'état provisoire à Twig. La redistribution générale reste distincte.
- `templates/ai/_single_plan.html.twig`, `studio.html.twig`, `_preparation_plans.html.twig` : action rapprochée du sélecteur, nombre de plans, explication et état d'enregistrement ; bouton ancien retiré du groupe éloigné des scènes.
- `assets/controllers/preparation_controller.js` : affiche l'action selon le mode et explique l'état actuel ; ne fusionne pas les textes dans le navigateur.
- `unsaved_controller.js` : peut démarrer avec une modification non enregistrée signalée par le serveur. Le formulaire d'épisode soumis, y compris invalide, conserve cette garde.
- `tests/Functional/AiEpisodeFlowTest.php`, `tests/Frontend/preparation.test.js`, `unsaved.test.js` : fusion ordonnée, métadonnées, refus sans perte, récapitulatif invalide, retour au Studio et distinction choix/transformation.

Flux Symfony : formulaire soumis avec CSRF → validation des champs → contrôleur → transformation métier → nouveau formulaire à relire → soumission du récapitulatif → enregistrement sous verrou propriétaire/révision → devis → confirmation distincte. Recréer un formulaire n'enregistre pas automatiquement une entité Doctrine ; c'est pourquoi l'état provisoire est explicite. Stimulus améliore l'affichage et le départ de page, mais le serveur protège les textes et durées même sans JS.

## Vérifications et limites de recette

- Ciblés après corrections : **6 tests / 92 assertions**, avant les trois assertions finales du récapitulatif invalide. Tests fonctionnels uniquement sur `shortflow_test`, migrations vérifiées, aucune suite DB concurrente.
- Passage complet final : **775 tests PHP / 8 882 assertions**, code 0, 2 min 06 s, 78,5 Mio. `php scripts/check.php` exécuté dans une copie Linux temporaire des sources et dépendances, chemin de `App\Kernel` vérifié avant les commandes ; schémas dev/test, lints Twig/YAML/conteneur, PHPStan et style réussis. Avertissements Compose sur volume historique/conteneurs orphelins et `tar` sur métadonnées changeantes du montage Windows pendant la copie ; aucune suppression de ces ressources. Aucun fichier applicatif modifié pendant le contrôle.
- **156 tests frontend**, `npm run check`, `npm run test:ui` et `npm run build` réussis. Avertissements préexistants de polices d'éditeur et chunk Three.js.
- Revue indépendante en lecture seule : séparateur compatible avec le champ HTML, état provisoire, conservation en session, alerte après refus du récapitulatif et protection des textes/images/durées examinés. Pas de revue globale R3 anticipée.
- Recette synthétique desktop 1440 × 900 et mobile 390 × 844 : action près du sélecteur, Tab du choix vers son bouton, choix seul conservant trois plans, résultat unique de quinze secondes, résumé intégral avec espaces, détails et Tab du résumé vers le prompt. Largeur utile/scroll 375/375 sur mobile, aucune erreur console sur le rendu du résultat. Les pages proviennent de requêtes Symfony de test réelles ; le navigateur lit ces captures avec les assets compilés sur loopback, sans accès DB ni fournisseur. Le POST du premier serveur de recette a renvoyé 405 à la racine, rendant cet onglet inutilisable ; résultat inspecté ensuite en GET dans un nouvel onglet. Ne pas présenter cette tentative comme un parcours POST navigateur réussi : le POST et l'enregistrement sont vérifiés par les tests fonctionnels. Serveur temporaire arrêté et viewport restauré.

Les premiers tests ont exposé des sélecteurs comptant aussi le prototype, un champ textarea vérifié comme input, une comparaison normalisant les retours de ligne, puis un vrai cas de champ facultatif `null` qui provoquait 500 : traitement corrigé. Une fixture de traduction utilisait le hash d'un brief vide alors que le formulaire reprenait le brief de série : fixture rendue explicite, protection de source conservée. Aucun test exclu ni contrôle de langue/coût affaibli. Le test de protection R3.2 utilise désormais « Répartir la durée », car la conversion unique conserve les résumés compatibles au lieu de les refuser systématiquement.

## Comment essayer

Dans Studio ou un épisode en préparation, choisir « Plan unique · un seul appel ». Vérifier le nombre actuel puis « Préparer un plan unique ». Avec trois plans de cinq secondes et un modèle acceptant quinze secondes, relire le prompt et le résumé réunis, puis ouvrir le récapitulatif. Avec un total non natif, des images par plan ou des textes trop longs, vérifier l'explication et les champs conservés. Quitter une conversion d'épisode non enregistrée doit afficher l'alerte du navigateur lorsque JS est actif. Ne pas confirmer une vidéo payante pour cette recette.

Aucune migration ajoutée, appel de modèle, vidéo payante, publication, activation ou push. Cache dev et quatre workers actualisés après garde d'inactivité à zéro. Commit local `feat: preserve scenes when preparing a single take`. Prochaine étape exacte : **R3.4** ; grand R3 en cours.
