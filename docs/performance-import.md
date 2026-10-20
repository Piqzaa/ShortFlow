# Navigation locale et aperçu avant import — 20 octobre 2026

## Objectif

Réduire l'attente lors des changements de page, puis identifier la vidéo choisie avant de confirmer son import. Le propriétaire valide également la suite « Studio simple et guidé » de l'audit IA ; elle constitue le lot suivant après cette correction de performance et d'import.

## Diagnostic mesuré

Requêtes GET anonymes vers `/connexion`, avec les mêmes fichiers Windows/Docker :

| Configuration | Temps de réponse observés |
| --- | --- |
| Localhost, Symfony dev/debug actif | 577 à 611 ms |
| Serveur social local, dev sans debug | 44 à 45 ms après le premier passage |
| Serveur temporaire, debug actif et OPcache CLI activé | 573 à 581 ms à chaud : pas de gain utile |
| Même serveur temporaire, sans debug | 36 à 41 ms à chaud |
| Localhost après correction et redémarrage des conteneurs | Premier passage 327 ms ; puis 45 à 49 ms |

1 000 lectures de date de fichier sans cache ont pris environ 1 200 ms sur le montage Windows et 2 ms sur le volume Docker des dépendances. Ces mesures indiquent un coût élevé des contrôles du mode debug ; elles ne mesurent pas toutes les pages authentifiées, les requêtes SQL ou le rendu visuel. CPU et mémoire des conteneurs étaient faibles pendant le relevé. Le JS principal était servi en environ 10 ms en local. Le chunk Three.js est séparé pour la landing : sa taille ne prouve pas qu'il ralentit le studio.

Le chronométrage du tunnel public n'a pas abouti : le domaine n'était pas résolu depuis cette exécution. Aucune conclusion chiffrée sur le HTTPS de bout en bout et aucun changement de tunnel. Après l'interruption, tous les conteneurs étaient arrêtés ; seuls database, mailpit et php ont été repris pour finir les contrôles locaux.

## Mode local rapide

`APP_DEBUG=0` est ajouté à `.env.local`, fichier privé ignoré par Git. L'environnement reste `dev` : même base, vidéos et comptes. Aucun changement de schéma ou recréation de PHP. Le cache a été recalculé. `config/packages/twig.yaml` conserve `auto_reload: true` en dev pour voir les changements de templates dans ce mode.

Pour déboguer, remettre `APP_DEBUG=1` dans `.env.local`. Après des changements de routes, services ou configuration en mode sans debug, recalculer le cache :

```powershell
docker compose exec php php bin/console cache:clear --env=dev --no-debug
```

Le premier chargement après redémarrage ou invalidation reste plus lent. PHPUnit garde l'environnement `test` et son debug de test.

## Vignettes de bibliothèque

Avant : chaque carte démarrait un lecteur vidéo privé en `preload=metadata` dès réception du HTML. Sur le serveur PHP de développement, qui traite les requêtes successivement par défaut, ces demandes peuvent retarder une navigation.

Après : `video_thumbnails_controller.js` observe les cartes proches de la fenêtre, charge deux sources au maximum par grille, capture une image JPEG bornée à 360 × 480 en mémoire dans le navigateur, puis retire la source vidéo. Aucun fichier image n'est créé sur le serveur et aucun tiers ne reçoit l'image. Les requêtes actives et chargements différés sont libérés en quittant la page. Le retour navigateur restaure les aperçus si nécessaire.

Les templates de bibliothèque, vidéos et brouillons utilisent cette stratégie. Les lecteurs de détail et le calendrier gardent leur fonctionnement. Sans JavaScript ou en cas d'échec de décodage, les noms, durées et liens restent utilisables avec un fond de carte statique. Aucun pourcentage de gain sur une bibliothèque réelle n'est annoncé sans mesure correspondante.

## Aperçu avant import

`upload_controller.js` utilise `URL.createObjectURL(file)` pour afficher le fichier choisi. Sélection native ou dépôt unique déclenchent l'aperçu : nom, durée et dimensions lorsque le navigateur les lit. Le lecteur est muet initialement, avec commandes et sans lecture automatique. « Retirer » efface le choix et replace le focus sur le champ.

Le fichier reste sur l'appareil jusqu'au clic « Importer ma vidéo ». Les URL blob sont révoquées au remplacement, retrait, départ et à la destruction du contrôleur. Une erreur de décodage affiche une explication sans déclarer le fichier invalide ; les validations serveur restent l'autorité sur format, codecs, durée et propriété. Extension et taille sont contrôlées côté navigateur pour le confort uniquement.

Le POST multipart existant conserve CSRF et progression. Les données sont collectées avant de désactiver le champ fichier pendant le transfert. Un double clic ne lance pas un second envoi.

## Composants et Symfony

- `VideoUploadType` ajoute l'action Stimulus de sélection ; les contraintes Symfony restent identiques.
- `templates/library/upload.html.twig` ajoute zone de dépôt et lecteur masqué ; aucun nouvel endpoint.
- `upload_controller.js` prépare l'aperçu local, puis conserve l'envoi vers `VideoController::upload`.
- `assets/styles/library.css` adapte le lecteur au téléphone et borne sa hauteur.
- `video_thumbnails_controller.js`, déclaré dans `assets/app.js`, limite le chargement des couvertures dans les grilles Twig.

À comprendre : Stimulus améliore le HTML Twig sans remplacer formulaires ou sécurité Symfony. Le serveur ne reçoit rien pour fabriquer cet aperçu. `APP_ENV` et `APP_DEBUG` sont distincts : désactiver le debug ne change ni la base ni les autorisations.

## Vérification manuelle

1. Choisir puis déposer un MP4/WebM : vérifier image, nom et durée, sans POST.
2. Lire, remplacer, retirer, sélectionner à nouveau ; essayer deux fichiers et un fichier non vidéo. Un codec non décodable doit expliquer l'absence d'aperçu.
3. Vérifier sur téléphone l'absence de débordement et l'accès aux commandes.
4. Importer uniquement une vidéo de test via le bouton : validation et parcours existants conservés.
5. Ouvrir Vidéos, défiler puis changer de page : les sources de couvertures sont chargées progressivement et libérées.

Références : [serveur PHP](https://www.php.net/manual/en/features.commandline.webserver.php), [OPcache](https://www.php.net/manual/en/opcache.configuration.php), [URL.createObjectURL](https://developer.mozilla.org/en-US/docs/Web/API/URL/createObjectURL_static).
