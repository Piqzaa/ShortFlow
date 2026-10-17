# Publier un Reel depuis la bibliothèque

## Objectif

Sélectionner une ou plusieurs vidéos privées, choisir Instagram Reels dans la préparation, modifier chaque légende puis confirmer. Chaque vidéo devient une publication distincte. La page Publications suit les envois Instagram et YouTube ; la bibliothèque affiche les deux icônes si la même source a réussi sur les deux réseaux.

Le formulaire Instagram individuel décrit ici reste disponible. Depuis la bibliothèque, le parcours principal prépare désormais YouTube, Instagram et/ou TikTok avec une confirmation commune et conservation des textes enregistrés dans la session : voir [publication-multireseaux.md](publication-multireseaux.md). Aucun recadrage, musique, montage, programmation ou génération IA.

## Pourquoi HTTPS est nécessaire

Le [guide officiel de publication Meta](https://developers.facebook.com/documentation/instagram-platform/content-publishing/) consulté le 16 octobre 2026 réserve explicitement l’envoi avec reprise à Facebook Login for Business. VydFlow utilise Instagram Login : le worker fournit donc un `video_url` que Meta doit pouvoir télécharger. La [référence media](https://developers.facebook.com/documentation/instagram-platform/instagram-graph-api/reference/ig-user/media) décrit les limites des Reels, légendes et conteneurs.

Configurer ultérieurement `INSTAGRAM_MEDIA_BASE_URL=https://adresse-vydflow` dans l’environnement privé, seulement sur un hébergement adapté. La valeur par défaut est vide et bloque les envois réels. Cette étape ne crée ni tunnel, ni hébergement, ni accès public au serveur de développement. Le callback OAuth et la revue Meta restent aussi à valider séparément.

Le téléchargement utilise une URL HMAC propre au job, valable au maximum 24 heures depuis sa création. La signature dépend d’APP_SECRET : utiliser un secret fort propre à la production, jamais celui public des tests. Seule cette vidéo est accessible aux détenteurs du lien ; le lien est une autorisation temporaire, à ne pas partager ni journaliser dans un proxy. Il cesse de fonctionner après réussite, arrêt ou expiration. Aucun jeton OAuth dans le lien, HTML ou message Messenger. La bibliothèque conserve son contrôle propriétaire. GET/HEAD et Range sont gérés par BinaryFileResponse ; pas d’index public des fichiers.

## Fichiers et rôles

- `InstagramPublication` et migration `Version20261016164818` : source, propriétaire, compte destinataire figé, légende/options, conteneur Meta, résultat et tentative de publication persistée. Suppression du compte en cascade ; suppression d’une vidéo terminée conserve l’historique sans source.
- `InstagramPublicationController` et `InstagramPublishType` : préparation en session pendant une heure, clé distincte par onglet, CSRF, confirmation obligatoire, contrôle propriétaire, compte inchangé, limiteur partagé avec YouTube. Jusqu’à 20 vidéos par lot.
- `InstagramPublishing` : vérifie la disponibilité, bloque les formats manifestement incompatibles et crée jobs + messages dans une transaction. Le verrou propriétaire et la clé de soumission empêchent les doubles clics et changements de compte concurrents.
- `InstagramApi` : crée un conteneur REELS avec la légende, share_to_feed et is_ai_generated ; lit status_code ; publie le conteneur. Hôte fixe graph.instagram.com, jeton déchiffré uniquement côté serveur, erreurs fournisseur masquées.
- `PublishInstagram` et `PublishInstagramHandler` : Messenger exécute les étapes via le worker Docker existant. Aucun lancement de terminal nécessaire pour chaque publication.
- `InstagramMediaLink` et `InstagramMediaController` : signature, expiration et lecture du seul fichier autorisé. Pas de cookie/session utilisateur requis pour la récupération par Meta.
- `publication/instagram_prepare.html.twig`, `_instagram_jobs.html.twig` et `library/_published.html.twig` : préparation, états/reprise/arrêt, badge. `VideoAssetRepository` interroge les succès par réseau et propriétaire ; `VideoLibrary` bloque la suppression des sources actives, suspendues ou à vérifier.

## Flux d’exécution et protection contre les doublons

1. Le formulaire Symfony valide la légende (2 200 caractères, 30 hashtags), les options et le consentement explicite.
2. Doctrine crée chaque job et son message dans la même transaction. Le POST répond 303 vers le suivi.
3. Le worker contrôle le compte et sa validité, puis demande le conteneur. Il persiste l’identifiant avant de demander la publication.
4. Une vérification par message, espacée de 60 secondes : IN_PROGRESS attend ; FINISHED autorise la publication ; ERROR/EXPIRED termine en échec. Après cinq vérifications sans fin de traitement, le suivi est suspendu et peut être repris sur le même conteneur. Après cinq erreurs réseau, suspension également.
5. Avant le POST media_publish, le worker persiste `publishAttempted`. Il n’émet jamais une deuxième fois ce POST pour ce job. Une réponse perdue est suivie par lecture du conteneur : PUBLISHED prouve la réussite, même sans identifiant du média reçu. Le lien de suivi ouvre alors le profil, sans inventer de permalien.
6. Si le résultat reste incertain après cinq lectures, état « À vérifier sur Instagram » : aucune reprise automatique de publication. L’utilisateur vérifie son profil avant tout nouvel envoi. Arrêter le suivi révoque le lien et ne supprime aucun contenu distant.

Une réponse de création perdue peut laisser un conteneur orphelin expirant chez Meta ; il ne sera jamais publié par VydFlow. Le mécanisme garantit l’absence de nouvelle tentative automatique de publication après un résultat incertain, au prix d’une vérification manuelle.

## Formats et limites

Contrôles locaux sur les métadonnées privées : MP4 H.264, durée minimale 3 secondes, largeur maximale 1 920 pixels. Les limites générales d’import (100 Mo, 3 minutes, audio AAC) continuent de s’appliquer. Meta contrôle également cadence 23–60 i/s, encodage progressif, débit et organisation MP4 ; ces caractéristiques ne sont pas toutes validées localement ni corrigées automatiquement. Export recommandé : vertical 9:16, H.264/AAC, MP4 faststart, cadence 25/30 i/s. Une vidéo refusée reste dans la bibliothèque et son erreur est visible dans le suivi. Le quota distant est appliqué par Meta ; aucune promesse de capacité illimitée.

Les jetons expirés imposent une reconnexion ; pas de renouvellement silencieux dans ce lot. La carte prévient sept jours avant expiration. Les tests simulés ne démontrent ni l’approbation de l’application Meta, ni la compatibilité de la configuration réelle.

## Comment tester et comprendre Symfony

Exécuter `docker compose exec -T php php scripts/check.php` : migration shortflow_test puis schéma, lints, PHPStan, CS-Fixer et PHPUnit. `npm run check` et `npm run build` vérifient le frontend.

Les tests Instagram utilisent exclusivement MockHttpClient : aucun appel Meta ni vidéo réelle publiée. La fixture reels-valid.mp4 est une couleur synthétique locale de trois secondes, sans média tiers. Tests : lot/confirmation, propriété, CSRF, format/légende/compte modifié, état persistant avant HTTP, récupération d’une réponse perdue, refus de republier, lien falsifié/expiré/révoqué, arrêt et déconnexion, protection de la source.

En local sans configuration, sélectionner une vidéo → Préparer → Instagram Reels : aperçu et légende disponibles, bouton désactivé avec explication. Une vidéo de moins de trois secondes affiche son problème. L’essai réel ne doit commencer qu’après configuration Meta/HTTPS et autorisation du propriétaire.

À retenir en Symfony : le contrôleur traite HTTP, le FormType valide et protège le POST, le service coordonne la transaction, l’entité garde l’état durable, et Messenger découple l’envoi de la requête navigateur. Le worker est un processus long : le redémarrer après modification des handlers. Le message ne transporte qu’un identifiant ; le handler recharge les données et revérifie le compte.
