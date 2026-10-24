# Audit du parcours de génération : Studio et Séries

Date : 24 octobre 2026. État audité : commit `68be0ca`. Audit et propositions uniquement : aucune logique applicative modifiée, aucune génération payante, aucune photo utilisateur envoyée, aucune publication ou activation réelle de récurrence.

## Verdict

Le socle de traitement est exploitable et possède de bonnes protections de coût et de propriété. Le parcours créatif n’est toutefois pas encore cohérent de bout en bout. Ses principales limites viennent de l’affectation des images à toute la vidéo, de capacités différentes regroupées sous « image », et de l’absence de distinction explicite entre un clip unique, plusieurs scènes et une extension temporelle.

Le prochain lot doit corriger ces règles communes avant d’ajouter davantage de modèles ou de refaire des essais payants. Il ne nécessite ni nouveau framework ni réécriture du monolithe. La qualité créative et l’identité ne pourront pas être garanties par le logiciel : on peut en revanche rendre les entrées, les limites et les coûts prévisibles, et vérifier le résultat avant publication.

## Périmètre et méthode

Lecture des contrôleurs/formulaires Studio, Séries, scénarios ; services de génération, requêtes Fal/API/MCP, Replicate/LTX, références, raccords, assemblage, autorisations récurrentes ; entités, templates, contrôleurs Stimulus et tests associés. Les grilles `backend-review` et `frontend-review` ont été appliquées sans modifier l’application.

Inventaire : **55 variantes locales, 35 groupes d’exécution** : 37 variantes Fal, 6 Replicate, 12 LTX. Les **27 endpoints Fal distincts** ont été interrogés en lecture seule pour leurs schémas et tarifs. Documentation primaire LTX et Replicate consultée. Les contraintes complètes Replicate et les 55 tarifs conditionnels ne sont pas tous certifiés par cet audit. Voir la [matrice exhaustive](audit-generation-modeles-2026-10-24.md), qui distingue les choix locaux des capacités disponibles chez les fournisseurs.

Deux anomalies frontend ont été reproduites sans navigateur ni réseau, en exécutant les contrôleurs existants dans un environnement synthétique : changement automatique d’endpoint Vidu au passage texte → image ; aperçu de fin incorrect lorsque l’image de début n’est pas encore décodée. Script ignoré : `artifacts/audit-generation-repro.mjs`.

## Règles actuelles, vérifiées dans le moteur

| Parcours actuel | Image principale envoyée | Image de fin envoyée |
|---|---|---|
| Texte | Aucune | Aucune |
| Personnage Vidu Q1 | Même image de sujet pour chaque clip : normal pour guider l’identité | Aucune |
| Image, un seul clip | Image de début choisie | Fin facultative ou obligatoire selon l’endpoint |
| Image, plusieurs scènes indépendantes | **Même premier cadre pour chaque clip** | Fin facultative au dernier clip seulement ; **même fin à chaque clip si le modèle exige une fin** |
| Prolongement par raccord d’image | Image choisie au premier clip, puis image extraite du précédent | Fin choisie au dernier clip ; les modèles exigeant une fin sont refusés |

Preuve : `AiEpisodeFlow::advance`, lignes 382–391. `AiShot` ne stocke actuellement que prompt, durée, résultat, historique et ancre de réparation ; aucun couple début/fin configurable par plan. Le passage d’une référence de personnage à plusieurs clips est volontaire et ne signifie pas que ces clips commencent par la même image.

**Rectification d’une explication précédente :** l’image de fin facultative n’est déjà envoyée qu’au dernier plan. Le libellé ajouté dans la configuration dit pourtant qu’une fin commune vise la fin de chaque clip ; ce texte est incorrect pour ce cas. Il faut corriger l’affichage et faire apparaître la portée exacte, pas repartir d’une hypothèse erronée sur le moteur.

## Problèmes à traiter avant un nouvel essai payant

### A01 — Élevée : cadres communs et absence de cadres par plan

`AiEpisodeFlow::prepare` construit une seule recette ; `advance` réutilise sa référence sur tous les plans indépendants. `AiShotType` et `AiShot` ne proposent pas de cadres propres à un plan. L’utilisateur peut demander des scènes différentes, mais les forcer involontairement à repartir de la même pose. Avec un modèle début+fin obligatoire, le défaut est amplifié par la répétition des deux cadres.

**Correction proposée :** références de personnage au niveau de la série ; début/fin au niveau du plan. Réserver une éventuelle première/dernière image de vidéo au premier/dernier plan, avec une portée lisible. Pour les endpoints qui imposent deux cadres, demander deux images adaptées à chaque plan ou proposer un autre mode compatible, sans recopier automatiquement la même paire. Conserver les anciennes préparations figées et demander la révision explicite d’une préparation héritée.

### A02 — Élevée : raccord d’image présenté à côté d’un besoin de plan continu

`AiEpisodeFlow::advance` appelle `AiFrameExtractor::lastFrame`, qui prend une image vers la fin du clip, puis lance une nouvelle génération. Ni la vitesse du mouvement, ni l’audio, ni le contexte temporel complet ne sont transmis. Cela peut raccorder une pose ; cela ne constitue pas une extension vidéo native.

**Correction proposée :** distinguer « Plan unique », « Plusieurs scènes » et « Prolonger un clip ». Un plan unique utilise un appel et une durée native compatible ; si la durée dépasse cette limite, expliquer les options avant devis. Une extension native n’est proposée que pour un endpoint vérifié qui reçoit la vidéo précédente. Le raccord actuel peut rester disponible sous un nom explicite, avec sa limite.

Une extension native existe chez [LTX avec `/v2/extend`](https://docs.ltx.io/api-documentation/api-reference/async-video-generation/submit-extend), uniquement avec `ltx-2-3-pro` d’après la documentation consultée. Ce Pro n’est pas intégré ici ; nos variantes Fast ne doivent pas être présentées comme compatibles. La facturation inclut le contexte vidéo utilisé, à intégrer au futur devis. L’extension aide le mouvement et l’audio mais ne garantit pas un plan parfait.

### A03 — Élevée : rôles et sélection des modèles différents dans Studio et Séries

Séries expose texte / premier cadre / personnage. Studio n’expose que texte / image et classe Vidu personnage parmi les cartes « Image → vidéo ». `CreationController.changeSource`, lignes 33–38, choisit automatiquement un endpoint compatible. Reproduction : passer du Vidu texte à l’image sélectionne `vidu-q1-subject`, placé avant le Vidu premier cadre. Il n’y a pas de paiement automatique, mais le rôle et le modèle peuvent changer sans décision explicite.

**Correction proposée :** une seule règle de sélection pour Studio/Séries et aucune substitution automatique d’endpoint ou de fournisseur. Un choix devenu incompatible reste à remplacer explicitement. Badges distincts « Personnage », « Premier cadre », « Dernier cadre », « Plusieurs plans natifs », « Extension vidéo » ; motifs d’incompatibilité visibles. Les recommandations doivent suivre le besoin de l’utilisateur plutôt qu’une liste fixe de trois modèles.

### A04 — Élevée : Pika recommandé malgré une dépréciation imminente

`templates/ai/_recommendations.html.twig:3` propose Pika en option économique. Les pages officielles des trois endpoints Pika 2.2 du catalogue annoncent leur dépréciation au **29 octobre 2026**. Les six variantes locales n’ont pas de statut de retrait.

**Correction proposée :** retirer Pika des nouveaux choix recommandés, afficher une information sur les préparations concernées et empêcher de nouveaux envois après sa date de retrait. Garder les anciens identifiants pour l’historique et la récupération. Le remplacement doit avoir un autre identifiant et un nouveau devis, sans migration payante automatique. [Texte](https://fal.ai/models/fal-ai/pika/v2.2/text-to-video), [image](https://fal.ai/models/fal-ai/pika/v2.2/image-to-video), [Pikaframes](https://fal.ai/models/fal-ai/pika/v2.2/pikaframes).

### A05 — Élevée : « Format de l’image » n’est pas conservé jusqu’à l’export

`VideoAsset::matchesRatio('auto')` accepte tout ratio. `VideoEdits::assembleEpisode`, lignes 248–253, transforme ensuite ce ratio en carré, 16:9 ou 9:16 selon l’orientation du premier clip. Une image 4:5 peut donc produire une vidéo 4:5, puis un export 9:16 avec bandes via le réglage de montage `contain`.

**Correction proposée :** choisir un format final explicite dès la préparation, ou conserver réellement les dimensions natives dans le montage. Si l’export n’accepte que trois formats, l’annoncer avant génération et montrer le cadrage final. Contrôler les dimensions attendues même quand le fournisseur déduit le ratio de l’image ; le libellé « auto » ne doit pas désactiver le contrôle utile.

### A06 — Élevée : capacités du catalogue trop exclusives et sous-utilisées

`image`, `subjectReference`, `requiresEnd`, `endImageField` décrivent l’intégration actuelle mais ne suffisent pas aux combinaisons. [Kling 3 image](https://fal.ai/models/fal-ai/kling-video/v3/pro/image-to-video/api) accepte à la fois un premier cadre obligatoire, des `elements` de personnage/objet, une fin facultative et du `multi_prompt`. Le constructeur actuel n’envoie pas les éléments. Vidu accepte plusieurs références, mais nous n’en envoyons qu’une.

**Correction proposée :** décrire des capacités indépendantes et leurs conditions exactes : premier cadre requis/facultatif, fin requise/facultative, références de sujets avec leur nombre, entrée vidéo, scènes natives, durée/résolution/audio. Ne pas simplement mettre `subjectReference=true` sur Kling : notre branche Fal actuelle retourne alors un payload Vidu sans premier cadre. Les modes de requête doivent rester spécifiques au schéma de chaque endpoint.

### A07 — Élevée pour le mode raccord : refaire un plan peut casser le raccord suivant

`AiEpisodeFlow::confirm`, ligne 267, conserve une ancre de fin pour une réparation seulement si le modèle accepte une fin. Sans cette capacité, un plan refait peut finir autrement tandis que le plan suivant déjà généré reste inchangé. Même avec ancre, la continuité du mouvement n’est pas prouvée.

**Correction proposée :** avant de refaire, montrer les dépendances et le raccord susceptible de changer. Permettre de conserver les suivants, ou les refaire après un nouveau devis explicite. Autoriser une correction du prompt/de l’image dans une nouvelle version préparée ; aujourd’hui la réparation rejoue le même prompt. Aucun relancement en cascade implicite.

## Améliorations de cohérence et de fiabilité

| ID / gravité | Constat et preuve | Proposition minimale |
|---|---|---|
| A08 / moyenne | Le rédacteur local reçoit idée, durées et contexte textuel, mais pas le rôle des images ni les contraintes de l’endpoint. Il ne voit pas la photo (`AiStoryProposalController`, `LocalStoryWriter`). | Transmettre les contraintes sélectionnées, réserver le budget de prompt, ne pas prétendre reconnaître l’image. Garder une description de personnage modifiable et signaler les contradictions connues. Une éventuelle analyse visuelle future doit être explicitement configurée. |
| A09 / moyenne | Le prompt envoyé ajoute consignes, scène et contexte commun (`AiEpisodeFlow::prompt`). La longueur autorisée dans chaque champ n’est pas le budget réel, notamment avec la limite Vidu de 1 500 caractères. | Calculer la longueur finale avant rédaction et afficher son budget. Garder uniquement identité/style utiles dans le contexte commun. Ne pas tronquer silencieusement une action ou changer l’histoire pour passer la limite. |
| A10 / moyenne | Le rédacteur demande une action achevée avant la fin de chaque clip court, même en raccord, tout en demandant une action continue. | Adapter ses instructions au mode : résolution du dernier plan, continuité des gestes intermédiaires, ou découpage temporel d’un seul appel natif. Évaluer avec plusieurs thèmes et références décrites. |
| A11 / moyenne | Deux contrôleurs/règles d’aperçu et retrait, deux formulaires de choix modèle ; un aperçu de fin « auto » peut rester carré s’il est rendu avant le décodage du début (`series_reference_controller.js:44`). | Partager les calculs utiles sans créer de framework ; rafraîchir les deux aperçus au chargement d’une image, gérer erreur de décodage et états retiré/enregistré. Reproduction synthétique confirmée. |
| A12 / moyenne | Retirer une image de recette ne supprime pas le fichier. `AiReferenceStorage::store` compte tous les JPG dans une limite de 100 Mo, sans collecte des références devenues inutilisées. Les extractions de raccord ajoutent aussi des fichiers. | Conserver les fichiers utilisés par une préparation/historique ; nettoyer seulement les fichiers sans référence après délai, avec contrôle des propriétaires et des transactions. Afficher l’espace utilisé ; ne pas confondre retrait de recette et effacement global. |
| A13 / moyenne | Les devis et tarifs reposent sur des constantes datées ; les prix fournisseurs sont hétérogènes. `AI Catalog` expire à 14 jours ; les avertissements de retrait sont indépendants. | Conserver source, unité, conditions et date par variante. Ajouter une vérification opérationnelle du catalogue. Ne pas recopier un tarif API brut « crédits » ou « tokens » comme un prix par seconde. Afficher estimation et maximum si réellement calculable ; ne pas promettre un plafond garanti par le fournisseur. |
| A14 / moyenne | Le devis fige la recette et les tarifs mais ne photographie pas tous les paramètres d’exécution de l’adaptateur. Des constantes de modèle peuvent évoluer avant un travail en attente. | Inclure version/empreinte des paramètres effectifs dans les devis et l’historique, et invalider une confirmation devenue incompatible. Conserver les identifiants durables, ne jamais réaffecter un ID à un autre endpoint. |
| A15 / moyenne | LTX 2.3 Fast texte 720p limité à 10 s, image 720p à 20 s, alors que l’adaptateur fixe 25 FPS et la matrice officielle permet 20 s dans les deux cas. | Décrire et tester les contraintes conditionnelles exactes ; choisir une restriction de produit volontaire ou aligner les variantes. Ne pas fragmenter inutilement en plusieurs appels. |
| A16 / moyenne | Couper le son au montage ne coupe pas la génération audio, activée dans plusieurs adaptateurs ; des clips indépendants ont des ambiances distinctes. | Séparer « générer de l’audio » et « conserver l’audio au montage ». Faire varier le devis si le fournisseur le permet, sans supposer qu’un modèle sans son sait reprendre une piste précédente. |
| A17 / moyenne | Une demande incertaine sans identifiant distant est bien bloquée, mais l’interface renvoie à l’historique fournisseur sans parcours guidé pour rattacher le résultat (`AiGenerations::retry`, `AiEpisodeFlow::resume`). | Prévoir une récupération du même résultat/identifiant, vérifiée pour le propriétaire, le compte et l’endpoint ; aucune nouvelle soumission payante. |
| A18 / moyenne | Le récapitulatif montre les scènes et le coût, mais pas une table claire des références effectivement transmises à chaque appel. | Avant consentement : appel 1/2/3, durée, modèle exact, personnage(s), début, fin, provenance du raccord, audio et estimation. Un futur cadre extrait est annoncé comme futur, pas montré comme un aperçu réel. |
| A19 / faible à moyenne | Le suivi d’épisode recharge la page à chaque changement et s’arrête après trois erreurs ; le statut fournisseur reste récupérable manuellement. | Mises à jour locales ou reprise avec temporisation croissante, bouton de vérification, dates du dernier retour. Ne pas inventer de pourcentage ni de temps restant. |
| A20 / faible à moyenne | La réservation protège la bibliothèque avec 100 Mo par plan + export, mais l’utilisateur découvre tard la contrainte de stockage. Un clip unique est aussi réassemblé. | Montrer le stockage disponible avant le devis ; pour un clip unique déjà conforme, réutiliser la source si aucun montage n’est nécessaire. Ne pas annoncer une vraie qualité 1080p parce qu’un clip 720p a été réencodé en 1080p. |
| A21 / moyenne | La suite élargie n’est pas verte : un sélecteur de test attend l’ancien texte alternatif, et un test de scénario compte toute la file `story_writing` sans isoler son message. | Mettre à jour le test du rôle de référence en conservant les vérifications de propriété, et vérifier le message du scénario concerné plutôt qu’un nombre global de messages. Ne pas masquer ces échecs ni purger une file pour obtenir artificiellement un résultat vert. |

## Ce qu’il faut conserver

- Propriété des épisodes/séries/fichiers et validation CSRF côté serveur ; stockage privé et noms sûrs. Les images sont validées et réencodées sans métadonnées.
- Préparations figées, révisions et devis hachés ; modification de série distincte des vidéos déjà préparées.
- Transition persistée avant l’envoi fournisseur, absence de renvoi automatique sur résultat incertain, récupération de la même requête.
- Génération ordonnée, limite de concurrence, pause/arrêt des étapes suivantes, conservation des résultats acquis.
- Contrôle du ratio explicite et d’un clip trop court avant de payer le suivant ; récupération et assemblage locaux distincts du coût IA.
- Consentement récurrent limité, vérifié avant production et avant chaque nouvel envoi ; un changement de configuration provoque une nouvelle validation. Validation avant publication.
- Rédacteur local asynchrone, aperçu avant application, aucun fallback texte payant.

L’audit n’a pas établi de faille bloquante de propriété ou d’envoi payant sans consentement dans les chemins examinés. Cela reste une revue ciblée de ce parcours, pas une certification générale de sécurité.

## Parcours cible proposé

| Intention choisie en premier | Exécution | Références | Limite à expliquer |
|---|---|---|---|
| Plusieurs scènes | Un appel par plan, ou génération multi-plan native explicitement choisie | Personnages communs ; cadres propres à chaque plan si utilisés | Plusieurs scènes signifient des coupes. Références communes ≠ mêmes premières images. |
| Un plan unique | Un seul appel avec une consigne continue et une durée native compatible | Personnage(s) si acceptés, première/dernière image selon l’endpoint | Un seul fichier n’interdit pas au modèle d’inventer une coupe ; le prompt et la validation doivent le contrôler. |
| Prolonger un clip | Endpoint d’extension vidéo qui reçoit le clip et son contexte | Personnages/cadres uniquement si cet endpoint les accepte | Extension native distincte du raccord depuis une image. Pas de promesse de 60 s continues pour tous les modèles. |

**Un multi-plan natif n’est pas un plan continu.** Kling `multi_prompt`, PixVerse multi-clip ou LTX multi-shot peuvent améliorer la cohérence de scènes dans une seule génération, mais introduisent des changements de plans. Pour une caméra continue, préférer un clip unique dans sa durée native, puis une extension vidéo documentée si nécessaire. Si impossible avec le modèle sélectionné, proposer les alternatives sans les appliquer automatiquement.

Dans Séries, conserver l’univers et les personnages, le style, les préférences de format et de modèle. Dans chaque épisode, figer la version utilisée et son histoire. Dans chaque plan, définir l’action, la durée et ses cadres éventuels. Un nouveau type d’entrée n’autorise jamais implicitement une génération de storyboard payante.

## Tri des modèles proposé

1. Filtrer par intention de réalisation et références réellement disponibles, puis format/durée compatibles.
2. Afficher les capacités combinables : sujet, début, fin, vidéo en entrée, audio, multi-plan natif. Certaines références de personnage exigent aussi un premier cadre : ne pas les rendre mutuellement exclusives.
3. Grouper famille + fournisseur + mode d’exécution ; résolution et audio deviennent des choix conditionnels du même groupe quand le contrat le permet.
4. Présenter quelques choix adaptés, puis le catalogue complet ; tri par coût total de ce projet, pas uniquement prix par seconde. Une dépense en tokens non bornée est marquée indicative.
5. Afficher accès configuré, capacités documentées, parcours testé en simulation, essai réel validé et dépréciation comme des informations distinctes. Un schéma valide ne prouve pas la qualité du modèle.

Implémentation suggérée : enrichir progressivement `AiCatalog` et réutiliser ses règles dans les formulaires/Stimulus, avec des constructeurs de requête explicites par contrat. Étendre les entités existantes pour les images de plans et la version de préparation ; éviter un moteur générique de workflow ou une nouvelle architecture.

## Ordre de réalisation recommandé

| Lot | Contenu concret | Critère de sortie |
|---|---|---|
| 1 — Cohérence avant dépense | Corriger la portée début/fin, retirer Pika des recommandations, supprimer les substitutions implicites, partager les rôles et filtres Studio/Séries, clarifier plan unique/raccord ; sécuriser l’export auto | Même préparation dans les deux entrées ; aucun modèle choisi à la place de l’utilisateur ; table exacte des appels avant consentement |
| 2 — Personnages et plans | Références de personnages communes et limitées par le modèle, cadres propres aux plans, versionnement et conservation des anciens projets, devis adapté aux paramètres effectifs | Deux scènes avec le même personnage et deux cadrages distincts, sans répétition cachée des cadres ; contraintes combinées testées |
| 3 — Continuité native | Plan unique explicite ; prototype simulé multi-plan natif ; extension vidéo uniquement pour l’endpoint vérifié, avec estimation incluant le contexte | Une prolongation reçoit une vidéo, jamais seulement un JPG présenté comme extension native ; aucune reprise/cascade payante implicite |
| 4 — Qualité et exploitation | Prompts par mode, contrôle des références, prévisualisations fiables, collecte des images inutilisées, diagnostic/reprise, meilleure gestion audio | Matrice de non-régression et préparation validées ; ensuite seulement un petit essai réel au coût explicitement confirmé |

## Recette attendue

- Texte sans image, personnage seul, premier cadre seul, début+fin facultatif et obligatoire : vérifier le payload exact pour chaque contrat, y compris API et MCP.
- Deux scènes indépendantes : la référence de sujet reste commune ; chaque premier/dernier cadre correspond seulement au plan choisi.
- Début de vidéo + fin de vidéo : respectivement premier et dernier plan ; aucun cadre recopié en silence dans les plans intermédiaires.
- Plan unique : un seul appel, durée native, paramètres de multi-clip désactivés quand disponibles ; un modèle incompatible n’est pas substitué.
- Extension : bon endpoint et vidéo en entrée, contexte/durée/format/audio et coût affichés ; interruption/reprise sans renvoi payant.
- Réparation d’un plan : prompt révisable, dépendances visibles et accord séparé pour les suivants.
- Format 9:16, 16:9, 1:1 et image 4:5/auto : aperçu et export conformes au choix final. Un clip hors format suspend le suivant.
- Changement de rôle, modèle, résolution ou audio : conserver les textes, vider explicitement les choix incompatibles, recalculer le devis ; consentement périmé refusé.
- Deux images dont les chargements arrivent dans le désordre, image invalide, retrait, annulation du sélecteur et rechargement : aucun aperçu trompeur ni fuite d’URL locale.
- Série modifiée, ancien épisode préparé, récurrence active puis pause, crédits/clé indisponibles, import en échec : conserver les résultats et ne demander aucun nouvel appel payant implicite.
- Clavier, lecteur d’écran, mobile, retour navigateur et onglet réouvert : état réel visible sans perdre une saisie non enregistrée.

## Pédagogie : comment lire cette partie de Symfony

Les contrôleurs traitent les routes et les formulaires ; les formulaires valident l’intention. `AiCatalog` décrit les choix compatibles. `AiEpisodeFlow` fige la préparation, calcule le devis et orchestre les plans. `AiGenerations` gère l’envoi et la récupération d’un appel fournisseur ; les adaptateurs construisent son schéma exact. Messenger exécute les traitements en arrière-plan. `VideoEdits` assemble les fichiers obtenus. Twig et Stimulus doivent représenter ces règles, sans devenir une seconde source de vérité métier.

Les changements proposés doivent donc d’abord définir les règles et contrats, puis étendre les entités existantes si nécessaire, ensuite adapter les formulaires et l’aperçu, et enfin vérifier les parcours avec des fournisseurs simulés. Les tests logiciels ne peuvent pas garantir la fidélité visuelle ; un essai réel borné intervient après cette recette, pas pendant cet audit.

## Résultats des contrôles de cet audit

- `npm run test:ui` : **112 tests réussis**, zéro échec. Journal ignoré : `artifacts/audit-generation-ui.log`.
- Suite PHP ciblée élargie : **175 tests, 2 873 assertions, 2 échecs** en 13 min 21 s ; exécution avec `APP_DEBUG=0`, exclusivement sur `shortflow_test`. Fichiers : `AiStudioTest`, `AiSeriesTest`, `AiEpisodeFlowTest`, `SeriesRecurrencesTest`, `AiStoryProposalTest`, `AiImageInputsTest`, `AiCatalogGroupsTest`. Journal : `artifacts/audit-generation-php.log`.
- Premier échec : `AiStudioTest::testImageReferenceIsPrivateAndPreparationRetainsTheImageWithoutSubmitting`, ligne 183. Le test attend `Image de départ de cette génération`, alors que le template affiche désormais `Référence visuelle de cette génération`. La modification précédente du libellé n’avait pas été reportée dans ce test. Ses assertions ultérieures ne sont pas exécutées dans cette tentative.
- Second échec : `AiStoryProposalTest::testProposalIsPrivateAndDoesNotCreateAGeneration`, ligne 88. La file globale contient 3 messages au lieu de 1. Ce test ne nettoie/isole pas sa file et la compte globalement, contrairement à plusieurs autres classes qui ont une préparation propre. Cela révèle un défaut d’isolation ; l’exécution isolée sur file vierge n’a pas été effectuée. Les assertions après cet échec ne sont pas validées dans cette tentative.
- Une tentative de nettoyage de `story_writing` dans la base test, protégée par vérification du nom de base, a été **refusée par le contrôle automatique d’approbation**, au motif que la file pourrait contenir des tâches non synthétiques. Aucun contournement ni nettoyage alternatif effectué. Une requête en lecture seule a confirmé les 3 messages `story_writing` et 1 message `background` présents. Leur contenu n’a pas été affiché.
- `node artifacts/audit-generation-repro.mjs` : les deux reproductions synthétiques ont abouti avec leurs assertions. Aucun appel fournisseur ni accès base.
- `git diff --check` : réussi. Aucun contrôle PHPStan/build supplémentaire nécessaire pour cet audit documentaire ; ne pas les compter comme réexécutés dans ce lot.

Ces résultats ne permettent pas d’annoncer « tout passe ». Ils complètent la revue de code et la vérification documentaire des schémas, sans valider la qualité visuelle de tous les modèles ni constituer une nouvelle recette visuelle exhaustive du navigateur. Les sources applicatives et les tests suivis par Git restent inchangés.
