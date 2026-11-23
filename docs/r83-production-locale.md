# R8.3 — répétition locale du mode production

Le Compose `compose.prod-rehearsal.yaml` construit une image Apache/PHP à partir des verrous Composer et npm. Apache expose uniquement `public/`. Le web, PostgreSQL, la migration et les workers sont sur le réseau interne du projet Docker. Le proxy de répétition publie le web sur `127.0.0.1:8180`. La base et les médias occupent deux volumes propres au projet. La configuration locale utilise `APP_ENV=prod`, `APP_DEBUG=0`, des identifiants de répétition dédiés, des inscriptions fermées et un transport email nul.

## Lancer sur Windows

Depuis la racine du dépôt, dans PowerShell 7, créer le fichier privé une seule fois et le conserver pour redémarrer avec le même volume PostgreSQL.

```powershell
$envFile = '.env.prod-rehearsal.local'
if (-not (Test-Path -LiteralPath $envFile)) {
  $secret = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
  $password = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(24))
  @("VYDFLOW_REHEARSAL_SECRET=$secret", "VYDFLOW_REHEARSAL_PASSWORD=$password") |
    Set-Content -LiteralPath $envFile -Encoding utf8NoBOM
}
$compose = @('--env-file', $envFile, '-f', 'compose.prod-rehearsal.yaml')
docker compose @compose config --quiet
docker compose @compose build web
docker compose @compose up -d --wait
docker compose @compose ps -a
```

La migration doit sortir avec le code zéro. L’accueil et `/build/app.js` doivent répondre HTTP 200. Les workers `publications`, `background` et `video-edits` doivent afficher `Queue ready`. Le worker montage dispose de FFmpeg. La transcription locale et la rédaction via Ollama n’entrent pas dans cette répétition.

`docker compose @compose stop` conserve les deux volumes et permet de mettre la répétition en pause. Surveiller l’espace du volume avec `docker compose @compose exec -T web sh docker/production/check-storage.sh`. Le code 1 signale que le seuil est atteint, par défaut à 80 %. Le choix du canal de notification et la fréquence du contrôle relèvent de R8.6. Dimensionner le disque avec de la marge pour les fichiers d’envoi et de montage temporaires, puis isoler la sauvegarde du volume courant.

## Sauvegarder et restaurer

Sur la répétition, arrêter les processus qui écrivent avant de prendre la paire de sauvegardes. Un dump SQL transactionnel et un tar pris pendant des écritures peuvent représenter deux instants différents. Ici `web`, `publications`, `background` et `video-edits` sont les seuls services applicatifs démarrés.

```powershell
docker compose @compose stop ingress publications background video-edits web
docker compose @compose exec -T database sh -ec 'pg_dump -U vydflow -d vydflow -Fc --no-owner --no-privileges > /tmp/vydflow.dump'
docker compose @compose cp database:/tmp/vydflow.dump artifacts/vydflow.dump
docker compose @compose run --rm --no-deps -T -v "$((Get-Location).Path)/artifacts:/backup" web tar -C /app/var/storage -cf /backup/vydflow-media.tar .
```

Créer une seconde paire de secrets avec le même bloc PowerShell, en utilisant `.env.prod-restore.local`. Y ajouter `VYDFLOW_REHEARSAL_PORT=8181`. Utiliser un nom de projet distinct et des volumes vierges. Démarrer la base seule puis importer le dump et le contenu du volume avant de lancer le site. Pour la restauration d’une vraie sauvegarde, garder les workers arrêtés pendant la vérification. Restaurer les clés de chiffrement via leur sauvegarde séparée au moment autorisé et contrôler notamment les tâches en attente, les publications échues et les accès réels avant toute reprise. Les secrets de répétition et les sauvegardes restent hors de Git.

```powershell
$restore = @('--project-name', 'vydflow-prod-restore', '--env-file', '.env.prod-restore.local', '-f', 'compose.prod-rehearsal.yaml')
docker compose @restore up -d --wait database
docker compose @restore cp artifacts/vydflow.dump database:/tmp/vydflow.dump
docker compose @restore exec -T database pg_restore -U vydflow -d vydflow --no-owner --no-privileges --exit-on-error /tmp/vydflow.dump
docker compose @restore run --rm --no-deps -T -v "$((Get-Location).Path)/artifacts:/backup:ro" web tar -C /app/var/storage -xf /backup/vydflow-media.tar
docker compose @restore up -d --wait ingress
docker compose @restore exec -T --user www-data web php bin/console doctrine:schema:validate
```

Vérifier le compte propriétaire, le nombre de vidéos, la taille et le SHA-256 de la vidéo de recette, puis sa lecture authentifiée sur `127.0.0.1:8181`.

## Passage à la cible

Choisir la capacité du volume et son mécanisme de sauvegarde avant achat. Pour la cible, configurer le proxy HTTPS, l’hôte autorisé dans `TRUSTED_HOSTS`, l’adresse réelle du proxy dans `TRUSTED_PROXIES` et l’URL canonique dans `APP_PUBLIC_URL` et `DEFAULT_URI`. Le navigateur doit recevoir le cookie de session `Secure` derrière ce proxy. Réserver le montage et la transcription aux ressources prévues pour ces tâches. Ajouter ensuite les secrets approuvés pour l’email et les retours OAuth, puis vérifier les connexions et les envois sur la cible. [Configuration des proxys dans Symfony 7.4](https://symfony.com/doc/7.4/deployment/proxies.html)

## Résultat de la répétition

- Construction de l’image réussie, 47 migrations depuis une base vide et 29 tables. Le schéma Doctrine est conforme, les trois workers écoutent leurs files et la file est vide à la fin. Symfony affiche `prod` et `Debug false`. Seul le proxy publie un port, sur le loopback. Le conteneur web appartient au réseau interne et n’a pas de route IPv4 par défaut.
- Accueil, connexion et assets HTTP 200. Un hôte inattendu reçoit HTTP 400. La route de profilage et le fichier d’environnement reçoivent HTTP 404. Le studio exige une session.
- Connexion fictive, import d’un MP4 synthétique de 3 638 octets (HTTP 201), lecture authentifiée intégrale et partielle (HTTP 200 et 206). Le contrôle d’espace est passé au seuil normal et a produit une alerte au seuil forcé de 1 %.
- Dump PostgreSQL et archive médias restaurés sur deux volumes vierges dans un autre projet Compose. La lecture authentifiée du fichier restauré et une requête `Range` ont réussi. Le propriétaire, la clé de stockage, la taille et le SHA-256 correspondent à la source. Les workers de restauration sont restés arrêtés.
