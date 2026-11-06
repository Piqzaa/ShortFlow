# R7.2 — premiers retours utilisateur et corrections R7.3

## Origine et limites du retour

Le 6 novembre 2026, le propriétaire transmet les retours de sa compagne après essai de l’application, puis ses propres difficultés dans le montage. Il s’agit d’un retour rapporté, pas d’une séance observée directement par l’agent. Une première testeuse externe au créateur est identifiée ; son niveau, les tâches exactes, durées et appareils ne sont pas documentés. Le propriétaire ne remplace pas un testeur indépendant. R7.2 prévoit trois à cinq personnes et reste ouvert ; première publication, série et reprise d’échec ne sont pas toutes attestées.

| Source | Retour rapporté | Conséquence produit |
| --- | --- | --- |
| Testeuse | Demande si l’application permet de faire du montage ; le propriétaire présente les éditions simples. | Rendre le montage accessible et expliquer ses capacités concrètes. |
| Testeuse | Studio complexe, trop de choses affichées. | Parcours court, options avancées repliées, modèles et références présentés au moment utile. |
| Testeuse | Préfère les euros aux dollars. | Prévoir estimation EUR, conversion datée, devise de facturation distinguée ; pas de simple remplacement du symbole. |
| Testeuse | Séries complexe ; souhaite créer une série à partir de ses propres vidéos, sans génération IA. | Clarifier génération récurrente et programmation de vidéos existantes ; étudier un vrai parcours de série manuelle. Importer/programmer dans Vidéos est le contournement actuel, pas une série manuelle livrée. |
| Testeuse | Ne comprend pas « clé API » ; explication jugée compliquée. Connexion Fal par navigateur plus accessible. | Mettre la connexion guidée en premier et les clés dans un accès avancé, avec explication simple. |
| Propriétaire | Enregistrer après ajout de sous-titres semble sans effet ; retour au menu, montage considéré perdu. | Vérifier la persistance, rendre la reprise visible et distinguer projet enregistré / vidéo exportée. |
| Propriétaire | Case obligatoire avant export jugée inutile. | Le bouton Exporter déclenche directement la création du fichier, sources conservées. |
| Propriétaire | Sous-titres petits à l’export ; taille cachée et exprimée en pourcentage difficile à trouver ; souhaite redimensionner à la souris. | Augmenter le défaut des nouveaux montages, proposer tailles simples et poignée dans l’aperçu. |

## Correctifs locaux de ce lot

Retour suivant sur la suppression : l’ancienne liste `/studio/bibliotheque` réapparaissait, avec un décalage de cartes lié aux libellés de sélection sur plusieurs lignes. **Cette ancienne liste n’est plus rendue : son adresse redirige vers la vue principale `/studio`, en conservant recherche, page et contexte de sélection/date/fuseau.** Suppression simple/groupée, renommage et import simple reviennent directement à cette vue ; les imports explicitement destinés au montage ou à une publication gardent leur destination. L’annulation d’une suppression simple revient aussi à Vidéos. Les anciens liens restent compatibles via la redirection ; confirmations de suppression, CSRF et propriété restent contrôlés. 19 fonctionnels bibliothèque / 241 assertions verts, style deux PHP/PHPStan contrôleur et Twig ciblés verts. Deux vues navigateur 390/1280 sans débordement, premier rang aligné sur ordinateur ; aucun JS/CSS modifié ni build requis. Cache dev actualisé, ancienne adresse avec recherche/page vérifiée en lecture seule, quatre GET dev200/27 compteurs inchangés/zéro HTTP fournisseur. Aucun worker redémarré ni média propriétaire supprimé par l’agent.

Retour suivant du propriétaire, après capture : le bloc « Reprendre un montage » prend trop de place et les noms « Mon montage » répétés n’aident pas. **La liste visible des trois projets décrite ci-dessous est remplacée par une seule ligne « Mes montages » repliée par défaut.** À l’ouverture, les titres génériques sont affichés avec la date/heure de dernière modification ; aucun titre enregistré ni contenu du projet n’est changé. Test ciblé de sauvegarde/réouverture 1 / 11 et lint Twig verts, caches test/dev actualisés. Aucun build frontend ou redémarrage worker nécessaire pour cette finition Twig.

Lecture seule du poste : plusieurs projets ont effectivement leurs cinq sous-titres persistés, dont un brouillon à taille 5 et un export à taille 4. Cela ne prouve pas ce qui s’est passé à chaque clic du propriétaire. Aucun projet réel n’a été modifié. Le point de reprise était replié et « Éditer » une vidéo source ouvre un nouveau montage : la confusion projet/source/export explique plausiblement le ressenti, sans conclure à une perte réelle ni prétendre avoir reproduit l’incident exact.

- Les trois derniers projets sont visibles dans « Reprendre un montage » ; les suivants restent repliés pour éviter une longue liste. Le retour d’enregistrement précise qu’un export crée la vidéo finale.
- Le formulaire passe par la validation serveur, avec erreurs affichées dans l’état de sauvegarde, plutôt qu’un blocage de validation native sur un champ masqué. CSRF, propriétaire, révision, bornes et quotas restent contrôlés.
- Case de confirmation d’export retirée, texte sur la nouvelle vidéo et la conservation des sources maintenu. Aucun changement aux confirmations de retrait, droits musicaux ou générations payantes.
- Taille par défaut 5 au lieu de 4 pour les montages sans réglage explicite ; les tailles déjà enregistrées restent conservées. Taille visible hors position précise, boutons Petit/Moyen/Grand, poignée souris/tactile sur le sous-titre sélectionné et clavier sur cette poignée. Taille commune à tous les sous-titres, bornée entre 2 et 10, pas de changement de la synchronisation.
- Le texte de l’aperçu est dans un élément dédié pour que son rafraîchissement conserve la poignée. La valeur sauvegardée pilote aussi l’export.

## Vérification

Quatre fonctionnels montage / 46 assertions (enregistrement JSON, réouverture, texte/taille 5,3, reprise visible, export sans case, refus coupes/CSRF, transcription simulée et export local de sources synthétiques avec textes/positions). Après limitation de la liste à trois projets, reprise ciblée 1 / 11 verte, chevauchant les quatre précédents. Trois unitaires sous-titres / 15 assertions ; 206 frontend, check et build verts. 16 Twig, style six PHP et PHPStan cinq fichiers applicatifs verts. Pas de suite PHP globale ni revue indépendante.

Chrome 390/1280 sur fixture HTML fonctionnelle : poignée réellement tirée, taille 5,3→7,3 / 6,6 envoyée à l’enregistrement simulé, message enregistré, poignée toujours présente, export sans case, aucun débordement/erreur JS/console. Deux captures relues. Les médias et réponses POST du navigateur sont synthétiques/interceptés ; aucune sauvegarde utilisateur réelle. Le build conserve l’avertissement existant de gros chunk.

Cache dev actualisé et seul `edit-worker` déjà actif redémarré après garde : zéro message, génération active, épisode actif et montage actif. Quatre GET dev authentifiés HTTP200, sous-titres toujours activés, 27 compteurs inchangés et zéro HTTP fournisseur dans le banc de lecture seule.

## Prochain lot R7.3

Studio, partie 1 validée explicitement par le propriétaire : les blocs « Reprendre une création » en haut et « Mes dernières vidéos à plans » en bas sont retirés. Les accès « Historique » et « Mes vidéos » restent dans l’en-tête ; aucune création n’est supprimée. Le test fonctionnel existant est adapté pour vérifier l’absence des listes, les deux accès et la présence des anciens projets dans l’historique. Les parties suivantes (ordre du parcours, modèles et cartes des plans) attendent chacune une validation du propriétaire avant modification.

Studio, partie 2 réalisée à sa demande pour relecture : point de départ → image importée/bibliothèque de personnages si nécessaire → format/durée/nombre de plans → rédaction. Le format passe après les références et le choix IA/manuellement après le découpage. Libellés « Prépare ta vidéo », « L’IA propose mes plans » et « J’écris mes plans ». Raccords dans un bloc avancé replié, ouvert si raccord déjà configuré ou erreur de champ ; conversion en plan unique et gardes de conservation inchangées. Les trois modèles et les cartes restent pour les parties suivantes, sans nouveau choix automatique ni changement de prix.

Deux fonctionnels existants / 75 assertions, 206 frontend/check/build, 37 Twig et style du formulaire PHP verts. Quatre largeurs Chrome 320/390/768/1280 : ordre des champs, raccords repliés, choix de référence upload/bibliothèque avec valeur importée synthétique conservée, écriture IA/manuelle/unique et textes conservés, aucun débordement/erreur JS/console ; capture mobile relue. POST navigateur interceptés, aucune référence réellement envoyée. Cache dev actualisé ; quatre GET200 de banc en lecture seule, 27 compteurs égaux, zéro HTTP fournisseur. Aucune nouvelle suite PHP globale, revue indépendante, génération/coût/publication/migration/worker redémarré/push/déploiement. **Validation visuelle du propriétaire attendue avant la partie 3.**

1. Réduire les écrans initiaux de Studio et Séries à une intention et aux réglages essentiels ; conserver les saisies et l’accès aux paramètres avancés.
2. Présenter les prix en euros avec conversion datée et explication de la monnaie facturée. Choisir la source et la politique de rafraîchissement avant implémentation.
3. Rendre l’entrée connexion Fal compréhensible sans jargon ; garder les clés API en option avancée.
4. Cadrer la série de vidéos importées comme un besoin distinct : ajout de médias, ordre/cadence et préparation de publications. Aucun faux support ajouté au parcours IA existant.
5. Refaire les tâches simplifiées avec la première testeuse, puis compléter avec deux à quatre autres personnes. Relever aide demandée, compréhension prix/projet/export et reprise d’échec. Ne pas clôturer R7.2/R7.3 à partir des seuls tests automatisés.

Aucune nouvelle génération IA, transcription propriétaire, dépense fournisseur, clé lue, migration, publication, push ou déploiement pour ce lot.
