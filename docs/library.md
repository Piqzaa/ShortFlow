# Bibliothèque privée — lot 2, 15 octobre 2026

## Objectif
Passer d’un compte vide à un parcours concret : inscription depuis la landing bêta, confirmation email, connexion, import, lecture, renommage et suppression de ses propres vidéos. La programmation, les réseaux et l’IA restent à venir. Aucun compte fournisseur, service cloud, worker ou paiement nécessaire.

## Limites explicites de la bêta
- Depuis l’import de la bibliothèque, jusqu’à 10 vidéos peuvent être sélectionnées ensemble. Elles sont envoyées une par une et leur résultat est affiché individuellement. Chaque fichier reste limité à 100 Mio (104 857 600 octets) et 180 secondes. Les imports depuis un brouillon, un montage ou une série gardent leur contexte et prennent un fichier à la fois.
- MP4 avec vidéo H.264 8 bits et audio AAC facultatif ; WebM avec VP8/VP9/AV1 8 bits et audio Opus/Vorbis facultatif. Une piste vidéo, au maximum une piste audio. Pas de pièce jointe, sous-titre ou piste de données.
- Dimensions entre 2 et 3 840 pixels par côté. Vertical, carré et horizontal acceptés. Ce sont des limites d’import VydFlow, pas une garantie de compatibilité avec chaque réseau ou navigateur.
- La bibliothèque est limitée à 500 Mo en Free et à 3 Go durant la période d’abonnement, et à 100 vidéos. L’avertissement apparaît à 400 Mo ou 2,5 Go selon l’offre. Le quota en octets s’applique aux imports permanents, exports et réservations IA. Dix fichiers temporaires par compte disposent de places séparées. L’import accepte 10 tentatives par compte sur 15 minutes.
- Aucun transcodage, montage, génération de miniature ni analyse de toutes les images. La sonde vérifie les métadonnées réelles ; un fichier endommagé plus loin peut encore échouer à la lecture. Les codecs effectivement lisibles dépendent du navigateur.

## Fichiers et composants Symfony
- `VideoAsset` dans src/Entity : propriétaire, titre, clé de stockage opaque, MIME, poids, durée, dimensions, codec, date. Doctrine conserve les métadonnées, jamais le contenu binaire dans PostgreSQL.
- `VideoAssetRepository` : requêtes filtrées par propriétaire, agrégat de stockage et pagination par 12. Le dashboard prend les 4 dernières vidéos réelles du compte.
- `VideoUploadType` / `VideoRenameType` : fichiers/champs, contraintes serveur et CSRF. Le champ accept du navigateur améliore le choix, il ne remplace aucune validation serveur.
- `VideoController` : routes sous /studio/bibliotheque, exigence ROLE_VERIFIED, recherche `(id, propriétaire)` pour chaque accès. Une vidéo d’autrui reçoit le même 404 qu’une vidéo inexistante.
- `VideoProbe` : Symfony Process lance ffprobe avec un tableau d’arguments, délai 10 s, allocation élémentaire limitée, protocoles file uniquement, démultiplexeurs MOV/MP4 et Matroska/WebM uniquement, références externes MOV désactivées. Pas d’URL utilisateur ni commande shell. La sortie JSON est limitée aux métadonnées utiles ; stderr n’est ni affiché ni journalisé.
- `PrivateVideoStorage` : fichiers dans var/storage/videos/dev (test dans test, production dans prod), hors de public et ignorés par Git. Noms aléatoires de 48 caractères hexadécimaux + extension canonique. Chemin validé, liens symboliques de fichier refusés, aucune donnée utilisateur utilisée comme chemin. Accès HTTP uniquement via le contrôleur.
- `VideoLibrary` : coordination sonde/stockage/Doctrine ; verrou propriétaire pour sérialiser quota et modifications. Si la transaction d’import échoue, le fichier déplacé est retiré. Un arrêt brutal entre déplacement et commit peut laisser un fichier orphelin : audit manuel et rapprochement avec la base avant toute purge.
- Migration Version20261014222452 : table video_asset, index de stockage unique, FK propriétaire. Aucun effacement de comptes ou de liste d’attente.
- templates/studio/base et templates/library : navigation partagée, galerie, lecteur, formulaire, état vide, quota, pagination et confirmation avant suppression. assets/styles/library.css : responsive et focus hérités.
- `upload_controller.js` : XMLHttpRequest et progression des octets transférés ; à 100 %, le texte indique la vérification serveur. Succès progressif : réponse JSON 201 contenant uniquement l’URL du détail, puis navigation GET, sans chargement intermédiaire. Erreurs HTML conservées dans le panneau, erreur réseau et timeout explicites. Pas de pourcentage inventé pour la sonde. Sans JS : formulaire multipart classique puis redirection 303.
- Docker : ffprobe est installé via le paquet Debian ffmpeg, mais l’application appelle uniquement ffprobe. uploads.ini configure 100M par fichier et 105M pour la requête. Le MP4 de test est un aplat original d’une seconde, pas une vidéo utilisateur transformée.

## Flux d’exécution à comprendre
1. GET /studio/bibliotheque : Security exige un compte confirmé ; le repository filtre les données ; Twig affiche la galerie ou l’état vide.
2. GET puis POST /studio/bibliotheque/importer : Symfony construit un UploadedFile temporaire et valide CSRF, taille, extension/MIME réel et titre. ffprobe vérifie conteneur, pistes, codecs, durée et dimensions.
3. Le service verrouille le compte dans une transaction, vérifie ses quotas, déplace le fichier sous un nom opaque et persiste VideoAsset. Après commit, le contrôleur répond 303 vers le détail. Le stockage et la base sont deux systèmes : la compensation explicite du fichier est nécessaire en cas d’échec.
4. Le lecteur demande /studio/bibliotheque/{id}/lecture. Le contrôleur vérifie de nouveau le propriétaire ; BinaryFileResponse sert le fichier, HEAD et Range (206/416). Cache privé non stockable, nosniff, protection contre l’inclusion depuis un autre site. Aucun chemin disque ni secret dans l’URL.
5. Renommer passe par un formulaire validé. Supprimer demande une confirmation séparée et un CSRF lié à l’id. Le fichier est d’abord déplacé dans une zone de suppression privée, le record supprimé en transaction, puis le fichier purgé. En cas d’échec de transaction, le déplacement est restauré.

## Nettoyage et exploitation locale

Dans **Mes vidéos → Libérer de la place**, les vidéos permanentes les plus anciennes sont proposées en premier, avec leur taille. Les fichiers temporaires ont leur propre liste. Le récapitulatif calcule l’espace de bibliothèque récupérable avant confirmation, et une programmation existante protège son fichier. À l’expiration de la période d’abonnement, la limite Free prend effet. Le compte conserve l’accès à ses vidéos existantes et peut publier depuis son appareil en utilisant ses places temporaires disponibles.

Pour tester le passage d’une offre à l’autre sur un compte fictif en développement, utiliser `docker compose exec -T php php bin/console app:subscription:simulate pilote@example.test paid`, puis la même commande avec `free`. L’écran Abonnement reflète l’offre et la date de fin. La facturation reste à connecter en mode test, avec confirmation des renouvellements et annulations par événements authentifiés côté serveur.

L’entrée **Importer pour publier** marque les téléversements comme temporaires. L’option **Conserver dans ma bibliothèque** au formulaire ou sur la fiche les rend permanents. Les vidéos existantes, les générations IA, les exports de montage, les imports ordinaires et les imports propres à un montage ou une série sont permanents par défaut. Une publication d’un fichier déjà permanent ne modifie pas ce statut.

Le fichier temporaire reste disponible tant qu’il manque un envoi réussi, qu’un brouillon ou un envoi reste à traiter, ou qu’une série, un montage ou un projet IA y fait référence. À la première vérification où il devient éligible, le service fixe un délai de sept jours et affiche la date sur sa fiche. Un nouveau succès ou une protection constatée repousse ou annule l’échéance. La vérification est rejouée sous verrou juste avant de retirer le fichier. L’historique de publication, ses textes et l’identifiant distant restent en base. Une vidéo jamais publiée reste disponible jusqu’à un retrait manuel.

`php bin/console app:videos:retention` simule le contrôle sans écriture. Avec `--apply`, elle enregistre les échéances et retire les fichiers arrivés à échéance. Le service `retention` effectue ce contrôle toutes les heures sur la répétition Compose et s’arrête pendant les sauvegardes. Les archives existantes expirent selon leur propre rétention.

Depuis **Mes vidéos**, **Publier une vidéo** ouvre l’import qui mène directement à la préparation. Lors du retrait automatique d’un fichier, le titre et une miniature JPEG de la première image, si elle peut être décodée, restent privés. La section **Publiées récemment** affiche les six dernières traces et mène au suivi des envois. Les traces ne sont pas des vidéos réutilisables.

Pour le pilote, stocker les fichiers privés sur un volume persistant. Dimensionner le volume au-dessus du total réellement consommé, avec de la place pour les transferts temporaires et les exports, puis prévoir une sauvegarde séparée et une alerte d’espace disque. À 5 Gio par personne, une cohorte de 50 comptes peut occuper jusqu’à 250 Gio de vidéos de bibliothèque. PostgreSQL continue de conserver uniquement les métadonnées de ces fichiers. Pour une cohorte plus grande, évaluer un stockage objet privé et prévoir la lecture, les téléchargements temporaires des workers, les liens de livraison à durée limitée, le trafic et la restauration avant de migrer les médias.
Si la purge immédiate après suppression échoue, le fichier `.deleted-*` reste privé. La commande `docker compose exec php php bin/console app:videos:cleanup-trash` purge uniquement les fichiers de ce motif datant de plus de 24 h et **sans référence active en base**. Elle ne purge pas les fichiers d’import orphelins ni les vidéos actives. Une restauration échouée nécessite un rapprochement manuel ; conserver le fichier tant qu’une ligne le référence. Les fichiers temporaires PHP rejetés restent gérés par le cycle de requête PHP.

Le serveur PHP intégré et le stockage bind-mounted restent réservés au local ; droits NTFS du propriétaire et droits Unix ne sont pas équivalents. La suppression future d’un compte devra supprimer les fichiers, pas seulement ses lignes Doctrine. Avant production : serveur HTTP adapté, limites d’upload cohérentes, HTTPS, sauvegarde conjointe base/fichiers, restauration et politique de conservation. Aucun déploiement réalisé dans ce lot.

## Comment tester
1. `docker compose build php` puis `docker compose up -d` ; appliquer les migrations et `npm run build`.
2. Créer un compte depuis /, confirmer via http://localhost:8025, puis ouvrir le studio. Le CTA devient « Ouvrir mon studio » une fois connecté.
3. Importer un MP4 H.264 8 bits ou WebM compatible ; vérifier la progression, le détail, la lecture et la recherche dans la timeline. Renommer et revenir à la bibliothèque/au dashboard.
4. Créer un second compte : la vidéo du premier ne doit apparaître nulle part ; ses URLs ne doivent pas fonctionner.
5. Essayer un faux MP4, un fichier trop gros, un titre vide au renommage, puis annuler une suppression avant de la confirmer sur une vidéo de test.
6. `docker compose exec php php scripts/check.php` applique les migrations test puis lints, PHPStan, CS-Fixer et PHPUnit. Le script Composer check désactive uniquement son délai de processus de 300 s, trop court pour la suite sur le montage Windows local. Les tests bibliothèque conservent un kernel par test, avec réinitialisation Symfony des services entre requêtes. Les tests de fichiers vérifient shortflow_test et le chemin /var/storage/videos/test avant nettoyage ; aucun nettoyage dev. `npm run check` et `npm run build` pour le frontend.

## Landing et liste d’attente
La landing principale GET / n’affiche plus de formulaire de liste d’attente. Tous les CTA d’entrée vont à /inscription, ou /studio pour un compte confirmé connecté. /liste-attente conserve le formulaire historique et ses protections, hors du parcours principal. Aucune ligne waitlist_entry n’a été supprimée. Les illustrations de calendrier restent des démonstrations clairement identifiées.
