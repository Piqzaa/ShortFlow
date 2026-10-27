# Navigation et recette du parcours — 27 octobre 2026

## Objectif et changements

Réduire les lectures inutiles sur les pages authentifiées, puis vérifier le parcours import → préparation → confirmation du créneau → publication simulée → suivi. Aucun appel IA payant, envoi social réel ou activation de récurrence dans ce lot.

- `PublicationCalendar::snapshot()` récupère ensemble les états globaux et les états par réseau. Si Doctrine n’a pas chargé les vidéos d’un brouillon, une seule requête compte leurs lignes pour tous les brouillons concernés, sans charger leurs textes.
- `EpisodePublications::summaries()` charge les brouillons et leurs sources ensemble, puis réutilise ce résultat. Les requêtes par épisode et par réseau disparaissent.
- `AiKeys::connectedProviders()` lit uniquement les noms des fournisseurs du propriétaire en une fois pour les indications du formulaire. Aucun secret n’est chargé par cette méthode. Ce résultat local à la requête ne remplace aucun contrôle d’accès au lancement.
- `AiStudioController` réutilise ces indications et la connexion Fal. Il affiche quatre projets récents, avec accès à l’historique complet, et évite leur lecture pendant la préparation d’un épisode.
- `templates/ai/studio.html.twig` désactive le préchargement du lecteur déjà masqué. Le navigateur peut lire sa source lorsque la lecture est demandée.
- `composer.json` active `optimize-autoloader`. Composer construit une table des classes existantes et conserve le repli PSR-4 pour les nouvelles classes ; le mode autoritaire n’est pas activé. Aucune dépendance ni migration ajoutée.

Les contrôles de propriété, confirmations de coûts, validations de publication et workers restent ceux du parcours existant.

## Mesures locales

Mesures sur le code monté depuis Windows dans Docker, environnement `test`, debug désactivé, avec un propriétaire synthétique : 6 séries, 48 épisodes, 48 vidéos ; la première série contient 8 épisodes validés et 8 brouillons envoyés sur deux réseaux, soit 16 états simulés. Aucun appel fournisseur. Un compteur DBAL compte les requêtes sans conserver SQL, paramètres ou jetons. Le propriétaire de mesure a été supprimé exclusivement de `shortflow_test` après capture des pages.

Chaque série comprend cinq GET authentifiés ; le premier passage est exclu et les temps représentent la médiane des quatre suivants. Il s’agit du traitement HTTP local, sans réseau public ni promesse de délai pour les clients.

| Page | Requêtes avant | Requêtes après |
| --- | ---: | ---: |
| Studio | 9 | 4 |
| Détail de série, 8 épisodes | 38 | 14 |
| Calendrier | 16 | 9 |

Comparaison complémentaire de l’autoloader, à code final identique :

| Page | Autoloader simple | Autoloader optimisé |
| --- | ---: | ---: |
| Vidéos | 297 ms | 210 ms |
| Studio | 617 ms | 362 ms |
| Séries | 301 ms | 185 ms |
| Détail de série | 928 ms | 272 ms |
| Calendrier | 278 ms | 225 ms |
| Publications | 372 ms | 277 ms |

Les temps varient avec les caches, Docker et la charge du poste ; les nombres de requêtes sont le repère le plus stable. Un essai OPCache CLI n’a pas amélioré ce montage Windows et n’a pas été intégré. La configuration locale privée `APP_DEBUG=0` reste utile ; remettre `1` pour déboguer. Après changement de services ou routes sans debug, vider le cache Symfony.

## Vérifications

Les tests ajoutés vérifient l’isolation des indications de connexion et leur actualisation après retrait, les quatre projets récents avec historique conservé, le lecteur masqué sans préchargement, et le comptage des sources sans initialiser leur collection Doctrine.

La recette fonctionnelle importe réellement le MP4 de test par le formulaire avec CSRF, choisit YouTube, enregistre les textes français et un créneau en Europe/Paris. Elle vérifie l’absence d’envoi avant confirmation, la conversion de 14 h Paris en 12 h UTC, le déclenchement à l’heure simulée, le transfert et le traitement YouTube simulés, puis l’état publié dans le calendrier. Rejouer le message ne crée pas de deuxième envoi. Le fichier privé de test est nettoyé à la fin.

Vérification navigateur sur les pages rendues du jeu synthétique : Studio à 1280 px et 390 px, détail de série à 390 px, calendrier à 320 px. Aucun débordement horizontal observé ; menu mobile accessible, six destinations présentes, fermeture avec Échap. Les huit épisodes affichent correctement YouTube publié et Instagram en échec. Console sans erreur ni avertissement sur cette visite. Le contrôle avec l’ancien préchargement déclenche une requête vidéo ; la page corrigée en déclenche zéro.

Les pages de cette vérification navigateur sont des captures HTML servies localement avec les assets réels et les POST désactivés. Les soumissions et les handlers sont vérifiés par les tests Symfony ; aucun clic n’envoie de vidéo réelle.

Contrôles exécutés : les cinq nouveaux tests passent (56 assertions), PHPStan niveau 5 sans erreur, CS-Fixer sans correction sur 303 fichiers, lints de 102 templates Twig et 19 fichiers YAML réussis, conteneur Symfony valide et manifeste Composer valide. `npm run check`, les 136 tests d’interface et `npm run build` passent. Vite signale encore des chunks supérieurs à 500 ko ; ce lot ne modifie pas le découpage JavaScript.

Après application des migrations sur `shortflow_test` et reconstruction du cache de test, la suite PHPUnit complète passe : **617 tests, 7 470 assertions**, en 4 min 09 s dans le conteneur `edit-worker`. Les réponses 403/404/405 consignées pendant la suite correspondent aux cas de refus testés ; aucune erreur de test ni aucun échec.

La revue indépendante du diff n’a relevé aucun blocage sur les états, l’isolation propriétaire et les autorisations.

## Repères Symfony et commandes

Le contrôleur prépare les données d’affichage, les services regroupent les lectures métier et Twig rend la page. Doctrine peut charger une association seulement lorsqu’on la parcourt : répéter ce geste dans une boucle produit des requêtes supplémentaires. Ici, une jointure ou un comptage groupé fournit les données nécessaires en une fois. Il n’y a pas de cache partagé susceptible de garder une ancienne autorisation.

Pour reproduire les contrôles, appliquer les migrations exclusivement dans `shortflow_test`, puis lancer PHPUnit dans l’image `edit-worker` pour disposer aussi du moteur de sous-titres local. Les tests modifient cette base isolée. Exécuter également PHPStan niveau 5, CS-Fixer en lecture seule, les lints Symfony, `npm run check`, `npm run test:ui` et `npm run build`.

Après récupération du changement, `composer install` ou `composer dump-autoload --optimize` reconstruit la table des classes. Vider ensuite le cache de l’environnement utilisé avec `php bin/console cache:clear --no-debug` si le debug est désactivé. Les sources complètes des projets restent accessibles depuis « Tout l’historique ».
