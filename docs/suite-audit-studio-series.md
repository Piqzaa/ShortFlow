# Suite de l’audit Studio/Séries — 25 octobre 2026

Le propriétaire a autorisé l’action après le rapport `audit-studio-series-global-2026-10-25.md`. Réalisation successive, revue entre agents, tests simulés et commits locaux. Aucun essai vidéo payant, publication réelle, activation de récurrence réelle ni push.

- [x] Lot 1 : prompts/contexte figés, cadres sans priorité cachée, empreinte récurrente complète.
- [x] Lot 2 : références nommées, capacités communes, transitions, limites et prévol cohérents.
- [x] Lot 3 : profil éditorial et propositions locales par réseau, liés à la vidéo validée.
- [ ] Lot 4 : reprise des occurrences interrompues, états et action suivante.
- [ ] Lot 5 : recette de montage limitée aux fonctions existantes et finition UX.
- [ ] Recette finale simulée, navigateur et remise en route des workers.

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
