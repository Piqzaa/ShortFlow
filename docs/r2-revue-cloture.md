# R2 — revue globale du 28 octobre 2026

## Décision et état de validation

**Grand R2 terminé, R2.1–R2.7 validés avec limitations explicites.** Ce bilan couvre tout R2, pas seulement ses derniers correctifs. La décision humaine de limiter l'automatisation est mise en œuvre ; aucune fiabilité narrative autonome ni qualité audiovisuelle n'est certifiée. Prochain point exact : **R3.1**, décrire puis valider le parcours commun idée/scènes/récapitulatif.

Le propriétaire a choisi explicitement : **« Préparation automatique, génération après relecture (recommandé) »**. La V1 prépare donc les scénarios récurrents sans autorisation de coût vidéo. Chaque épisode attend la relecture des scènes/prompts puis sa confirmation de devis. L'absence d'alerte n'autorise jamais une vidéo automatique. La publication garde sa validation distincte.

## Confrontation à toutes les tâches

| Tâche | Résultat et preuve | Limite conservée |
| --- | --- | --- |
| R2.1 | Quinze reproductions historiques conservées, corrigées par les lots suivants ; `WriterQualityRegressionTest` et corpus d'audit inchangés en R2.7. | Tests de défauts connus, pas compréhension universelle. |
| R2.2 | [Contrat des scènes parlantes](scenes-parlantes-contrat.md), notices avant proposition, confirmation, réparation et reprise. | Aucun contrat structuré voix/locuteur/lipsync implémenté. Audio disponible ne garantit pas les paroles. |
| R2.3 | Consignes de langue, citations exactes et contrôles ciblés ; [preuves et limites](redacteur-langue-fidelite.md). | Préfixes contenant actions, causalité, filmabilité et propriétés inventées parfois non signalés sur Qwen réel. |
| R2.4 | Packs complets stricts, hashtags séparés dans la réponse brute, diagnostic sûr, aucun retry automatique ni pack incomplet accepté ; application volontaire des champs choisis conservée ; [contrat](packs-sociaux-diagnostic.md). | Structure valide ne certifie pas les faits ni la pertinence des hashtags. |
| R2.5 | Description/scènes distinctes du titre requises avant HTTP et application, textes humains protégés ; [guide](redacteur-contexte-factuel.md). | Le contexte fourni est requis, sa vérité n'est pas certifiée ; le rédacteur ne voit pas la vidéo. |
| R2.6 | [Deux passages fixes, captures et métrologie](r2-evaluation-redacteur.md), 18 essais / 28 appels locaux. | Douze scénarios normalisés à corriger, quatre timeouts éditoriaux, deux refus de contexte ; programme code 1. |
| R2.7 | Relecture indépendante des sources et sorties ; correction éditoriale testée séparément ; limitation effective de toute génération récurrente automatique, notices avant dépense. | Tests simulés et petite mesure locale ne valident ni rendu vidéo, ni débit, ni fiabilité autonome. |

## Correction éditoriale et mesure complémentaire

`LocalStoryWriter::publication()` utilise des consignes plus courtes, un exemple complet limité aux réseaux demandés, une cible de texte bref et **600 tokens de sortie** au lieu de 1 800. Les scénarios gardent 1 800 tokens. Le délai de 180 s, le contexte 4 096, la température 0,6, le modèle, le schéma et le normaliseur restent inchangés. Aucun validateur assoupli, fallback réparateur, troncature ou relance automatique.

Les diagnostics synthétiques conservés montrent que réduire seulement le budget à 450 tokens ne suffisait pas : réponse coupée après environ 97 s avec répétition de hashtags. Le diagnostic ajoutant un motif au schéma produit encore une sortie invalide, vers 69 s ; ce motif n'est pas livré. Un minuscule contrôle de format renvoie la valeur contrainte attendue ; cela ne prouve pas que tous les schémas sont respectés. Le nouveau prompt concis avec exemple et 600 tokens aboutit dans l'essai exploratoire puis dans les deux répétitions suivantes.

Les six essais complémentaires réutilisent **sans modification** les trois cas éditoriaux du corpus R2.6. Ils ne remplacent pas les 18 captures précédentes. Sources applicatives évaluées en arbre de travail, base Git `c8ffe5aad4e51e5e9df2fcbcb4623cc6d2f1982b`, trois empreintes vérifiées, source du programme et captures intégrales dans [l'archive distincte](fixtures/redacteur-cloture-r2.json).

| Cas | Répétition | Temps mural / monotone | Résultat |
| --- | --- | --- | --- |
| Produit | 1 | 41,999085 / 41,981091 s | Pack complet normalisé, un appel |
| Récit | 1 | 23,095250 / 23,064779 s | Pack complet normalisé, un appel |
| Import titre seul | 1 | 0,012647 / 0,012649 s | Refus `context`, zéro appel |
| Produit | 2 | 27,143402 / 27,106530 s | Pack complet normalisé, un appel |
| Récit | 2 | 28,289632 / 28,317441 s | Pack complet normalisé, un appel, erreurs factuelles |
| Import titre seul | 2 | 0,000077 / 0,000078 s | Refus `context`, zéro appel |

Programme complémentaire code 0 pour le contrat structure/refus attendu, **pas six sorties prêtes à publier**. Les deux produits et le premier récit restent cohérents avec les faits fournis. Le second récit invente boîte préférée/favorite, long sommeil et **`#chatgris` pour un chat blanc**. Les produits ont des textes presque identiques entre réseaux : aucune finesse d'adaptation démontrée. Le normaliseur ajoute les hashtags validés aux légendes finales ; les tableaux de hashtags sont bien séparés dans les réponses brutes.

Même poste/modèle local que R2.6 : Qwen3:8b, Ollama 0.40.11, Q4_K_M, i5-9300HF, RAM 15,92 Gio, RTX 2060 / 6 144 Mio. Deux répétitions seulement, ordre et charge non contrôlés ; relais capturant la réponse complète avant relecture via `MockResponse`, pas une recette du worker natif ou du streaming. Les 23–42 s observées ne sont pas un engagement de délai. Aucun nouveau scénario vidéo n'a été réévalué en R2.7, aucune image, vidéo, voix ou publication générée réellement.

## Parcours livré et anciennes autorisations

- Planification hebdomadaire de 1 à 12 scénarios, scènes et références conservées. Aucune clé vidéo nécessaire pour cette seule préparation ; aucun budget fournisseur réservé. Le montant affiché est informatif pour une décision ultérieure.
- Un scénario écrit reste un brouillon manuel, même sans alerte. Une alerte de qualité ne bloque plus à elle seule la prochaine préparation textuelle ; erreur de rédaction, créneau manqué ou changement de source gardent leurs contrôles.
- Après relecture, le devis de l'épisode autorise ses plans et l'assemblage existants. La version vidéo obtenue doit toujours être validée avant préparation de publication.
- Les anciennes autorisations de vidéo automatique sont refusées avant rédaction récurrente et avant tout nouveau POST vidéo en file. Leurs données et historiques ne sont pas effacés. Les appels déjà transmis peuvent encore aboutir et être facturés ; leur récupération reste possible.
- Pour reprendre les anciens plans non transmis : **Revoir les plans restants**, vérifier les prompts et confirmer un nouveau devis. La simple reprise de l'ancienne autorisation automatique est refusée. Une nouvelle confirmation de planification active uniquement les préparations textuelles.
- La confirmation conserve l'identité fournisseur (identifiant de compte MCP ou empreinte de la clé chiffrée, jamais la clé en clair). Pour les occurrences récurrentes manuelles, cette identité est revérifiée avant création d'un plan et avant soumission. Une clé remplacée ou une ancienne confirmation non liée impose un nouveau devis ; zéro POST dans la reproduction.
- La pause de préparation suspend les anciennes étapes automatiques non transmises ; les générations déjà confirmées manuellement et les publications déjà confirmées restent indépendantes.

`_quality_notice.html.twig` annonce explicitement les incohérences possibles même sans alerte avant les confirmations et reprises. L'aide éditoriale demande de vérifier faits **et hashtags** avant application. Les notices de parole restent distinctes, et les descriptions/prompts manuels restent accessibles. Aucune garantie de voix, de fidélité générale ou de rendu vidéo ajoutée.

## Composants et flux Symfony

1. `SeriesRecurrenceType` collecte une autorisation de préparation textuelle, le contrôleur conserve CSRF et propriété. `SeriesRecurrences` valide la configuration, fige sa source et planifie le message Messenger ; ce formulaire n'autorise plus une dépense vidéo.
2. Le worker écrit et prépare l'épisode, puis `SeriesOccurrence::preparedForReview()` le place en gestion manuelle. Il n'appelle ni réservation ni confirmation vidéo. Le créateur retrouve son brouillon dans la série.
3. Le parcours existant de devis/relecture appelle `AiEpisodeFlow::confirm()` après action volontaire. L'identité est conservée dans le champ existant `flowAccount` : aucune migration. Les plans se mettent ensuite en file selon le moteur existant.
4. `SeriesGenerationConsent` vérifie les anciennes autorisations et l'identité sous verrou propriétaire. `SeriesReviewRequired` distingue un refus **avant envoi** d'une réponse fournisseur incertaine ; `AiGenerations` laisse ce plan en attente de révision, sans prétendre à une éventuelle facturation.
5. Les templates montrent la limite avant coût ; le normaliseur et l'application volontaire des textes conservent les protections des saisies humaines.

À comprendre : un formulaire valide ne suffit pas à autoriser un worker différé. Le service métier doit revérifier le consentement et les données au moment de l'effet externe. Les verrous et les statuts ne jugent pas la qualité narrative ; la relecture humaine reste une étape produit.

## Contrôles et décision finale

- Suite complète dans un conteneur temporaire dédié, copie Linux des sources et base contrôlée `shortflow_test` uniquement, migrations avant PHPUnit : **761 tests / 8 715 assertions réussis**, 4 min 37,776 s. Les journaux de refus CSRF/propriété/404 sont attendus par les tests ; aucun test exclu.
- Ciblés finaux après la dernière clarification du texte de présentation des séries : **96 tests / 824 assertions réussis** (récurrence, notices, série, packs, contexte, writer et quinze reproductions). Pas de second passage complet après cette seule modification de texte Twig.
- Nouvelles régressions : préparation sans clé vidéo et moteur vidéo désactivé ; ancienne autorisation refusée avant inférence ; ancien job non transmis bloqué, nouvelle confirmation manuelle possible, récupération d'un job déjà transmis sans second POST. La reproduction existante de clé remplacée reste à zéro appel fournisseur ; état désormais `waiting_key`, car refus certain avant envoi, et non issue fournisseur incertaine.
- `phpstan analyse --no-progress --memory-limit=512M` : zéro erreur. CS-Fixer `--dry-run --diff` sur quinze fichiers PHP concernés : zéro correction. Lints conteneur, 19 YAML, **104 Twig** et schéma Doctrine test réussis ; aucune migration créée ou appliquée pour R2.7.
- `npm run check`, `npm run test:ui` (**152 réussis, zéro échec**) et `npm run build` réussis. Le build signale encore le chunk Three.js supérieur à 500 ko et des chemins de polices d'éditeur non résolus ; ces avertissements ne valident pas les performances ou le chargement de ces polices.
- Recette locale synthétique en lecture seule, trois pages rendues HTTP 200 (série sans clé, devis d'épisode, ancien automatique en pause), HTTP externe/inférence interdits dans les fixtures, POST du serveur de recette refusé. À **390 × 844**, notices avant coût lisibles, formulaire ouvert par Entrée, menu mobile ouvert par Entrée et six destinations présentes, pas de débordement horizontal. À **1440 × 900**, série et formulaire vérifiés ; nombre passé à trois, estimation informative mise à jour à 3,15 $ US, aucune activation. Devis et pause historique également vérifiés à **1280 px de large** (nouvel onglet revenu à la taille normale), notice visible, ancien bouton de reprise absent et « Revoir les plans restants » présent ; aucune erreur/alerte console sur cet onglet. Un changement de formulaire déclenche bien la protection de sortie non enregistrée. Dimensions rétablies, onglets et conteneur de recette temporaires retirés. Ce contrôle n'est pas la matrice responsive complète R5 ni la recette landing R6.
- Revue indépendante globale en lecture seule : protections, héritage des autorisations, parcours et quatre sorties brutes vérifiés ; aucune objection bloquante restante identifiée. Les empreintes des trois services concordent avec les mesures. Les incohérences factuelles du second récit et l'adaptation faible des produits restent explicitement documentées.

**Décision de sortie : R2 clôturé pour le parcours supervisé choisi par le propriétaire.** Les défauts connus ont leurs reproductions ou sont annoncés avant dépense ; les limites réellement constatées ont conduit au retrait de la génération récurrente automatique. Les textes humains et validations restent protégés. Les essais vidéo/audio réels et leur coût restent en R7, avec intervention explicite du propriétaire ; aucune dépense requise pour clôturer R2.

Commit local à retrouver avec `git log --oneline --grep="fix: require reviewed generation for recurring stories"`. Aucun push, activation réelle, publication, média généré, paiement, déploiement ni nouvelle installation de modèle.

## Reprise sur un autre poste

Lire ce bilan puis le repère de [la roadmap](roadmap.md). Aucun changement de schéma dans R2.7. Actualiser le cache et **les deux workers `story-worker` et `background-worker`** hors rédaction/génération active pour ne pas laisser l'ancien code autoriser un nouveau POST. Les messages/données historiques restent contrôlés par les nouvelles gardes lorsqu'ils sont consommés. Aucune série réelle activée pendant ce lot, aucun push : le commit local doit être poussé sur demande avant d'être disponible ailleurs.
