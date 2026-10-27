# VydFlow — feuille de route active jusqu'au déploiement

Mise à jour : **27 octobre 2026**. Ce document est la source unique des priorités et de l'avancement. Il remplace les ordres de travail et hypothèses commerciales des anciennes roadmaps, conservés dans [l'archive historique](roadmap-historique-20261027.md). Les consignes de sécurité et autorisations restent applicables.

## Repère de reprise immédiat

- **Phase actuelle :** feuille de route préparée ; aucun lot d'implémentation ci-dessous commencé.
- **Dernier état applicatif validé :** `d3e4700` (performance et recette), sur `master` au moment du cadrage.
- **Prochaine tâche exacte : `R1.1`**, lire et reproduire l'expiration prématurée des rédactions en attente.
- **Ne pas commencer maintenant :** abonnement, nouvelle UI, publication autonome, blog ou déploiement.
- **Comptes confirmés :** Free 1, Creator 3 et Pro 9 comptes **par plateforme**, soit respectivement 3, 9 et 27 comptes maximum sur les trois réseaux.
- **Publications Free confirmées :** cinq par mois ; une vidéo diffusée sur plusieurs réseaux dans la même publication compte une seule fois. L'ancienne recommandation de décompte par destinataire est écartée.
- **Décisions en attente :** accès Studio/Séries par offre, plafonds payants et ressources de calcul/stockage ; identité précise de la publication, réservation et consommation à spécifier en R9. Voir les propositions commerciales plus bas.
- **Passage sur un autre PC :** lire ce repère, le journal en fin de document, puis [le protocole de reprise](#reprise-sur-un-autre-pc). Les conversations et `artifacts` ne sont pas nécessaires pour retrouver le prochain travail.

La demande du 27 octobre porte sur cette préparation documentaire. Le démarrage de l'implémentation se fera ensuite, point par point, sur instruction du propriétaire. Cette roadmap n'est pas une autorisation de dépense, de publication réelle, de paiement réel ou d'exposition publique.

## Règles de suivi à respecter

1. Suivre les lots dans l'ordre et traiter la première tâche non terminée dont les dépendances sont satisfaites. Les dossiers externes peuvent être préparés à leur lot ; ne pas sauter une porte de validation silencieusement.
2. `[ ]` = à faire ; `[x]` = terminé et vérifié. Pour un travail partiel, garder `[ ]` et inscrire précisément son état dans le journal. Pour un blocage, noter cause, action nécessaire et travaux indépendants possibles ; ne pas cocher ni annoncer une réussite.
3. Une tâche est terminée quand son résultat est livré, ses contrôles adaptés exécutés et leurs résultats consignés. La réussite des tests simulés ne valide pas un fournisseur ou un rendu vidéo réel.
4. À chaque fin de lot : bilan pédagogique, mise à jour des cases/du repère/du journal, documentation et commit local identifié. Un push suit seulement l'autorisation du propriétaire ; un commit local seul n'est pas disponible sur l'autre PC.
5. Avant changement de lot, vérifier sa porte de sortie et mettre explicitement la prochaine tâche. Toute modification de périmètre, prix, quota, unité ou ordre est décidée avec le propriétaire puis datée dans le journal avant implémentation.
6. Conserver les sources et préparations, textes humains, validations, comptes destinataires, isolation propriétaire, confirmations de coûts et limites de récurrence. Aucune suppression de données pour appliquer une nouvelle offre.
7. Revues indépendantes ciblées lors des lots importants, sans modifier simultanément les mêmes fichiers. Les tests de base utilisent exclusivement `shortflow_test`, migrations appliquées avant PHPUnit ; aucune suite DB concurrente sur cette base.
8. Pas de changement d'architecture/framework ni de service ajouté sans problème démontré. Garder le monolithe Symfony/Twig/Stimulus et l'orchestration existante.

Une preuve de fin de lot indique au minimum : tâches, comportement livré, fichiers principaux, commandes et résultats exacts, recette desktop/mobile pertinente, limites restantes, commit, push effectué ou non, prochaine tâche et décision attendue. Ne jamais recopier une ancienne suite comme si elle venait d'être exécutée.

## Avancement global

| Lot | Objectif | État | Porte avant le suivant |
| --- | --- | --- | --- |
| R0 | Audit, preuves et feuille de route portable | Terminé documentaire | Repère et données synthétiques lisibles dans Git |
| R1 | Attente, exécution et reprise du rédacteur | À faire | Une attente valide n'est plus confondue avec une exécution bloquée |
| R2 | Qualité des scénarios, dialogues et textes | À faire | Corpus rejoué, écarts signalés, textes humains préservés |
| R3 | Studio/Séries plus simples et compacts | À faire | Parcours commun compréhensible, devis et contrôles conservés |
| R4 | Performance et volumes bornés | À faire | Grandes séries paginées et mesures comparables |
| R5 | Responsive, accessibilité et finitions | À faire | Matrice d'écrans/états vérifiée, défauts bloquants corrigés |
| R6 | Landing, aide, contact et SEO de base | À faire | Promesse fidèle et premier parcours expliqué |
| R7 | Recette produit et qualité audiovisuelle | À faire | Scénarios réels de compréhension validés ; limites vidéo documentées |
| R8 | Accès plateformes et exploitation de production | À faire | Périmètre tiers, cible, données, emails et surveillance prêts |
| R9 | Offres et règles commerciales définitives | À faire | Prix, unités, plafonds et droits approuvés par écrit |
| R10 | Quotas puis abonnement logiciel | À faire | Paiements de test et droits robustes ; aucun débit réel implicite |
| R11 | Répétition générale avant mise en ligne | À faire | Dossier de validation complet et retour arrière testé |
| R12 | Déploiement et ouverture contrôlée | À faire | Go explicite du propriétaire et vérifications sur cible |

L'abonnement est le **dernier lot fonctionnel**. La recette finale et le déploiement viennent après, pas avant ses tests. Aucun calendrier de livraison n'est promis avant de connaître les accès externes, le budget et les retours de testeurs.

## R0 — Audit et suivi portable

- [x] **R0.1** Réaliser et croiser les audits UX, interface/responsive, technique, marché/SEO et qualité ; [synthèse et annexes](audit-global-20261027.md).
- [x] **R0.2** Conserver les neuf cas synthétiques et le diagnostic éditorial dans [le corpus versionné](fixtures/redacteur-audit-20261027.json), sans secret ni média privé.
- [x] **R0.3** Écrire cette roadmap, l'index de reprise dans README/AGENTS et le journal initial ; préserver l'ancienne roadmap en archive.

Référence de tests applicatifs déjà exécutés avant ce cadrage : **617 tests PHP / 7 470 assertions et 136 tests frontend** pour `d3e4700`. Pas de nouvelle suite applicative exécutée pour écrire la roadmap. Le corpus comprend six scénarios, trois essais éditoriaux et un diagnostic complémentaire avec réponse capturée ; les neuf cas et ce diagnostic ne constituent pas une suite PHPUnit.

## R1 — Attente et fiabilité du rédacteur

Dépendance : R0. Preuve : [T4 de l'audit technique](audit-global-20261027-technique.md), `StoryProposals::expire()`.

- [ ] **R1.1** Ajouter une reproduction automatisée déterministe de la demande `queued` de plus de dix minutes, sans inférence ni appel externe. Distinguer explicitement le résultat actuel incorrect du comportement attendu.
- [ ] **R1.2** Séparer temps en file et temps d'exécution/phase ; définir admission et délais explicites. Préserver reprise, nouvelle demande et protection contre un ancien résultat qui écraserait le nouveau.
- [ ] **R1.3** Afficher des états compréhensibles : en attente, rédaction, préparation des plans/textes, résultat ou erreur ; permettre de quitter puis retrouver le travail.
- [ ] **R1.4** Vérifier plusieurs propriétaires, travail retardé, résultat tardif, worker interrompu et reprise ; aucun double lancement ni génération vidéo déclenchée pour réparer un texte.
- [ ] **R1.5** Revue ciblée, contrôles adaptés, documentation Symfony et commit de fin de lot ; actualiser le repère de reprise.

Sortie : attente valide traitée comme telle, exécution bloquée bornée, tests simulés réussis et messages fidèles aux phases. Ne pas résoudre le défaut uniquement en augmentant un timeout.

## R2 — Qualité des scénarios et textes

Dépendance : R1. Preuves : [rapport qualité](audit-global-20261027-qualite.md) et corpus versionné.

- [ ] **R2.1** Transformer les défauts capturés en cas de non-régression : répliques perdues, prompts français non signalés, hashtags intégrés au texte, contexte inventé depuis un titre pauvre. Garder séparés les contrôles de structure et de sens.
- [ ] **R2.2** Définir le contrat des scènes parlantes : locuteur, réplique exacte, langue et capacité réelle du chemin vidéo. Tant que le contrat n'est pas porté, l'indiquer avant coût ; ne pas promettre de voix/lipsync depuis le seul indicateur audio.
- [ ] **R2.3** Corriger les consignes/validations de langue et fidélité ; accepter un prompt anglais contenant intentionnellement une citation française. Éviter les traits inventés et les actions contradictoires ; conserver une alerte honnête quand le contrôle ne peut pas conclure.
- [ ] **R2.4** Fiabiliser les packs sociaux complets et leur diagnostic. Garder la séparation des hashtags et les limites des réseaux ; ne pas désactiver le validateur pour accepter une mauvaise sortie.
- [ ] **R2.5** Demander un contexte factuel suffisant et rendre explicite que le rédacteur ne voit pas la vidéo. Proposer une réponse neutre ou une demande de précision plutôt qu'inventer ; préserver les textes saisis et l'application volontaire.
- [ ] **R2.6** Évaluer sur un corpus fixe avec quelques répétitions : récit, tutoriel, produit, dialogue, danse, mémoire de série, import peu décrit. Consigner structure, fidélité, erreurs, langue, corrections humaines, durées murale/monotone et conditions du poste. Aucun score commercial déduit d'un petit échantillon.
- [ ] **R2.7** Revue indépendante des sorties, bilan des limites, contrôles et commit. Si une capacité reste non validée, la limiter ou l'annoncer explicitement avant de cocher la sortie, avec décision du propriétaire ; ne pas l'omettre silencieusement.

Sortie : les défauts connus sont corrigés ou signalés avant dépense, textes humains protégés et évaluation publiée. Aucune autonomie éditoriale activée par ce lot. Un nouveau fournisseur/modèle ou appel payant demande son cadrage propre.

## R3 — Studio et Séries plus simples

Dépendance : R2. Préserver la préparation commune et les six destinations de navigation.

- [ ] **R3.1** Décrire puis valider un parcours commun en trois groupes : idée, scènes, récapitulatif ; aucun nouvel onglet « À valider » ni assistant multipage imposé.
- [ ] **R3.2** Présenter les plans en cartes compactes avec résumé français, durée et référence ; détails des prompts/images progressifs, accès manuel conservé. Signaler un résumé à revérifier après modification du prompt.
- [ ] **R3.3** Rapprocher choix « plan unique » et transformation effective, sans perte des textes ni réduction silencieuse des durées.
- [ ] **R3.4** Harmoniser le choix guidé des modèles et connexions entre vidéo ponctuelle et série ; distinguer proposition locale de texte et connexion nécessaire pour générer la vidéo.
- [ ] **R3.5** Garder action suivante, erreurs, modèle/compte, références réellement utilisées, nombre de plans, coût et confirmation accessibles. Replier les détails, pas les protections.
- [ ] **R3.6** Corriger le contexte de navigation après `forward`, le libellé « Subjects » et les résumés de comptes par vidéo. Expliquer disponibilités locales et reprise des créneaux manqués sans vocabulaire inutilement technique.
- [ ] **R3.7** Recette des préparations anciennes/nouvelles, brouillons, clavier et mobile ; revue UX, documentation et commit.

Sortie : un créateur sait saisir son idée, retrouver ses scènes et comprendre le récapitulatif ; les données existantes et consentements sont conservés. La hauteur seule n'est pas un critère de réussite.

## R4 — Performance et volumes

Dépendance : R3. Conserver les gains de `d3e4700` ; distinguer GET, calcul local, file et fournisseur distant.

- [ ] **R4.1** Construire des jeux synthétiques bornés petit/grand volume et mesurer séparément requêtes SQL, taille HTML, temps serveur et attente de rédaction, dans des conditions consignées.
- [ ] **R4.2** Paginer/borner les épisodes, occurrences et formulaires d'une série ; conserver accès à l'historique et aux épisodes liés au calendrier.
- [ ] **R4.3** Corriger les autres lenteurs démontrées ; ne découper les assets par écran que si les mesures le justifient. Préserver le chargement conditionnel et les animations/fallback de la landing.
- [ ] **R4.4** Vérifier isolation des données, états de publication identiques, navigation avec historique long, reprise et absence de nouveaux appels externes sur les lectures.
- [ ] **R4.5** Publier les mesures avant/après et limites, contrôles et commit.

Sortie : lectures et rendu des séries bornés ; résultats comparables sans promesse arbitraire de délai. Le test de capacité sur l'hébergement choisi reste en R8/R11.

## R5 — Responsive, accessibilité et états d'erreur

Dépendance : R4. Références : [matrice de l'audit interface](audit-global-20261027-interface.md).

- [ ] **R5.1** Vérifier les écrans principaux à 320, 390, 768 et 1280 px : vide/rempli, long titre, erreur, envoi actif, brouillon, compte déconnecté et contenu ancien. Inclure montage/export et historique, pas seulement les écrans heureux.
- [ ] **R5.2** Améliorer la lisibilité de la navigation étroite ; conserver menu, accès compte et toutes les destinations. Vérifier barres fixes, safe areas et clavier virtuel sur téléphone réel.
- [ ] **R5.3** Vérifier clavier complet, focus, libellés, annonces d'état, erreurs ouvrant leurs panneaux, contraste, zoom 200 %, lecteur d'écran ciblé et reduced motion système.
- [ ] **R5.4** Vérifier fallback sans JS, médias manquants, chargement lent/erreur et actions désactivées avec explication. Aucune action payante/sociale ne dépend uniquement d'une protection frontend.
- [ ] **R5.5** Corriger les défauts bloquants, consigner la matrice et les limites, revue et commit.

Sortie : parcours principaux utilisables dans les états contrôlés. Ne pas annoncer une conformité complète sur la seule base d'un outil automatique.

## R6 — Landing, aide et acquisition de base

Dépendance : R5. Référence : [audit marché/SEO](audit-global-20261027-marche-seo.md).

- [ ] **R6.1** Corriger les mentions obsolètes, descriptions SEO et accès connexion mobile ; conserver identité, animations et indication des démonstrations.
- [ ] **R6.2** Expliquer en HTML les parcours import/création/série, coûts VydFlow et fournisseur séparés, rôle des validations et statut bêta. Préparer une démonstration réelle autorisée ; aucun résultat fabriqué.
- [ ] **R6.3** Créer ou regrouper les contenus fonctionnement, coûts/bêta, réseaux compatibles et premiers pas. Aucun tarif provisoire affiché comme offre achetable.
- [ ] **R6.4** Ajouter aide/contact accessibles depuis landing et studio : adresse lisible/copiable et mailto ; formulaire facultatif. Réception réelle et délai de support à vérifier avec le propriétaire avant ouverture, sans inventer de disponibilité.
- [ ] **R6.5** Préparer titres/descriptions par page, partage social, indexation volontaire et sitemap/canonical pour le domaine retenu. Ne pas indexer le studio privé ou les pages de test ; ne pas confondre robots et sécurité.
- [ ] **R6.6** Recette responsive/clavier/sans JS, liens publics, cohérence de la promesse et commit.

Sortie : premier parcours et coût compris, contact visible, textes fidèles à ce qui est accessible. Blog/CMS et articles en série restent après les retours testeurs ; aucun blog nécessaire pour terminer la V1.

## R7 — Recette produit, compréhension et rendu

Dépendance : R6. Les essais vidéo et sociaux réels sont des portes distinctes, à autoriser explicitement.

- [ ] **R7.1** Répéter en local avec API simulées : import → montage facultatif → comptes/textes/date propres à chaque vidéo → confirmation → suivi/reprise, et série → scènes → assemblage → validation → programmation.
- [ ] **R7.2** Faire observer trois à cinq créateurs ou testeurs autorisés sur un périmètre accompagné, sans ouvrir publiquement le poste : première publication préparée, série préparée, reprise d'un échec. Consigner où ils demandent de l'aide et ce qu'ils comprennent des coûts/comptes/dates.
- [ ] **R7.3** Corriger les difficultés prioritaires et rejouer les tâches concernées. Conserver la différence entre observation de testeurs et impression d'expert.
- [ ] **R7.4** Cadrer avec le propriétaire un essai IA borné : modèle/endpoint, durée, nombre de plans, maximum de dépense et compte facturé ; utiliser une vidéo existante si aucun crédit disponible. Aucun lancement sans accord.
- [ ] **R7.5** Une fois autorisé, évaluer continuité, fin des gestes, image, son, répliques/langue et assemblage. Si le budget manque ou le chemin échoue, noter la capacité non validée et faire décider explicitement sa mise à l'écart du périmètre commercial ; ne pas cocher « testé ».
- [ ] **R7.6** Fixer la liste des capacités réellement présentables, limites et démonstrations ; bilan et commit.

Sortie : périmètre produit assumé et documenté, preuves de compréhension et limites audio/vidéo explicites. Les danses guidées et le lipsync dédié ne deviennent pas des chantiers implicites : les cadrer séparément si nécessaires à ce périmètre.

## R8 — Plateformes, infrastructure et exploitation

Dépendance : R7. Les démarches peuvent être longues ; à ce lot, le propriétaire choisit les comptes, le domaine, l'hébergement et le budget. Créer/configurer un service externe, envoyer un email réel ou exposer publiquement demande l'autorisation correspondante.

- [ ] **R8.1** Établir une matrice datée YouTube/Instagram/TikTok : revue/audit, comptes tiers autorisés, types de compte, permissions, visibilité et capacités. Préparer les dossiers requis ; ne pas traiter les réussites sur les comptes du propriétaire comme une ouverture à tous.
- [ ] **R8.2** Choisir une cible simple de production et ses coûts : web/HTTPS, workers, Ollama ou solution texte retenue, stockage privé, trafic, transcodage et transcription. Définir la capacité initiale et les limites de la bêta avant paiement.
- [ ] **R8.3** Préparer un environnement de production séparé du Compose local : serveur adapté, `prod`, debug désactivé, assets construits, secrets privés, origine/proxies/cookies, migrations et déploiement reproductibles. Aucun tunnel de développement assimilé à la production.
- [ ] **R8.4** Rendre l'expéditeur email configurable et configurer un envoi réel autorisé ; vérifier confirmation/récupération, liens du bon domaine et panne du transport. Ne pas exposer Mailpit.
- [ ] **R8.5** Définir et tester sauvegarde/restauration de base + médias privés + clés séparées ; reprise sur environnement isolé avec workers et sorties externes désactivés. Fixer rétention et objectifs de reprise.
- [ ] **R8.6** Mettre en place disponibilité web, état/âge des files, travaux bloqués, échecs métier et espace disque, alertes opérateur et guide d'intervention. Ne pas rejouer automatiquement une soumission incertaine.
- [ ] **R8.7** Finaliser données/support/confiance selon les prestataires choisis : conservation, suppression/export, confidentialité, coordonnées et responsabilités. Vérifier l'exécution des procédures, pas seulement leurs textes.
- [ ] **R8.8** Réaliser les essais de comptes tiers et sociaux uniquement dans le périmètre autorisé ; mesurer une charge synthétique réaliste sur la cible choisie. Consigner les restrictions restant applicables.
- [ ] **R8.9** Revue d'exploitation/sécurité, dossier des preuves et commit.

Sortie : périmètre tiers connu, environnement reproductible et reprise testée, emails/support opérationnels et charge initiale compatible. Si un réseau reste restreint, décider une bêta limitée ou son retrait explicite de la promesse ; aucune fausse disponibilité.

## R9 — Spécification définitive des offres

Dépendance : R8 pour les coûts/capacités. La direction commerciale demandée le 27 octobre remplace l'ancienne hypothèse à deux offres ; **aucune facturation ni limite nouvelle n'existe encore**.

| Offre | Prix mensuel proposé | Engagement annuel proposé | Comptes confirmés | Publications |
| --- | --- | --- | --- | --- |
| Free | 0 € | Sans abonnement payant | 1 YouTube + 1 Instagram + 1 TikTok | **5 publications multi-réseaux par mois**, même vidéo diffusée ensemble comptée une fois |
| Creator | 12,99 €/mois | 9,99 €/mois équivalent, soit **119,88 €/an** facturés annuellement | 3 YouTube + 3 Instagram + 3 TikTok, soit 9 maximum | Plafond à décider |
| Pro (nom provisoire) | 24,99 €/mois | 19,99 €/mois équivalent, soit **239,88 €/an** facturés annuellement | 9 YouTube + 9 Instagram + 9 TikTok, soit 27 maximum | Plafond à décider |

Les montants sont des propositions du propriétaire, pas une offre publiée. Prix affichés TTC/HT, monnaie des factures et traitement fiscal sont à arrêter selon la commercialisation retenue. L'annuel n'est pas « 9,99 € par an ». L'abonnement rémunère le logiciel ; l'IA vidéo reste payée au fournisseur connecté, sans crédits IA inclus ni wallet.

**Questions encore ouvertes, à ne pas trancher silencieusement :**

- Limite des comptes **résolue le 27 octobre** par réponse explicite du propriétaire : plafond par plateforme. Cette décision reste acquise ; ne pas la redemander à la reprise.
- Unité des publications **résolue le 27 octobre** après comparaison et accord du propriétaire : cinq publications multi-réseaux mensuelles en Free. Une vidéo diffusée ensemble sur YouTube/Instagram/TikTok compte une fois ; cinq vidéos sur les trois réseaux représentent cinq publications et jusqu'à quinze envois. Republier dans une nouvelle préparation constitue une nouvelle publication ; une reprise de la même soumission ne compte pas une deuxième fois. R9.3 définit l'identité durable du regroupement, le mois, les réservations et les échecs ; ne pas déduire l'unité du nombre de requêtes HTTP.
- Accès aux fonctions **en discussion** : le propriétaire demande si le Free doit exclure Studio IA et Séries. Recommandation de l'agent, non validée : accès d'essai limité au Studio pour découvrir la création, avec compte fournisseur et coût vidéo propres à l'utilisateur ; Séries/récurrence réservées aux offres payantes. Quotas d'essai, rédaction, stockage et export à définir avant implémentation. Ne pas traiter cette proposition comme une restriction décidée.
- Quels plafonds mensuels payants, stockage, durée/poids d'import, exports/montage, rédaction locale et récurrences ? Ne pas annoncer « illimité » sans capacité et coûts vérifiés. Ne pas inventer 50/100/500 publications ici.

- [ ] **R9.1** Faire confirmer et consigner prix, nom de l'offre supérieure et périodicité annuelle ; reprendre limites de comptes par plateforme et unité multi-réseaux déjà confirmées, avec exemples sans ambiguïté.
- [ ] **R9.2** Définir la matrice des fonctions/ressources par offre et ses plafonds à partir de R8 ; conserver un parcours Free réellement utile sans obliger un paiement vidéo pour tester l'import/publication.
- [ ] **R9.3** Définir le mois de quota, fuseau, réservation des programmations futures, consommation effective, échec/annulation/incertitude, reprise sans double compte et vidéos multi-destinations.
- [ ] **R9.4** Définir montée/descente d'offre, fin d'abonnement, impayé, surquota et anciens comptes connectés : aucune suppression automatique, aucune déconnexion ou publication silencieuse pour rentrer dans une limite.
- [ ] **R9.5** Valider les exemples utilisateurs et les critères comptables/commerciaux, faire approuver la spécification avant développement et l'enregistrer dans Git.

Sortie : tableau complet approuvé, aucune unité ou limite implicite. Les décisions tarifaires peuvent être recueillies plus tôt et ajoutées à ce tableau ; cela n'autorise pas à commencer R10 avant ses dépendances.

## R10 — Quotas et abonnement, dernier lot fonctionnel

Dépendances : R9 approuvé, socle R1–R8 validé. Réutiliser le parcours Compte/Abonnement existant ; Stripe prévu dans l'ancienne vision, à confirmer avec le propriétaire lors du choix/configuration du prestataire. Développement et recettes en mode test ; aucun moyen de paiement réel ni abonnement réel par défaut.

- [ ] **R10.1** Implémenter les droits et compteurs locaux transactionnels, indépendants de l'affichage et testables sans prestataire de paiement. Valeur par défaut sûre pour les comptes existants ; traiter leur transition sans effacer ni masquer des données.
- [ ] **R10.2** Appliquer les limites aux connexions, préparations, programmations et départs des workers selon la spécification. Vérifier requêtes concurrentes, limites exactes, plusieurs réseaux/comptes et reprises sans double décompte.
- [ ] **R10.3** Afficher offre, consommé/réservé/restant et prochaine période, avec messages et chemins de correction compréhensibles. Le Free ne demande pas de carte bancaire pour commencer.
- [ ] **R10.4** Intégrer souscription mensuelle/annuelle, gestion client et retour d'annulation en environnement de paiement test. Compte fournisseur IA et abonnement logiciel restent distincts.
- [ ] **R10.5** Vérifier événements signés, idempotence, ordre des événements, rattachement propriétaire et cohérence des droits. Ne jamais accorder un abonnement depuis la seule URL de retour ou le frontend.
- [ ] **R10.6** Tester annulation, renouvellement, impayé, changement d'offre, quotas à échéance, événements retardés/doublés et rupture réseau ; appliquer uniquement les règles commerciales approuvées.
- [ ] **R10.7** Finaliser pages tarifs/conditions applicables et récapitulatifs mensuel/annuel, vérifier l'absence de prix ou droits contradictoires avec l'app.
- [ ] **R10.8** Recette complète Free/Creator/Pro, revue indépendante, contrôles qualité, pédagogie Symfony et commit.

Sortie : compte Free utilisable, plafonds prouvés côté serveur, abonnement test stable et données conservées lors des changements. Activation du prestataire réel distincte et réservée au Go de lancement.

## R11 — Répétition générale

Dépendance : R10. Ne pas ajouter de nouvelle fonctionnalité pendant cette étape ; les défauts découverts sont corrigés puis revérifiés dans leur lot.

- [ ] **R11.1** Rejouer les contrôles complets disponibles : migrations/schéma test, PHPUnit dans `edit-worker`, PHPStan niveau 5, CS-Fixer, lints Symfony, `npm run check`, `npm run test:ui`, build ; résultats exacts et réserves consignés.
- [ ] **R11.2** Refaire la matrice navigateur et les parcours sur environnement représentatif : inscription/email, import, préparation, génération simulée, montage, série, calendrier, compte choisi, statut par réseau et droits d'offre.
- [ ] **R11.3** Tester sauvegarde/restauration, panne worker/fournisseur, disque/limites simulés, déploiement et retour arrière sans doublon de publication ni perte des préparations.
- [ ] **R11.4** Vérifier les portails/conditions d'accès, paiements de test, emails/support, documents publics, consentements, limites et ce qui restera annoncé non disponible.
- [ ] **R11.5** Écrire le dossier Go/No-Go : version, preuves, limites connues, budget, cible, responsables, surveillance et procédure de retour arrière. Faire valider le Go par le propriétaire ; aucun déploiement automatique depuis une case cochée.

Sortie : aucun défaut bloquant accepté implicitement, dossier approuvé et version prête à déployer. Les essais réels supplémentaires restent explicitement bornés.

## R12 — Déploiement et ouverture contrôlée

Dépendance : R11 et autorisation explicite de la cible, du budget, du paiement réel et des éventuels essais publics.

- [ ] **R12.1** Sauvegarder, déployer la version approuvée, appliquer migrations/assets/config et vérifier le stockage commun web/workers. Aucun secret dans Git ou dans le navigateur.
- [ ] **R12.2** Vérifier HTTPS, sessions, accès privés, emails, connexions et services de fond ; activer uniquement le périmètre réseau et paiement réellement approuvé.
- [ ] **R12.3** Réaliser les tests de fumée réels autorisés avec comptes/vidéos et dépenses choisis ; vérifier quotas Free/Creator/Pro et absence de double envoi/débit.
- [ ] **R12.4** Ouvrir à une cohorte limitée, contrôler files/erreurs/stockage/coûts/support et capacité avant élargissement. Définir les seuils de pause/retour arrière et agir s'ils sont dépassés.
- [ ] **R12.5** Consigner version effectivement déployée, date, périmètre ouvert, vérifications, limites, prochaine revue et suite post-lancement dans le journal.

Après lancement : amélioration fondée sur les retours ; quelques guides/blog utiles seulement ensuite. Publication entièrement autonome, mode agence/équipe, motion control/lipsync dédié et nouveaux fournisseurs restent des chantiers séparés à cadrer, pas une dette à masquer par une coche.

## Reprise sur un autre PC

1. Pousser les commits voulus sur GitHub depuis le PC source, puis récupérer la branche sur l'autre PC. Vérifier `git status` et les commits ; ne pas écraser un travail local ni utiliser un reset forcé pour synchroniser.
2. Lire `AGENTS.md`, le repère en haut de ce fichier et la dernière entrée du journal. L'ordre actif est ici ; les documents datés plus anciens servent de preuves/historique.
3. Suivre [le README](../README.md) pour les dépendances, images, migrations additives, assets et cache du poste. Ne pas supprimer de volumes/données. Vérifier la base de test avant toute suite fonctionnelle.
4. Git ne transfère ni `.env.local`, ni base, médias privés, accès OAuth, clés de chiffrement, modèles Ollama ou abonnements du prestataire. Reconstituer ces éléments seulement par une procédure privée autorisée ; préserver les clés originales si une base est restaurée. Utiliser des fixtures synthétiques si les données privées ne sont pas disponibles.
5. Reprendre la première tâche à faire indiquée dans le repère. Si le matériel/runtime manque, noter le blocage sans activer de service externe ni dépenser pour le contourner. Les cas versionnés permettent les tests simulés sans crédits Fal.
6. Avant de quitter ce PC, actualiser les preuves et le prochain geste, créer le commit de lot et noter si son push a été fait. Une case sans preuve ni résultat ne vaut pas livraison.

Message de reprise suggéré : « Lis AGENTS.md puis docs/roadmap.md, vérifie le journal et l'état Git, résume le prochain point exact puis reprends seulement ce point et ses vérifications, sans action externe réelle non autorisée. »

## Journal de progression

### 27 octobre 2026 — Cadrage après audit

- État applicatif de référence : `d3e4700`, performances/pages et recette simulée déjà livrées ; 617 tests PHP / 7 470 assertions, 136 tests frontend selon le bilan de ce lot.
- R0.1–R0.3 terminés documentaire : audit à quatre rôles, six rapports, corpus synthétique versionné, roadmap unique et index de reprise. Les anciens plans sont archivés, pas effacés.
- La direction commerciale devient Free + Creator + offre supérieure provisoirement Pro, tarifs proposés ci-dessus. Unités et plafonds restant ouverts sont listés ; aucun abonnement/compteur nouveau implémenté.
- Code applicatif inchangé dans ce cadrage ; contrôles documentaires : 76 identifiants uniques, trois tâches documentaires cochées, prochaine tâche R1.1, neuf cas JSON et diagnostic complets, liens locaux vérifiés et `git diff --check` réussi. Aucune nouvelle suite applicative exécutée. Aucun essai vidéo payant, envoi social, activation réelle, débit client ou déploiement.
- **Prochain point : R1.1**, après instruction de démarrage du propriétaire. Aucune tâche R1–R12 commencée.
- Référence du commit documentaire initial : retrouver avec `git log --all --oneline --grep="docs: version audit and deployment roadmap"` après le commit de ce cadrage. Pour la dernière mise à jour du suivi, utiliser `git log -1 -- docs/roadmap.md`. Le statut de push doit être vérifié sur le dépôt distant, jamais déduit d'un commit local.

### 27 octobre 2026 — Précision commerciale du propriétaire

- Réponse explicite : limite de comptes **par plateforme**. Free = 1/1/1 (3 maximum), Creator = 3/3/3 (9 maximum), Pro = 9/9/9 (27 maximum). Pas de nouvelle restriction applicative implémentée.
- Pour l'unité des publications, le propriétaire demande conseil. Recommandation présentée : compter chaque compte destinataire, indiquer le nombre avant confirmation et dédupliquer les reprises techniques. Cette recommandation n'est pas encore une décision approuvée ; le nombre Free reste proposé à cinq par mois.
- Mise à jour documentaire uniquement ; aucun lot fonctionnel commencé. **Prochaine tâche de développement inchangée : R1.1**, après instruction de démarrage.

### 27 octobre 2026 — Publications multi-réseaux et accès Free

- Après recommandation de cinq publications plutôt que trois et comparaison des offres gratuites, le propriétaire accepte la formule : **cinq publications multi-réseaux/mois**, un compte de chaque réseau en Free. La recommandation antérieure par destinataire n'est plus retenue.
- Question actuelle : accès Free au Studio IA et aux Séries. Proposition exposée, encore à valider : essai Studio limité, génération vidéo chez le fournisseur financée par l'utilisateur, Séries/récurrence payantes. Aucun quota d'essai ni droit fonctionnel inventé/implémenté.
- Mise à jour documentaire uniquement. **Prochaine tâche de développement inchangée : R1.1**, après instruction de démarrage.

### Modèle à recopier à chaque fin de lot ou interruption

- Date et lot/tâches :
- Résultat livré et cases cochées :
- Travail partiel ou blocage, données à préserver :
- Fichiers et explication Symfony :
- Commandes réellement exécutées et résultats exacts :
- Recette visuelle / modèle réel / API simulées ou réelles :
- Limites et décisions du propriétaire :
- Commit(s) et push effectué ou non :
- Version déployée si applicable :
- **Prochaine tâche exacte :**
