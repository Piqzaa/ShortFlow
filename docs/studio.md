# Studio — vue d'ensemble, publications et compte

## Objectif

La bibliothèque gère les vidéos sources ; Publications suit chaque envoi par réseau. La vue d'ensemble donne les vrais compteurs, les trois derniers envois à traiter, l'état local des réseaux et les dernières vidéos. Une même source peut produire plusieurs résultats. Calendrier, IA et TikTok ne sont pas ajoutés à la navigation avant leurs lots.

## Fichiers et composants

- `PublicationOverview` lit PostgreSQL via DBAL : union des deux tables actuelles de publication, filtre propriétaire obligatoire, tri commun date/réseau/id, pagination de 20 envois et filtres réseau/état. Les projections ne lisent aucun jeton. Aucun adaptateur futur ni nouvelle table.
- `DashboardController` et `studio/index.html.twig` présentent les compteurs et les actions. Les connexions sont lues localement, sans appel Google/Meta ; un jeton d'accès YouTube expiré n'impose pas à lui seul une reconnexion, puisque le client sait le renouveler. L'expiration Instagram est indiquée.
- `PublicationController::index`, `publication/index.html.twig` et `_job.html.twig` fournissent le suivi commun et conservent les actions/CSRF propres à chaque réseau. Les filtres et la pagination sont des liens/formulaires GET utilisables sans JavaScript.
- `ProfileController`, `ChangePasswordType`, `AccountAccess::changePassword` et `studio/profile.html.twig` : informations du compte, stockage utilisé et changement de mot de passe. `User::getCreatedAt` expose la date déjà stockée. L'email reste en lecture seule ; ni changement d'email, ni abonnement, ni suppression automatisée ne sont ajoutés.
- `studio/base.html.twig` : Mon compte sur desktop et mobile, Réseaux et état actif de navigation. `legal/_links.html.twig` propose un mode compact vers l'index légal dans les pieds de page ; les documents gardent leur navigation complète et l'inscription conserve les liens contextuels CGU/confidentialité.
- `assets/styles/studio.css` : compteurs, filtres, états d'attention, profil et adaptation mobile, sans framework ajouté.

## Flux et sécurité Symfony

GET /studio → Security exige ROLE_VERIFIED → CurrentUser fournit le propriétaire → requêtes dédiées → Twig échappé. Les nombres sont calculés depuis les publications, jamais des métriques marketing inventées. Les états « à traiter » regroupent les échecs, suspensions et résultats incertains ; « en cours » regroupe file, transfert et traitement. Les requêtes sont filtrées côté serveur ; les paramètres GET réseau/état sont limités aux valeurs connues.

POST /studio/mon-compte → formulaire/CSRF et limiteur compte → validation des nouveaux mots de passe → transaction et verrou propriétaire → vérification du mot de passe actuel sur l'utilisateur rafraîchi → hashage Symfony → invalidation des anciens liens de récupération → déconnexion et invalidation de la session courante. Les autres sessions sont rejetées à leur prochaine requête grâce à l'empreinte du mot de passe déjà présente dans `User::__serialize`. Le changement de mot de passe ne déconnecte pas les comptes sociaux et ne modifie pas les vidéos.

À comprendre : une requête de lecture DBAL peut réunir les données de deux entités sans changer leur mapping ni les services de publication. Le contrôleur prépare la réponse, le service porte les requêtes ou la modification transactionnelle, le FormType porte les contraintes, Twig porte le rendu. La pagination concerne l'affichage ; elle ne limite pas les compteurs globaux.

## Tester

Exécuter `docker compose exec -T php php scripts/check.php` (migrations de shortflow_test avant PHPUnit), puis les commandes Node du README. Les tests `StudioOverviewTest` couvrent les deux réseaux, leur ordre, les filtres, la pagination, l'isolation propriétaire, l'échappement, l'absence de jetons dans le HTML, le profil privé, le CSRF, le mot de passe actuel, les sessions, les liens de récupération et le limiteur. Les tests de publication existants continuent à couvrir reprise/arrêt avec API simulées.

Parcours manuel : vue d'ensemble → compteur À traiter → filtres → vidéo source ; Mon compte → mot de passe actuel incorrect puis nouveau valide ; vérifier la reconnexion demandée. Contrôler les états vides, desktop/mobile, Tab, formulaires et l'accès aux documents légaux depuis le lien unique. Aucun essai social réel n'est nécessaire.
