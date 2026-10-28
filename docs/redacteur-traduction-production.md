# Complément R2 — traduction contrôlée avant R3.3

28 octobre 2026. Correctif demandé et autorisé par le propriétaire après R3.2 : les trois prompts de sa proposition étaient français malgré la consigne de traduction. Ce complément précède R3.3 ; il ne rouvre pas les anciennes captures R2 et n'autorise aucune génération vidéo réelle. La relecture et la confirmation du devis de chaque vidéo restent obligatoires.

## Objectif et comportement

Le résumé et les scènes lisibles restent français. Le second appel local produit les directions et l'identité en anglais, avec les citations explicitement demandées conservées exactement. Le modèle reçoit seulement identité, style, scènes, durées, continuité et citations ; le brief complet, le résumé et la mémoire ne sont plus répétés à cette étape. Une consigne courte et un exemple remplacent les longues instructions de réécriture. Deux appels maximum, budgets et délais existants conservés ; aucun retry automatique, API texte distante, modèle supplémentaire ou coût fournisseur.

Chaque nouvelle proposition porte une identité traduite distincte et une empreinte de sa source (idée, personnages, style). Ces deux valeurs sont enregistrées dans le JSON existant des plans, sans migration. Le prompt fournisseur utilise cette identité anglaise plutôt que de réajouter le brief français. Le brief original reste enregistré. Le détail de la carte expose l'identité pour relecture ; l'empreinte indique seulement si la source a changé, elle ne certifie pas la fidélité de la traduction.

Une sortie signalée devient « Traduction à corriger ». Le scénario français et les prompts reçus restent disponibles comme brouillon, à appliquer volontairement pour correction manuelle. Les alertes de la proposition initiale restent rappelées après application. Aucune traduction automatique ne réécrit des textes humains existants.

## Blocages avant dépense

Les contrôles examinent la scène brute, y compris son identité préfixée, puis l'identité traduite séparément. Une identité française courte ne peut donc pas se cacher dans une scène anglaise plus longue. Les marqueurs français, anglais et les cas indéterminés restent des heuristiques finies : ils ne reconnaissent pas toutes les langues et n'établissent aucune vérité narrative.

Les citations sont exemptées seulement si elles figurent exactement, avec guillemets fermés, dans la source fournie. Entourer une consigne française de guillemets ne la rend pas automatiquement autorisée. Les noms français et les noms structurés des références ne sont pas assimilés à des directions françaises. Les citations de parole identifiables sont aussi contrôlées sur l'ensemble des scènes : absence, modification, répétition supplémentaire ou ordre incorrect bloquent la vidéo. Ce contrôle textuel ne valide ni locuteur audiovisuel, ni voix, ni lipsync.

Les protections s'appliquent :

1. Au devis des scènes, avant confirmation ; réparation d'un plan comprise.
2. À la confirmation/reprise d'une génération non transmise.
3. Dans le worker, avant tout nouveau POST, puis à nouveau dans la transaction de départ après verrou propriétaire et rafraîchissement du plan, de l'épisode et de la série.

Un ancien travail en attente avec scène française revient à `prepared` et son épisode est mis en pause, sans nouveau POST. Ce retour est réservé à l'exception typée `VideoPromptReviewRequired`, qui établit le refus avant envoi ; les autres situations incertaines restent en `review`. Les demandes déjà transmises restent récupérables : le contrôle ne bloque pas `processing`, ne retire pas leurs identifiants et ne les renvoie jamais pour corriger un texte.

« Revoir les plans restants » libère uniquement les travaux non transmis, conserve les textes et ouvre leur correction manuelle. Chaque plan dispose d'un prompt et d'une identité modifiables ; enregistrer incrémente la révision, conserve durée, résumé et références, puis ouvre un nouveau devis. La génération exige toujours une nouvelle confirmation. Les clips déjà terminés restent conservés et ne sont pas éditables par ce formulaire. Propriété, CSRF, révision et verrou propriétaire s'appliquent aussi sans JavaScript.

Une source modifiée rend son identité traduite périmée : demander un nouveau scénario ou choisir explicitement « Détacher ce contexte et reprendre manuellement ». Le détachement conserve scènes, résumés et références ; il retire uniquement les métadonnées de traduction. Cette action fonctionne aussi par soumission serveur sans JavaScript et conserve CSRF/propriété/révision.

Au moment de la confirmation, le contexte source de personnages/style est figé dans la recette JSON de l'épisode. Une modification ultérieure des réglages généraux de la série ne remplace donc pas le contexte déjà accepté et ne bloque pas à tort ses plans ou leur réparation. Avant confirmation, la série est relue depuis la base pour vérifier une traduction encore à confirmer.

**Portée conservée pour les anciens/manuels/détachés :** les scènes sont contrôlées, mais le contexte humain historique peut rester français, distinct des directions. Le correctif n'efface ni ne traduit silencieusement ce contexte. Les nouvelles propositions traduites utilisent leur identité anglaise dédiée ; le devis montre toujours le texte exact envoyé. Aucun contrôle lexical ne garantit que l'intégralité du contexte manuel est anglais.

## Fichiers et rôles Symfony

- `src/Service/LocalStoryWriter.php` : deuxième appel ciblé ; contrôle distinct de l'identité, des scènes et des citations ; indicateur `translationReady`, brouillon préservé.
- `StoryTextChecks.php` : diagnostic de langue réutilisable, empreinte de source et contrôle des citations sur plusieurs plans. Contrôles purs, sans HTTP ni modification de texte.
- `VideoPromptLanguage.php` : applique les contrôles aux entités et au contexte d'un épisode ; ne traite que les directions des demandes non transmises.
- `AiEpisodeFlow.php` : garde au devis, liaison source/traduction et composition depuis l'identité anglaise, y compris après ajout de métadonnées à une ancienne préparation ; correction sous verrou des plans non transmis en révision.
- `AiGenerations.php` : garde à confirmation/reprise et avant POST ; récupération distante inchangée.
- `AiPlanInputs.php`, `AiShotType.php`, documentation de type `AiShot.php` : validation et aller-retour du contexte traduit dans le JSON existant ; `AiEpisode.php` fige le contexte source lors de confirmation.
- `SeriesRecurrences.php` : transmet les métadonnées des scénarios futurs en les liant à la forme enregistrée du brief de série ; aucune activation ni vidéo automatique.
- `AiStudioController.php`, `AiEpisodeController.php`, `_shot_card.html.twig`, `_remaining_prompt_correction.html.twig` : affichage, détachement serveur du contexte et formulaire de correction des plans non transmis, sans effacer les textes.
- Contrôleurs Stimulus `story_writer`, `shot_editor`, `preparation` : application volontaire, conservation des champs lors d'une redistribution, détachement local et annonces. L'intitulé de l'aperçu ne prétend plus qu'un prompt signalé est anglais.
- Tests PHP/frontend ; `scripts/evaluate-translation-local.php` pour les essais synthétiques, sans Kernel ni DB.

Flux à comprendre : formulaire → demande Messenger → rédaction française → traduction locale ciblée → aperçu/brouillon → application humaine → validation serveur des scènes et du contexte → devis → confirmation → worker avec nouveau contrôle avant POST. Une protection Stimulus aide à comprendre ; Symfony et le worker restent responsables du blocage réel. Un champ caché ou une empreinte fournie par le navigateur n'est pas une autorisation de coût.

## Évaluation locale et limites

[Captures complètes distinctes](fixtures/redacteur-traduction-r2-resultats.json), requêtes/réponses et empreintes conservées. Corpus historique intact. Tutoriel et dialogue proviennent des entrées fixes R2.6 ; banane/fraise est un nouvel exemple synthétique, pas la capture brute de la demande privée du propriétaire.

- **Campagne exploratoire : six essais / douze appels.** Cinq sorties `translationReady`, cinq contrôles de citations réussis ; deux essais différents révèlent un problème : une identité anglaise descriptive classée indéterminée et une réplique manquante dans le récit du second dialogue. Campagne code 1, conservée séparément ; ses sources étaient un état de travail intermédiaire, identifié par empreintes, pas un commit autonome.
- **Campagne finale après correction : six essais / douze appels**, programme code 0. Identités/directions anglaises sur les six sorties examinées ; les deux dialogues gardent les deux citations françaises exactes dans l'ordre. Empreintes `LocalStoryWriter` et `StoryTextChecks` vérifiées identiques aux sources finales. Un lancement intermédiaire sans Ollama disponible a échoué avant les essais ; service local relancé, aucun modèle téléchargé.
- Ollama **0.24.0**, Qwen3 8B installé sur ce poste ; ni matériel ni ancien benchmark R2.6 supposés identiques. Durées finales environ **4 à 9 secondes par cas** observées, avec la suite PHP en parallèle pendant une partie des mesures. Ce ne sont pas un benchmark isolé ni une promesse de délai.
- Revue indépendante des sorties : inventions physiques et accessoires, « wearing/dressed in their own flesh », identité contenant des actions/états, roux rendu par `red`, recette avec beurre au dos de la main et résultat en sandwich. Plusieurs écarts restent sans alerte. **Six sorties anglaises ne signifient pas six scénarios fidèles ou prêts à publier.** Aucune vidéo/audio produite ni qualité audiovisuelle validée.

## Vérifications et recette

Unitaires ciblés : **56 tests / 228 assertions réussis** après les contrôles de langue/citations et le témoin anglais descriptif. Tests fonctionnels ciblés : refus avant devis, ancien queued sans POST, contexte traduit conservé et résultat processing récupérable. La suite complète et la recette finale sont consignées dans [le journal de la roadmap](roadmap.md).

Une première suite réellement isolée est verte : **769 tests / 8 782 assertions**, code 0, 2 min 03 s, 78,5 Mio, sans avertissement PHPUnit. Puis protection du contexte confirmé renforcée : **770 / 8 790** verts. Après ajout du parcours de correction, un passage **771 / 8 803** a trouvé une assertion relisant une entité détachée au lieu de l'état enregistré. Le test relit désormais la base ; **1 / 18 ciblé** vert après actualisation du cache test (première tentative sur cache périmé sans formulaire). Contrôle complet de clôture consigné dans la roadmap. Les suites PHP fonctionnelles utilisent uniquement `shortflow_test`, migrations vérifiées avant exécution, jamais en parallèle sur cette base. **155 tests frontend**, check JS et build réussis ; avertissements Vite/polices d'éditeur connus.

Recette synthétique desktop 1440 × 900 / mobile 390 × 844 : diagnostic de traduction refusée, application volontaire du brouillon et maintien des alertes, contexte lisible, détachement sans perte de prompt/résumé, focus sur résumé puis Tab vers prompt ; aucun débordement horizontal observé (375/375 utiles), aucune erreur console capturée. Les cartes Twig sont rendues en mémoire sans DB ; le serveur local fournit une réponse de scénario simulée, n'appelle aucun fournisseur, refuse les autres POST et ne sauvegarde rien. Onglets fermés, viewport restauré, serveur temporaire arrêté. Le fallback sans JS du détachement est couvert fonctionnellement côté Symfony.

Première suite complète diagnostique : **769 tests / 8 774 assertions, un échec et un avertissement**. L'échec était le texte attendu d'une fixture de réutilisation, resté français après traduction en anglais de cette fixture pour les tests de confirmation ; attente alignée sans retirer de contrôle de propriété, CSRF, conservation ou absence de POST. Un passage statique intermédiaire a ensuite arrêté le contrôle avant PHPUnit : contrat d'exception complété pour le garde. La relance suivante a été interrompue volontairement : l'autoload de `vendor` lié par symlink utilisait encore `/app/src`, malgré la configuration PHPUnit copiée en `/tmp`. Ces passages ne sont pas la preuve finale d'une source figée. La copie des dépendances en Linux remplace le symlink et le chemin réel de `App\Kernel` est désormais vérifié avant tests ; les résultats finaux sont consignés séparément.

Pour essayer : recharger le Studio, demander volontairement un nouveau scénario, comparer résumé français, prompt et identité traduits dans les détails. Une ancienne proposition française n'est pas corrigée rétroactivement : sa génération doit être refusée jusqu'à correction. Modifier le brief après traduction doit demander une nouvelle préparation ou le détachement manuel du contexte. Ne pas confirmer de vidéo payante pour vérifier ce correctif.

Commandes de contrôle : `php scripts/check.php` dans le conteneur `edit-worker` (migrations test, schémas, lints, PHPStan, style et PHPUnit), puis `npm run check`, `npm test` et `npm run build`. Sur ce poste, le passage complet utilise une copie temporaire Linux des sources et de `vendor` pour éviter la lenteur du montage Windows ; la classe `App\Kernel` est vérifiée dans cette copie avant exécution. `php scripts/evaluate-translation-local.php --dry-run` décrit les trois entrées et deux répétitions sans inférence ; sans ce paramètre, le programme effectue douze appels au moteur texte local et conserve les captures.

Clôture : **771 tests PHP / 8 808 assertions réussis**, code 0, 2 min 03 s, 78,5 Mio, sans avertissement PHPUnit ; PHPStan/style/lints/schémas verts. Les **155 tests frontend**, check JS et build restent ceux du même code frontend, sans nouvelle modification JS depuis leur passage. Commit local `fix: validate translated video directions before generation`, aucun push.

Le nouveau formulaire des plans non transmis a aussi été examiné sur desktop/mobile, avec Tab du prompt vers l'identité, sans débordement horizontal ni erreur console. Enregistrement et nouveau devis couverts par le test fonctionnel, zéro POST fournisseur. Cache dev et quatre workers actualisés après vérification des files et traitements actifs à zéro ; aucune migration ajoutée.

Revue indépendante des sources : rafraîchissement sous verrou, détachement sans JS, noms de références et correction des plans non transmis contrôlés après correction, aucun nouveau blocage identifié. La décision de préparation automatique/génération relue reste inchangée. **Suite prévue après clôture de ce complément : R3.3**, pas R4, pas déploiement.
