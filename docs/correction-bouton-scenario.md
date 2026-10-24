# Bouton de scénario — diagnostic du 24 octobre 2026

Le propriétaire signale un clic sans réaction dans un épisode de série de dix secondes. Le formulaire frais rendu avec Symfony et le build présent sur ce PC réagit correctement. Le navigateur personnel n'est pas accessible à cette session ; le cache d'un ancien JavaScript reste une hypothèse, pas une cause démontrée. Le nom fixe `/build/app.js` permettait de conserver un ancien script après une mise à jour.

## Changements

- `src/Twig/BuildAssetExtension.php` expose `build_asset` pour les deux entrées publiques JS/CSS. L'URL reçoit une empreinte de leur contenu : même contenu, même URL ; nouveau build, nouvelle URL. Aucun secret ni chemin arbitraire n'est exposé.
- `templates/base.html.twig` utilise cette fonction. Le navigateur charge donc la version correspondant au build actuel lors du prochain chargement de page.
- `assets/controllers/story_writer_controller.js` intercepte également les erreurs de lecture des champs et de calcul de l'empreinte de préparation, auparavant hors du `try`. Une erreur ne doit plus laisser le clic sans retour. Les erreurs JavaScript de type lecture/réseau montrent une instruction de rechargement, sans exception technique brute.
- Les tests frontend couvrent les erreurs avant requête, la fin du chargement après erreur et l'absence d'appel fournisseur. Les tests unitaires PHP vérifient que le changement de contenu renouvelle l'URL, sans dépendre de l'heure du fichier, et que les chemins privés sont refusés.

## Flux et vérification manuelle

Twig appelle l'extension autoconfigurée par Symfony au rendu HTML → l'URL du build sélectionne le bon script → Stimulus prend le clic → vérifie la préparation → affiche le chargement → enregistre la demande locale → suit le worker → propose un aperçu. Appliquer les scènes et confirmer une génération vidéo restent des actions distinctes.

Pour tester : enregistrer les modifications en cours dans l'épisode, recharger la page, cliquer sur « Proposer mon scénario ». Attendre le message du rédacteur puis relire l'aperçu. Une idée, une durée ou un modèle manquants doivent afficher une explication. Ce bouton ne lance pas de vidéo Fal et ne consomme pas de crédits Fal.

La recette navigateur utilise le vrai HTML Symfony pour une série fictive Pika 720p, deux plans de cinq secondes, sur un serveur temporaire loopback. Seules les réponses de rédaction sont simulées dans cette vérification du bouton. Le chargement, le temps écoulé, l'aperçu et les URLs versionnées sont observés ; la capture ignorée `artifacts/story-button-loading.png` montre le chargement. L'utilisateur synthétique est créé puis supprimé uniquement dans `shortflow_test`. Le compte du propriétaire et son épisode ne sont pas modifiés.

## Contrôles

`npm run check`, 109 tests frontend et `npm run build` réussis. Le build conserve les avertissements existants sur les ressources publiques résolues à l'exécution et le chunk Three.js. Lint des 83 Twig, lint du conteneur, PHPStan et CS-Fixer des deux nouveaux fichiers PHP réussis. Tests ciblés du scénario et de l'extension : 3 tests, 79 assertions réussis après nettoyage de messages résiduels exclusivement dans `shortflow_test` ; la première exécution avait trouvé trois messages au lieu d'un. Aucun test PHP complet annoncé pour ce lot.

Contrôle complémentaire des séries : 10 tests, 103 assertions réussis avec `APP_DEBUG=0`. Un premier passage élargi en mode debug, pendant la préparation du harnais, avait échoué sur un bouton de confirmation absent ; cet échec ne se reproduit pas lors de cette exécution isolée. Le serveur temporaire est supprimé après la recette.

À comprendre en Symfony : mettre à jour les sources et vider le cache Twig ne suffit pas à renouveler un fichier JavaScript déjà conservé par le navigateur. L'empreinte relie le HTML au contenu du build, tandis que la gestion d'erreur Stimulus rend les échecs visibles. L'essai dans le navigateur du propriétaire reste nécessaire pour confirmer la disparition du symptôme initial.
