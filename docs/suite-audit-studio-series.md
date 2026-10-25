# Suite de l’audit Studio/Séries — 25 octobre 2026

Le propriétaire a autorisé l’action après le rapport `audit-studio-series-global-2026-10-25.md`. Réalisation successive, revue entre agents, tests simulés et commits locaux. Aucun essai vidéo payant, publication réelle, activation de récurrence réelle ni push.

- [x] Lot 1 : prompts/contexte figés, cadres sans priorité cachée, empreinte récurrente complète.
- [x] Lot 2 : références nommées, capacités communes, transitions, limites et prévol cohérents.
- [x] Lot 3 : profil éditorial et propositions locales par réseau, liés à la vidéo validée.
- [x] Lot 4 : reprise des occurrences interrompues, états et action suivante.
- [x] Lot 5 : recette de montage limitée aux fonctions existantes et finition UX.
- [x] Recette finale simulée, navigateur et remise en route des workers.

Les résultats, fichiers, flux Symfony et tests seront consignés à la fin de chaque lot. Les essais réels restent séparément confirmés.

## Lot 1 — intégrité de la préparation acceptée

Objectif : un devis accepté conserve ses personnages, son style et chaque prompt, même si la série change ensuite. `AiEpisodeFlow` prépare une recette v4 avec `visualContext` figé ; la confirmation conserve le prompt effectif dans `AiShot.inputs.confirmedPrompt`. L’envoi utilise ce texte. Les cadres propres aux plans priment sur les raccourcis globaux encore renseignés.

`SeriesAuthorization` construit une empreinte commune de l’autorisation récurrente : recette, brief, durées, coût, accès BYOK, contrat complet du modèle et version des consignes. Les anciennes autorisations incompatibles exigent une nouvelle confirmation ; elles ne sont pas renouvelées implicitement.

Une ancienne recette v3 confirmée sans prompt figé ne permet plus de nouvel envoi. Le garde-fou intervient avant le prévol fournisseur, et encore sous verrou avant POST. Les demandes déjà transmises restent récupérables. Depuis une pause, « Revoir les plans restants » conserve les clips terminés, abandonne uniquement les demandes préparées/en attente jamais transmises, puis affiche un devis des plans restant à produire. Une demande incertaine n’est jamais considérée comme non envoyée.

Composants : `AiEpisode` conserve la recette et l’état de révision ; `AiShot` conserve prompt accepté et historique ; `AiGenerations` protège la transition payante ; `AiEpisodeController` et le template épisode donnent le chemin de relecture avec CSRF/révision. Les colonnes JSON existantes suffisent, sans migration propre à ce lot.

Vérifications : trois tests fonctionnels ciblés, 20 assertions réussies, exclusivement sur `shortflow_test`, avec fournisseur simulé ; un test unitaire d’autorisation, quatre assertions réussies. Cas couverts : série modifiée après préparation, cadres A/B, ancien job en file bloqué avant POST puis remplacé après nouveau devis. La revue de l’agent modèles a ajouté le garde-fou avant prévol pour éviter une impasse en cas de contrat catalogue modifié. Les contrôles globaux seront consolidés après les lots suivants.

Flux Symfony : formulaire → service sous verrou propriétaire → instantané Doctrine → devis et consentement → message Messenger → prompt accepté vers adaptateur. À tester sans coût : préparer des plans, modifier les valeurs par défaut de la série dans un autre onglet, puis vérifier que le devis de cet épisode reste identique ; aucune génération ne part sans confirmation.

## Lot 2 — références et modèles cohérents

Les fichiers de sujets restent une liste ordonnée compatible avec l’historique. Une collection `AiSubjectType` ajoute nom, rôle personnage/objet, présence dans le premier cadre et vues complémentaires. `AiPlanInputs` contrôle la sélection, les fichiers privés, les limites et les liens entre ces champs ; la recette, le devis et `AiGeneration.inputSettings` conservent ces informations. `FalVideoApi` transmet maintenant les éléments Kling avec vue frontale et vues complémentaires distinctes selon le chemin documenté ; une ancienne préparation incomplète doit être complétée avant un nouvel envoi. La récupération d’une demande déjà transmise conserve son ancien contrat.

Studio et Séries utilisent les mêmes cartes et limites. Changer vers une référence d’identité désactive l’option de fin incompatible sans choisir un autre modèle. Le modèle est présenté avant le rédacteur et les références nommées. Les raccourcis globaux restent hors du formulaire principal pour éviter des formulaires HTML imbriqués ; leur portée premier/dernier plan est indiquée. Les métadonnées et vues participent à l’empreinte du brouillon et d’une proposition de scénario.

`AiShotPrompts` associe les noms aux éléments fournisseur et distingue sujet déjà visible ou entrant dans l’action. Le rédacteur local reçoit uniquement ces indications textuelles, jamais les photos ; son budget de scène s’adapte notamment à sept références Vidu. `AiCatalog` impose aussi les limites en caractères documentées pour Kling, Vidu, Grok et Hailuo image, en complément des limites en octets. Les anciennes scènes trop longues sont refusées avant POST, sans troncature cachée. Le prévol de récurrence refuse désormais un format `auto` ou des éléments incomplets avant rédaction.

Vérifications : trois tests fonctionnels ciblés, 24 assertions réussies sur `shortflow_test` ; cartes/cadres distincts, identité figée et devis avec vues nommées. Les agents ont vérifié 15 tests unitaires/44 assertions sur sujets, budgets et formulaires, et 92 tests/1 700 assertions de contrats images simulés. Suite frontend à ce stade : 126 tests réussis, avec les nouveaux contrôles de transitions et de références. La recette navigateur et la suite globale sont encore à consolider en fin de chantier.

En Symfony, les collections indexées sont transformées par les formulaires, puis normalisées dans le service ; le nom du fichier n’est pas utilisé comme nom de champ. Les données de l’interface ne donnent jamais accès à un fichier d’un autre propriétaire. À tester sans coût : décor en cadre initial, sujet nommé qui entre dans l’action, vues Kling distinctes, puis récapitulatif des entrées exactes.

## Lot 3 — textes de publication supervisés

Une version vidéo validée peut demander une proposition locale par réseau depuis son préparateur existant. Le profil de série précise langue, ton, public, mots-clés, marque, appel à l’action et hashtags autorisés/exclus. Il reste distinct de la recette vidéo. Le moteur local reçoit les scènes connues et une description facultative de la version ; il ne regarde pas la vidéo. Les hashtags sont assemblés dans les descriptions/légendes existantes, sans modifier les réglages sociaux.

`StoryProposal.kind` isole les scénarios des textes éditoriaux en réutilisant Messenger et le mutex du moteur local. `PublicationEditorial` lie la proposition à la vidéo approuvée, aux scènes, au profil et aux réseaux. `PublicationDraft.editorial` conserve l’origine des champs : existant, généré ou modifié par le créateur. Le préparateur sauvegarde les saisies avant toute demande/application et demande un accord explicite pour remplacer un champ modifié. Les dates et programmations ne changent pas. La migration additive `Version20261025190000` conserve les anciennes données.

Fichiers, parcours et flux Symfony détaillés dans `editorial-supervise.md`. La route de profil est séparée (`/studio/series/{id}/textes`) ; le bouton de rédaction est dans le préparateur de la vidéo validée. Une nouvelle version/profil/réseau invalide l’application de l’ancien résultat, sans effacer les brouillons antérieurs.

Vérifications consolidées : cinq tests fonctionnels éditoriaux, 52 assertions réussies sur `shortflow_test`, sans API sociale ni vidéo. Les tests ont révélé puis corrigé la conservation de clés numériques dans `PublicationDraft.videoIds()` après une seconde sauvegarde ; ils vérifient aussi un champ volontairement vidé. L’agent a vérifié 19 tests unitaires (65 assertions, incluant le rédacteur existant) et trois tests frontend du nouveau suivi. La suite globale de fin de chantier revérifiera les scénarios et publications existants.

## Lot 4 — rédaction interrompue et suivi de récurrence

La création d’une occurrence programme `InspectSeriesWriting` sur le transport de fond existant. Après dix minutes, le contrôle relit sous verrou l’occurrence : une rédaction automatique réellement interrompue passe « à vérifier » et suspend la même autorisation. Un contrôle trop précoce se reprogramme ; un ancien contrôle ne suspend pas une nouvelle autorisation. Aucune rédaction ni vidéo n’est renvoyée par ce contrôle. `RecoverSeriesRecurrencesCommand` réconcilie également les anciennes occurrences interrompues avant de réveiller les récurrences encore actives.

Une reprise humaine normalise le statut dans `SeriesOccurrence::manageManually`, et une réponse tardive du rédacteur ne peut plus reprendre la main ni réserver un budget. Les résultats existants sont conservés. Réautoriser une série saute un créneau qui possède déjà une occurrence, plutôt que provoquer une collision de la contrainte unique. Les textes de l’interface distinguent publication visée, vidéo à valider, textes à relire et publication à confirmer ; les états sociaux restent ceux du moteur existant.

Fichiers : `SeriesRecurrences`, `SeriesOccurrence`, message/handler `InspectSeriesWriting`, routage Messenger et commande de récupération ; templates série et récurrence. Pas de nouvelle table ni de boucle de rattrapage. Symfony persiste le contrôle différé avec l’occurrence dans la même transaction ; une redélivrance réexamine l’état courant au lieu de répéter le travail.

Vérifications : toute la classe `SeriesRecurrencesTest` passe, 16 tests et 138 assertions sur `shortflow_test`, clients texte/vidéo/sociaux simulés. Elle couvre mort du worker représentée par une occurrence durable, rédaction de six minutes non interrompue, ancien contrôle, reprise manuelle pendant rédaction, changement de clé/recette/délai, déduplication et réautorisation du créneau. Aucun contrôle d’entretien n’a été exécuté sur les séries réelles du propriétaire.

## Lot 5 — montage commun et finition

Objectif : conserver les choix de finition avec chaque préparation. Le lien « Profil de montage » de la série ouvre le volume des clips (0–100 %), un titre pendant les 2,5 premières secondes et le choix image entière/recadrage centré. Ces réglages utilisent le moteur de montage existant. Ils ne changent pas le format demandé au fournisseur et ne réparent pas un clip généré au mauvais format. Musique et sous-titres restent accessibles dans le montage individuel, sans import ni transcription automatique.

`SeriesMontageType` valide les champs du formulaire ; `SeriesMontageController` contrôle propriétaire, CSRF et révision sous verrou ; `SeriesMontage` normalise les trois valeurs autorisées dans le JSON existant. `AiSeriesStudio` conserve le profil lors d’une modification des autres réglages. `AiEpisodeFlow` en fige un instantané avec la préparation ; `VideoEdits` le transmet à l’assemblage. L’option de coupure du son de l’épisode reste prioritaire. Aucun nouveau schéma SQL propre à ce lot.

Flux Symfony : formulaire → validation et verrou Doctrine → recette de série → instantané de préparation → devis → génération existante → assemblage Messenger/FFmpeg. Une modification ultérieure du profil ne change pas un épisode déjà préparé. Le récapitulatif indique les valeurs réellement retenues.

Vérifications ciblées : dix tests unitaires, onze assertions ; test fonctionnel de propriété/CSRF, huit assertions ; assemblage réel FFmpeg d’un clip synthétique avec titre et volume réduit réussi. Aucun fournisseur vidéo contacté. Pour tester manuellement sans coût : modifier le profil, préparer un épisode sans confirmer la génération, puis lire ses réglages au récapitulatif ; changer ensuite le profil et constater que la préparation précédente reste identique.

La vérification navigateur sur six pages fictives couvre configuration, références, récapitulatif, profil éditorial, profil de montage et propositions de textes. Les transitions Kling/Vidu conservent les images et signalent les vues incompatibles ; le mode identité désactive l’image de fin. Contrôle clavier des champs éditoriaux et largeurs 1280/390 px : aucun débordement horizontal ; aucune erreur ou alerte console relevée. Le contrôle mobile a révélé et corrigé la taille excessive des sous-titres du profil de montage. Les formulaires du serveur de recette étaient bloqués en écriture (405), sans données ni envois réels.

La revue finale indépendante n’a trouvé aucun blocage dans le profil de montage. Elle a signalé une omission dans l’ancienne branche `intent=plans`, toujours acceptée en POST malgré l’absence de bouton actuel : les références et leurs noms sont désormais conservés dans sa recette et son empreinte, comme dans le parcours commun.

La suite élargie a également fait vérifier le retrait sans JavaScript d’une image de série : les métadonnées d’une référence explicitement retirée sont éliminées avant normalisation, tout en gardant celles d’une image encore utilisée comme référence principale. La revue croisée a précisé ce dernier cas. Les fichiers privés et les instantanés des anciennes préparations restent conservés.

Le garde-fou de suppression des séries filtre aussi `StoryProposal.kind = story` : une proposition éditoriale porte l’identifiant d’un brouillon de publication, qui peut partager sa valeur numérique avec un épisode sans rapport. Le test de suppression conserve désormais une proposition éditoriale en attente pour couvrir cette collision.

La suite élargie a trouvé une deuxième régression dans la réparation d’un plan : une requête Doctrine avec jointure exécutée après détachement du clip, mais avant `flush`, pouvait réhydrater l’ancienne relation. La confirmation charge maintenant sa liste de plans avant de modifier la relation, puis fige les prompts sur ces mêmes objets. Le clip remplacé reste dans l’historique ; un seul nouveau plan est autorisé, les autres et leur coût ne changent pas. Le test existant de réparation contrôle aussi la double confirmation et l’invalidation de la vidéo approuvée.

Limites : cette recette vérifie le parcours et les contrats simulés, pas la fidélité visuelle d’un modèle distant ni la qualité sémantique du rédacteur local réel. Les vues complémentaires Kling suivent le chemin documenté ; leur acceptation réelle n’a pas été essayée avec des crédits. Une prise continue reste une consigne au modèle et ne garantit pas l’absence de coupe.

## Bilan de vérification — 26 octobre 2026

- Suite PHP élargie : 267 tests, 3 585 assertions, deux échecs initiaux (retrait des métadonnées d’image et réparation d’un plan). Ces deux défauts ont été corrigés. La suite complète n’a pas été relancée après ces corrections.
- Rejeu après corrections : toute la classe `AiSeriesTest`, réparation, deux scénarios de montage et ancien chemin de préparation avec sujet nommé : **18 tests, 222 assertions, tous réussis**. Les cas de retrait d’image et de suppression ont été enrichis pour les bords trouvés en revue. Journaux locaux ignorés : `artifacts/suite-audit-final-php.log` et `artifacts/suite-audit-corrections-php.log`.
- PHPStan niveau 5 : aucune erreur. CS-Fixer : zéro fichier à corriger sur 288 fichiers, puis contrôle des trois fichiers concernés par les derniers correctifs réussi. `git diff --check` réussi.
- Twig : 98 templates valides ; YAML : 19 fichiers valides ; conteneur Symfony valide. Schémas Doctrine local et test synchronisés. Migration additive `Version20261025190000` appliquée aux deux bases.
- Frontend : `npm run check`, **128 tests UI**, puis `npm run build` réussis. Le build conserve les avertissements existants sur les URL statiques résolues à l’exécution et le chunk Three.js dépassant 500 Ko.
- Navigateur : six pages synthétiques, desktop/mobile, clavier, transitions des références, protection du titre existant, aucune erreur console relevée. Captures locales dans `artifacts/suite-audit-montage-mobile.png` et `artifacts/suite-audit-editorial-desktop.png`.
- Les quatre workers sont remis en route, l’application locale répond en HTTP 200 et le conteneur temporaire de recette a été supprimé. Aucun essai vidéo payant, envoi social, activation de récurrence réelle ni push réalisé.

Suite recommandée : parcourir manuellement une préparation réelle sans confirmer le coût, puis tester la rédaction locale de textes depuis une vidéo validée existante. Un essai vidéo fournisseur reste une étape distincte à autoriser avec modèle, durée et coût choisis. Une notification d’approche de date et un profil de montage plus riche restent des améliorations possibles ; les états et actions suivantes sont disponibles dans l’interface.
