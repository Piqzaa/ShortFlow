# Séries → textes → publication → suivi — 7 novembre 2026

Premier lot fonctionnel de l’audit `audit-series-publication-20261107.md`, demandé par le propriétaire. Les séries IA et importées utilisent désormais le même préparateur de publication existant. Aucun nouveau moteur d’envoi, fournisseur, quota commercial ou lancement réel n’est ajouté.

## Parcours utilisateur

À la création/configuration d’une série, « Où publier tes épisodes ? » permet de choisir les réseaux et comptes. Les noms des comptes sont lisibles ; un compte absent peut être connecté plus tard. Ces valeurs préremplissent uniquement les nouvelles préparations. Un compte manquant ne se remplace jamais automatiquement par un autre ; une ancienne identité indisponible reste signalée et doit être vérifiée avant publication. Les destinations communes sont rappelées sur la page de la série. La préparation hebdomadaire conserve ses snapshots existants ; une nouvelle configuration peut reprendre les destinations communes.

Chaque épisode importé ou vidéo IA prête a une action Préparer la publication, puis retrouve son brouillon durable. Pour une vidéo IA sans version déjà choisie, ce clic choisit explicitement la version affichée et prépare son brouillon dans la même transaction : l’ID de la vidéo, la propriété, la révision et les anciennes programmations restent contrôlés. Le choix d’un autre export reste disponible dans l’épisode. Le clic n’autorise aucune nouvelle génération ni publication.

Le préparateur permet d’écrire les textes propres à l’épisode : titre et description YouTube, légende Instagram/TikTok et hashtags dans les textes finaux. Les réseaux et comptes restent modifiables pour cet épisode. Choisir Maintenant ou une date/fuseau conduit au récapitulatif et à la confirmation existants. Enregistrer le brouillon ne programme rien. Après confirmation, retour au bon épisode de la série, qui affiche sa programmation ou le suivi par destination ; le calendrier utilise la même préparation. Les anciennes versions et résultats partiels ne sont pas remplacés silencieusement.

L’aide IA aux textes des épisodes IA reste disponible, avec proposition et application contrôlée. **L’aide rédactionnelle IA des vidéos importées reste le lot suivant**, comme prévu par l’audit ; elles disposent déjà de la rédaction manuelle complète par réseau. La cadence et la confirmation groupée de plusieurs épisodes restent également séparées. Aucune promesse de publication automatique d’une série entière.

Les options « Voix et hashtags » deviennent Style des textes : langue et ton visibles, consignes détaillées et restrictions de hashtags dans un bloc avancé, valeurs anciennes conservées. Le terme ne désigne pas une voix audio. Le montage est présenté comme automatique/avancé ; volume, titre incrusté et cadrage restent inchangés. Les listes de hashtags autorisés/exclus gardent leur sens de restrictions, sans les transformer en hashtags communs à ajouter.

## Conservation et protections

Les épisodes importés gardent leur ID lors d’un déplacement ou renommage de série. Leurs brouillons, textes, comptes et dates restent attachés au même épisode et à la même vidéo. Les positions passent temporairement dans un espace négatif transactionnel pour éviter les collisions d’unicité lors d’une permutation, puis les épisodes retenus retrouvent leurs positions positives.

Un retrait archive l’association au lieu de la supprimer ; réajouter la même vidéo retrouve son épisode. Une programmation ou un état de publication à traiter empêche le retrait jusqu’à annulation ou résolution explicite. La vidéo et les brouillons ne sont pas supprimés. Les épisodes archivés ne sont plus dans la liste courante ; leurs préparations conservées restent consultables dans les préparations/calendrier.

Une préparation liée à un épisode conserve sa source : modification des vidéos bloquée dans le service et dans les routes/formulaires, y compris pour une requête forgée de montage. Propriété et CSRF contrôlés, confirmations révisionnées, double confirmation sans double envoi. Changer les destinations communes ne réécrit ni les brouillons, ni les anciennes occurrences, ni les textes humains ou programmations.

## Comprendre Symfony et les fichiers

- `SeriesPublicationType` est le sous-formulaire partagé. `SeriesPublicationSettings` valide les réseaux, la propriété des comptes et la conservation explicite d’une identité devenue indisponible. `series_destinations_controller.js` masque seulement les champs non concernés sans effacer leurs valeurs ; sans JavaScript, les champs restent dans le formulaire.
- `AiSeries` et `VideoSeries` enregistrent leurs valeurs communes dans `publicationSettings`. `VideoSeriesEpisode.active` conserve l’identité des associations retirées. `VideoSeriesStudio` sérialise les changements sous verrou propriétaire/série et préserve les épisodes existants.
- `PublicationDraft.videoEpisode` complète le lien IA déjà existant. `hasEpisodeSource()` sert aux protections communes. Les routes `app_episode_publication` et `app_video_episode_publication` vérifient la requête avant d’appeler `EpisodePublications` ; celui-ci crée ou retrouve un brouillon lié à la source, sans l’armer.
- `PublicationComposerController`, `PublicationDrafts` et `PublicationDraftController` réutilisent les textes, dates, validations, confirmations et envois existants. Le retour vers la série est calculé depuis les relations enregistrées, pas depuis une URL libre donnée par le navigateur.
- Les partials `series/_publication_settings`, `_destinations_summary`, `_publication_action` et `publication/_series_return` assurent la présentation commune. `EpisodePublications` réutilise `PublicationCalendar` pour les états et les détails par réseau.

Le flux est : formulaire validé → valeurs communes enregistrées → clic explicite sur un épisode/version → brouillon durable → édition des textes et du créneau → récapitulatif → confirmation → suivi dans la série. Les entités assurent les relations, les services portent les règles métier, les contrôleurs traitent les requêtes et Twig/Stimulus présentent ce parcours. L’interface seule n’accorde aucun droit d’envoi.

## Vérifications

Passages ciblés distincts, sans additionner les reprises du même scénario :

| Banc | Tests / assertions |
|---|---:|
| Nouveau parcours complet, dernier passage intégral | 6 / 102 |
| Publications d’épisodes existantes | 8 / 73 |
| Séries importées et archivage | 5 / 73 |
| Configuration et anciennes préparations IA | 3 / 179 |
| Assemblage/résultat/montage IA | 2 / 52 |
| Profil de montage, propriété/CSRF | 1 / 8 |
| Parcours intégré série → assemblage → programmation | 1 / 59 |
| Consentement de préparation récurrente | 1 / 13 |
| Conservation des textes édités et des propositions | 2 / 17 |

Soit **29 tests ciblés / 576 assertions**, exécutés en plusieurs passages, pas une suite PHP globale. Le nouveau banc confirme une vraie programmation future et un envoi immédiat **dans la base de test**, sans appel sortant : une seule demande YouTube synthétique en file après double confirmation, aucun envoi fournisseur consommé. Les autres lectures/préparations ne créent pas d’envoi.

210 frontend, check/build, PHPStan 19 applicatifs, style 25 PHP, 49 Twig, conteneur et schémas test/dev verts. Avertissement connu de gros chunk Vite conservé. Pas de revue indépendante ni de recette sociale réelle revendiquée.

Vingt vues Chrome 320/390/768/1280 : réglages IA/import, changement de réseaux sans perte de compte ou nom, formulaire réellement soumis via FormData, textes, date conservée après changement de mode, confirmation non cochée, programmation et suivi importé. Zéro POST navigateur réel, formulaire imbriqué, débordement, erreur JS ou console ; captures mobile relues. Les médias servis dans ce banc sont synthétiques.

Échecs intermédiaires corrigés : conteneur de test périmé après changement d’injection, imports de classes du nouveau test ajoutés après formatage, attentes correspondant aux anciens libellés, et faute dans la liste d’URL du banc de lecture seule. Derniers passages applicatifs/statique/navigateur verts ; aucun résultat intermédiaire n’est présenté comme une validation finale.

## Activation locale

Migration additive `Version20261107010000`, six SQL applicatifs, appliquée en test puis dev après simulation : deux colonnes de réglages, indicateur actif et lien indexé vers l’épisode importé. Les tables restent au nombre de 29. Compteurs **et empreintes des anciennes valeurs** identiques avant/après migration, hors métadonnées de migration ; les nouvelles colonnes sont exclues de cette comparaison.

Cache dev actualisé. Les quatre workers déjà actifs (`worker`, `background-worker`, `story-worker`, `edit-worker`) ont été redémarrés après garde : zéro message, génération active, épisode actif et montage actif ; aucun service arrêté n’a été démarré. Sept GET authentifiés HTTP200 dans un kernel isolé en transaction de lecture seule : 29 compteurs égaux, clients fournisseurs bloqués, zéro HTTP fournisseur.

Aucune série, vidéo, préparation ou publication réelle du propriétaire créée/modifiée par l’agent ; aucun secret réel déchiffré/affiché, appel IA payant, upload fournisseur, email, publication réelle, push ou déploiement public. L’activation locale ne vaut pas validation d’exploitation R8 ni clôture de R7.

## Essai propriétaire et suite

Dans une série IA prête ou importée, règle tes destinations puis ouvre Préparer la publication. Écris des textes différents pour deux épisodes, enregistre un brouillon avec une date et reviens à la série. Réordonne les vidéos importées puis vérifie que les textes et dates suivent toujours le bon épisode. Tu peux t’arrêter avant la confirmation d’envoi pour relire le parcours.

Relecture propriétaire attendue avant le prochain lot. Ensuite : harmoniser l’aide IA aux textes pour les vidéos importées, puis proposer les dates/cadence de plusieurs épisodes selon le cadrage de l’audit.
