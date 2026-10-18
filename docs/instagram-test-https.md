# Essai Instagram par tunnel HTTPS temporaire

## Objectif et limites

Le propriétaire a autorisé le 18 octobre 2026 la préparation d'une entrée Docker sans debug et l'ouverture d'un tunnel temporaire pour les essais Instagram réels. Ce dispositif est réservé au test personnel sur le PC allumé ; ce n'est pas un hébergement de production ni une ouverture commerciale. Il ne déclenche aucune publication. Les comptes, fichiers et connexions utilisés sont ceux de la base locale existante, pas ceux de `shortflow_test`.

Le tunnel rend l'écran de connexion accessible sur Internet. Le studio conserve Symfony Security et ses contrôles de propriété. Les inscriptions, récupération de mot de passe, liste d'attente, pages publiques et outils de debug sont refusés par cette entrée. Importer les vidéos depuis le site local habituel est également possible. PostgreSQL et Mailpit conservent leurs ports loopback ; aucun tunnel ne les cible.

## Fichiers et rôle des composants

- `compose.social-test.yaml` ajoute deux services optionnels, sans modifier le serveur local habituel : `social-web` utilise PHP avec `APP_DEBUG=0`, le stockage `dev` existant et un cache séparé ; `social-tunnel` établit une connexion sortante vers Cloudflare. Seul le port de diagnostic 8001 est lié à loopback.
- `docker/social-test/router.php` filtre les chemins avant Symfony : connexion, déconnexion, studio protégé, assets compilés autorisés et route Instagram à signature. Aucun fichier arbitraire n'est servi par le serveur CLI. Le routeur définit le contexte HTTPS pour cette entrée dédiée, sans faire confiance aux en-têtes Forwarded du visiteur. Il fixe aussi le front controller pour Symfony Runtime.
- Le visuel public `/media/creators/hero-fashion.webp` de la page de connexion est explicitement autorisé, sans ouvrir les autres fichiers du dossier média ni les vidéos privées.
- `.env.local`, ignoré : identifiant Instagram, secret, clé de chiffrement, callback HTTPS et origine des vidéos. Ne jamais changer une clé de chiffrement déjà utilisée. Le secret doit être copié directement depuis Meta dans ce fichier, jamais dans une conversation.

Les journaux HTTP/PHP de cette entrée sont désactivés pour éviter l'enregistrement des codes OAuth, emails ou liens signés. Le front controller dédié `docker/social-test/index.php` enregistre seulement les exceptions non HTTP ou HTTP 500+ dans `/tmp/vydflow-social-errors.jsonl`, interne au conteneur et mode 0600 : date, classe, fichier/ligne et six emplacements de pile sans arguments. Aucun message d'exception, URL, requête, email, en-tête ni jeton. Une recréation de `social-web` supprime ce diagnostic éphémère ; un redémarrage conserve son système de fichiers. En cas de besoin d'un diagnostic plus détaillé, arrêter d'abord le tunnel. Le serveur PHP CLI est limité à ces essais manuels ; une mise en production demandera un serveur adapté, une exploitation et une sécurité complémentaires.

## Démarrer et arrêter

Avant toute exposition, vérifier les routes sur `http://127.0.0.1:8001`. Cette entrée suppose HTTPS et émet des cookies Secure : utiliser l'URL du tunnel pour une véritable session navigateur.

```sh
docker compose -f compose.yaml -f compose.social-test.yaml --profile social-test config --quiet
docker compose -f compose.yaml -f compose.social-test.yaml --profile social-test up -d social-web
docker compose -f compose.yaml -f compose.social-test.yaml --profile social-test up -d social-tunnel
docker compose -f compose.yaml -f compose.social-test.yaml logs --tail 30 social-tunnel
```

Le dernier affichage donne l'URL `https://….trycloudflare.com`. La recopier dans Meta avec le suffixe `/studio/connexions/instagram/retour`, et dans `.env.local` :

```dotenv
INSTAGRAM_REDIRECT_URI=https://ADRESSE/studio/connexions/instagram/retour
INSTAGRAM_MEDIA_BASE_URL=https://ADRESSE
```

Configurer aussi `INSTAGRAM_CLIENT_ID`, `INSTAGRAM_CLIENT_SECRET` et `INSTAGRAM_TOKEN_KEY`. Les identifiants appartiennent au produit Instagram Login, pas à Facebook Login. Après modification privée :

```sh
docker compose -f compose.yaml -f compose.social-test.yaml exec -T social-web php bin/console cache:clear --env=dev --no-debug
docker compose exec -T php php bin/console cache:clear --env=dev --no-debug
docker compose restart worker
```

Se connecter à VydFlow par l'URL HTTPS **avant** de lancer la connexion Instagram : la session et le state OAuth doivent rester sur ce même domaine. Les cookies de localhost sont distincts. Le compte Instagram doit être professionnel et autorisé comme testeur. Aucun token manuel n'est nécessaire.

Attention à YouTube : son retour local historique ne doit pas être utilisé pour un départ depuis HTTPS. Pour tester les deux réseaux sur HTTPS, ajouter dans le client OAuth Web de Google la même origine avec `/studio/connexions/youtube/retour` (conserver localhost dans les URI autorisées), puis mettre `YOUTUBE_REDIRECT_URI` sur cette nouvelle valeur dans `.env.local` et recharger les services. Si YouTube doit rester local, lancer sa connexion depuis localhost. Une connexion déjà enregistrée en base reste visible sur les deux origines pour le même propriétaire ; seule la session navigateur est distincte. Les contrôleurs refusent désormais un départ YouTube/Instagram d'une origine différente de leur retour configuré, au lieu de créer un flow qui échouera plus tard.

Pour fermer l'accès Internet et l'entrée de test, en conservant Docker local et toutes les données :

```sh
docker compose -f compose.yaml -f compose.social-test.yaml stop social-tunnel social-web
```

Une recréation du tunnel peut changer son URL : actualiser alors Meta et `.env.local` avant toute connexion/publication. Pas de garantie de continuité ; ne pas utiliser pour une programmation durable. TikTok nécessite en plus la validation de propriété du domaine/préfixe média et ses accès : ce tunnel Instagram ne valide pas cette exigence.

## Flux et points Symfony à comprendre

Navigateur HTTPS → Cloudflare → `social-web:8080` → routeur restreint → `public/index.php` → Symfony Security/controller/service. Le retour Instagram retrouve la session HTTPS et vérifie le state avant de stocker le jeton chiffré. Après une confirmation de publication distincte, le worker existant envoie à Meta un lien signé ; Meta télécharge la vidéo par la route média autorisée. La bibliothèque elle-même reste privée.

`APP_DEBUG=0` désactive les détails d'erreur ; `APP_ENV=dev` conserve volontairement la même base et le même répertoire vidéo que le poste local. Ce sont deux réglages distincts. Le cache séparé évite de mélanger le conteneur de services du serveur local et celui de l'entrée sans debug. Le worker est un processus permanent : il doit être redémarré pour recharger sa configuration privée.

## Vérifier avant l'essai réel

- `/connexion` et `/build/app.css` : 200 ; `/studio` sans session : redirection HTTPS vers `/connexion`.
- `/.env.local`, `/_profiler`, `/inscription` : 404 ; lien média falsifié/inexistant : 404.
- Session : cookie Secure, HttpOnly et SameSite=Lax ; aucun en-tête de debug. Aucun lien signé réel ni jeton dans les captures ou journaux.
- Après autorisation Meta, vérifier le profil connecté dans Réseaux avant toute confirmation. Premier envoi : une vidéo de test explicitement choisie ; vérifier sa visibilité et le résultat sur Instagram. Un Reel peut être public.

Les contrôles sans connexion ne valident pas encore OAuth, le téléchargement d'une vraie vidéo par Meta ou sa publication. Résultats exécutés : `verification.md`.

Sources : [Cloudflare Quick Tunnels](https://developers.cloudflare.com/tunnel/get-started/quick-tunnels/), [contexte HTTPS et reverse proxies Symfony](https://symfony.com/doc/7.4/deployment/proxies.html). Aucun proxy de confiance n'est ajouté à la configuration générale de VydFlow.
