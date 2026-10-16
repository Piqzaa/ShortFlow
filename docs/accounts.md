# Comptes VydFlow — guide du lot 1

## Objectif et périmètre
Un créateur crée son compte, confirme son email, puis se connecte à son espace privé. Il peut aussi récupérer l’accès. La confirmation est obligatoire avant toute connexion ; une récupération ne confirme pas l’adresse. Ce guide décrit le lot comptes. La bibliothèque et l’import sont ajoutés au lot suivant, décrit dans docs/library.md ; IA, calendrier et publication restent à venir. La landing bêta va désormais à l’inscription, la liste d’attente historique reste sur /liste-attente.

## Fichiers et responsabilités
- src/Entity/User.php : données du compte, identifiant Security, rôles et empreinte de session. Le mot de passe brut ne fait pas partie de l’entité.
- src/Repository/UserRepository.php : recherche Doctrine normalisée pour Security et renouvellement du hash lorsque nécessaire.
- src/Entity/AccountToken.php : propriétaire, empreinte d’un code, usage, expiration et consommation.
- migrations/Version20261014211700.php : création de app_user/account_token et index uniques, sans modification de waitlist_entry. Ne pas lancer le down sur des comptes à conserver.
- src/Form/RegistrationType.php : email et mot de passe répété de 12 à 128 caractères. AccountEmailType pour demander un lien ; AccountTokenType pour l’utiliser avec CSRF.
- src/Service/AccountAccess.php : création, hash via Symfony, émission d’emails et consommation transactionnelle des codes.
- src/Controller/AccountController.php : routes publiques, formulaires, limiteurs et réponses. DashboardController protège le rendu via la règle /studio de Security.
- config/packages/security.yaml : fournisseur Doctrine, firewall, form_login, CSRF, login_throttling et logout POST. rate_limiter.yaml : limites publiques par IP et email pseudonymisé.
- src/Security/UserChecker.php : contrôle de confirmation exécuté par Symfony après validation du mot de passe ; affiche une consigne de confirmation sans révéler ce statut à quelqu’un qui ne connaît pas le mot de passe.
- config/packages/mailer.yaml, compose.yaml, .env : SMTP local, transport null en test, origine des liens. APP_SECRET reste privé dans .env.local.
- templates/account, templates/studio et assets/styles/studio.css : écrans responsive accessibles sans animation obligatoire. assets/controllers/account_token_controller.js transfère le fragment du lien dans le formulaire.
- tests/Functional/AccountTest.php : parcours HTTP et protections. Les tests vérifient shortflow_test avant toute suppression de données de test.
- assets/controllers/cursor_controller.js, assets/styles/cursor.css et public/cursor*.svg : curseur original anthracite bordé, halo violet discret sur souris fine. Le halo est décoratif et ne bloque aucun clic. Les champs gardent le curseur texte ; tactile et mouvement réduit désactivent le halo. RAF seulement pendant le rattrapage de la position ; arrêt au repos, au clavier Tab, à la sortie et à la déconnexion Stimulus.

## Comprendre les flux Symfony
**Inscription** : navigateur → route /inscription → AccountController → RegistrationType (CSRF et contraintes) → AccountAccess → UserPasswordHasher → Doctrine persist/flush → AccountToken et Mailer → redirection 303 vers connexion. Un doublon reçoit le même message. L’index unique couvre aussi les requêtes concurrentes. En cas d’envoi SMTP indisponible, le compte peut exister : demander un renvoi de confirmation quand le transport revient.

**Connexion** : le firewall intercepte le POST avant le contrôleur ; il valide le CSRF, charge User via le repository et vérifie le hash. Puis UserChecker vérifie la confirmation d’email. Un compte non confirmé reçoit un message et un lien pour demander un renvoi de confirmation, sans ouvrir de session authentifiée. Après confirmation, Symfony crée la session et redirige vers /studio. Sur GET /studio, access_control exige ROLE_VERIFIED ; un visiteur anonyme revient à /connexion et une ancienne session non confirmée reçoit un refus. Ce contrôle serveur ne dépend pas du bouton ou du JavaScript.

**Confirmation/récupération** : un lien contient un code aléatoire en fragment #token=… ; le serveur ne reçoit pas ce fragment dans le GET. Stimulus le place dans le formulaire et nettoie l’URL. Le POST valide le CSRF puis le service compare l’empreinte SHA-256 en base, vérifie usage/expiration et verrouille la consommation dans une transaction. Le GET ne modifie jamais le compte. Sans JS, copier le code de l’email dans le champ. Un reset invalide tous les liens de reset et les anciennes sessions ; il ne connecte pas automatiquement l’utilisateur.

**Pourquoi Messenger n’intervient pas encore** : ces emails sont envoyés au SMTP local pendant la requête. Le premier worker sera ajouté pour une véritable tâche longue, notamment la génération IA. Ne pas confondre service injecté et processus en arrière-plan.

## Tester en local
1. `docker compose up -d`, puis `docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction` et `npm run build`.
2. Ouvrir http://localhost:8000/inscription et créer un compte de test avec une adresse fictive du domaine example.test.
3. Dans http://localhost:8026, ouvrir l’email, suivre le lien et cliquer « Confirmer mon adresse ». Rien n’est envoyé à un fournisseur email externe.
4. Se connecter ; vérifier l’espace vide et la disparition du bandeau de confirmation. Se déconnecter ; /studio doit renvoyer à la connexion.
5. Demander un mot de passe oublié, suivre le nouvel email, choisir un mot de passe puis essayer une seconde fois le même lien : il doit être refusé. Les anciennes sessions doivent être invalidées.
6. Tester email invalide, mots de passe différents, lien invalide et saisie manuelle du code sans JS. Après plusieurs tentatives, attendre le délai du limiteur local.
7. `docker compose exec php php scripts/check.php`, `npm run check`, `npm run build`. Les migrations test précèdent PHPUnit ; aucune remise à zéro des données dev.

## Limites avant production
Serveur PHP intégré et debug réservés au local. Mailpit non persistant : ses emails disparaissent à la recréation du conteneur. Cache des limiteurs local à une instance. Configurer HTTPS, mail transactionnel, adresse expéditeur réelle, purge des codes expirés/consommés, sauvegardes/restauration, suppression de compte et conservation des données avant commercialisation. Pas d’engagement de sécurité par simple existence des tests. Pas de garantie de réponse à temps constant pour les demandes neutres.
