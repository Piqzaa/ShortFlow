# Studio — vue d'ensemble, publications et compte

## Objectif

La bibliothèque gère les vidéos sources ; Publications suit chaque envoi YouTube, Instagram ou TikTok. La vue d'ensemble donne les vrais compteurs, les trois derniers envois à traiter et en cours, l'état local des réseaux et les dernières vidéos. Une même source peut produire plusieurs résultats. Brouillons et Calendrier rejoignent la navigation dans leur lot du 18 octobre : préparations durables, choix du créneau confirmé et suivi commun, voir calendrier.md. IA reste à venir.

## Fichiers et composants

- `PublicationOverview` lit PostgreSQL via DBAL : union des trois tables actuelles de publication, filtre propriétaire obligatoire, tri commun date/réseau/id, pagination de 20 envois et filtres réseau/état. Les projections ne lisent aucun jeton. Aucun adaptateur futur ni nouvelle table.
- `DashboardController` et `studio/index.html.twig` présentent les compteurs et les actions. Les connexions sont lues localement, sans appel Google/Meta ; un jeton d'accès YouTube expiré n'impose pas à lui seul une reconnexion, puisque le client sait le renouveler. L'expiration Instagram est indiquée.
- `PublicationController::index`, `publication/index.html.twig` et `_job.html.twig` fournissent le suivi commun et conservent les actions/CSRF propres à chaque réseau. Les filtres et la pagination sont des liens/formulaires GET utilisables sans JavaScript.
- `ProfileController`, `ChangePasswordType`, `AccountAccess::changePassword` et `studio/profile.html.twig` : informations du compte, stockage utilisé et changement de mot de passe. `User::getCreatedAt` expose la date déjà stockée. L'email reste en lecture seule ; ni changement d'email, ni abonnement, ni suppression automatisée ne sont ajoutés.
- `studio/base.html.twig` : Mon compte sur desktop et mobile, Réseaux et état actif de navigation. Les documents du studio sont accessibles depuis Mon compte, sans lien légal dans la barre latérale, l'en-tête ou le pied de page. Mon compte regroupe confidentialité, documents légaux et exercice des droits. Les pages publiques gardent leur pied de page et l'inscription conserve les liens contextuels CGU/confidentialité.
- `assets/styles/studio.css` : structure commune, filtres, profil et adaptation mobile ; `assets/styles/dashboard.css` : tokens du studio, navigation compacte et composants de la vue d’ensemble, importés après les styles de publication. Sans framework ajouté.
- `studio/_activity.html.twig` réutilise le même rendu pour les aperçus à reprendre/en cours, avec les trois noms de réseaux et des états explicites. Le contrôleur ne charge chaque aperçu que si son compteur n'est pas nul.
- `assets/controllers/studio_navigation_controller.js` ajoute un retour d'attente aux navigations internes et soumissions natives. Il ignore clics modifiés, téléchargements, autres onglets, liens externes et ancres de la page. Les imports qui gèrent déjà leur progression ne sont pas interceptés. Les champs restent soumis normalement ; les soumissions répétées du même formulaire sont bloquées jusqu'au changement de page. `pageshow` rétablit les contrôles après retour navigateur ; les écouteurs sont nettoyés à la déconnexion Stimulus. Sans JS, les parcours HTTP habituels restent disponibles.

## Parcours quotidien

### Améliorations du 18 octobre — activité et préparations

Objectif : retrouver l’activité avant le tutoriel et reprendre rapidement une préparation. Les compteurs et les envois à vérifier précèdent les prochains départs et les brouillons. L’aide devient un panneau natif repliable, ouvert si la bibliothèque est vide. Sur mobile, le menu natif repliable indique la rubrique courante et conserve Mon compte. Les liens restent des navigations HTTP ; ouvrir le menu ne nécessite pas JavaScript.

`PublicationDraftRepository::dashboard` ajoute une lecture filtrée par propriétaire : compteurs globaux, trois prochains créneaux confirmés, deux préparations bloquées/en retard et deux derniers brouillons. Les totaux ne sont pas limités à ces aperçus. Le délai de tolérance de 120 secondes correspond au calendrier existant ; un départ plus ancien est signalé à reprogrammer sans modifier son état en base. Les préparations annulées ou déjà distribuées n’entrent pas dans ces listes. Les envois déjà créés restent comptés séparément dans Publications.

Flux Symfony : Security et CurrentUser → contrôleur → repository Doctrine/DBAL avec propriétaire et horloge injectée → projection des libellés par PublicationCalendar → Twig échappé → CSS. Le repository lit les données ; le contrôleur compose la page ; Twig rend les liens ; aucun handler Messenger ni adaptateur HTTP social n’est exécuté. Aucune migration nécessaire.

Fichiers concernés : `src/Repository/PublicationDraftRepository.php`, `src/Controller/DashboardController.php`, `templates/studio/index.html.twig`, `templates/studio/base.html.twig`, `assets/styles/dashboard.css`, `assets/styles/studio.css` et l’import dans `assets/app.js`. Les anciennes définitions du dashboard sont retirées de studio.css. Les surcharges contradictoires des aperçus dans `assets/styles/publication.css` sont regroupées en conservant le lecteur de 160 × 240 px.

À tester : `docker compose exec -T php php vendor/bin/phpunit --filter StudioOverviewTest` après migration de shortflow_test. Les scénarios couvrent l’isolation entre deux comptes, la limite exacte de retard, l’ordre des créneaux, les totaux supérieurs aux listes, l’état vide, l’absence de message/envoi créé et des clients HTTP qui échouent si appelés. Dans le navigateur, vérifier le menu avec Entrée/Tab, les liens calendrier/brouillons et les états vides ; aucun envoi réel nécessaire. Détails de QA dans audit-dashboard-ux-ui.md et verification.md.

À comprendre en Symfony : une page GET peut assembler plusieurs projections de données existantes sans nouvelle entité. Une horloge injectée rend les limites temporelles testables ; un repository doit toujours appliquer le propriétaire côté serveur. Le nombre affiché ne doit pas être déduit d’une liste limitée.

Le titre est compact et le dashboard propose « Préparer une publication » lorsqu'une source existe, sinon l'import. Ce lien rejoint la sélection de la bibliothèque (`#library-selection`) : sélectionner → choisir les réseaux → adapter les textes → enregistrer ou confirmer un envoi/créneau → suivre chaque destination. Les échecs et vérifications apparaissent avant les traitements actifs, puis les connexions et les vidéos récentes. « À configurer » signifie que l'intégration n'est pas activée ; « À connecter », « À reconnecter » et « À renouveler » décrivent l'état local du compte, sans appel social pendant l'affichage.

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


### Largeur et publications (17 octobre 2026)

Le conteneur commun du studio occupe la largeur disponible avec des marges internes et une limite de 2 200 px pour les très grands écrans. Les formulaires conservent leur largeur de lecture. Publications présente directement la vidéo source à côté des informations, sans lecture automatique. Les indications statistiques communes sont regroupées en haut de liste ; sans collecte active ni relevé, les compteurs vides ne s’affichent pas. Voir statistics.md.


### Supprimer une sélection

La barre de sélection de la bibliothèque propose Préparer la publication et Supprimer la sélection. Les deux boutons suivent le même compteur et se réactivent après retour navigateur. Le second mène par GET à une page de confirmation listant les titres ; aucune suppression à ce stade. Le POST de confirmation contient un jeton CSRF lié à la liste triée/dédupliquée des identifiants. Chaque vidéo doit appartenir au compte, sélection limitée à 20 (12 par page actuellement).

`VideoController::deleteSelection` valide la sélection et affiche la confirmation ; `VideoLibrary::deleteMany` verrouille le propriétaire et vérifie tous les envois YouTube/Instagram/TikTok avant de déplacer un fichier. Une transaction unique retire les lignes ; les fichiers déplacés sont restaurés si une erreur survient, puis purgés uniquement après validation. La suppression individuelle réutilise ce service. Une vidéo active bloque toute la sélection. Les publications déjà effectuées sur les réseaux ne sont pas supprimées ; l’historique local garde ses destinations et perd seulement sa source.

À tester avec des vidéos de test : sélectionner plusieurs fichiers → supprimer → annuler, puis confirmer ; contrôler les titres, les boutons sans sélection, le mobile et le retour arrière. Les scénarios automatisés couvrent isolation propriétaire, CSRF lié à la sélection exacte, déduplication, succès, vidéo active et restauration des fichiers après erreur de stockage. En Symfony, GET prépare une vue de confirmation ; seul un POST autorisé et validé déclenche la transaction métier.
