# R8 — Installation Linux du pilote fermé

La cible utilise Docker Engine et Compose sur Linux. Seul Nginx expose les ports 80 et 443. PostgreSQL et les médias privés ont leurs propres volumes. La migration doit réussir avant le démarrage du web et des workers. Le web et le worker de publication disposent d’un accès sortant pour les connexions et les envois autorisés. L’indexation et les inscriptions ouvertes sont désactivées.

## Préparer l’hôte

1. Faire pointer l’enregistrement DNS de `vydflow.com` vers le serveur. Ajouter un AAAA uniquement si IPv6 est configuré sur la cible. Ouvrir HTTP et HTTPS. Restreindre l’accès SSH à l’administration.
2. Copier `.env.production.example` vers `.env.production.local` avec des permissions `0600`. Renseigner deux valeurs aléatoires distinctes avec `openssl rand -hex 32` pour `VYDFLOW_APP_SECRET` et `openssl rand -hex 24` pour `VYDFLOW_DATABASE_PASSWORD`. Conserver les mêmes valeurs lors des mises à jour. Vérifier que le sous-réseau du proxy et son adresse n’entrent pas en conflit avec le réseau de l’hôte.
3. Obtenir le premier certificat avec Certbot en mode autonome pendant que le port 80 est libre. Installer `fullchain.pem` et `privkey.pem` dans `VYDFLOW_TLS_DIR`, avec une clé privée lisible uniquement par root. Créer `VYDFLOW_ACME_DIR` avec des permissions de lecture pour Nginx. Configurer le renouvellement via le répertoire webroot et une action post-renouvellement qui installe les deux fichiers puis recharge Nginx. Vérifier le renouvellement avant d’ouvrir le pilote. [Certbot et renouvellement](https://eff-certbot.readthedocs.io/en/stable/using.html#renewing-certificates)
4. Configurer le transport SMTP et l’expéditeur, puis les adresses invitées. Configurer OAuth YouTube avec l’URI exacte `https://vydflow.com/studio/connexions/youtube/retour`. Les invités reconnecteront leur chaîne sur le domaine définitif. Conserver les clés et la configuration dans une sauvegarde séparée. [Configuration OAuth Google](https://developers.google.com/identity/protocols/oauth2/web-server#creatingcred)

```bash
stack=(docker compose --env-file .env.production.local -f compose.production.yaml)
"${stack[@]}" config --quiet
"${stack[@]}" build web
"${stack[@]}" up -d --wait
"${stack[@]}" ps -a
```

Depuis le domaine, vérifier la redirection HTTP vers HTTPS, la connexion, le cookie de session `Secure`, la lecture privée et les liens des emails. Vérifier une migration terminée et les listeners des trois files. Le contrôle disque est disponible avec `"${stack[@]}" exec -T web sh docker/production/check-storage.sh`. L’alerte opérateur et sa réception restent à tester sur la cible.

## Sauvegarder hors serveur

Installer `age`, `rclone` et `flock` sur Linux. Créer la paire `age` sur un poste de récupération, avec `age-keygen -o identity.txt`. La clé privée reste sur ce poste et dans une copie séparée. Seule la clé publique renvoyée par `age-keygen -y identity.txt` va sur le serveur. Créer un espace de sauvegarde privé dédié chez le second prestataire et configurer le remote `rclone` avec des droits limités à cet espace. [Clés age](https://github.com/FiloSottile/age) · [Configuration S3 de rclone](https://rclone.org/s3/)

Pour l’exécution quotidienne, définir dans l’environnement du service ou du compte opérateur les variables suivantes. Le fichier de configuration rclone doit rester privé.

```bash
export VYDFLOW_BACKUP_RECIPIENT='<clé publique age>'
export VYDFLOW_BACKUP_REMOTE='archive:bucket-prive/vydflow'
export VYDFLOW_BACKUP_SPOOL=/var/lib/vydflow/backup-spool
export RCLONE_CONFIG=/etc/vydflow/rclone.conf
bash scripts/production-backup.sh backup
```

Le script arrête les services applicatifs actifs le temps de produire la paire cohérente. Il relance les services avant le transfert. La base et le tar des médias sont chiffrés au fil de leur lecture. Le manifeste d’empreintes est envoyé en dernier. En cas de coupure, la copie chiffrée reste sur le disque et l’invocation suivante reprend le même identifiant. Réserver la place pour une copie chiffrée complète en attente de transfert. Programmer une exécution quotidienne en période creuse avec un contrôle de l’âge de la sauvegarde distante et une alerte sur code de sortie non nul. Sur le compartiment, configurer l’expiration des objets sous le préfixe VydFlow après sept jours, puis vérifier qu’une sauvegarde complète récente reste restaurable. [Commande de transfert rclone](https://rclone.org/commands/rclone_copyto/)

## Vérifier une restauration

Préparer un autre projet Compose avec un fichier d’environnement propre, d’autres adresses du réseau interne et des volumes neufs. Sur un hôte qui sert déjà VydFlow, réserver des ports de test sur le loopback. Garder les workers et l’accès public arrêtés. Sur un poste de récupération qui a accès à la clé privée, télécharger la paire par son identifiant puis vérifier son déchiffrement. Lors de la restauration d’une sauvegarde de comptes réels, remettre également les clés de chiffrement applicatives depuis la copie séparée.

```bash
set -e -o pipefail
bash scripts/production-backup.sh fetch "$backup_id" "$recovery_dir"
export VYDFLOW_ENV_FILE="$restore_env"
restore=(docker compose -p vydflow-restore --env-file "$restore_env" -f compose.production.yaml)
"${restore[@]}" up -d --wait database
age -d -i "$identity" "$recovery_dir/database.dump.age" |
  "${restore[@]}" exec -T database pg_restore -U vydflow -d vydflow --no-owner --no-privileges --exit-on-error
age -d -i "$identity" "$recovery_dir/media.tar.age" |
  "${restore[@]}" run --rm --no-deps -T --entrypoint tar web -C /app/var/storage -xf -
"${restore[@]}" up -d --wait ingress
"${restore[@]}" exec -T --user www-data web php bin/console doctrine:schema:validate
```

Contrôler le propriétaire, l’empreinte d’un média, sa lecture authentifiée, les files et les publications déjà confirmées avant toute reprise. En cas d’échec de restauration, reprendre sur deux volumes vierges.

## Recette sur la cible

| Contrôle | Résultat attendu |
| --- | --- |
| Web et accès | Redirection HTTPS, certificat valide et renouvellement vérifié, cookie de session sécurisé et accès réservé aux invités. |
| Emails et comptes | Confirmation et récupération reçues dans une boîte de test, liens sur le domaine du pilote, retour OAuth sur une chaîne autorisée. |
| Sauvegarde et alerte | Restauration isolée de PostgreSQL et d’un média de test depuis la copie chiffrée hors machine, empreinte identique et lecture privée. Relever la durée de la copie et de la restauration, puis vérifier la réception d’une alerte de test. |
| Import et publication | Depuis un iPhone et un Android, vérifier image, son et orientation. Suivre un envoi autorisé jusqu’à confirmation sur la chaîne, puis programmer et retrouver les deux résultats du second lot. [Protocole du pilote](pilote-publication-20261123.md) |
| Conservation | Sur un échantillon synthétique en environnement isolé, vérifier le lecteur et le badge « Déjà publiée » avant retrait, puis la trace sous « Publiées récemment » et l’envoi confirmé dans l’historique après retrait. Garder l’échéance normale sur le pilote. |

## Premier démarrage sur le VPS du 28 novembre

La révision `917cdd5` a été construite sur le VPS Debian 13 à Marseille. La base PostgreSQL et le volume médias ont été créés sur le serveur. Les migrations et la validation du schéma sont passées. En HTTPS sur le serveur, l’accueil, la connexion et l’inscription répondent 200. La page Studio redirige un visiteur anonyme vers la connexion. Les trois workers consomment leurs files, qui étaient vides à la vérification. Le disque disposait de 71 Gio libres après installation.

La configuration du pilote se trouve dans `/etc/vydflow/pilot.env`. Pour les commandes Compose, définir `VYDFLOW_ENV_FILE` avec ce chemin et le passer aussi à `--env-file`. Les premiers contrôles ont été effectués sur les ports de boucle locale avec un certificat de test.

Après l’ajout de l’enregistrement A dans Cloudflare, le proxy est passé aux ports publics 80 et 443 avec un certificat Let’s Encrypt. Depuis le PC, la page d’accueil et la connexion répondent 200, HTTP redirige vers HTTPS et le Studio exige une connexion. Les inscriptions libres et l’indexation sont désactivées. Le test de renouvellement Certbot par webroot a réussi. Le timer est actif et le script `/etc/letsencrypt/renewal-hooks/deploy/vydflow-nginx` a rechargé Nginx après validation de la configuration. Ce script pointe vers la révision installée et son chemin doit être actualisé si la prochaine version est installée ailleurs. Le SMTP, OAuth YouTube et la sauvegarde externe restent à configurer avant la recette avec des invités.

### Configuration actuelle des inscriptions

À la demande du propriétaire, `ACCOUNT_REGISTRATION_OPEN=1` sur le VPS. La valeur par défaut de Compose reste `0`. Le compte préparé manuellement a été supprimé pour permettre un essai depuis le formulaire. Le relais Brevo est configuré sur le port 587 avec TLS obligatoire. Le relais a accepté l’authentification, les deux entrées DKIM Brevo sont visibles et la page d’inscription répond 200.

Le propriétaire a confirmé avoir reçu le message et terminé l’inscription. L’image web active est `vydflow-production:pilot-20261128-media`, construite à partir des fichiers transférés par SSH dans `/opt/vydflow/releases/pilot-20261128-media`. La base `vydflow` est persistée dans le volume Docker `vydflow-production_postgres`. Les vidéos sont dans le volume `vydflow-production_media`. Une sauvegarde PostgreSQL avant mise à jour est présente sous `/var/backups/vydflow`. La sauvegarde hors serveur reste à configurer.

## Répétition locale du 24 novembre

- Image de production reconstruite. Projet Linux isolé accessible en HTTPS sur le loopback avec un certificat de test. Accueil et JavaScript servis, redirection HTTP 308, hôte inattendu refusé, connexion du compte fictif réussie et cookie de session `Secure`.
- Endpoint WebDAV local utilisé comme destination de test. L’indisponibilité a laissé la copie chiffrée en attente et les services ont repris. La tentative suivante a transféré la même copie. Une modification d’un octet côté destination a produit un échec de vérification au téléchargement.
- Sauvegarde intacte déchiffrée et restaurée dans deux volumes vierges. Schéma Doctrine conforme, compte lié au média, octets identiques au fichier de départ et vidéo lisible après connexion HTTPS. Les workers du projet restauré sont restés arrêtés et sa file était vide.
- Restent à vérifier en hébergement réel le stockage S3, la notification d’incident, le renouvellement TLS, la délivrabilité SMTP et le retour OAuth. Mesurer la coupure d’écriture pendant la sauvegarde et le temps de restauration avec un volume représentatif avant d’ouvrir les invitations.
