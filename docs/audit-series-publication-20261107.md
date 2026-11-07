# Audit produit : une série doit mener à la publication

Suivi suivant du 7 novembre : **lot 2 livré localement pour relecture**, aide aux textes des vidéos importées, style partagé et application contrôlée. Voir `series-textes-importes-20261107.md`. Le prochain lot après retour propriétaire est la cadence/programmation groupée ; les constats d’origine restent historiques.

Suivi du 7 novembre : **lot 1 réalisé localement, relecture propriétaire attendue**. Voir `series-publication-parcours-20261107.md` pour le périmètre livré, les preuves et les limites. Les constats d’origine ci-dessous décrivent l’état avant ce lot ; l’aide IA aux textes importés et la cadence groupée restent à traiter.

7 novembre 2026 — analyse demandée par le propriétaire après les simplifications d’interface. Lecture du code et des formulaires ; aucune modification applicative, requête propriétaire, inférence ou publication exécutée. Les constats ci-dessous décrivent les chemins du code, pas une recette sociale réelle.

## Diagnostic

La page Séries reste centrée sur la production de vidéos alors que le besoin est de produire **et publier** des épisodes sur des comptes choisis. La simplification précédente a réduit l’affichage sans réunir ces tâches. Les capacités de publication existent déjà, mais leur point d’entrée, leurs valeurs communes et leur suivi diffèrent selon l’origine de l’épisode. Replier les options ne suffit pas.

| Sujet | Existant vérifié | Écart à traiter |
|---|---|---|
| Réseaux/comptes de la série | Choisis dans la préparation hebdomadaire ; `EpisodePublications::prepare` reprend seulement le snapshot d’une `SeriesOccurrence` | Une série créée/préparée manuellement n’a pas ces destinations communes ; le choix doit exister sans activer de préparation automatique |
| Publication d’un épisode IA | `_result.html.twig` affiche la préparation après sélection d’une version ; `EpisodePublications` crée/retrouve un brouillon lié à l’épisode et à sa vidéo | Action enfouie derrière un bloc facultatif et une étape de choix de version ; la liste privilégie Voir la vidéo plutôt que l’étape publication |
| Textes par épisode/réseau | Préparateur : titre/description YouTube, légende Instagram/TikTok, hashtags dans les textes finaux | Pas de vue cohérente depuis la série ; titre interne d’épisode et titre de publication à distinguer |
| Aide IA aux textes | `PublicationEditorial` utilise les scènes, le contexte et `AiSeries.editorialProfile`, avec proposition puis application contrôlée | L’aide n’est exposée que si le brouillon est lié à un `AiEpisode` ; les vidéos importées n’en bénéficient pas dans ce parcours |
| Publication maintenant/date | `PublicationComposerType` expose `immediate`/`scheduled` ; le préparateur dirige vers la confirmation existante | Capacité existante à rendre accessible et visible depuis chaque épisode, avec retour à sa série |
| Publication des vidéos importées | Le lien transmet `videos=[id]` au préparateur générique | `PublicationDraft` ne connaît qu’un lien `AiEpisode` ; aucun lien `VideoSeriesEpisode`, donc pas de suivi fiable depuis la série et risque de créer plusieurs préparations indépendantes |
| Identité des épisodes importés | `VideoSeriesStudio::save` supprime/recrée les associations lors de la sauvegarde de l’ordre | Conserver les identités avant de leur rattacher des brouillons ; un déplacement ne doit ni perdre les textes/dates, ni déplacer une publication vers une autre vidéo |
| Suivi | Résumés IA par version/réseau existent dans `EpisodePublications::summaries` et `_publications.html.twig` | Unifier la présentation pour les deux types et distinguer état de fabrication, état des textes et état de publication |
| Voix et hashtags | 8 indications éditoriales : langue, ton, public, mots-clés, marque, appel à l’action, listes autorisées/exclues | « Voix » est ambigu avec l’audio ; trop de champs de personnalisation exposés pour un usage courant |
| Profil de montage | Volume, titre incrusté pendant 2,5 s, cadrage contain/cover, appliqués aux futures préparations | Option secondaire, à appeler réglages de montage automatique, garder des défauts utiles et déplacer dans les options avancées |

Sources principales : `src/Service/EpisodePublications.php`, `PublicationEditorial.php`, `PublicationTexts.php`, `VideoSeriesStudio.php`, `src/Entity/PublicationDraft.php`, `src/Form/PublicationComposerType.php`, `SeriesEditorialType.php`, `SeriesMontageType.php`, `templates/series/_result.html.twig`, `show.html.twig`, `videos_show.html.twig`, `templates/publication/composer.html.twig` et `_editorial.html.twig`.

## Parcours cible proposé

1. **Créer sa série** : choix IA/mes vidéos conservé, nom/concept puis « Où publier ? » avec réseaux et comptes. Ces destinations sont des valeurs par défaut, modifiables par épisode ; connexion manquante signalée sans empêcher de préparer un brouillon. Format/durée compatibles à examiner en amont et validation effective lors de la préparation de publication. Aucune connexion sociale ajoutée ni compte substitué automatiquement.
2. **Préparer un épisode** : produire/importer sa vidéo, puis accéder directement à « Préparer la publication ». La version affichée est clairement identifiée, avec choix explicite d’un export modifié si nécessaire ; le raccourci ne doit pas supprimer le contrôle de version/propriété.
3. **Textes et destinations** : comptes préremplis depuis la série, textes propres à l’épisode et adaptés au réseau. YouTube : titre, description, hashtags ; Instagram/TikTok : légende et hashtags. Écriture manuelle toujours possible. Aide IA facultative ; les scènes servent au contexte IA, et une vidéo importée peut demander un court résumé sans prétendre analyser sa vidéo. Les champs humains ne sont pas écrasés.
4. **Quand publier ?** : maintenant ou date/heure/fuseau pour cet épisode, puis récapitulatif et confirmation explicite. Sauvegarder reste possible avant d’avoir complété les comptes/déclarations. Rien n’est envoyé simplement en ouvrant la page ou en choisissant les réglages de série.
5. **Revenir à la série** : vidéo/textes à préparer, prête à programmer, programmée avec date, publiée ou problème à reprendre. Détail par réseau si les résultats diffèrent ; ne pas afficher « Publiée » globalement si une seule destination a réussi. Bouton contextuel : préparer la vidéo, préparer la publication, reprendre le brouillon, modifier la programmation ou voir le suivi. Voir/modifier la vidéo reste une action secondaire quand la prochaine tâche est sa publication.

Une seule présentation commune des publications est souhaitable pour IA et import, en réutilisant le moteur existant de brouillons, calendrier et envoi. Éviter un second système de publication dans Séries.

## Réglages à réduire et clarifier

- **Publication de la série** : réseaux/comptes, langue, ton facultatif, éventuels hashtags communs. Les textes définitifs restent propres aux épisodes. Les réglages sociaux spécifiques à confirmer par destination ne doivent pas devenir des valeurs dangereusement implicites.
- **Style des textes** : expliquer le ton rédactionnel, sans le nommer voix audio. Mots-clés, marque, appel à l’action et restrictions de hashtags restent avancés avec anciennes valeurs conservées.
- Attention : `allowedHashtags` est actuellement une **liste blanche qui restreint toutes les propositions**, pas une liste de hashtags à ajouter systématiquement. Ne pas la renommer « hashtags communs » en gardant ce comportement. Un éventuel jeu de hashtags communs doit avoir une sémantique distincte.
- **Montage automatique**, facultatif et seulement pour la fabrication IA : garder volume normal, sans titre incrusté, cadrage existant ; dévoiler les détails sur demande. Il ne génère ni sous-titres, ni musique, ni voix off : ne pas le présenter comme tel.
- **Préparer des épisodes IA en avance**, distinct de **programmer leur publication**. Choisir une cadence de publication ne consent pas à générer des vidéos payantes. Une date proposée n’est pas une publication confirmée.
- Une modification des réglages communs s’applique aux prochaines préparations ; les textes modifiés et les comptes/dates déjà confirmés restent figés tant que l’utilisateur ne demande pas leur changement.

## Ordre de réalisation recommandé — à valider

**Lot 1 : une tranche complète, pas une nouvelle retouche cosmétique.** Choisir les destinations communes, relier durablement les deux types d’épisodes à leur brouillon, exposer la préparation des textes puis maintenant/date via le moteur existant, revenir à la série avec son suivi et simplifier les réglages secondaires. Stabiliser les identités des épisodes importés avant tout rattachement. Valider le trajet complet sur un épisode IA et un importé, sans envoi réel par l’agent.

**Lot 2 : harmoniser l’aide aux textes.** Étendre le contexte/profil aux séries importées, conserver les propositions/applications contrôlées, simplifier les consignes et définir correctement hashtags communs/règles avancées. Si l’IA locale est absente ou en échec, écrire et publier manuellement doit rester possible.

**Lot 3 : organiser plusieurs publications.** Proposer dates/cadence à partir d’une première date, montrer une prévisualisation épisode → réseaux → créneau → état des textes/vidéo. Permettre les corrections individuelles et n’autoriser une confirmation groupée que pour les épisodes prêts et relus. La préparation automatique des scénarios reste une option distincte.

## Critères de recette

- Réseaux/comptes choisis à la création, repris pour un épisode manuel sans `SeriesOccurrence`, modifiables explicitement, sans substitution en cas de compte manquant.
- Un épisode = une version source identifiée + des textes par destination + un brouillon durable ; rouvrir retrouve la préparation au lieu d’en créer une nouvelle.
- Renommer/réordonner une série importée conserve l’identité des épisodes, leurs textes, brouillons et dates. Retirer un épisode n’efface pas silencieusement une vidéo ni une publication programmée ; le comportement doit être explicite.
- Modifier la vidéo après préparation n’envoie jamais la nouvelle version à la place d’une ancienne déjà programmée. Anciennes préparations consultables, conflit expliqué et contrôles actuels conservés.
- Enregistrer/modifier les valeurs communes ne remplace pas les textes humains et ne reprogramme pas les anciens épisodes.
- Les déclarations propres à chaque réseau, les états de connexion, les contrôles de format et les confirmations ne sont pas supprimés pour raccourcir l’écran.
- Maintenant et date passent par le moteur/confirmation existants ; même date affichée dans la série, le préparateur et le calendrier. Résultats partiels visibles par réseau et reprise sans doublonner une destination déjà réussie.
- Mobile/clavier, zéro réseau connecté, brouillon incomplet, modèle local absent, vidéo supprimée, ancienne version programmée et fuseau/changement d’heure couverts. Essai propriétaire nécessaire pour la lisibilité ; les tests automatiques ne suffisent pas.

Audit uniquement : pas de test applicatif, migration, génération, cache/worker modifié, coût, clé lue, publication, push ou déploiement. L’usage du skill local `ui-ux-pro-max` sert à l’analyse du parcours ; ses propositions visuelles génériques ne remplacent pas l’identité VydFlow existante et ses chiffres promotionnels n’ont pas été retenus comme preuves.
