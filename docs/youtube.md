# YouTube — guide du lot publication

## Objectif et état

Publier une ou plusieurs vidéos privées depuis VydFlow vers une chaîne YouTube, avec préparation explicite et suivi indépendant. Le code utilise les API officielles. Les tests automatisés utilisent exclusivement Google simulé et shortflow_test. Les identifiants réels restent dans .env.local ignoré ; le premier essai réel autorisé est consigné dans docs/verification.md. Instagram, TikTok, programmation, montage et IA restent hors de ce lot.

## Les fichiers et leur rôle

- src/Controller/YouTubeConnectionController.php : page Connexions, départ OAuth POST/CSRF, retour avec state à usage unique lié au compte et expiration de dix minutes, déconnexion locale.
- src/Service/YouTubeApi.php : OAuth avec PKCE S256, identification de la chaîne, renouvellement des autorisations, sessions de transfert reprenables, envoi de blocs de 8 Mio et suivi du traitement YouTube. Client HTTP dédié sans traceur de profiler ; erreurs fournisseur remplacées par des messages neutres.
- src/Service/TokenVault.php : chiffrement authentifié libsodium, nonce aléatoire par valeur. Chiffre access token, refresh token et URL de session de transfert. La clé de chiffrement est indépendante du secret Symfony.
- src/Entity/YouTubeConnection.php : une chaîne connectée par compte VydFlow, identité publique de la chaîne et autorisations chiffrées.
- src/Entity/YouTubePublication.php : une demande par vidéo, options figées, chaîne destinataire, état, progression, session chiffrée et identifiant YouTube. La suppression d’une source terminée conserve cet historique avec une source nulle.
- src/Form/YouTubePublishType.php et YouTubeVideoType.php : titre de 100 caractères sans chevrons, description limitée à 5 000 octets UTF-8 sans chevrons, visibilité, audience, déclaration de contenu synthétique réaliste et confirmation. Le nonce de préparation empêche un formulaire périmé dans un autre onglet de confirmer une nouvelle sélection.
- src/Controller/PublicationController.php : préparation à partir des IDs appartenant au compte, limite de 20 vidéos, historique des 100 derniers envois, reprise et arrêt confirmés. La sélection de l’interface porte sur la page courante de 12 vidéos.
- src/Service/YouTubePublishing.php : création atomique des demandes et messages Messenger. Verrou du propriétaire partagé avec la bibliothèque, clé de soumission contre les doubles clics, vérification de la chaîne sous verrou. Limite de 100 envois actifs par compte.
- src/Service/PublicationLock.php : verrou PostgreSQL par propriétaire, partagé par connexion/déconnexion, envoi, reprise et arrêt. Une action concurrente reçoit une réponse invitant à réessayer ; elle ne bloque pas pendant un transfert.
- src/Message/PublishYouTube.php et src/MessageHandler/PublishYouTubeHandler.php : message contenant uniquement un ID, une étape de transfert ou de suivi par exécution. Les jetons ne circulent pas dans la file.
- config/packages/messenger.yaml : file Doctrine publications, seconde file d’échecs techniques, redélivrance après 300 secondes. Migration Version20261014233023 additive, relue : deux tables métier et messenger_messages, indexes et clés étrangères.
- templates/publication et assets/styles/publication.css : connexion, préparation, suivi et arrêt, avec navigation desktop/mobile. assets/controllers/selection_controller.js améliore compteur et sélection globale ; la sélection reste un formulaire GET natif sans JavaScript.
- src/Service/VideoLibrary.php : refuse de supprimer une source ayant un envoi actif ou suspendu. Arrêter l’envoi dans Publications avant sa suppression.
- tests/Unit/YouTubeApiTest.php et tests/Functional/YouTubePublicationTest.php : chiffrement, OAuth, propriété, CSRF, transaction/file, transferts, interruptions, traitement et état incertain. config/services_test.yaml remplace toujours le client réseau par MockHttpClient, même avec la file Doctrine réelle de test.

## Flux d’exécution à comprendre en Symfony

1. Bibliothèque : les cases transmettent uniquement les IDs. Le contrôleur vérifie leur propriétaire ; la session conserve une préparation limitée à une heure et sa chaîne destinataire.
2. Le formulaire Symfony contrôle les données côté serveur et le CSRF. Une confirmation explicite crée les demandes et leurs messages dans une même transaction PostgreSQL. En cas d’échec, aucun envoi partiel du lot n’est mis en file.
3. Le navigateur reçoit une redirection 303 vers Publications. Le worker Messenger, processus distinct, dépile les messages : garder une page ouverte ne lance pas le worker.
4. Le handler récupère la connexion, renouvelle son autorisation si nécessaire, initialise une session YouTube et la conserve AVANT tout octet vidéo. Il interroge cette session avant de reprendre un bloc. Une réponse de transfert perdue est rapprochée avec YouTube, sans recréer une vidéo.
5. Après réception de l’ID distant, il suit le traitement. « Disponible sur YouTube » signifie traitement réussi, pas nécessairement visibilité publique : la page indique la visibilité demandée et fournit le lien. Actualisation manuelle du suivi pour cette V1.
6. Après cinq erreurs fournisseur cumulées, l’envoi est suspendu et peut reprendre avec la même session. Une session expirée passe en « À vérifier » sans recréation automatique, afin d’éviter un doublon. Le traitement non abouti après 24 h suspend le suivi ; Reprendre relance ce délai.

Messenger n’exécute donc pas automatiquement une fonction plus tard par magie : le transport stocke un message, le worker le lit, le handler effectue une étape. Doctrine persiste l’état métier ; la file est transactionnelle sur la même connexion PostgreSQL. Une redélivrance peut refaire une lecture ou une étape de suivi, mais conserve la session distante.

## Configuration locale pour un essai réel ultérieur

L’application démarre sans configuration YouTube. Connexions affiche alors une indisponibilité, et le formulaire permet de préparer les vidéos sans lancer d’envoi. Ne pas mettre de faux compte connecté dans la base dev.

Après autorisation du propriétaire, préparer dans Google Cloud un projet avec YouTube Data API v3, consentement OAuth, utilisateur(s) de test et client de type application Web. Ces opérations n’ont pas été effectuées par Codex dans ce lot. Consigner l’URI de retour exacte :

    http://localhost:8000/studio/connexions/youtube/retour

Configurer exclusivement .env.local (ignoré) :

    YOUTUBE_CLIENT_ID=valeur-du-client-google
    YOUTUBE_CLIENT_SECRET=secret-du-client-google
    YOUTUBE_REDIRECT_URI=http://localhost:8000/studio/connexions/youtube/retour
    YOUTUBE_TOKEN_KEY=cle-base64-de-32-octets-aleatoires

La clé doit provenir de random_bytes(32) et être encodée en base64. Générer/enregistrer localement cette valeur sans la coller dans le chat ni la versionner. Conserver une sauvegarde sûre : changer la clé rend les autorisations et sessions existantes illisibles ; ne pas la régénérer au démarrage. Les identifiants .env.test sont synthétiques et impropres à tout usage réel.

Scopes demandés : youtube.upload pour envoyer et youtube.readonly pour afficher la chaîne et suivre les traitements. VydFlow ne demande pas de mot de passe Google. Le compte est choisi chez Google. La déconnexion efface les tokens de la base VydFlow ; elle n’annule pas les vidéos déjà reçues et ne révoque pas automatiquement l’autorisation Google. Le lien vers les connexions Google permet de retirer celle-ci.

Le worker est désormais un service Docker démarré par `docker compose up -d`. Après une mise à jour de l’image : `docker compose build php` puis `docker compose up -d`. Aucune commande de consommation dans un terminal séparé n’est nécessaire. Dès que la connexion est configurée et qu’une publication est confirmée, son traitement commence automatiquement.

### Le worker et ses composants

- `compose.yaml` : service worker sans port public, partageant l’image PHP, les fichiers privés et la base dev. `restart: unless-stopped` assure sa relance après sortie, notamment après sa limite d’une heure. Un arrêt explicite reste respecté.
- `docker/php/worker.sh` : attend Composer et la table Messenger avant de lancer la consommation. Il n’installe rien et n’applique pas de migration implicitement. Si l’attente échoue, Docker relance le service.
- `docker/php/Dockerfile` : extension pcntl pour recevoir les signaux d’arrêt. Docker laisse cinq minutes au processus pour terminer l’étape en cours ; la session reprenable reste conservée en cas d’interruption.

Le transport Doctrine est la boîte des tâches à effectuer ; le worker est le processus permanent qui appelle le handler Symfony. Le navigateur peut être fermé pendant le transfert. Les limites mémoire/temps évitent un processus vieillissant sans borne ; Docker reprend ensuite la consommation. APP_ENV=dev est fixé pour éviter toute consommation de la base de test.

Comment vérifier : `docker compose ps`, `docker compose logs --tail 30 worker`, puis actualiser Publications. Sans worker, les demandes restent En attente. Après un changement de code PHP ou de secrets .env.local, `docker compose restart worker` recharge la configuration. Ne jamais lancer un worker test pendant PHPUnit. Déploiement futur : traiter les messages techniques échoués et surveiller la file ; cette configuration reste locale.

## Comment tester

- Migrer shortflow_test avant PHPUnit ; scripts/check.php prépare la base, ou utiliser la procédure README. Jamais la base dev pour les tests.
- Tests ciblés : docker compose exec -T php php vendor/bin/phpunit --filter YouTube.
- Parcours local : bibliothèque → cocher des vidéos → Préparer la publication. Vérifier titres, légendes, aperçu, audience/visibilité obligatoires, navigation clavier et mobile. Sans chaîne : envoi désactivé, Connexions explique l’indisponibilité.
- Essai réel ultérieur : choisir une courte vidéo originale, une chaîne de test et visibilité Privée ; confirmer personnellement le compte et le contenu. Vérifier le résultat dans YouTube Studio avant d’annoncer la connexion réelle validée. Une vidéo n’est pas automatiquement un Short : YouTube classe le contenu selon ses propres règles.

## Limites opérationnelles

Pas de transaction atomique avec YouTube : une vidéo reçue peut rester distante malgré un arrêt local. L’action Arrêter ne supprime aucune vidéo YouTube. Les sessions expirées exigent une vérification humaine. Les messages techniques en file d’échecs nécessitent une intervention d’exploitation ; ils ne sont pas automatiquement convertis en statut métier. Les transferts ont un délai réseau maximal de 120 s par requête, inférieur au délai de redélivrance.

La confidentialité publique, les durées de conservation, les exigences OAuth et règles YouTube doivent être finalisées avant lancement. Masquer les paramètres de requête des callbacks OAuth dans les logs d’accès d’une infrastructure publique ; le serveur PHP intégré reste strictement local. La suppression autonome du compte n’est pas dans ce lot.

Sources officielles consultées : https://developers.google.com/youtube/data/reference/rest/v3/videos ; https://developers.google.com/identity/protocols/oauth2/web-server ; https://developers.google.com/youtube/v3/docs/videos/insert ; https://developers.google.com/youtube/v3/guides/using_resumable_upload_protocol . Résultats exacts : docs/verification.md.

## Navigation dans l’application

Un compte confirmé connecté arrive directement au studio en ouvrant /. Le logo du studio mène à sa vue d’ensemble ; le footer conserve seulement Confidentialité. Se déconnecter ramène à la landing. Les cases de sélection et de préparation gardent 14 px d’espace avec leur libellé, même sur plusieurs lignes.

## Essai de configuration réelle — 15 octobre 2026

Le propriétaire a autorisé la préparation d’une connexion réelle YouTube. Projet personnel Google Cloud créé et confirmé dans la console : VydFlow Local, ID vydflow-local, hors organisation. L’ancien projet Google Maps a été conservé. Une clé YOUTUBE_TOKEN_KEY aléatoire a été enregistrée dans .env.local ignoré, sans affichage ; elle doit être conservée.

La fiche YouTube Data API v3 affiche « Échec du chargement » dans le navigateur intégré malgré rechargement. Activation de l’API non confirmée. Aucun client OAuth créé, aucun consentement configuré, aucun compte YouTube connecté et aucune publication. Reprendre dans un navigateur habituel depuis https://console.cloud.google.com/apis/library/youtube.googleapis.com?project=vydflow-local . L’erreur Google indique un problème possible de navigateur/réseau ; sa cause exacte n’a pas été établie.

Le propriétaire confirme que la fiche de l’API s’affiche dans son navigateur habituel avec le bouton Activer. Ce navigateur n’est pas exposé aux outils de cette session ; poursuite guidée manuelle de l’activation et du consentement OAuth. La connexion effective reste à valider.

Le propriétaire a confirmé l’activation de YouTube Data API v3 (capture avec état Activé), configuré l’écran de consentement et créé un client OAuth Web. Il a renseigné lui-même YOUTUBE_CLIENT_ID et YOUTUBE_CLIENT_SECRET dans .env.local. Vérification locale sans affichage : deux champs présents, clé de chiffrement de 32 octets valide, URI de retour conforme et serveur disponible. Cela ne valide pas les identifiants auprès de Google. Première autorisation depuis le compte VydFlow du propriétaire en attente ; aucune publication demandée ni effectuée.
