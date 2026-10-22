# Correctif PHP — 22 octobre 2026

## Objectif

Corriger CVE-2026-109618 (corruption mémoire après épuisement mémoire) et CVE-2026-109619 (état interne périmé entre requêtes). Les conteneurs utilisaient PHP 8.4.26 ; PHP 8.4.27 corrige les deux vulnérabilités. Référence : [avis officiel PHP du 21 octobre](https://www.php.net/archive/2026.php#2026-10-21-5).

## Composants et exécution

- `docker/php/Dockerfile` fixe explicitement la base à `php:8.4.27-cli-bookworm`, au lieu d'un tag mobile `8.4` dont une ancienne copie pouvait rester en cache. Cette version doit continuer à être actualisée lors des prochaines mises à jour de sécurité.
- L'image locale `vydflow-php` fournit PHP au serveur local, au serveur HTTPS de test et au worker des publications.
- `docker/edit/Dockerfile` hérite de cette image locale ; reconstruire aussi `vydflow-edit` après la base pour mettre à jour PHP dans le worker de montage.
- Un conteneur déjà créé conserve son ancienne image. Reconstruire une image puis recréer les conteneurs est nécessaire ; `restart` seul ne met pas PHP à jour.

Symfony s'exécute à l'intérieur de PHP. Mettre à jour les bibliothèques avec Composer ne remplace pas le moteur PHP. Aucun changement métier, migration de données ou modification des secrets n'est nécessaire pour ce correctif.

## Procédure

1. Arrêter `social-tunnel` avant l'intervention. Contrôler les tâches actives et laisser finir un transfert ou un rendu avant de remplacer les workers.
2. Exécuter `docker compose build --pull php`, puis `docker compose build edit-worker` (la seconde construction utilise la base locale venant d'être reconstruite).
3. Arrêter proprement les workers, puis recréer les services `php`, `worker`, `edit-worker` et `social-web` avec les images corrigées. Conserver les volumes de base, les médias et les clés existantes.
4. Contrôler `php -v` dans chacun des quatre services, les extensions, FFmpeg et le moteur de sous-titrage local.
5. Exécuter `php scripts/check.php` dans le conteneur PHP : migrations de `shortflow_test`, validation des schémas, lints, PHPStan, CS-Fixer et PHPUnit. Vérifier aussi les réponses HTTP locales et les restrictions de l'entrée sociale.
6. Ne reprendre le tunnel existant qu'après vérification des services corrigés ; ne lancer aucune publication ou génération pour tester la mise à jour.

Ne jamais utiliser `down -v` : les volumes existants doivent être conservés. Aucun test d'exploitation n'est nécessaire sur les données du propriétaire. Les mesures de configuration partielles de l'avis PHP ne remplacent pas la mise à jour.

Les résultats effectivement exécutés sont consignés dans `docs/verification.md`.

## Résultat sur ce poste

Les quatre services exécutent PHP 8.4.27, les workers sont actifs et la connexion HTTPS de test répond. Cloudflare a attribué au tunnel redémarré l'adresse `https://racks-directly-shanghai-namely.trycloudflare.com`. Les anciennes origines dans la configuration privée et les portails sociaux n'ont pas été modifiées : les callbacks, liens médias et la vérification du préfixe TikTok doivent être revérifiés avant un prochain essai réel.

La suite complète a exécuté 408 tests / 5 552 assertions : un échec de navigation attend encore `.creation-tool-soon[aria-disabled="true"]`, absent des templates actuels. CS-Fixer signale deux écarts de formatage dans des fichiers hors du correctif ; ils ne sont pas modifiés ici. Voir le journal pour les commandes et limites exactes.
