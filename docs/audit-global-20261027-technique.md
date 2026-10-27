# Audit technique global — 27 octobre 2026

## Périmètre et méthode

Revue de code et de configuration, en lecture seule, du backend Symfony, des contrôles d'accès, des médias privés, de l'asynchrone, des publications, des coûts IA, des séries et de l'exploitation d'une bêta multi-utilisateurs. Seul ce rapport a été écrit. Aucun test, changement de base, appel fournisseur, inférence, envoi social, commit ou push exécuté pour cet audit.

Référence communiquée par le responsable du lot : commit `d3e4700`, 617 tests / 7 470 assertions et 136 tests frontend réussis. Ces résultats ne sont pas réexécutés ni présentés comme une mesure indépendante. Les gains de requêtes du lot précédent ne remplacent pas un test de charge.

Les liens ci-dessous sont relatifs à ce document ; les numéros désignent les lignes observées pendant la revue. Gravité élevée = obstacle avant une bêta publique fiable ; moyenne = défaut ou limite à traiter avant d'augmenter la charge. « Lacune opérationnelle » signifie absence de preuve dans le dépôt, et non preuve qu'aucun dispositif externe n'existe.

## Décision technique

Le socle local protège correctement les ressources et les confirmations examinées. Aucun contournement transversal de propriété, renvoi payant automatique après réponse incertaine, ou changement silencieux de compte destinataire n'a été constaté dans les parcours relus. Cela ne constitue pas une certification de sécurité.

Le dépôt décrit toujours une installation locale, pas un service public exploitable tel quel. Avant une bêta ouverte, les priorités sont une entrée serveur de production, des emails réellement délivrables, une restauration testée et une surveillance des tâches. Un défaut concret de durée de vie des rédactions peut aussi produire des échecs sous file d'attente multi-utilisateurs.

## Constats prioritaires

### T1 — L'installation fournie reste une installation locale

- **Nature :** lacune opérationnelle constatée dans la configuration versionnée ; gravité **élevée**, priorité avant exposition publique.
- **Preuve :** [Dockerfile:8](../docker/php/Dockerfile#L8) lance `php -S` ; [compose.yaml:6](../compose.yaml#L6) expose uniquement loopback, [compose.yaml:7](../compose.yaml#L7) monte tout le checkout ; les workers fixent `APP_ENV: dev` aux lignes 26, 44, 62 et 85. [README:201](../README.md#L201) interdit explicitement de publier cette configuration. Le seul healthcheck Compose porte sur PostgreSQL ([compose.yaml:102](../compose.yaml#L102)).
- **Impact :** changer simplement le binding ou placer un tunnel devant ce service ne donne ni une capacité de traitement web adaptée, ni un cycle de déploiement reproductible, ni une vérification de disponibilité de l'application. Une requête lente peut immobiliser le serveur intégré. Les choix actuels sont appropriés au développement et ne sont pas une fuite réseau publique observée.
- **Proposition minimale :** conserver cette configuration locale ; fournir séparément un serveur HTTP/PHP de production avec HTTPS, `APP_ENV=prod`, `APP_DEBUG=0`, assets/dependencies construits, stockage persistant explicite et contrôle de santé applicatif. Définir l'origine publique, les proxies autorisés et la politique de cookies pour cette topologie. Une instance suffit pour une première bêta ; ne pas introduire de cluster ou Redis sans besoin.
- **Effort :** moyen, environ 2–4 jours avec recette sur l'hébergement choisi.
- **Critères :** installation depuis un checkout propre ; aucun serveur intégré, outil de développement ou Mailpit exposé ; connexion/session/upload privé valides derrière HTTPS ; web et workers utilisent le même environnement et le même stockage ; redémarrage et déploiement sans perte des travaux confirmés. Les en-têtes et cookies réels restent à vérifier sur la cible.

### T2 — Changer le transport email ne suffit pas à rendre les comptes utilisables publiquement

- **Nature :** configuration applicative de développement constatée ; gravité **élevée** pour l'ouverture des inscriptions.
- **Preuve :** [AccountAccess.php:73](../src/Service/AccountAccess.php#L73) impose `VydFlow <bonjour@vydflow.test>` pour confirmation et récupération de mot de passe. [compose.yaml:10](../compose.yaml#L10) utilise Mailpit. La confirmation est nécessaire pour accéder au studio ([security.yaml:29](../config/packages/security.yaml#L29), contrôle `ROLE_VERIFIED`).
- **Impact :** un SMTP externe configuré seul conserve un expéditeur `.test` impropre à une délivrabilité publique. La confirmation et la récupération peuvent être rejetées ou ne pas arriver ; le client reste bloqué avant le produit.
- **Proposition minimale :** rendre l'expéditeur configurable, configurer un domaine et un transport d'envoi réels ; vérifier les liens contre `APP_PUBLIC_URL`. Conserver la réponse neutre des demandes et ne pas journaliser les adresses/liens secrets.
- **Effort :** petit pour le code, moyen pour DNS et recette, environ 0,5–1,5 jour hors délais DNS.
- **Critères :** confirmation puis récupération reçues dans deux boîtes externes de test ; liens HTTPS vers le bon domaine ; refus du rejeu et expiration maintenus ; panne SMTP rendue proprement. Aucun email réel envoyé dans cet audit.

### T3 — Pas de restauration complète démontrée pour les données et les clés

- **Nature :** lacune opérationnelle ; gravité **élevée**.
- **Preuve :** [README:118](../README.md#L118) demande de sauvegarder les fichiers avec PostgreSQL ; [README:102](../README.md#L102) précise que restaurer la base sans les anciennes clés rend les jetons illisibles. [PrivateVideoStorage.php:11](../src/Service/PrivateVideoStorage.php#L11), [AiReferenceStorage.php:12](../src/Service/AiReferenceStorage.php#L12) et [ProfileAvatarStorage.php:11](../src/Service/ProfileAvatarStorage.php#L11) utilisent plusieurs répertoires privés sous `var/storage`. Aucun script/procédure de sauvegarde puis restauration vérifiée n'a été trouvé dans `scripts`, `docker` ou le guide de démarrage.
- **Impact :** une sauvegarde du code ou du seul volume PostgreSQL ne restaure pas les vidéos, références, exports et accès chiffrés. Après restauration, une file ancienne peut reprendre des traitements ou afficher des sources absentes. Les duplications distantes doivent être évitées avant de redémarrer des consommateurs.
- **Proposition minimale :** définir un ensemble de sauvegarde cohérent base + tout stockage privé + clés conservées séparément et chiffrées ; rétention, copie hors machine et procédure de reprise. Restaurer d'abord avec workers arrêtés et sorties externes désactivées, examiner les travaux incertains, puis reprendre explicitement. Ne pas changer les clés pendant une restauration.
- **Effort :** moyen, 1–2 jours puis contrôle régulier.
- **Critères :** restauration sur une machine/racine isolée ; vidéo et référence réellement lisibles par leur propriétaire seulement ; jeton synthétique déchiffrable ; préparation, version et consentement conservés ; aucun appel externe lors de l'essai. Fixer et mesurer une perte de données maximale et un temps de reprise acceptables. Aucun état de sauvegarde du poste n'a été inspecté.

### T4 — Les rédactions peuvent expirer avant leur premier passage au worker

- **Nature :** défaut logique constaté, scénario de charge non exécuté ; gravité **moyenne**, priorité avant une bêta utilisant le rédacteur partagé.
- **Preuve :** [StoryProposals.php:73](../src/Service/StoryProposals.php#L73) expire aussi l'état `queued` dès dix minutes depuis `createdAt`. [StoryProposals.php:90](../src/Service/StoryProposals.php#L90) applique ce contrôle avant de réclamer le travail ; les lectures `owned/latest` l'appliquent également aux lignes 55/65. [compose.yaml:33](../compose.yaml#L33) définit un seul `story-worker`, et [messenger.yaml:51](../config/packages/messenger.yaml#L51) / ligne 53 lui adressent les séries et propositions manuelles. [LocalStoryWriter.php:32](../src/Service/LocalStoryWriter.php#L32) commence deux étapes d'inférence ; chaque appel HTTP autorise 180 secondes ([LocalStoryWriter.php:227](../src/Service/LocalStoryWriter.php#L227)).
- **Impact :** plusieurs propriétaires peuvent chacun soumettre une rédaction valide. Deux travaux précédents prenant environ six minutes chacun suffisent à faire dépasser les dix minutes d'attente au suivant. Sa rédaction est alors déclarée expirée sans avoir été exécutée. C'est une erreur d'attente en file, différente d'une inférence réellement bloquée ; le limiteur par utilisateur ne la prévient pas.
- **Proposition minimale :** distinguer l'âge en attente de l'âge d'exécution (`startedAt` ou échéance par phase), afficher l'attente et gérer explicitement une admission bornée. Conserver un délai d'exécution strict et ne jamais lancer de génération vidéo pour « réparer » une rédaction expirée. Augmenter arbitrairement la durée ne donne pas une capacité garantie.
- **Effort :** moyen, 0,5–1 jour avec tests simulés.
- **Critères :** plusieurs propriétaires, horloge simulée et worker retardé de plus de dix minutes : un travail encore attendu ne doit pas être présenté comme une inférence interrompue ; une exécution réellement bloquée expire ; un ancien résultat n'écrase pas une nouvelle demande. Aucun appel Ollama requis pour cette vérification.
- **Corroboration communiquée par l'agent principal pendant l'audit :** reproduction en mémoire avec un vrai `StoryProposal` en attente depuis onze minutes, appel de la méthode `expire()` réelle et gestionnaire Doctrine simulé : `queued → failed`, un `flush`, zéro appel de base ou d'inférence. L'artefact local ignoré `artifacts/audit-global-expiry.log` a été lu par le rédacteur de ce rapport ; la reproduction a été exécutée par l'agent principal. Elle démontre l'expiration avant exécution, pas un débit multi-utilisateurs mesuré.

### T5 — Le redémarrage des workers ne remplace pas une surveillance des traitements

- **Nature :** lacune opérationnelle constatée dans le dépôt ; gravité **élevée** pour des créneaux promis à des clients.
- **Preuve :** [worker.sh:22](../docker/php/worker.sh#L22) consomme avec limites mémoire/temps, et Compose le redémarre : cela couvre la sortie du processus. [messenger.yaml:3](../config/packages/messenger.yaml#L3) déclare une file d'échecs. [PublishYouTubeHandler.php:38](../src/MessageHandler/PublishYouTubeHandler.php#L38) absorbe les exceptions et stocke une notice neutre ; [AiGenerations.php:208](../src/Service/AiGenerations.php#L208) conserve l'incertitude plutôt que renvoyer. Le dépôt ne fournit pas de contrôle périodique de l'âge des files, du dernier traitement réussi, de la file d'échecs et de l'espace disque. Les commandes de reprise existent mais restent manuelles : [RecoverPublicationScheduleCommand.php:14](../src/Command/RecoverPublicationScheduleCommand.php#L14), [RecoverSeriesRecurrencesCommand.php:14](../src/Command/RecoverSeriesRecurrencesCommand.php#L14).
- **Impact :** un worker vivant mais lent, une file saturée, une panne fournisseur ou un disque plein peuvent être découverts seulement par le client. Les erreurs métier rattrapées ne vont pas nécessairement dans `publication_failures`. Le créneau peut alors dépasser la tolérance de deux minutes et nécessiter une nouvelle confirmation.
- **Proposition minimale :** contrôle de disponibilité web, heartbeat par file, âge du plus ancien message prêt, volume d'échecs, tâches bloquées et espace libre ; notification opérateur sur seuils. Ajouter des événements structurés limités à identifiant interne, phase et catégorie de cause lorsque nécessaire ; exclure tokens, URLs signées, emails, prompts et corps fournisseurs. Écrire un court guide d'intervention distinguant suivi, reprise et nouvelle soumission.
- **Effort :** moyen, 1–2 jours pour une première version simple sur une instance.
- **Critères :** arrêt d'un consommateur, travail synthétique en échec et seuil disque simulé produisent une alerte ; pas de faux « tout va bien » basé uniquement sur `docker compose ps` ; reprise documentée sans envoi automatique d'un traitement incertain. Les outils de supervision éventuellement externes n'ont pas été consultés.

### T6 — Une série peut croître sans borne alors que sa page charge tout son historique

- **Nature :** limite de performance constatée dans le code ; gravité **moyenne**. Aucune latence ni saturation mesurée à grand volume.
- **Preuve :** [AiSeriesStudio.php:158](../src/Service/AiSeriesStudio.php#L158) limite un ajout à douze épisodes, sans borne totale ; les ajouts et les nouvelles activations peuvent se répéter. [AiSeriesStudio.php:27](../src/Service/AiSeriesStudio.php#L27) charge tous les épisodes avec génération. [AiSeriesController.php:181](../src/Controller/AiSeriesController.php#L181) charge toutes les occurrences, puis [AiSeriesController.php:228](../src/Controller/AiSeriesController.php#L228) calcule formulaires, couvertures et résumés de publication pour la série entière. Le regroupement récent des requêtes supprime des allers-retours mais ne borne pas le nombre de lignes et de formulaires.
- **Impact :** une série de longue durée ou de nombreux ajouts accroissent mémoire PHP, taille HTML et travail de base à chaque visite. Le nombre de requêtes peut rester bas pendant que le coût de la page augmente. Cela concerne d'abord le propriétaire de la série et ensuite les ressources partagées.
- **Proposition minimale :** paginer les épisodes et l'historique des occurrences, avec les actions courantes et les épisodes à traiter accessibles. Calculer couvertures/résumés seulement pour la page. Éviter un plafond silencieux qui cacherait les données existantes.
- **Effort :** moyen, 1–2 jours, y compris adaptation du rendu.
- **Critères :** jeu synthétique de 500 épisodes et occurrences ; quantité chargée et formulaires bornés par la page, navigation stable et aucune perte d'accès aux anciennes versions ; budget de latence/mémoire mesuré avant/après. Aucun jeu volumineux créé ici.

## Contrôles favorables observés

- **Comptes et sessions :** [security.yaml](../config/packages/security.yaml) impose CSRF au login/logout, limite les tentatives et protège `/studio` ; `UserChecker` impose la confirmation. [User.php](../src/Entity/User.php) sérialise une empreinte du mot de passe afin que les anciennes sessions soient invalidées après changement. [AccountAccess.php](../src/Service/AccountAccess.php) stocke le hash du token et sérialise la consommation/changement de mot de passe sous verrou. Les liens de compte utilisent un fragment et une origine configurée.
- **Propriété et secrets :** `SocialAccounts` choisit un compte par propriétaire et identité distante explicite ; les workers/statistiques conservent l'identité des publications. `AiKeys.connectedProviders` ne lit que les noms des fournisseurs du propriétaire pour l'affichage. `TokenVault` utilise un chiffrement authentifié avec nonce aléatoire ; les clés restent hors Git. Les clients HTTP fournisseurs sont dédiés et non instrumentés pour ne pas capturer leurs secrets ([services.yaml:29](../config/services.yaml#L29)).
- **Médias :** noms aléatoires stricts, stockage hors `public`, vérification réelle par FFprobe/FFmpeg, paramètres de processus sous forme de tableaux, limites de taille/durée et protocole restreint. [VideoLibrary.php:47](../src/Service/VideoLibrary.php#L47) contrôle quota et réservations sous verrou propriétaire ; les imports de références prennent aussi ce verrou. Les routes Meta/TikTok publiques vérifient une signature et la cohérence du propriétaire de la source : ce sont des URL de transfert temporaires, pas la bibliothèque publique.
- **Dépenses et publication :** marqueurs persistés avant demandes incertaines, contraintes uniques de soumission/source, confirmations de coût, révisions et comptes figés. `AiGenerations.process` met une soumission interrompue en vérification au lieu de la retransmettre. Les séries bornent chaque autorisation à 1–12 créneaux, lient recette/tarif/compte par empreinte, et restent séparées de la confirmation de publication.
- **Lectures :** listes principales paginées, calendrier mensuel borné dans le temps, éléments sans date paginés, compte des sources sans hydratation des textes et résumés groupés. Les lectures `ValidationQueue` et `ProductionCalendar` sont SQL sans appels fournisseurs. Leur comportement ne doit pas être extrapolé en preuve de tenue en charge.

## Non vérifié et limites de conclusion

Aucun hébergement public, TLS, SMTP réel, configuration privée, secret en clair, compte social réel, solde fournisseur, sauvegarde existante, journal d'exploitation ou outil de monitoring externe n'a été consulté. Aucun scan réseau, audit de dépendances en ligne, test d'intrusion, analyse `EXPLAIN` sur une base chargée, test multi-processus ni benchmark de montée en charge n'a été effectué. Les versions/API et politiques des plateformes n'ont pas été revérifiées : cet audit porte sur le code local, pas sur leur conformité externe actuelle.

La session, le cache des limiteurs et le verrou fichier du rédacteur sont compatibles avec une installation locale/single-instance sous hypothèses maîtrisées. Leur partage entre plusieurs répliques n'est pas démontré ; ne pas annoncer une architecture distribuée prête. Le nombre de classes ou la taille de certains services n'est pas retenu comme défaut bloquant sans conséquence précise. Le monolithe Symfony et Messenger restent adaptés à cette V1.

## Croisement avec la revue marché et ouverture

La [revue marché/SEO](audit-global-20261027-marche-seo.md), relue sans nouvelle recherche externe, distingue correctement fonctions locales, disponibilité commerciale et autorisations des plateformes. Ses recommandations de bêta limitée, matrice d'accès datée, contact réellement exploité et restauration/suppression vérifiées sont proportionnées. Le travail de pages produit et d'aide peut avancer en parallèle, mais leur finalisation ne lève pas à elle seule les obstacles techniques T1–T5 ; aucune ouverture ou capacité de publication publique ne se déduit de cette revue. Les conclusions commerciales et tarifs concurrents n'ont pas été revérifiés par cet audit technique.

Le banc local du rédacteur conduit par l'agent principal rapporte six scénarios entre environ 126 et 171 secondes d'horloge murale, avec les limites de mesure explicitées dans le [rapport qualité final](audit-global-20261027-qualite.md). Ce ne sont pas une mesure de débit ni un engagement de délai. Ces essais renforcent la nécessité de distinguer attente et exécution dans T4 et de borner une première cohorte. La qualité des scénarios, dialogues et langues relève de ce rapport associé ; aucun résultat de ce banc n'autorise une promesse d'autonomie éditoriale ou de génération instantanée.

## Ordre minimal proposé

1. Cible de déploiement de production, emails de compte et restauration isolée démontrée (T1–T3).
2. Durée de vie/admission des rédactions partagées et surveillance opérationnelle (T4–T5).
3. Pagination de l'historique d'une série, puis une courte recette de charge réaliste et un scénario de panne/reprise (T6).

Ces travaux sont des recommandations d'audit : aucune correction, activation réelle, dépense ou ouverture publique n'est autorisée par ce document.
