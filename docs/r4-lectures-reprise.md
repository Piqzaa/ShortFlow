# R4.4 — lectures isolées, historique long et reprise

1er novembre 2026. **R4.4 terminé ; grand R4 encore en cours. Prochain point : R4.5**, revue globale et décision de sortie avant R5.

## Objectif et résultat

Vérifier que la pagination R4.2 et le partage du catalogue R4.3 conservent les données, les états de publication et les chemins de reprise. Aucun correctif applicatif supplémentaire n'a été nécessaire. Aucun service PHP, template, asset, schéma, consentement ou protection métier n'est modifié dans R4.4.

Les deux nouveaux cas de `tests/Functional/SeriesPaginationTest.php` complètent les anciens tests de volumes, anciennes ancres, POST refusés puis récupérés, propriétaires et historique indépendant :

| Vérification | Preuve nouvelle |
| --- | --- |
| Historique long | 500 épisodes, 505 créneaux dont cinq sans épisode, pages 1/2/21/42 et page trop grande ; 12 cartes au maximum, huit sur la dernière page, un créneau sur la page 43 |
| Conservation et navigation | Total complet, trois créneaux récents sans épisode, anciens consultables, retour de l'historique vers l'épisode 1 sans perdre sa page d'historique, cible épisode 500 retrouvée |
| Isolation | Accès directs à série, épisode, génération et état de génération d'un autre propriétaire : 404, aucun prompt étranger exposé ; focus étranger et autre série du même propriétaire déjà couverts par les cas existants |
| Publications hors première page | Brouillon, programmée, à reprogrammer, annulée, réussite partielle, en cours, à traiter et publiée sur les épisodes 13 à 20 |
| Versions et destinataires | Ancienne version conservée et signalée, comptes enregistrés YouTube/Instagram affichés, réussite YouTube + échec Instagram distincts |
| Identité des états | Libellés attendus explicites et comparaison à `PublicationCalendar::states()` ; cette comparaison ne prétend pas vérifier toute l'interface calendrier |
| Lectures sans effet | Contenu exact de douze tables métier/file identique avant/après les lectures ; clients HTTP applicatifs simulés configurés pour échouer à tout appel |

Le snapshot couvre séries, épisodes, récurrences, occurrences, générations, vidéos, brouillons, éléments de brouillon, publications YouTube/Instagram/TikTok et messages Messenger. Il ne couvre pas toute la base ni les sessions/caches ; les vidéos sont des métadonnées synthétiques, sans fichiers ou rendu audiovisuel.

## Exécution et contrôles

Copie Linux isolée `/tmp/r43` dans le conteneur edit-worker existant, PHP 8.4.27 ; seules les sources/tests nécessaires sont actualisés. Utilisation de `shortflow_test` vérifiée par les tests, aucune suite DB concurrente. Les tests fonctionnels réinitialisent leurs données de test ; aucune donnée dev n'est supprimée. Aucun environnement prod utilisé.

- Ciblage : `APP_ENV=test php vendor/bin/phpunit tests/Functional/SeriesPaginationTest.php` : **9 tests / 208 assertions réussis**.
- Ensemble : `php scripts/check.php` : **813 PHP / 9 437 assertions**, schémas dev/test, migrations test déjà à jour, 19 YAML, 114 Twig, conteneur, PHPStan et style réussis. Aucun test exclu. Logs locaux : `artifacts/r44-target.log` et `artifacts/r44-full.log`.
- Frontend : `npm run test:ui` : **183 tests réussis** ; `npm run check` et `npm run build` réussis. Avertissements de build connus : chunk Three.js et chemins absolus médias/polices à résoudre au runtime.
- Revue indépendante lecture seule du diff, des projections et des logs : aucun blocage identifié. Le relecteur n'a lancé aucune suite DB.

Premier ciblage : 9 tests / 173 assertions, une erreur de fixture `Invalid episode publication source`. Le brouillon était lié avant création de son élément et obtention de l'identifiant vidéo. Les fixtures utilisent désormais le contrat public : vidéo persistée, élément ajouté, puis association à la version alors approuvée ; la nouvelle version est approuvée ensuite pour conserver une vraie ancienne préparation. Aucune garde applicative assouplie.

## Recette synthétique navigateur

Playwright CLI local a permis de lever la limite d'accès du navigateur intégré signalée en R4.3. Le premier serveur dans le sandbox n'était pas accessible ; le serveur de recette a ensuite été démarré hors sandbox sur **127.0.0.1 uniquement**, sans tunnel. Il refuse tous les POST. Les pages sont des réponses Symfony authentifiées exportées depuis une transaction synthétique annulée, avec clients HTTP fournisseurs interdits : Studio, épisode 500, seize combinaisons de pagination et une réponse 422 d'ancien onglet. Aucune génération ni activation acceptée par le serveur.

Vérifications réussies avec les assets construits :

- Desktop 1280 × 900 : huit épisodes sur la dernière page, actualisation conserve la page ; historique 43 avec un ancien créneau puis lien vers l'épisode 1, page d'historique conservée.
- Mobile 375 × 812 : absence de débordement horizontal sur historique, Studio et préparation d'épisode ; retour de l'épisode 500 vers ses huit cartes.
- Clavier : lien Suivants activé avec Entrée, page 2 chargée et focus sur `episodes-title`.
- Ancienne ancre vers un épisode non rendu sur réponse refusée : URL inchangée, marqueur de navigation bloquée actif, prompt humain toujours présent dans le formulaire de récupération.
- Sans JS : sélecteur de modèle natif visible et retour de l'historique vers l'épisode 1 fonctionnel.
- Catalogue partagé : choix du plan unique puis Kling 3, durées activées 3–15 s ; passage à 10 s et Kling 2.5, durées activées 5/10 s. Changement de modèle également vérifié sur la préparation de l'épisode 500. Aucune erreur JS/console dans les contextes finaux du Studio et de l'épisode.

La sauvegarde automatique Studio est **simulée dans le navigateur** (`saved: true`) pour ce contrôle de modèle ; aucune persistance ni validité serveur d'une sauvegarde n'est déduite de ce mock. Le résultat de rédaction est simulé vide. Les premiers essais ont produit des 404 d'assets/endpoint non servis et un 405 de sauvegarde sur le serveur incomplet ; les contextes finaux utilisent les fichiers publics utiles et le résultat vide explicite. Un premier oracle de durée comptait les options cachées/désactivées : il a été corrigé pour vérifier les options réellement proposées, sans changement applicatif.

Captures locales inspectées : `artifacts/r44/history-desktop.png`, `history-mobile.png`, `studio-mobile.png`, `episode-return-mobile.png` et `refused-fields-mobile.png`. Cette recette porte sur des exports et des métadonnées synthétiques ; elle ne certifie ni fournisseurs réels, qualité vidéo/audio, capacité de production ou conformité mobile complète. R5 garde sa matrice responsive/accessibilité plus large.

## Pédagogie et reprise

Une requête GET sert à lire un état ; elle ne doit pas reprendre un worker ni confirmer un coût. Le test compare les lignes persistées avant/après et fait échouer tout HTTP applicatif : il détecterait un lancement ou une mutation inattendue. La pagination doit borner la requête Doctrine avant hydratation, sans limiter l'accès aux anciens épisodes. Les projections groupées de publications gardent le même calcul d'état que le calendrier.

Pour tester à la main sans coût : dernière page d'une série longue → historique ancien → retrouver un épisode → ouvrir sa préparation → revenir à sa page. Vérifier une saisie refusée avant toute actualisation, puis les champs natifs sans JS. Aucune confirmation de génération ou de publication nécessaire.

Les serveurs/onglets de recette sont fermés en fin de contrôle. Aucun cache dev ou worker redémarré : aucun service applicatif changé. Aucun appel IA/fournisseur/social réel, dépense, activation, migration applicative, déploiement ou push. **R4.5 reste à faire** ; cette revue ciblée ne remplace pas la revue globale du grand R4.
