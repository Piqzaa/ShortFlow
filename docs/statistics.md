# Publications : aperçu et statistiques de base

## Objectif et état

Lot local autorisé le 17 octobre 2026 : depuis Publications, lire la source privée et consulter vues, j’aime et nombre de commentaires par vidéo/destination. Le propriétaire a choisi la préparation des trois réseaux avec API simulées. Préparation initiale simulée, puis lecture YouTube réelle autorisée par le propriétaire le 17 octobre et premier relevé réussi. Aucun accès réel Instagram/TikTok activé. Aucun texte de commentaire, réponse ou modération ; aucun historique, calendrier ou IA.

L’aperçu est celui du fichier importé dans VydFlow, pas une copie téléchargée du réseau. Il est visible dès l’arrivée dans la liste, sans autoplay et avec `preload=metadata`, dans une colonne dédiée à côté des informations. Le volet peut être replié pour interrompre la lecture. Les métadonnées des vidéos visibles sont donc chargées dès l’affichage. La route privée existante conserve le contrôle propriétaire et Range. Source supprimée : message explicite et aucun lecteur cassé. Pas d’iframe externe ni de cookie social ajouté.

Les compteurs s’affichent uniquement pour les publications confirmées lorsque la collecte est active ou qu’un relevé existe déjà. Sans collecte ni relevé, les compteurs vides sont masqués et un seul message en haut de liste explique que les statistiques ne sont pas connectées. Zéro est une vraie valeur ; `—` signifie non fourni. Le relevé est daté et reste visible en cas d’échec ultérieur. Aucun appel social sur GET Publications ou dashboard. Les vues n’ont pas la même définition selon les plateformes : pas de total commun ou de comparaison présentée comme homogène.

## Fichiers et rôle des composants

- `PublicationStatistic` et la migration additive `Version20261017133101` : un relevé privé par paire réseau/id de publication, propriétaire, compteurs JSON, date du relevé, date/key de demande, état et message neutre. Clé étrangère propriétaire avec cascade ; aucune modification des tables sociales existantes. Les publications gardent leur historique quand une source est supprimée, donc le relevé n’est pas lié au fichier source.
- `PublicationStatistics` : vérifie propriété/publication/identifiant distant, limite à une demande par minute, réserve le relevé et le message dans une transaction Doctrine. Le verrou propriétaire partagé évite les courses avec connexions/envois. Le worker vérifie de nouveau le compte destinataire avant lecture.
- `RefreshPublicationStatistics` / handler : tâche Messenger dans le transport existant, utilisant le worker local. Une clé de demande obsolète ou un message déjà traité ne relance pas la lecture. En cas d’erreur distante, aucun jeton/réponse brute n’est conservé ou chaîné dans une exception affichée ; le dernier relevé n’est pas écrasé.
- `YouTubeApi::statistics`, `InstagramApi::statistics`, `TikTokApi::statistics` : petits adaptateurs du réseau, identifiant vérifié, jetons chiffrés déchiffrés côté serveur seulement, clients HTTP sans profiler existants. Réponse partielle autorisée ; compteur négatif, flottant ou dépassant PHP_INT_MAX refusé.
- `PublicationStatisticsController` : POST propriétaire, CSRF propre à la vidéo/réseau et limiteur 20 demandes/minute/propriétaire. Il met en file puis redirige vers les publications de la destination, sans attendre l’API.
- `PublicationOverview` : joint les relevés au flux par propriétaire/réseau/id, sans sélectionner clé de demande ou jeton. Les identifiants numériques des trois tables peuvent coïncider sans mélanger leurs relevés.
- `publication/_job.html.twig`, `_statistics.html.twig`, `publication.css` et `publication_preview_controller.js` : aperçu accessible, compteurs, date, attente/échec, bouton et arrêt de lecture à la fermeture. Twig échappe les titres ; pas de données utilisateur en `raw`.

## Flux Symfony

1. GET Publications : Security exige ROLE_VERIFIED, la requête locale filtre le propriétaire, Twig rend les relevés existants.
2. Lancer le lecteur déjà visible : le navigateur récupère la source via la route privée déjà autorisée. Aucun appel au réseau social.
3. POST Actualiser les statistiques : CSRF, propriété et limiteur ; service + transaction Doctrine enregistrent demande et message Messenger atomiquement.
4. Le worker prend le verrou propriétaire, ignore une ancienne clé, relit publication/connexion et compare le compte avec celui de la publication. Il ne publie rien.
5. L’adaptateur récupère les seuls compteurs, le service valide les valeurs et enregistre le relevé, ou un état indisponible en conservant les dernières valeurs.
6. Actualiser la liste pour voir le résultat. Les compteurs ne sont pas annoncés comme du temps réel ; une demande par minute par publication évite de consommer les quotas à chaque clic.

À comprendre : une lecture distante peut être lente ou échouer. Messenger la sort de la requête navigateur ; Doctrine garde un état durable et la transaction évite d’afficher une demande sans message. L’idempotence empêche une redélivrance de message d’écraser le résultat d’une demande plus récente. Il s’agit d’une lecture, pas d’un nouvel envoi de vidéo.

## Accès réels ultérieurs

`YOUTUBE_STATISTICS_ENABLED`, `INSTAGRAM_STATISTICS_ENABLED` et `TIKTOK_STATISTICS_ENABLED` valent `0` dans `.env`. `.env.test` les active uniquement avec MockHttpClient. `PublicationStatisticsAccess` fournit la même décision par réseau au contrôleur et au service : un bouton masqué ne suffit pas à bloquer une lecture, le worker vérifie aussi ce réglage.

Après autorisation explicite du 17 octobre, `.env.local` active YouTube seul (`1`, `0`, `0`). Premier relevé réel de la publication existante réussi : trois compteurs à zéro ; aucun nouvel envoi. Une connexion YouTube était déjà présente avec la permission readonly demandée par le code. Instagram/TikTok restent désactivés.

Après changement de code/activation, reconstruire aussi le cache utilisé par le worker sans debug : `docker compose exec php php bin/console cache:clear --no-debug`, puis `docker compose restart worker`. Un cache sans debug ancien peut conserver un registre Messenger sans le nouveau handler malgré un redémarrage.

Depuis l’interface : Publications → Actualiser les statistiques → actualiser la liste. Pour un diagnostic local avec l’identifiant d’une publication existante : `docker compose exec php php bin/console app:youtube:statistics:refresh ID`. La commande met la même demande en file via le même service et ses contrôles ; elle ne publie pas de vidéo et n’affiche aucun jeton.

- YouTube : Data API `videos.list` (`snippet,statistics`), identité de chaîne vérifiée ; l’autorisation readonly est déjà demandée par VydFlow. Pas d’ajout YouTube Analytics API. Les Shorts ont leur propre définition de vue, rappelée dans les documents Google.
- TikTok : Display API `/v2/video/query/`, permission de lecture `video.list` désormais demandée en plus des permissions de publication. Une ancienne connexion peut nécessiter une nouvelle autorisation pour les statistiques. Les permissions de publication seules continuent d’être acceptées pour ne pas casser leur renouvellement. Les vidéos privées peuvent ne pas être retournées ; sans identifiant distant, aucune requête hasardeuse pour retrouver la vidéo.
- Instagram : adaptateur local de média puis insights `views,likes,comments`, permission de lecture `instagram_business_manage_insights` demandée en plus des permissions existantes. Le refus de cette permission ne bloque pas les permissions de publication de base. **Limite de validation : les pages Meta d’insights ont renvoyé 429/erreur pendant ce lot ; seule la page d’accueil de la collection officielle Meta a pu être lue. L’endpoint, les champs et cette permission doivent donc être revalidés sur la documentation Meta accessible et sur l’application réelle avant activation. Les simulations ne prouvent pas la disponibilité des Insights pour le compte choisi.**

Avant une exploitation continue en production, finaliser aussi la politique de conservation et son exécution : les règles YouTube imposent notamment une vérification périodique de l’autorisation et de l’existence de la vidéo pour conserver des statistiques autorisées au-delà de 30 jours. Aucun historique analytique ni conservation réelle illimitée validé ici. La maintenance de purge/vérification avant production reste à mettre en place ; les données de test sont synthétiques ; seule la lecture locale YouTube a été autorisée et vérifiée.

## Tester

Base `shortflow_test` migrée avant PHPUnit (commande globale du README). Tests :

```sh
docker compose exec -T -e SHELL_VERBOSITY=-1 php php vendor/bin/phpunit --filter PublicationStatisticsTest
docker compose exec -T php php scripts/check.php
docker compose --profile tools run --rm --no-deps node npm run check
docker compose --profile tools run --rm --no-deps node npm run test:ui
docker compose --profile tools run --rm --no-deps node npm run build
```

Les tests utilisent uniquement shortflow_test et des réponses HTTP synthétiques. Parcours visuel : publication confirmée → ouvrir/fermer l’aperçu → contrôler les compteurs (dont zéro et non fourni), date, activation absente, demande en attente et erreur gardant le dernier relevé. Vérifier mobile, clavier, absence de lecture automatique et pause après fermeture. Les résultats exécutés figurent dans verification.md.

## Sources consultées

- [YouTube videos](https://developers.google.com/youtube/data/reference/rest/v3/videos) et [videos.list](https://developers.google.com/youtube/data/reference/rest/v3/videos/list).
- [TikTok Query Videos](https://developers.tiktok.com/docs/en/tiktok-api-v2-video-query).
- [Collection officielle Instagram de Meta](https://www.postman.com/meta/instagram/overview) ; documentation détaillée inaccessible pendant ce lot, limite ci-dessus.
- [Politiques YouTube](https://developers.google.com/youtube/terms/developer-policies), notamment stockage et autorisation des données, à appliquer avant activation réelle.
