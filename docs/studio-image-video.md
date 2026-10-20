# Image vers vidéo — premier lot local

## Objectif et parcours

Depuis Studio, choisir **Animer une image**, importer un JPG/PNG/WebP, vérifier son aperçu, décrire le mouvement puis ouvrir le récapitulatif. L’image accompagne la préparation et sa réutilisation. Aucune génération ni publication ne démarre à l’import. Le bouton de génération nécessite la confirmation du coût, la connexion appropriée et l’activation globale habituelle.

Premier modèle : Kling 2.5 Turbo Pro Image via Fal, 5 ou 10 secondes, sans audio, configuration Pro 1080p. Le format suit l’image (`ratio=auto`) : aucun `aspect_ratio` non documenté ni recadrage implicite envoyé. Les autres modèles texte restent disponibles en mode Décrire une vidéo. Le catalogue contient désormais 22 configurations regroupées en 16 modèles/parcours, dont une configuration image. Pika a été examiné mais n’est pas activé dans ce lot : sa tarification par résolution/durée demande une vérification complémentaire.

## Fichiers et composants

- `AiReferenceType` valide le formulaire multipart et son CSRF ; `AiIdeaType` ajoute source/référence, contrôle leur compatibilité avec le modèle et conserve la sauvegarde d’idée incomplète.
- `AiReferenceStorage` valide le format réel, les dimensions, la taille, réencode par FFmpeg sans métadonnées et stocke hors du répertoire public, dans un dossier propre au propriétaire et à l’environnement. Noms aléatoires, chemins contrôlés, refus des liens symboliques, aucun nom utilisateur repris.
- `AiStudioController` reçoit l’import limité en fréquence, sérialise le quota par verrou propriétaire, sert l’image au seul propriétaire sans cache public et l’attache au récapitulatif. Une référence invalide ne peut pas provoquer une génération d’URL de prévisualisation ni une fuite vers un autre compte.
- `AiGeneration.referenceImage` mémorise le nom privé immuable. La migration additive `Version20261020010000` laisse les générations existantes intactes ; son retour arrière refuse de supprimer un champ déjà utilisé.
- `AiCatalog` conserve un identifiant distinct `kling-25-image` et un tarif daté au 20/10/2026. `AiGenerations` vérifie l’image avant confirmation, reprise et envoi ; une image manquante bloque avant l’appel payant.
- `FalVideoApi` ajoute `image_url` sous forme de data URI JPEG uniquement dans le worker, après confirmation. `FalMcpVideoApi` réutilise ce même constructeur d’entrée avec la connexion choisie, sans changer de méthode d’accès.
- `image_reference_controller.js` montre un aperçu local dès le choix du fichier puis importe dans VydFlow. États chargement/réussite/erreur, timeout, nouvelle tentative, retrait et réponses tardives sont gérés. Les URL locales sont libérées. `creation_controller.js` filtre modèles et résultats selon la source et adapte durée/format ; `app.js` enregistre le nouveau contrôleur.
- Templates Studio/récapitulatif et styles : choix du point de départ, aperçu, retrait et confirmation avec l’image. Sans JavaScript, importer d’abord l’image avec le bouton explicite puis compléter l’idée.

## Flux d’exécution et points Symfony

1. Le navigateur affiche une URL `blob:` locale ; elle n’est pas transmise au fournisseur.
2. POST multipart `/studio/creation/images` avec CSRF → fichier vérifié/réencodé → stockage privé → référence en session/JSON. Aucun job Messenger à cette étape.
3. POST du formulaire d’idée → validation propriétaire/modèle → ligne `AiGeneration` préparée avec la référence → récapitulatif. L’autosauvegarde ne stocke que le nom opaque et les réglages, jamais les octets.
4. Confirmation → vérification de clé/compte/coût/quota/image → Messenger → nouvelle vérification de l’image → envoi Fal API ou MCP → suivi/récupération existants → bibliothèque privée.

À comprendre : le fichier HTTP reçu ne doit jamais devenir directement un chemin de stockage ou une URL publique. Le FormType contrôle la requête ; le stockage vérifie/réencode les octets ; l’entité conserve une référence stable ; le worker reconstruit l’entrée fournisseur. Le choix visuel de Stimulus ne remplace aucun contrôle serveur.

## Limites et données

- Original : 10 Mo, 300–4 096 pixels par côté, rapport entre 2:5 et 5:2. JPEG normalisé sans recadrage à 2 048 pixels maximum par côté, 2 Mo maximum. Une vidéo 1080p ne restaure pas les détails absents d’une petite image.
- Quota de références séparé : 100 Mo par compte, accès privé en lecture. Retirer une image la détache de l’idée ; cela ne supprime pas les références des générations passées. Les fichiers restent conservés ; une gestion/suppression des anciennes références doit précéder une ouverture large de cette fonction. Les fichiers partiels de réencodage en erreur sont supprimés.
- L’envoi inline évite un hébergement public ou un tunnel pour l’image, au prix d’une requête fournisseur plus lourde. Fal documente les data URI, tout en recommandant son CDN pour les gros fichiers ; ce lot borne le JPEG à 2 Mo. Aucun upload CDN anticipé.
- L’intégration API/MCP est vérifiée avec HTTP simulé ; aucun nouveau test payant réel, notamment MCP avec image. `AI_GENERATION_ENABLED=0` reste conservé sur l’installation locale.

## Comment tester

1. Studio → Animer une image → sélectionner une image autorisée. L’aperçu apparaît immédiatement ; attendre l’état enregistré.
2. Vérifier que seuls les modèles image sont proposés et que le format affiche Celui de l’image. Choisir 5 ou 10 s puis vérifier l’estimation au récapitulatif.
3. Revenir au Studio ou réutiliser l’idée : retrouver la même référence. Essayer le retrait puis un nouvel import.
4. Tester un faux JPG, une image trop petite ou un fichier trop lourd ; aucune préparation payante ne doit être envoyée. Une autre session propriétaire ne doit pas lire l’image.
5. Pour un essai réel, autoriser séparément le fournisseur, l’image, le modèle, la durée et le coût, puis activer l’envoi pour cet essai borné.

## Sources vérifiées le 20 octobre 2026

- [Schéma Kling image vers vidéo et prise en charge des data URI](https://fal.ai/models/fal-ai/kling-video/v2.5-turbo/pro/image-to-video/api).
- [Tarification image vers vidéo Fal](https://fal.ai/explore/image-to-video-apis) : 0,35 USD pour 5 s, puis 0,07 USD/s ; tarif également confirmé par l’outil Fal `get_pricing`, soit 0,70 USD pour 10 s, hors taxes/conversion.
- [Fichiers et data URI Fal](https://fal.ai/docs/documentation/model-apis/fal-cdn#data-uris).
