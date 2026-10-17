# Studio — vue d'ensemble, publications et compte

## Objectif

La bibliothèque gère les vidéos sources ; Publications suit chaque envoi YouTube, Instagram ou TikTok. La vue d'ensemble donne les vrais compteurs, les trois derniers envois à traiter et en cours, l'état local des réseaux et les dernières vidéos. Une même source peut produire plusieurs résultats. Calendrier et IA ne sont pas ajoutés à la navigation avant leurs lots.

## Fichiers et composants

- `PublicationOverview` lit PostgreSQL via DBAL : union des trois tables actuelles de publication, filtre propriétaire obligatoire, tri commun date/réseau/id, pagination de 20 envois et filtres réseau/état. Les projections ne lisent aucun jeton. Aucun adaptateur futur ni nouvelle table.
- `DashboardController` et `studio/index.html.twig` présentent les compteurs et les actions. Les connexions sont lues localement, sans appel Google/Meta ; un jeton d'accès YouTube expiré n'impose pas à lui seul une reconnexion, puisque le client sait le renouveler. L'expiration Instagram est indiquée.
- `PublicationController::index`, `publication/index.html.twig` et `_job.html.twig` fournissent le suivi commun et conservent les actions/CSRF propres à chaque réseau. Les filtres et la pagination sont des liens/formulaires GET utilisables sans JavaScript.
- `ProfileController`, `ChangePasswordType`, `AccountAccess::changePassword` et `studio/profile.html.twig` : informations du compte, stockage utilisé et changement de mot de passe. `User::getCreatedAt` expose la date déjà stockée. L'email reste en lecture seule ; ni changement d'email, ni abonnement, ni suppression automatisée ne sont ajoutés.
- `studio/base.html.twig` : Mon compte sur desktop et mobile, Réseaux et état actif de navigation. Les documents du studio sont accessibles depuis Mon compte, sans lien légal dans la barre latérale, l'en-tête ou le pied de page. Mon compte regroupe confidentialité, documents légaux et exercice des droits. Les pages publiques gardent leur pied de page et l'inscription conserve les liens contextuels CGU/confidentialité.
- `assets/styles/studio.css` : compteurs, filtres, états d'attention, profil et adaptation mobile, sans framework ajouté.
- `studio/_activity.html.twig` réutilise le même rendu pour les aperçus à reprendre/en cours, avec les trois noms de réseaux et des états explicites. Le contrôleur ne charge chaque aperçu que si son compteur n'est pas nul.
- `assets/controllers/studio_navigation_controller.js` ajoute un retour d'attente aux navigations internes et soumissions natives. Il ignore clics modifiés, téléchargements, autres onglets, liens externes et ancres de la page. Les imports qui gèrent déjà leur progression ne sont pas interceptés. Les champs restent soumis normalement ; les soumissions répétées du même formulaire sont bloquées jusqu'au changement de page. `pageshow` rétablit les contrôles après retour navigateur ; les écouteurs sont nettoyés à la déconnexion Stimulus. Sans JS, les parcours HTTP habituels restent disponibles.

## Parcours quotidien

Le titre est compact et le dashboard propose « Préparer une publication » lorsqu'une source existe, sinon l'import. Ce lien rejoint la sélection de la bibliothèque (`#library-selection`) : sélectionner → préparer par réseau → confirmer → suivre. La préparation reste distincte par réseau ; cette amélioration n'ajoute pas la confirmation simultanée multi-réseaux. Les échecs et vérifications apparaissent avant les traitements actifs, puis les connexions et les vidéos récentes. « À configurer » signifie que l'intégration n'est pas activée ; « À connecter », « À reconnecter » et « À renouveler » décrivent l'état local du compte, sans appel social pendant l'affichage.

À comprendre en Symfony : le contrôleur prépare les données, Twig produit les liens et les sections, CSS règle leur disposition et Stimulus ajoute seulement le retour d'attente. Une navigation déclenche toujours une vraie requête Symfony ; l'indicateur n'est pas un moteur de publication ni une navigation SPA. Le formulaire garde son CSRF et sa validation serveur.

## Flux et sécurité Symfony

GET /studio → Security exige ROLE_VERIFIED → CurrentUser fournit le propriétaire → requêtes dédiées → Twig échappé. Les nombres sont calculés depuis les publications, jamais des métriques marketing inventées. Les états « à traiter » regroupent les échecs, suspensions et résultats incertains ; « en cours » regroupe file, transfert et traitement. Les requêtes sont filtrées côté serveur ; les paramètres GET réseau/état sont limités aux valeurs connues.

POST /studio/mon-compte → formulaire/CSRF et limiteur compte → validation des nouveaux mots de passe → transaction et verrou propriétaire → vérification du mot de passe actuel sur l'utilisateur rafraîchi → hashage Symfony → invalidation des anciens liens de récupération → déconnexion et invalidation de la session courante. Les autres sessions sont rejetées à leur prochaine requête grâce à l'empreinte du mot de passe déjà présente dans `User::__serialize`. Le changement de mot de passe ne déconnecte pas les comptes sociaux et ne modifie pas les vidéos.

À comprendre : une requête de lecture DBAL peut réunir les données de deux entités sans changer leur mapping ni les services de publication. Le contrôleur prépare la réponse, le service porte les requêtes ou la modification transactionnelle, le FormType porte les contraintes, Twig porte le rendu. La pagination concerne l'affichage ; elle ne limite pas les compteurs globaux.

## Tester

Après synchronisation du code sur un autre poste, reconstruire `public/build` avec `docker compose --profile tools run --rm --no-deps node` puis recharger le navigateur. Twig produit le HTML côté Symfony ; Vite compile séparément les fichiers CSS/JS, ignorés par Git. Une migration de base ne reconstruit pas ces assets.

Les styles `studio.css` espacent les états réseau avec un conteneur flex qui revient à la ligne. `publication.css` réserve une colonne d'actions commune aux cartes de connexion, puis place les actions sous le contenu sur tablette/mobile. Le template `publication/connections.html.twig` affiche aussi un bouton désactivé explicite lorsqu'un réseau attend son activation, sans lancer OAuth.

Exécuter `docker compose exec -T php php scripts/check.php` (migrations de shortflow_test avant PHPUnit), puis les commandes Node du README. Les tests `StudioOverviewTest` couvrent ordre, filtres, pagination, isolation propriétaire, échappement, absence de jetons dans le HTML, aperçus d'activité, états vides, liens légaux et profil privé (CSRF, mot de passe actuel, sessions, liens de récupération et limiteur). Les tests de publication existants continuent à couvrir reprise/arrêt avec API simulées. `npm run test:ui` vérifie clics modifiés/liens externes/ancres, import déjà géré, doubles soumissions et restauration des formulaires/sélections après retour navigateur.

Parcours manuel : vue d'ensemble → compteur À traiter → filtres → vidéo source ; Mon compte → mot de passe actuel incorrect puis nouveau valide ; vérifier la reconnexion demandée. Contrôler les états vides, desktop/mobile, Tab, formulaires et l'accès aux documents légaux depuis le lien unique. Aucun essai social réel n'est nécessaire.


### Largeur du studio

Le conteneur commun occupe la largeur disponible avec des marges internes et une limite de 2 200 px pour les très grands écrans. Les formulaires conservent leur largeur de lecture.
