# Prompt de lancement — VydFlow V1

> Document historique remplacé le 14 octobre 2026 par docs/roadmap.md, docs/decisions.md et AGENTS.md. Les instructions ci-dessous ne sont plus une autorisation active. Le lot courant couvre les comptes et l’espace privé ; la cible V1 inclut ensuite calendrier et IA BYOK. Aucun système de crédits IA VydFlow.

Tu travailles dans le projet Symfony existant VydFlow. Inspecte AGENTS.md, README.md, docs/architecture.md, docs/decisions.md, docs/roadmap.md, les tests, les routes et les fichiers existants avant de modifier le projet.

## Autorisation et objectif
Cette demande autorise explicitement le passage au-delà de la phase 1 décrite dans AGENTS.md : développe la V1 avec authentification, dashboard privé, bibliothèque et import privé sécurisé, brouillons, connexions OAuth et publication immédiate via les API officielles de YouTube Shorts, TikTok et Instagram Reels. Le verrou historique « phase 1 uniquement » est donc levé pour ce périmètre. Mets les instructions et la documentation en cohérence avec cette autorisation précise.

Pars de vidéos déjà créées. Le parcours attendu est : inscription ou connexion → dashboard → import d’une vidéo → préparation → choix de une à trois plateformes → confirmation → suivi du résultat par plateforme.

La landing immersive validée est sauvegardée au commit c979403 ; des clarifications éditoriales ont ensuite été ajoutées. Préserve leur état courant et les animations. Ne réinitialise pas le checkout pour revenir à ce commit. Ne reconstruis pas Symfony.

## Méthode
Travaille directement dans ce projet, par étapes cohérentes et testables. Commence par un état des lieux et un plan court, puis implémente. Ne t’arrête pas au plan. Ne demande pas une nouvelle autorisation pour les opérations locales courantes déjà couvertes ici. Ne publie, ne déploie et ne pousse rien. Aucun service payant, dépense, inscription externe ou suppression de données existantes sans accord spécifique.

Recherche tôt les capacités, contraintes et conditions d’accès actuelles des trois API dans leurs documentations officielles. Ne suppose pas qu’une vidéo acceptée par une plateforme le sera par les autres. Documente les comptes et autorisations requis, les modalités d’envoi et de suivi, les champs obligatoires et les contraintes réelles. N’invente pas la disponibilité d’une intégration.

Si des identifiants, permissions ou validations externes manquent, poursuis tout le travail local indépendant. Fournis une liste précise des étapes nécessitant le propriétaire. Une intégration non configurée doit apparaître indisponible ; aucun succès fictif dans l’application. Les doubles de test sont réservés aux tests et clairement distingués.

## Stack et architecture
Conserve Symfony 7.4 LTS, PHP 8.4 Docker, Doctrine/PostgreSQL, Twig, Stimulus et Vite. Aucun React, microservice ou refonte d’architecture. Controllers légers, formulaires Symfony, services ciblés, entités et migrations versionnées. Utilise Symfony Security et le hashage automatique des mots de passe. Ajoute un transport Messenger et un worker uniquement pour les véritables publications asynchrones ; définis alors les commandes locales, la stratégie de reprise et les états visibles.

## Étape 1 — Comptes et espace privé
- Inscription, connexion, déconnexion et récupération sécurisée du mot de passe.
- Vérification de l’adresse avant les actions qui la nécessitent ; mails via un environnement local de développement, sans service payant imposé.
- CSRF, validation serveur, limitation des tentatives et réponses évitant l’énumération des comptes.
- Routes privées protégées et contrôles de propriété serveur sur chaque ressource.
- Dashboard sobre et responsive : vidéos récentes, brouillons, publications, connexions sociales, bouton principal « Importer une vidéo ».
- États vides utiles, navigation clavier, erreurs compréhensibles. Aucun chiffre ou contenu utilisateur inventé.

## Étape 2 — Bibliothèque privée
- Importer une vidéo avec progression quand possible, prévisualiser, renommer et supprimer sa propre vidéo.
- Fixer et documenter des limites locales raisonnables de taille et formats ; contrôler côté serveur le fichier réel, pas seulement son extension ou le MIME fourni par le navigateur.
- Stockage hors de public, noms opaques, téléchargement et lecture autorisés uniquement au propriétaire ; ne jamais exposer un chemin de stockage.
- Prévoir une lecture vidéo compatible avec les requêtes Range si nécessaire.
- Gérer les fichiers refusés, imports incomplets, erreurs et nettoyage des fichiers temporaires.
- Ne pas ajouter de montage ou transcodage. Si une vérification de compatibilité nécessite une sonde média, justifie une dépendance minimale avant de l’ajouter ; pas de pipeline FFmpeg spéculatif.

## Étape 3 — Brouillons et préparation
- Choisir une vidéo de sa bibliothèque, créer et reprendre un brouillon.
- Informations communes puis champs spécifiques réellement requis par chaque API.
- Choisir une, deux ou trois destinations connectées : YouTube Shorts, TikTok, Instagram Reels.
- Prévisualisation et vérification avant confirmation ; signaler les incompatibilités et comptes déconnectés.
- Aucun bouton laissant croire qu’une plateforme non configurée peut publier.

## Étape 4 — Connexions et publication réelle
- OAuth officiel : contrôle du state et PKCE lorsque pertinent, scopes minimaux, retour d’autorisation sécurisé, reconnexion et déconnexion.
- Tokens chiffrés au repos avec clé hors Git ; jamais dans le HTML, le JavaScript ou les logs.
- Un adaptateur ciblé par plateforme, suivant son API réelle. Ne pas transformer cela en framework générique.
- Au clic de confirmation, créer une tentative d’envoi distincte pour chaque destination sélectionnée.
- États séparés et lisibles : en attente, en cours, publiée, échouée ; conserver l’identifiant externe et un lien si disponible. Distinguer réception par l’API et publication réellement confirmée.
- Idempotence : double clic, réexécution ou nouvelle tentative ne doivent pas créer un doublon. Traiter les états incertains après timeout avant de renvoyer une vidéo.
- Si une plateforme échoue, conserver les réussites et permettre une nouvelle tentative uniquement pour la destination concernée, après vérification de son état.
- Erreurs utiles sans secrets, limites API et expiration des tokens gérées. Pas de contournement des règles des réseaux.
- N’expose pas le serveur local publiquement pour faciliter un callback ou un transfert. Documente les éventuelles exigences HTTPS, domaines ou webhooks qui nécessiteront une future infrastructure autorisée.

## Hors périmètre
Pas de calendrier ni programmation automatique dans cette V1, pas de génération IA, montage, découpage, sous-titres, transcodage, statistiques avancées, équipe, facturation Stripe, traitements en masse ou automatisations. La publication immédiate peut être asynchrone techniquement, sans devenir de la programmation à une date future.

## Qualité et validation
- Migrations relues et appliquées sans effacer les données du développeur. Les noms historiques shortflow/shortflow_test peuvent rester pour préserver les bases ; branding visible VydFlow.
- Tests fonctionnels uniquement sur shortflow_test, migrations avant PHPUnit.
- Tester authentification, accès anonyme, isolation entre deux utilisateurs, CSRF, fichiers refusés, accès vidéo privé et suppression autorisée.
- Tester avec doubles d’API : succès, erreur partielle, expiration de token, timeout incertain, double soumission et nouvelle tentative sans doublon.
- Exécuter les contrôles disponibles du README : PHPStan, CS-Fixer, lints Symfony, PHPUnit, npm run check et npm run build. Rapporter exactement ce qui a été exécuté et ce qui reste bloqué.
- Vérifier le dashboard sur desktop/mobile, au clavier et avec états vides/chargement/erreur. Préserver la landing et le formulaire de liste d’attente.
- Ne présenter comme testée une publication réelle que si elle a réellement été exécutée avec les autorisations nécessaires. Sans compte configuré, les tests locaux ne prouvent pas la publication réelle.

## Pédagogie et livraison
Le propriétaire découvre Symfony. Pour chaque étape importante, expliquer son objectif, les fichiers modifiés et leur rôle, le flux d’exécution, comment la tester et le concept Symfony à comprendre. Mettre à jour la documentation et les commandes de démarrage.

Terminer avec un bilan : fonctions utilisables localement, validations exécutées, configuration externe manquante, limites et prochaines actions précises. Aucun commit automatique, aucun push ni déploiement sans demande.
