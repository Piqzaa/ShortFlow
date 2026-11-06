# Upload personnages : erreur PHP et aperçu — 6 novembre 2026

## Cause et correction

Le propriétaire signale `PHP Request Startup: Maximum number of allowable file uploads has been exceeded`, suivi d’un 500, et aucune image visible au choix du portrait.

La configuration Docker imposait `max_file_uploads = 1`, incompatible avec le formulaire portrait + trois vues et les formulaires historiques multi-images. Le log web montre ensuite `headers already sent` puis échec du démarrage de session : le warning de démarrage a déjà écrit la réponse avant Symfony.

La limite PHP passe à 10 fichiers par requête. Les contraintes applicatives restent inchangées : un portrait et trois vues par fiche, limites spécifiques aux autres formulaires, image 10 Mo, vidéo 100 Mo ; `post_max_size=105M` conservé. Dix couvre les neuf entrées possibles des anciens formulaires sept références + début/fin. La bibliothèque vidéo reste un import unitaire.

Le formulaire personnage n’avait aucun aperçu local : seules les images d’une fiche déjà enregistrée étaient affichées. Portrait et vues utilisent maintenant chacun une instance indépendante du contrôleur existant `subject-upload`, avec aperçu entier et retrait d’une sélection avant enregistrement. Aucun upload automatique, appel IA ou changement du stockage privé.

## Vérifications

- Parseur PHP réel via serveur HTTP isolé lié à 127.0.0.1 dans le conteneur : 1, 4 et 9 JPEG synthétiques reçus intégralement, limite 10, codes d’upload tous zéro et aucun en-tête déjà envoyé. Aucun accès à la base dans ce serveur de test.
- Service web PHP redémarré après contrôle : zéro message/génération/épisode/export actif. Aucun worker redémarré. Le serveur de test isolé est arrêté avec ce redémarrage ; inventaire final : seul le serveur web habituel dans le service PHP.
- Vrai serveur web : requête multipart anonyme portrait + trois vues synthétiques vers la route de création, redirection 302 normale vers connexion, aucun warning de démarrage/500. Aucun formulaire de propriétaire soumis, aucune fiche réelle créée par cette requête.
- Trois tests fonctionnels ciblés, 52 assertions : création rapide, remplacement avec vues, accès privé, archivage et fichier invalide avec texte conservé. Les 404 attendus concernent les contrôles d’accès. Six Twig valides, cache dev actualisé. Pas de nouvelle suite globale ni nouveau passage frontend/build : aucun code JavaScript/CSS n’est changé, le contrôleur d’aperçu existant est réutilisé.
- Navigateur 390/1280 sur HTML synthétique : portrait immédiatement visible et décodé, trois vues prévisualisées, retrait d’une vue sans toucher au portrait/autres vues, retrait du portrait, zéro POST avant enregistrement, aucune erreur JS ni débordement. Capture mobile relue.

Scripts de banc locaux ignorés : `artifacts/upload-parser-probe.php`, `check-upload-parser.php`, `check-web-upload-limit.php`, `character-preview-browser.mjs`. Fixtures JPEG synthétiques uniquement.

Aucune génération réelle, coût, clé réelle lue, upload fournisseur, migration, email, publication, push ou déploiement. R7 reste ouvert ; les corrections locales ne valident pas la qualité IA.
