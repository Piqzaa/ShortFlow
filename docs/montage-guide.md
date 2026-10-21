# Montage guidé — premier lot, 21 octobre 2026

Complément livré ensuite : [éditeur visuel et sous-titres locaux](montage-sous-titres.md). Les limites ci-dessous décrivent le premier lot historique.

## Objectif et périmètre autorisé

Le propriétaire demande de retirer « Tes créations » du Studio puis de commencer le montage guidé, pour les imports **et** les vidéos IA. Aucun fournisseur ni essai payant nécessaire : ces deux origines donnent déjà un `VideoAsset` privé. On monte un même type de source, sans dupliquer les parcours ni régénérer l’IA.

Ce premier lot assemble 1 à 8 clips distincts, avec ordre, coupes début/fin, aperçu séquentiel et export MP4. Formats 9:16, 16:9 ou 1:1 en 1080p, 30 images/s ; son original conservé, silence pour une source sans piste audio. L’image entière reste visible avec bandes noires selon le format. Une petite source peut être agrandie sans gain de détails. Pas de recadrage automatique, transitions, sous-titres, musique, voix off ou séries dans ce lot.

Durée totale avant export : 179,5 s maximum, pour laisser une marge au conteneur/encodage sous la limite bibliothèque de 180 s. Chaque coupe dure au moins 0,1 s. Résultat limité à 100 Mo ; 1 place et 100 Mo libres exigés avant le départ, quotas réévalués au worker puis à l’import effectif. La taille finale peut nécessiter de raccourcir le montage. Une seule exportation active par propriétaire, 50 projets maximum. Les réglages sont enregistrés explicitement ; un message indique les modifications non enregistrées. Enregistrer avant une recherche/ajout de clip ou de quitter la page ; aucun dialogue navigateur bloquant ajouté.

## Fichiers et composants

- `src/Entity/VideoEdit.php` : projet propriétaire, titre, format, liste bornée d’identifiants/coupes/ordre, état, problème neutre et référence facultative au résultat. Les clips sont des références JSON validées ; les fichiers sources ne sont jamais modifiés. Si une source est supprimée hors export actif, le projet signale sa disparition. Le résultat utilise une relation `SET NULL` : supprimer la vidéo finale ne supprime pas le projet.
- `migrations/Version20261021000000.php` : ajout de la table et de ses index/relations ; aucune transformation de donnée existante. Retour arrière bloqué si des projets sont présents.
- `src/Form/VideoEditType.php` : formulaire Symfony pour nom/format et CSRF. Coupes et positions sont des contrôles HTML natifs, vérifiés par le service ; aucun chemin disque reçu du navigateur.
- `src/Controller/VideoEditController.php` : choix/recherche paginée, projet, ajout de clip, copie, suivi propriétaire et retrait confirmé. Réponses privées non stockables. GET de choix et de suivi ne créent ni export ni projet. Les POST de création/ajout/copie/retrait exigent leur CSRF ; le POST d’export utilise le formulaire Symfony et une confirmation distincte.
- `src/Service/VideoEdits.php` : règles de propriété, coupes, limites, transactions et orchestration de l’export. Le second envoi d’une confirmation déjà traitée ne crée aucun second message. Verrou de ligne sur propriétaire pour admission/suppression, verrou consultatif PostgreSQL par projet pendant le rendu ; reprise d’un message après arrêt brutal possible, sans doubler un export terminé.
- `src/Service/VideoEditRenderer.php` : FFmpeg via `Process` avec tableau d’arguments, protocoles fichiers seuls, chemins privés générés, filtre et noms temporaires internes. Normalisation H.264/AAC puis concaténation de clips homogènes. Limite mémoire d’allocation, deux threads d’encodage, échéance de rendu, taille plafonnée et inspection finale réelle. Durée/dimensions vérifiées avant l’import. Temporaires nettoyés en sortie, et répertoires temporaires de ce moteur de plus de 24 h retirés au prochain export. Un arrêt brutal peut laisser un temporaire jusque-là ; une panne entre déplacement du fichier et commit doit aussi être traitée dans le futur contrôle des fichiers orphelins de la bibliothèque.
- `src/Message/RenderVideoEdit.php`, son handler et `config/packages/messenger.yaml` : transport Doctrine `video_edits`, même monolithe et même table Messenger, file indépendante de `publications`.
- `compose.yaml`, `docker/php/worker.sh` : `edit-worker` dédié à cette file, 2 CPU/1 Go, un rendu à la fois dans ce processus. Le worker social continue sa file ; le rendu ne monopolise pas son consommateur. Le script existant accepte une variable de file avec défaut publications.
- `src/Service/VideoLibrary.php` : la suppression multiple refuse tout le lot si une source est utilisée par un montage en attente/en cours. Les exports entrent par l’import sécurisé existant ; pas de seconde bibliothèque ni de lecteur public.
- `templates/edit/`, `assets/controllers/edit*_controller.js`, `assets/styles/edit.css` : galerie de choix, reprise de projet, sources/coupes, commandes clavier, lecture séquentielle, confirmation et suivi. Les vidéos et contrôles restent accessibles sans JS ; positions numériques et cases de retrait fonctionnent côté serveur. Sans JS, l’aperçu assemblé est le résultat exporté ; chaque source conserve son lecteur. Avec JS, l’aperçu lit successivement les sources et peut marquer une courte pause de chargement ; le fichier exporté constitue la référence finale.
- Entrées depuis le Studio, la sélection de Vidéos, une fiche vidéo et un résultat IA. Le Studio ne liste plus « Tes créations » ; l’historique reste accessible en haut et la dernière création garde son aperçu. Navigation commune classe correctement le montage dans Studio.

## Flux Symfony à comprendre

1. **GET /studio/montage** : recherche privée dans `VideoAssetRepository`. Aucune différence de traitement import/IA. La sélection d’une page démarre le projet ; un autre clip peut être recherché et ajouté ensuite.
2. **POST de création** : contrôle CSRF + appartenance, crée un projet durable sans toucher aux fichiers. Redirection vers le montage.
3. **POST d’enregistrement** : formulaire Symfony + règles du service ; ordre et coupes enregistrés. En cas d’erreur de coupe, les valeurs soumises des sources déjà connues sont représentées pour correction.
4. **POST d’export confirmé** : vérifications répétées sous verrou, état `queued` et message enregistrés dans la même transaction Doctrine. Rien n’est publié sur un réseau.
5. **Messenger** : handler → `VideoEdits::process()` → `rendering` → FFmpeg → import privé et `ready` dans une transaction commune. Aucun appel IA ou social. L’état `failed` conserve le projet et propose sa correction/reprise manuelle, sans répétition automatique d’un rendu ayant échoué normalement.
6. **GET /etat** : lecture propriétaire seule, interrogée toutes les 4 s en page visible ; aucune génération d’export. Affiche attente/en cours, puis recharge pour le résultat ou l’erreur. Aucun pourcentage inventé, arrêt après trois erreurs, actualisation manuelle conservée.
7. **Résultat** : téléchargement, bibliothèque ou préparation de publication. « Modifier une copie » conserve le premier export. Retirer un projet ne supprime ni ses sources ni son export.

Dans Symfony, le Controller relie HTTP aux composants ; le Form valide les champs standards et le CSRF ; le Service porte les règles de montage et les transactions ; Doctrine conserve le projet ; Messenger déplace le calcul hors de la requête web ; Twig/Stimulus présentent et améliorent le parcours. Un worker séparé est un processus du même monolithe, pas un nouveau service applicatif distant.

## Installation et essai manuel

1. Appliquer les migrations : `docker compose exec php composer db:sync`. Démarrer `docker compose up -d --no-deps edit-worker`. Ne pas utiliser `schema:update --force`.
2. Importer un clip ; générer une vidéo IA uniquement si l’on souhaite aussi tester ce parcours et après sa confirmation de coût habituelle. Une vidéo IA déjà dans la bibliothèque suffit au test de montage.
3. Studio → Montage guidé, choisir les deux vidéos ; ou Vidéos → sélectionner → Monter la sélection. Régler ordre, début/fin et format, enregistrer. Recharger et vérifier la conservation.
4. Tester « Lire l’assemblage », puis confirmer l’export. Quitter/revenir pendant le rendu ; vérifier son résultat et le téléchargement. Les sources doivent toujours se lire.
5. Vérifier qu’une seconde confirmation ne double pas l’export, qu’une source active ne se supprime pas et qu’un autre compte n’accède ni au projet ni à son état.
6. Tester une fin avant le début, au-delà de la source, un projet trop long et une bibliothèque pleine : correction compréhensible, aucune vidéo finale ajoutée. Tester ajout/recherche d’un autre clip, modification d’une copie et retrait du projet.
7. Tests automatisés exclusivement sur `shortflow_test`, migrations avant PHPUnit. Résultats dans [verification.md](verification.md).

Références techniques : [filtres FFmpeg](https://ffmpeg.org/ffmpeg-filters.html) et [concaténation des formats FFmpeg](https://ffmpeg.org/ffmpeg-formats.html#concat-1).
