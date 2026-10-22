# Éditeur visuel, recette et sous-titres locaux — 21 octobre 2026

## Pourquoi ce changement

Le propriétaire juge le premier montage trop proche d’un formulaire. Il demande une inspiration des éditeurs existants et un chemin automatique, utilisable pour imports et générations IA. Il choisit une solution gratuite et efficace plutôt qu’un nouveau fournisseur payant.

Les parcours officiels [CapCut](https://www.capcut.com/help/how-to-recognise-subtitles), [VEED](https://www.veed.io/tools/add-subtitles) et [Descript](https://www.descript.com/tools/video-caption-generator) inspirent l’organisation : lecteur central, clips sélectionnables dans une timeline, transcription à corriger, style puis export. Aucun code, logo ou visuel propriétaire de ces produits repris.

Le Studio corrige également les deux onglets visuellement actifs : seule la présence de `aria-current="page"` donne le trait/couleur actifs. Le lien voisin reste neutre.

## Ce qui est disponible

- Un lecteur central, une tête de lecture globale, une timeline de clips proportionnels à leur durée. Sélection, glisser-déposer et flèches pour réordonner ; curseurs de début/fin et valeurs précises pour couper. L’aperçu suit les coupes et affiche le cadrage, le titre et les sous-titres du moment. Les clips IA et importés utilisent toujours le même `VideoAsset` privé.
- Panneaux Clip / Sous-titres / Style. Recadrage centré pour remplir ou image entière avec bandes. Titre d’ouverture facultatif (nom du montage, premières 2,5 s), styles blanc ou jaune contrasté. Les styles sont de vrais réglages du rendu, pas seulement une décoration de l’aperçu.
- « Générer les sous-titres » : sauvegarde d’abord les coupes, extrait localement l’audio et lance la transcription dans le worker dédié. Français par défaut, anglais ou détection automatique possibles. Retour au projet pour relire/corriger texte et temps ; blocs vides ignorés, 300 maximum, 160 caractères par bloc, aucun chevauchement accepté. Export privé SRT facultatif et incrustation dans le MP4 final.
- « Préparer l’assemblage » conserve les clips, leur ordre, leurs coupes, le format et le cadrage choisis. La case « Ajouter les sous-titres automatiques » est facultative et décochée. Aucun titre ni recadrage imposé, aucun export ou post implicite. Les sources sont conservées.
- Depuis l’import ouvert par Montage, le résultat est présélectionné pour le montage. L’import seul ne crée ni projet ni travail d’export.

Cette recette n’analyse pas le récit, ne choisit pas les meilleurs moments, ne coupe pas les silences et ne synchronise pas au rythme. Une piste musicale peut être importée séparément et mixée. Ce sont des lots suivants. On ne promet pas une transcription instantanée ni une exactitude parfaite. Une voix absente ne donne pas de sous-titres inventés. Une voix synthétique, une musique forte, plusieurs personnes ou une mauvaise langue peuvent augmenter les erreurs ; le texte est à relire. L’aperçu séquentiel peut charger entre sources ; le fichier exporté est la référence.

## Architecture Symfony et fichiers

- `VideoEdit` : ajoute `settings` (fit, captionStyle, intro, langue) et `captions` (temps/texte). La migration additive `Version20261021010000` remplit les montages existants avec des réglages neutres, conserve les sources et bloque le retour arrière s’il perdrait des sous-titres/réglages. Les coupes changées effacent leurs sous-titres ; un changement de nom, format ou style conserve le texte. Export avec style activé mais sans texte bloqué pour éviter une fausse promesse.
- `VideoEditType` valide les choix ; `VideoEditController` utilise le même POST avec CSRF pour enregistrer, demander les sous-titres, préparer la recette ou exporter. SRT et état exigent le propriétaire connecté ; réponses privées et non stockables.
- `VideoEdits` conserve les règles, quotas et export idempotent ; vérifie les réglages et les blocs de sous-titres. Une copie d’un projet conserve les sous-titres valides. `VideoLibrary` bloque aussi la suppression d’une source en cours de transcription.
- `VideoTranscriptions` + `TranscribeVideoEdit`/handler : file Doctrine `video_edits`, même worker local que le rendu mais distinct des publications. État `transcribing`, verrou PostgreSQL par projet et admission sous verrou propriétaire. Aucun débit fournisseur. Retour `draft` avec texte, ou avec message si aucune parole ; échec neutre corrigeable sans perdre les sources. La double réception d’un travail terminé ne relance pas la transcription.
- `EditAudio` : FFmpeg produit un WAV mono 16 kHz privé, dans l’ordre et aux coupes du projet, silence pour une piste absente. Arguments séparés, entrées privées validées, protocoles fichiers seulement et durée bornée. Nettoyage normal et reprise des temporaires abandonnés de plus de 24 h au prochain traitement.
- `SpeechTranscriber` est le contrat de la frontière de calcul, simulable en tests. `LocalSpeechTranscriber` appelle `scripts/transcribe.py` via `Process` ; sortie JSON bornée par 300 blocs, délai borné, erreurs neutres. Le script utilise [faster-whisper](https://github.com/SYSTRAN/faster-whisper) 1.2.1, CTranslate2 CPU/int8, 2 threads, alignement des mots et détection de parole. Groupes courts de cinq mots pour les sous-titres.
- `docker/edit/Dockerfile` : image locale `vydflow-edit`, modèle [Systran/faster-whisper-small](https://huggingface.co/Systran/faster-whisper-small), révision `536b0662742c02347bc0e980a01041f333bce120`, chargé à la construction. Pas de téléchargement du modèle pendant une transcription : `local_files_only=True`, `HF_HUB_OFFLINE=1`, télémétrie désactivée. Aucun audio personnel envoyé au dépôt du modèle. Dépendances Python, FFmpeg/libass et police DejaVu incluses. Worker limité à 2 CPU / 2 Go. Le modèle Base essayé d’abord est remplacé par Small ; les erreurs constatées sur voix synthétique ont motivé ce choix, sans prétendre à un benchmark général.
- `EditCaptions` valide les temps/textes, génère SRT et ASS. Les caractères de contrôle ASS sont neutralisés ; les textes restent échappés dans Twig et assignés par `textContent` dans l’aperçu.
- `VideoEditRenderer` applique le cadrage demandé ; après assemblage, FFmpeg incruste les sous-titres ASS et le titre d’ouverture. Titres passés par fichier interne avec expansion désactivée ; aucune instruction de filtre issue du texte utilisateur. Les résultats rejoignent toujours l’import privé existant.
- `templates/edit/`, `edit_controller.js`, `edit.css` : aperçu unique et timeline, inspecteur unique, modes mobiles empilés sans débordement horizontal. Sans JS, les contrôles de source/coupes restent présents et les champs de sous-titres peuvent être saisis ; l’export produit l’aperçu final. Avec JS, les coupes invalident aussi l’aperçu des anciens sous-titres pour éviter un affichage trompeur. Les modifications sont enregistrées explicitement.

### Flux à comprendre

POST authentifié et CSRF → FormType → `VideoEdits::save()` → `VideoTranscriptions::queue()` → message Doctrine → worker → WAV temporaire → Python hors ligne → validation des blocs → JSON dans le projet → correction dans Twig/Stimulus → export confirmé → ASS/FFmpeg → nouvelle vidéo privée. Messenger exécute le calcul hors de la requête web ; le GET de suivi ne fait que lire un état.

## Installation et test

1. `docker compose exec php composer db:sync`, puis `docker compose build edit-worker` et `docker compose up -d --no-deps edit-worker`. Téléchargement initial de dépendances/modèle nécessaire ; pas de compte fournisseur, de wallet ni de facturation IA.
2. Activer `LOCAL_CAPTIONS_ENABLED=1` dans `.env.local` ignoré ; le défaut versionné reste à 0. Rafraîchir les caches et recharger les workers. Cette activation a été autorisée et réalisée sur le poste actuel ; la génération IA payante conserve son réglage précédent.
3. Choisir une vidéo contenant une voix, importée ou IA. Sélectionner la langue, générer les sous-titres, quitter/revenir pendant le travail, relire le texte, choisir le style puis exporter après confirmation. Vérifier aussi SRT, copie, autre compte et vidéo sans parole.
4. Modifier une coupe : les anciens blocs sont retirés et doivent être régénérés ou saisis. Changer seulement le titre ou le style : ils restent conservés. Tester le recadrage « Remplir » avant export : il peut couper les bords, aucun suivi automatique du visage n’est annoncé.
5. Les tests automatisés restent exclusivement dans `shortflow_test`, sans publication ni API IA réelle. Export incrusté réel testé avec médias fictifs ; moteur Python réel testé sur son/silence synthétiques et l’extrait vocal public de [whisper.cpp](https://github.com/ggml-org/whisper.cpp/tree/master/samples). L’extrait public parlé anglais est reconnu ; les voix françaises synthétiques produisent des erreurs et ne constituent pas une garantie de précision sur les vidéos du propriétaire, qui restent à tester par lui.

La transcription ne coûte pas de crédits fournisseur, mais utilise le CPU, la mémoire et le stockage du serveur. Elle n’est pas une promesse future d’hébergement gratuit ou de traitement illimité.

## Extension demandée : mini éditeur et audio

Le propriétaire précise le 21 octobre : une timeline simple inspirée des usages CapCut/TikTok, séparation au curseur, ordre des fragments, sous-titres optionnels, musique, puis séries cohérentes. Pas de logiciel de montage professionnel complet.

- Le bouton « Séparer au curseur » crée deux fragments indépendants d’une même source, sans recopier la vidéo. 8 fragments maximum ; les bornes restent validées sur le serveur. Chaque fragment a une identité de ligne dans Stimulus, distincte de l’identifiant de la vidéo source. L’ordre et les coupes sont conservés dans le JSON du projet ; copie, transcription et rendu acceptent ces fragments.
- Trois pistes visibles : vidéo, blocs de sous-titres sélectionnables, audio. Pas de fausse forme d’onde ; les barres représentent les durées.
- Import audio privé MP3/WAV/M4A/OGG, 20 Mo, 10 minutes maximum ; FFprobe vérifie un véritable flux audio seul, puis FFmpeg normalise en AAC. Aucun téléchargement de son tiers par le serveur. Confirmation des droits d’utilisation dans le formulaire.
- Panneau Audio : volumes des sources et de la musique, début dans la piste, répétition jusqu’à la fin ou arrêt naturel. La piste commence au début du montage. Le mix final FFmpeg combine les volumes, garde la durée du montage et limite les crêtes. La transcription porte sur les voix des clips sources, avant l’ajout musical.
- L’aperçu synchronise une piste audio navigateur sur le temps global ; l’export est la référence pour la synchronisation et le limiteur final.
- `EditMusic` gère validation, normalisation, noms aléatoires et chemins privés ; `VideoEdits` verrouille les changements contre un export concurrent ; `VideoEditController` protège import/retrait/écoute par propriétaire et CSRF en écriture. `VideoEditRenderer` réalise le mix réel. Copie avec fichier audio indépendant, retrait du projet nettoie sa seule piste, jamais les vidéos sources ni les exports.
- Migration `Version20261021020000` : deux colonnes facultatives de piste, aucune modification des montages existants. Les nouveaux réglages audio ont des valeurs de repli dans l’entité pour les anciens projets. Aucun secret ni fichier audio dans le HTML, uniquement une route propriétaire.

Test manuel : choisir un import ou un clip IA → placer le curseur → séparer → déplacer un fragment → enregistrer → importer une musique autorisée → régler Audio → écouter l’assemblage → sous-titres si souhaités → exporter. Tester aussi la piste plus courte que le montage, son des clips coupé, absence de musique, copie et retrait. Enregistrer avant les imports de clip/musique, qui changent de page.

La différence Symfony à comprendre : l’entité mémorise la recette et les références privées ; le FormType valide la saisie ; le contrôleur applique authentification/CSRF puis délègue ; les services exécutent les règles et le calcul ; Messenger lance le rendu hors requête web. La timeline Stimulus n’est qu’un aperçu interactif : elle ne remplace jamais la validation ni le rendu serveur.

### Bibliothèque musicale : proposition

L’import est disponible. Un lien vers [Pixabay Music](https://pixabay.com/music/) aide à trouver une piste ; il ne constitue pas un catalogue intégré ni une garantie d’absence de réclamation. [La FAQ officielle](https://pixabay.com/service/faq/) explique les éventuelles réclamations Content ID ; conserver la preuve de licence. La [bibliothèque commerciale TikTok](https://ads.tiktok.com/resources/help/article/commercial-music-library?lang=en) vise les usages TikTok et ne donne pas à VydFlow un catalogue redistribuable sur tous les réseaux.

Prochain lot proposé : petit catalogue éditorial de pistes CC0 ou sous licence explicite de synchronisation et de redistribution adaptée au SaaS. Pour chaque piste : auteur, source, licence/version, preuve datée, usages commerciaux et réseaux permis, éventuelle attribution. Pas de récupération automatique des catalogues Instagram/TikTok, ni de fausse promesse « sans copyright ». Aucune musique tierce ajoutée sans choix de licence.


## Reprise de l’ergonomie — mini éditeur direct

Le propriétaire précise qu’il veut manipuler les clips comme dans un mini éditeur, et terminer cette partie avant les séries. Le moteur Symfony/FFmpeg existant est conservé ; l’écran est recomposé.

- `templates/edit/show.html.twig` : barre compacte nom/enregistrer/exporter, bibliothèque de médias à gauche, lecteur au centre, inspecteur contextuel à droite et timeline sur toute la largeur en bas. Export, import musical et actions du projet dans des dialogues natifs : focus contenu, Échap et retour au bouton d’ouverture. Sans JavaScript, formulaires serveur et choix précis restent présents.
- `templates/edit/_clip.html.twig` : valeurs précises disponibles dans un volet replié ; les poignées de la timeline deviennent le moyen principal de raccourcir.
- `assets/controllers/edit_controller.js` : miniatures par fragment, tête de lecture, séparation, réorganisation, poignées tactiles/souris et ajustement clavier par 0,1 s (Maj : 1 s), retrait du fragment sélectionné sans supprimer le média source. Historique local des 15 derniers changements de clips avec restauration des sous-titres valides ; cet historique n’est pas un historique permanent du projet ni une annulation de tous les réglages.
- Ajout depuis la bibliothèque sans navigation, par clonage d’un gabarit Twig échappé. Sources, bornes, quantité et propriétaire toujours revalidés dans `VideoEdits` à l’enregistrement. La recherche/pagination, le bouton retour de l’éditeur, l’import d’une nouvelle vidéo et l’import musical attendent un POST d’enregistrement réussi avant de poursuivre ; un échec ou retour à la connexion bloque cette navigation. Aucun export implicite : le POST de préservation impose `intent=save`. Les autres sorties de l’application conservent le fonctionnement d’enregistrement explicite ; aucun dialogue beforeunload ajouté.
- `assets/controllers/video_thumbnails_controller.js` : cibles ajoutées/retirées dynamiquement, décodage à 0,1 s pour éviter une miniature noire au premier événement loadeddata, deux captures simultanées maximum et libération des flux. La bande répète la couverture du clip : il ne s’agit pas d’une suite de toutes les images ni d’une forme d’onde sonore.
- `assets/styles/editor-desk.css`, chargé dans `assets/app.js` : géométrie d’éditeur à hauteur de fenêtre sur bureau/tablette, outils et bibliothèque à la demande sur téléphone. Les autres pages du studio gardent leur disposition. Aucune nouvelle dépendance ni migration pour cette reprise.

Pour tester : ouvrir un montage → tirer un bord du clip (ou flèches sur sa poignée) → séparer au curseur → ajouter un média à gauche → déplacer/retirer un morceau → annuler → ouvrir Sous-titres/Audio → enregistrer → exporter. Sur téléphone : bouton Médias pour afficher le choix des clips. Le moteur d’export reste le même : Stimulus fournit un aperçu ; les formulaires Symfony et `VideoEdits` restent autoritaires, Messenger exécute l’export FFmpeg en arrière-plan.

## Montage dans le parcours — bibliothèque et préparation

Objectif : un éditeur partagé, accessible au moment où l’utilisateur en a besoin. Le Studio conserve la génération IA ; l’onglet Montage guidé disparaît. La bibliothèque et le résultat IA proposent Éditer ; une sélection de vidéos propose Assembler les clips, distinct de Préparer la sélection (plusieurs publications indépendantes). Les projets sont repris dans Mes montages depuis la bibliothèque et restent séparés des brouillons de publication.

Fichiers et responsabilités :
- `templates/edit/_start.html.twig`, les vues bibliothèque/résultat IA : lancement POST avec CSRF. Sur la galerie, les formulaires sont associés aux boutons sans imbriquer de formulaires.
- `PublicationComposerController` : le bouton Modifier la vidéo soumet d’abord les textes, réseaux et le créneau, même incomplets. Il enregistre le brouillon avant d’ouvrir le montage. Un formulaire périmé garde sa protection de révision.
- `VideoEdit.publicationContext` et migration `Version20261021030000` : identifiants du brouillon et de la source, révision attendue, état appliqué. Ce contexte est construit au serveur et survit à la fermeture du navigateur.
- `VideoEdits::forPublication` crée le projet à partir d’une vidéo de cette préparation ; `VideoEditController::apply` impose propriétaire et CSRF.
- `PublicationDrafts::applyEdit` : verrou propriétaire et transaction Doctrine, export terminé exigé, révision et statut brouillon vérifiés. Seule la source de l’élément concerné change ; ses textes/réglages, l’ordre, les autres vidéos, les comptes et le créneau sont conservés. Double application sans second changement. Une préparation modifiée ou déjà confirmée refuse le remplacement ; l’export reste dans la bibliothèque.
- `templates/edit/show.html.twig` : retour à la préparation, puis Appliquer et revenir une fois le rendu réellement terminé. Aucune publication automatique. Le retour sans application laisse la source initiale dans le brouillon.

Flux : préparation → Modifier la vidéo → sauvegarde → éditeur → exporter → traitement Messenger/FFmpeg → Appliquer et revenir → préparation avec le nouveau média → vérification → confirmation habituelle. Une publication déjà programmée doit d’abord être remise en brouillon depuis le parcours existant avant de modifier son média.

Test manuel : saisir une légende et un horaire, modifier la vidéo, raccourcir, exporter puis appliquer. Vérifier légende, destinations, visibilité et horaire ; vérifier que la source et les autres publications n’ont pas changé. Reprendre aussi depuis Mes montages après reconnexion. Dans un autre onglet, modifier le brouillon pendant le montage : l’application doit demander de rouvrir la préparation, sans l’écraser.

À comprendre en Symfony : un lien GET affiche ; un formulaire POST avec CSRF modifie. Le montage et le brouillon sont deux entités indépendantes. Le contrôle de révision évite d’écraser une préparation plus récente, le verrou sérialise les écritures, et Messenger calcule le fichier avant son application explicite. L’état de la session du compositeur est reconstruit à partir du brouillon durable au retour.

Import depuis le mini éditeur : le lien transmet son identifiant de montage, validé par propriétaire au serveur. L’import revient au même projet et y ajoute le clip après sauvegarde des coupes. Si la limite de huit fragments est atteinte ou si un export a commencé, le fichier reste dans la bibliothèque et un message explique le refus de l’ajout. Annuler l’import revient aussi au montage.


## Lot de fiabilité : sous-titres dans l’éditeur et sauvegarde

Objectif : garder le contexte de montage et les corrections de texte pendant la transcription et les coupes. Ce lot précède les textes libres positionnables et le découpage audio.

Composants :
- `VideoEdit` et migration `Version20261021040000` : révision entière du projet. Les fragments possèdent une origine stable dans la recette JSON ; les anciennes recettes obtiennent des repères de repli sans modification immédiate des données.
- `CaptionTiming` et `assets/edit-timing.js` : conversion des temps de sous-titres entre les anciennes et nouvelles positions des fragments. Une séparation garde l’origine ; un nouvel ajout du même fichier obtient une autre origine. Le serveur assure aussi le recalage pour les formulaires sans JavaScript. Les cas métier communs sont vérifiés depuis la même fixture dans PHPUnit et Node.
- `VideoEdits` : valide les champs et sous-titres, recale ceux du projet lorsqu’ils ne sont pas déjà saisis pour la nouvelle recette, puis vérifie la révision sous verrou propriétaire avant toute écriture. Une page ancienne ne peut pas écraser une version plus récente.
- `VideoEditController` : POST avec formulaire Symfony/CSRF, réponses JSON pour les requêtes de l’éditeur et redirection conservée sans JavaScript. Le suivi privé inclut statut, révision, sous-titres et éventuel message. Aucun contenu d’un autre propriétaire n’est accessible.
- `VideoTranscriptions` : remplace le texte uniquement lorsqu’une transcription exploitable est produite. Les anciens sous-titres restent en cas d’erreur ou d’absence de parole.
- `edit_controller.js` et Twig : sauvegarde temporisée après une seconde d’inactivité ; sauvegardes sérialisées, état de sauvegarde visible et navigation interne ordinaire attendue en cas de modifications. La transcription utilise une requête et un suivi JSON sans rechargement. L’aperçu et la timeline restent présents ; leurs outils de modification sont temporairement inertes, la lecture reste disponible. Fin/échec mettent à jour les blocs et réactivent les outils, sans remettre le curseur à zéro. Après une coupure réseau, le suivi réessaie avec un délai croissant, sans lancer une nouvelle transcription.

Flux : modification → recalage navigateur → POST de sauvegarde avec révision → validation Symfony → contrôle sous verrou → nouvelle révision. Pour générer : sauvegarde et mise en file Messenger → suivi de l’état → insertion du résultat dans le panneau déjà ouvert. Une sauvegarde automatique ne crée ni export ni publication.

À tester : corriger une phrase, séparer le clip, déplacer ses morceaux, retirer un passage et vérifier les temps restants ; ajouter deux fois la même source et garder leurs corrections distinctes. Générer les sous-titres pendant la lecture, constater que l’écran reste visible, puis retrouver les blocs sans navigation. Tester un échec de transcription : ancien texte conservé. Ouvrir deux onglets : la sauvegarde du plus ancien est refusée avec un message, ses champs restent affichés.

Limites explicites : le recalage travaille sur les blocs de texte, sans redistribuer les mots d’une phrase partiellement coupée ; relire ces blocs. Le bouton Annuler reste celui des clips et de leurs sous-titres : l’historique universel, les textes libres et les positions dans l’image appartiennent à la suite. La sauvegarde temporisée ne constitue pas une récupération hors ligne ou après fermeture brutale du navigateur avant son départ. Les limites de fragments/durée et le moteur d’export restent celles du lot précédent.

À comprendre en Symfony : une réponse JSON et une réponse HTML utilisent le même formulaire et les mêmes règles métier. La révision contrôle la concurrence ; le verrou rend l’écriture atomique. Messenger continue le calcul après la requête et le navigateur consulte uniquement le résultat enregistré.
# Textes libres et positionnement — 21 octobre 2026

Objectif : ajouter des accroches indépendantes des sous-titres et modifier leur durée directement dans le mini éditeur, sur une vidéo importée comme sur une vidéo IA.

- Dans **Textes**, ajouter un bloc, saisir son texte, puis le déplacer dans l’aperçu. Sa piste permet déplacement et raccourcissement ; Séparer agit sur le bloc sélectionné au curseur. Retirer, Dupliquer, Annuler et Rétablir complètent ces opérations. Les clips vidéo restent indépendants.
- Dans **Sous-titres**, choisir Haut, Centre ou Bas pour tous les blocs ou le bloc sélectionné ; les coordonnées précises restent accessibles. Une séparation répartit les mots approximativement : relire le résultat avant export.
- L’import d’une vidéo de plus de trois minutes affiche désormais le motif du refus. La vérification navigateur est complétée par celle du serveur ; une erreur JSON reste dans le formulaire avec la sélection et l’aperçu.

## Composants et flux Symfony

`VideoEdit` conserve les textes dans un champ JSON ajouté par `Version20261021050000`. `VideoEditType` porte les réglages de position des sous-titres ; `VideoEditController` transmet les données à `VideoEdits`. `EditTextLayers` contrôle les identifiants, durées, positions, tailles et couleurs avant sauvegarde propriétaire avec révision. Les positions sont normalisées par rapport au cadre pour conserver leur sens à différentes résolutions.

`templates/edit/_text.html.twig` décrit un bloc ; `edit_controller.js` gère aperçu, pistes et historique local. `EditCaptions` produit les instructions ASS autorisées à partir des valeurs validées et échappe le texte utilisateur. `VideoEditRenderer` les applique au rendu FFmpeg. L’aperçu est interactif, mais le serveur valide et fabrique toujours le véritable export : les données du navigateur ne sont jamais une autorité suffisante.

`upload_controller.js` lit la durée du fichier et `VideoProbe` la vérifie côté serveur. `VideoController` renvoie les erreurs de formulaire en JSON lors d’un import asynchrone. La configuration PHP locale accepte 3000 variables pour les formulaires contenant de nombreux blocs ; le fichier INI est monté en lecture seule dans les services web Docker.

## Vérification manuelle

Ouvrir un montage, ajouter une accroche, la déplacer puis la séparer au curseur. Annuler/rétablir, changer la position d’un sous-titre seul puis de tous, enregistrer et rouvrir. Exporter et comparer le résultat. Importer un fichier de plus de trois minutes : le message doit être visible sans perdre le montage. La découpe et le déplacement indépendants de la musique restent le prochain lot ; cette livraison concerne les textes et les sous-titres.

## Fragments audio indépendants — 21 octobre 2026

Objectif : monter la musique et les paroles sans recouper obligatoirement l’image, pour les imports comme pour les vidéos IA.

Dans **Audio**, importer une musique ou choisir **Détacher le son des clips**. Le détachement crée un fragment par clip et règle le son intégré à 0 % pour éviter le doublage. Un clip sans piste sonore reste silencieux. Les fragments se déplacent dans la timeline ; leurs poignées règlent début et fin. **Séparer** coupe le fragment sélectionné au curseur en conservant la continuité du fichier source. **Retirer**, **Dupliquer**, annuler et rétablir sont disponibles. Les réglages précis distinguent le début dans le montage du début dans le fichier, avec volume par fragment et répétition musicale. Les flèches déplacent/ajustent de 0,1 s, Maj de 0,5 s.

Les fragments qui ne se chevauchent pas partagent une ligne ; les superpositions restent accessibles sur des lignes distinctes. La préécoute suit le curseur et libère les lecteurs retirés. Les pistes restent fixées dans le temps si l’image est réorganisée. Un raccourcissement du montage retire la portion audio située après sa nouvelle fin.

### Fichiers et responsabilité des composants

- `VideoEdit` stocke `audioClips` en JSON nullable ; `Version20261021060000` ajoute le champ sans réécrire les projets. Une valeur nulle reprend les anciens réglages musicaux ; une liste vide signifie que les fragments ont été retirés.
- `EditAudioClips` valide les durées, volumes, limites et propriétaires des sources, puis construit le mixage FFmpeg commun. Maximum 32 fragments, aucun chemin ni URL fourni par l’utilisateur. Les arguments restent transmis à Process sous forme de tableau.
- `VideoEdits` sauvegarde les fragments avec la recette et sa révision. Remplacer ou retirer le fichier musical réinitialise uniquement les fragments musicaux ; les sons détachés sont conservés. Une copie reprend les fragments avec sa propre copie du fichier musical.
- `VideoEditController` reçoit les champs du formulaire existant protégé par CSRF. `_audio.html.twig` rend les réglages ; `edit_controller.js` gère manipulations, lecteurs et historique local ; `editor-desk.css` organise les lignes et le petit écran.
- `VideoEditRenderer` utilise le mixage pour l’export. `EditAudio` utilise le même moteur pour extraire les paroles aux bons instants, en excluant la musique ajoutée de la transcription.

### Flux et notion Symfony

La modification passe de Stimulus au formulaire Symfony, puis au service métier qui valide avant la transaction Doctrine. L’export et la transcription reprennent les données enregistrées dans le worker. L’injection du service de mixage permet aux deux traitements de partager les mêmes règles de temps et de volume. L’interface ne remplace jamais la validation du serveur.

### Essai manuel et limites

Importer une musique, déplacer son début, séparer au curseur puis régler différemment les deux volumes. Détacher le son, déplacer un passage de paroles, annuler/rétablir et rouvrir après sauvegarde. Exporter et écouter le résultat. Après modification des paroles, régénérer les sous-titres : leurs anciens textes sont conservés jusqu’au résultat, mais ne se recalculent pas automatiquement pendant un déplacement audio.

Ce lot conserve un seul fichier musical importé par projet, utilisable en plusieurs fragments, avec plusieurs sons de vidéos. Pas de forme d’onde, fondu, effet sonore, catalogue musical intégré ou enregistrement de voix dans cette livraison. La musique non répétée devient silencieuse après la fin du fichier. Les fichiers sources restent dans la bibliothèque et peuvent être retirés par leur propriétaire ; un son dont la source a disparu bloque la validation avec un message explicite.


Suite du 22 octobre : [timeline libre, typographie, effets et médias](montage-timeline-libre.md). Les limites du lot précédent ci-dessus sont historiques.
