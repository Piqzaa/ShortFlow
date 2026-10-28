# VydFlow — feuille de route active jusqu'au déploiement

Mise à jour : **28 octobre 2026**. Ce document est la source unique des priorités et de l'avancement. Il remplace les ordres de travail et hypothèses commerciales des anciennes roadmaps, conservés dans [l'archive historique](roadmap-historique-20261027.md). Les consignes de sécurité et autorisations restent applicables.

## Repère de reprise immédiat

- **Phase actuelle :** **R2 en cours, R2.1–R2.6 terminés**. Les 15 reproductions R2.1 passent sans exclusion ; l'évaluation réelle R2.6 relève encore des incohérences non alertées et quatre packs sociaux expirés. **Grand R2 non clôturé**, aucune voix, qualité vidéo ou fiabilité autonome validée. R1 reste terminé et revu.
- **Dernière suite complète verte :** R2.5, **758 tests / 8 619 assertions** et **152 tests frontend** ; voir le journal et le commit `fix: require factual context for publication texts`. La clôture R1 `abda42e` et son résultat 667 / 8 044 restent historiques. La référence `d3e4700` reste le socle d'avant R1.
- **Prochaine tâche exacte : `R2.7`**, revue de clôture du grand R2 avec les corrections/limitations nécessaires ; priorités constatées : timeouts éditoriaux, préfixes d'identité contenant actions/états et incohérences physiques non alertées. Voir [le suivi R2](r2-qualite-redacteur.md) et [le bilan réel R2.6](r2-evaluation-redacteur.md). Ne pas passer directement à R3.
- **Validation actuelle :** R2.6 **18 essais réels, 28 appels d'inférence locaux : 12 scénarios normalisés, quatre timeouts éditoriaux, deux refus de contexte sans HTTP** ; campagne complète, **code 1**, pas 18 réussites. Deux revues ciblées indépendantes, corrections humaines proposées sans modifier les captures. Corpus/résultats/empreintes versionnés, script syntaxe/style/mode sans appel vérifiés. Application inchangée : les **758 / 8 619**, **129 / 989 ciblés** et **152 frontend** verts restent les contrôles R2.5 historiques, non rejoués ici. Aucun média, paiement, publication ou activation ; pas de migration.
- **Ne pas commencer maintenant :** abonnement, nouvelle UI, publication autonome, blog ou déploiement.
- **Comptes confirmés :** Free 1, Creator 3 et Pro 9 comptes **par plateforme**, soit respectivement 3, 9 et 27 comptes maximum sur les trois réseaux.
- **Publications Free confirmées :** cinq par mois ; une vidéo diffusée sur plusieurs réseaux dans la même publication compte une seule fois. L'ancienne recommandation de décompte par destinataire est écartée.
- **Accès Free confirmé :** essai limité du Studio IA, sans accès aux Séries ni à leur récurrence. Séries réservées aux offres payantes ; générations vidéo financées directement par le compte fournisseur de l'utilisateur.
- **Décisions en attente :** quotas précis de l'essai Studio, plafonds payants et ressources de calcul/stockage ; identité précise de la publication, réservation et consommation à spécifier en R9. Voir les propositions commerciales plus bas.
- **Passage sur un autre PC :** lire ce repère, le journal en fin de document, puis [le protocole de reprise](#reprise-sur-un-autre-pc). Les conversations et `artifacts` ne sont pas nécessaires pour retrouver le prochain travail.

Le propriétaire a autorisé le démarrage de R1.1 puis ses suites le 27 octobre ; **le grand lot R1 est terminé**, revue globale incluse. Ses demandes de suite du 28 octobre ont livré R2.1–R2.6 ; R2.7 est le prochain point. Cette roadmap n'est pas une autorisation de dépense, de publication réelle, de paiement réel ou d'exposition publique.

## Règles de suivi à respecter

1. Suivre les lots dans l'ordre et traiter la première tâche non terminée dont les dépendances sont satisfaites. Les dossiers externes peuvent être préparés à leur lot ; ne pas sauter une porte de validation silencieusement.
2. `[ ]` = à faire ; `[x]` = terminé et vérifié. Pour un travail partiel, garder `[ ]` et inscrire précisément son état dans le journal. Pour un blocage, noter cause, action nécessaire et travaux indépendants possibles ; ne pas cocher ni annoncer une réussite.
3. Une tâche est terminée quand son résultat est livré, ses contrôles adaptés exécutés et leurs résultats consignés. La réussite des tests simulés ne valide pas un fournisseur ou un rendu vidéo réel.
4. À chaque fin de lot : bilan pédagogique, mise à jour des cases/du repère/du journal, documentation et commit local identifié. Un push suit seulement l'autorisation du propriétaire ; un commit local seul n'est pas disponible sur l'autre PC.
5. Avant changement de lot, vérifier sa porte de sortie et mettre explicitement la prochaine tâche. Toute modification de périmètre, prix, quota, unité ou ordre est décidée avec le propriétaire puis datée dans le journal avant implémentation.
6. Conserver les sources et préparations, textes humains, validations, comptes destinataires, isolation propriétaire, confirmations de coûts et limites de récurrence. Aucune suppression de données pour appliquer une nouvelle offre.
7. Revues indépendantes ciblées lors des lots importants, sans modifier simultanément les mêmes fichiers. Les tests de base utilisent exclusivement `shortflow_test`, migrations appliquées avant PHPUnit ; aucune suite DB concurrente sur cette base.
8. Pas de changement d'architecture/framework ni de service ajouté sans problème démontré. Garder le monolithe Symfony/Twig/Stimulus et l'orchestration existante.
9. Après **chaque grand lot R**, réaliser une revue de clôture avant de commencer le suivant : confronter le résultat à toutes ses tâches et à sa porte de sortie, rechercher omissions et régressions, relire les protections, examiner les preuves de tests et la recette pertinente. Faire intervenir un agent indépendant en lecture seule, consigner constats/correctifs/limites et décision de sortie dans le journal. Un défaut bloquant empêche de déclarer le lot terminé. Cette revue globale ne se limite pas aux vérifications de chaque sous-tâche Rn.n ; les points de revue déjà prévus en fin de lot la portent.

Une preuve de fin de lot indique au minimum : tâches, comportement livré, fichiers principaux, commandes et résultats exacts, recette desktop/mobile pertinente, limites restantes, commit, push effectué ou non, prochaine tâche et décision attendue. Ne jamais recopier une ancienne suite comme si elle venait d'être exécutée.

## Avancement global

| Lot | Objectif | État | Porte avant le suivant |
| --- | --- | --- | --- |
| R0 | Audit, preuves et feuille de route portable | Terminé documentaire | Repère et données synthétiques lisibles dans Git |
| R1 | Attente, exécution et reprise du rédacteur | Terminé, revue globale et contrôles validés | Attente/exécution distinctes, traitements bornés, reprise sans double appel |
| R2 | Qualité des scénarios, dialogues et textes | En cours : R2.1–R2.6 terminés, revue R2.7 et écarts réels à traiter | Corpus rejoué, écarts signalés, textes humains préservés |
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

- [x] **R1.1** Ajouter une reproduction automatisée déterministe de la demande `queued` de plus de dix minutes, sans inférence ni appel externe. Distinguer explicitement le résultat actuel incorrect du comportement attendu. [Preuve : 12 cas, six échecs attendus, moteur inchangé](r1-redacteur-attente.md).
- [x] **R1.2** Séparer temps en file et temps d'exécution/phase ; définir admission et délais explicites. Préserver reprise, nouvelle demande et protection contre un ancien résultat qui écraserait le nouveau. [Horloge, délais, admission, migration et preuves](r1-redacteur-attente.md).
- [x] **R1.3** Afficher des états compréhensibles : en attente, rédaction, préparation des plans/textes, résultat ou erreur ; permettre de quitter puis retrouver le travail. [Étapes, reprise GET, protections et recette desktop/mobile](r1-redacteur-attente.md).
- [x] **R1.4** Vérifier plusieurs propriétaires, travail retardé, résultat tardif, worker interrompu et reprise ; aucun double lancement ni génération vidéo déclenchée pour réparer un texte. [14 cas, barrières PostgreSQL, SIGKILL et redélivrance simulée](r1-redacteur-attente.md).
- [x] **R1.5** Revue ciblée, contrôles adaptés, documentation Symfony et commit de fin de lot ; actualiser le repère de reprise. [Revue globale, preuves, limites et décision de sortie](r1-revue-cloture.md).

Sortie : attente valide traitée comme telle, exécution bloquée bornée, tests simulés réussis et messages fidèles aux phases. Ne pas résoudre le défaut uniquement en augmentant un timeout.

## R2 — Qualité des scénarios et textes

Dépendance : R1. Preuves : [rapport qualité](audit-global-20261027-qualite.md) et corpus versionné.

- [x] **R2.1** Transformer les défauts capturés en cas de non-régression : répliques perdues, prompts français non signalés, hashtags intégrés au texte, contexte inventé depuis un titre pauvre. Garder séparés les contrôles de structure et de sens. [15 cas, quatre rouges attendus, aucun correctif applicatif](r2-qualite-redacteur.md).
- [x] **R2.2** Définir le contrat des scènes parlantes : locuteur, réplique exacte, langue et capacité réelle du chemin vidéo. Tant que le contrat n'est pas porté, l'indiquer avant coût ; ne pas promettre de voix/lipsync depuis le seul indicateur audio. [Contrat défini, portage non implémenté et notices avant coût](scenes-parlantes-contrat.md).
- [x] **R2.3** Corriger les consignes/validations de langue et fidélité ; accepter un prompt anglais contenant intentionnellement une citation française. Éviter les traits inventés et les actions contradictoires ; conserver une alerte honnête quand le contrôle ne peut pas conclure. [Règles ciblées, témoins et limites de la relecture](redacteur-langue-fidelite.md).
- [x] **R2.4** Fiabiliser les packs sociaux complets et leur diagnostic. Garder la séparation des hashtags et les limites des réseaux ; ne pas désactiver le validateur pour accepter une mauvaise sortie. [Contrat complet, diagnostics sûrs, reprise manuelle et preuves](packs-sociaux-diagnostic.md).
- [x] **R2.5** Demander un contexte factuel suffisant et rendre explicite que le rédacteur ne voit pas la vidéo. Proposer une réponse neutre ou une demande de précision plutôt qu'inventer ; préserver les textes saisis et l'application volontaire. [Refus sans inférence, aide et protection des anciennes propositions](redacteur-contexte-factuel.md).
- [x] **R2.6** Évaluer sur un corpus fixe avec quelques répétitions : récit, tutoriel, produit, dialogue, danse, mémoire de série, import peu décrit. Consigner structure, fidélité, erreurs, langue, corrections humaines, durées murale/monotone et conditions du poste. Aucun score commercial déduit d'un petit échantillon. [Deux passages réels, captures et limites constatées](r2-evaluation-redacteur.md).
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

- [ ] **R5.1** Vérifier les écrans principaux à 320, 390, 768 et 1280 px : vide/rempli, long titre, erreur, envoi actif, brouillon, compte déconnecté et contenu ancien. Inclure landing publique, montage/export et historique, pas seulement les écrans heureux. Reproduire le chevauchement du téléphone et du texte/CTA de la landing signalé par le propriétaire le 27 octobre ; vérifier animations, état statique et reduced motion, portrait/paysage et zoom.
- [ ] **R5.2** Améliorer la lisibilité de la navigation étroite ; conserver menu, accès compte et toutes les destinations. Inclure la navigation publique : connexion et inscription accessibles sur mobile, avec menu utilisable au clavier et repli sans JS si nécessaire. Vérifier barres fixes, safe areas et clavier virtuel sur téléphone réel.
- [ ] **R5.3** Vérifier clavier complet, focus, libellés, annonces d'état, erreurs ouvrant leurs panneaux, contraste, zoom 200 %, lecteur d'écran ciblé et reduced motion système.
- [ ] **R5.4** Vérifier fallback sans JS, médias manquants, chargement lent/erreur et actions désactivées avec explication. Aucune action payante/sociale ne dépend uniquement d'une protection frontend.
- [ ] **R5.5** Corriger les défauts bloquants, consigner la matrice et les limites, revue et commit.

Sortie : parcours principaux utilisables dans les états contrôlés. Ne pas annoncer une conformité complète sur la seule base d'un outil automatique.

## R6 — Landing, aide et acquisition de base

Dépendance : R5. Référence : [audit marché/SEO](audit-global-20261027-marche-seo.md).

- [ ] **R6.1** Corriger les mentions obsolètes, descriptions SEO et accès connexion mobile ; conserver identité, animations et indication des démonstrations. Recontrôler les défauts publics R5.1/R5.2 : connexion trouvable, navigation mobile et aucune superposition qui gêne lecture ou CTA ; ne pas réintroduire ces défauts lors des changements de contenu.
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
- [ ] **R8.2** Choisir une cible simple de production et ses coûts : web/HTTPS, workers, Ollama ou solution texte retenue, stockage privé, trafic, transcodage et transcription. Définir la capacité initiale et les limites de la bêta avant paiement. Reprendre les limites de [la clôture R1](r1-revue-cloture.md) : mutex local au TMPDIR, topologie réelle des workers, récurrences hors admission manuelle et capacité du moteur.
- [ ] **R8.3** Préparer un environnement de production séparé du Compose local : serveur adapté, `prod`, debug désactivé, assets construits, secrets privés, origine/proxies/cookies, migrations et déploiement reproductibles. Aucun tunnel de développement assimilé à la production.
- [ ] **R8.4** Rendre l'expéditeur email configurable et configurer un envoi réel autorisé ; vérifier confirmation/récupération, liens du bon domaine et panne du transport. Ne pas exposer Mailpit.
- [ ] **R8.5** Définir et tester sauvegarde/restauration de base + médias privés + clés séparées ; reprise sur environnement isolé avec workers et sorties externes désactivés. Fixer rétention et objectifs de reprise.
- [ ] **R8.6** Mettre en place disponibilité web, état/âge des files, travaux bloqués, échecs métier et espace disque, alertes opérateur et guide d'intervention. Ne pas rejouer automatiquement une soumission incertaine. Vérifier la commande et les listeners opérationnels du worker sur la cible ; la recette R1 du Worker à dispatcher minimal ne les couvre pas entièrement.
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
- Accès aux fonctions **confirmé le 27 octobre** : le propriétaire choisit de laisser tester le Studio IA en Free, sans accès aux Séries/récurrence, réservées aux offres payantes. Le compte fournisseur et les dépenses vidéo restent propres à l'utilisateur. Les quotas précis d'essai, rédaction, stockage et export restent à définir avant implémentation ; ne pas inventer de volumes ou annoncer des générations offertes.
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

### 27 octobre 2026 — Accès Free confirmé

- Décision explicite du propriétaire : laisser tester le Studio IA en Free, pas les Séries. Séries et récurrence réservées aux offres payantes. Les plafonds précis de l'essai et des ressources seront définis en R9 ; les générations vidéo restent facturées par le fournisseur à l'utilisateur.
- Cette confirmation ne constitue pas l'implémentation des restrictions : aucun contrôle d'offre ajouté et aucune préparation existante modifiée.
- **Premier point à travailler : R1.1**, reproduction automatisée de l'expiration d'une rédaction encore en attente, avant correction du cycle de traitement en R1.2. Aucun lot fonctionnel commencé dans cette mise à jour documentaire.

### 27 octobre 2026 — R1.1, reproduction automatisée terminée

- Autorisation explicite du propriétaire de commencer R1.1. Nouveau `tests/Unit/StoryProposalQueueTest.php` : âges 0/11 minutes, types scénario/éditorial, chemins `owned/latest/process`.
- Résultat final : 12 cas / 98 assertions, six témoins récents réussis et six échecs attendus reproduisant `queued` → `failed` avant toute requête texte. Moteur et horloge système inchangés ; aucun test d'expiration à la frontière exacte.
- Tests voisins : 17 tests / 60 assertions réussis. PHPStan et CS-Fixer ciblés réussis, `git diff --check` réussi. Base `shortflow_test` vérifiée et migrations test déjà à jour. Aucun appel Ollama, vidéo payante, réseau social ou modification de données du développeur.
- Bilan pédagogique, limites et commandes : [suivi R1](r1-redacteur-attente.md). Pas de suite complète relancée ; le nouveau test rouge fait échouer la suite par défaut tant que la correction n'est pas faite.
- Point de contrôle local de tests et documentation, **pas fin du lot R1**. Aucun push. Retrouver le commit avec `git log --all --oneline --grep="test: reproduce premature story queue expiry"`.
- **Prochaine tâche exacte : R1.2.** Aucun correctif applicatif commencé ; aucun essai manuel ni crédit fournisseur requis pour cette étape.

### 27 octobre 2026 — R1.2 terminé, correctif temporel

- Horloge injectée ; démarrage et début de phase persistés séparément. Attente maximum 1 h, phase 210 s, exécution totale 420 s, sans renouveler le délai à phase identique.
- Admission `StoryProposals` bornée à dix travaux encore valides et un par propriétaire ; réutilisation d'une demande identique, sérialisation PostgreSQL et exclusion des périmées. Les préparations récurrentes directes restent hors de ce comptage, sans promesse de débit.
- Réponses tardives ou devenues anciennes rejetées sous contrôle d'état avant sauvegarde ; pas de transaction pendant l'inférence, pas de nouvel appel vidéo ou renvoi automatique.
- Migration additive `Version20261027160000` appliquée en test/dev, schémas synchronisés, cache dev/worker de rédaction actualisés hors exécution réelle, HTTP local 200.
- Résultats : 21 tests temporels / 171 assertions ; 44 ciblés / 455 assertions ; suite complète Linux 651 / 7 766 ; dernier rejeu des tests finaux 34 / 296. Tous réussis. PHPStan, style, YAML/Twig/conteneur et schéma test réussis. Pas de nouveau build/contrôle visuel, les assets/templates ne changent pas.
- Revue indépendante sans blocage ; vraie concurrence multi-processus et contrôle complet de reprise encore à faire en R1.4. Bilan pédagogique et limites dans [le suivi R1](r1-redacteur-attente.md).
- Commit local à retrouver avec `git log --oneline --grep="fix: separate story queue and execution deadlines"` ; aucun push. **Prochaine tâche exacte : R1.3.** Aucun crédit IA ni test social requis.

### 27 octobre 2026 — R1.3 terminé, états et reprise du suivi

- Phases nommées sans pourcentage fictif, attente distincte du traitement réel, métadonnées serveur communes et repli pour les anciens travaux.
- Retour sur la préparation sans nouvelle demande ; ambiance retrouvée uniquement si l'empreinte concorde et sans saisie humaine intervenue. Éditions et application volontaire conservées. Une coupure éditoriale propose une reprise GET et ne réactive pas un envoi ambigu.
- Suite complète : 653 tests / 7 808 assertions réussis ; 152 tests frontend réussis. PHPStan, CS-Fixer, lints Symfony, schéma test, `npm run check` et build réussis. Avertissement Vite habituel sur la taille du chunk Three.js ; pas de correction de performance dans ce point.
- Recette simulée desktop 1440 × 900 / mobile 390 × 844 : attente, rédaction, scènes, retour après navigation, textes, coupure/reprise clavier, résultat prêt sans perte du titre modifié et échec confirmé. Aucun POST accepté par le serveur de recette, aucun appel réel. Revue indépendante sans blocage.
- Bilan pédagogique et protocole dans [le suivi R1](r1-redacteur-attente.md). Cache dev actualisé ; aucun changement de schéma. Six tâches sur 76 désormais cochées, R1 toujours en cours.
- Commit local à retrouver avec `git log --oneline --grep="feat: clarify writing progress and recovery"` ; aucun push. **Prochaine tâche exacte : R1.4.** Aucun test vidéo payant nécessaire pour cette suite.

### 27 octobre 2026 — Revue globale obligatoire et signalement mobile

- Le propriétaire demande une analyse de clôture après chaque **grand R**, avant le suivant. Règle 9 ajoutée ; elle complète les revues de sous-tâches et s'applique notamment à la future clôture R1.5. Aucun grand lot supplémentaire déclaré terminé par cette décision.
- Capture de landing fournie par le propriétaire : navigation mobile sans connexion/menu apparent, téléphone qui chevauche la description et le CTA. Signalement visuel confirmé sur la capture, sans prétendre reproduire son viewport exact ni exécuter déjà la recette navigateur de correction.
- La connexion mobile figurait déjà en R6.1. Landing et défauts précis sont désormais explicités en R5.1/R5.2, puis à revérifier en R6.1. Les animations et leur repli doivent rester utilisables. Ces tâches restent non cochées ; aucun correctif landing n'est mélangé à R1.4.
- Suite active inchangée : R1.4, tests de concurrence/interruption sans API réelle. Intervention humaine à demander seulement si une future porte l'exige (téléphone réel, compréhension produit, accès externes ou essai payant autorisé).

### 27 octobre 2026 — R1.4 terminé, concurrence et interruption

- Deux nouveaux fichiers de tests : orchestrateur PHPUnit et fixture de processus autonome, chaque connexion gardée sur `shortflow_test`. Aucun code applicatif, schéma, configuration ni asset modifié.
- 14 tests / 236 assertions réussis : même propriétaire, dernière place globale, double claim, propriétaires isolés, moteur occupé, remplacement tardif, six interruptions/reprises via Worker/transport réels et SIGKILL. Tables vidéo/sociales et files étrangères vérifiées vides.
- La redélivrance est accélérée uniquement sur les messages de test ; pas de validation du temps mural de dix minutes ou de toute la commande opérationnelle. Le mutex est local au TMPDIR ; le second propriétaire peut recevoir « occupé », puis réessayer volontairement. Aucun renvoi automatique ni capacité réelle promise.
- PHPStan ciblé réussi, style appliqué et revue indépendante sans blocage. Preuves et guide Symfony dans [le suivi R1](r1-redacteur-attente.md).
- Commit local `test: verify story concurrency and worker recovery`, aucun push. **Suite immédiate : R1.5**, revue globale et contrôles complets avant R2 ; cette continuation les exécute aussi conformément à la règle 9 demandée.

### 27 octobre 2026 — R1.5, clôture globale de R1

- Chaque tâche R1.1–R1.4 confrontée à ses preuves ; relectures indépendantes backend et interface sans blocage restant identifié. [Analyse de clôture versionnée](r1-revue-cloture.md), avec pédagogie, limites de la reprise et éléments à vérifier en production.
- Tests R1.4 finaux puis deux répétitions : 14 / 236 à chaque passage. Suite PHP complète : **667 / 8 044** ; frontend : **152** tests. Tous réussis, aucune suite DB concurrente, `shortflow_test` vérifiée et migrations à jour.
- PHPStan applicatif et deux nouveaux fichiers de tests, style, lints conteneur/YAML/Twig, schéma test, `npm run check`, `npm run build`, liens locaux et diff propres. Avertissement Vite Three.js conservé ; pas de nouvelle recette navigateur depuis R1.3, code frontend inchangé.
- Limites affectées explicitement à R8.2/R8.6/R8.8 : capacité réelle, topologie/mutex local, récurrences hors admission, commande/listeners opérationnels et surveillance. La correction locale ne promet ni débit commercial ni coordination multi-hôtes.
- **Grand R1 terminé, huit tâches sur 76 cochées.** Commit de clôture à retrouver via `git log --oneline --grep="docs: close R1 writing reliability review"` ; R1.4 dans `818261c`. Aucun push, essai vidéo payant, envoi social, activation réelle ou déploiement.
- **Prochaine tâche exacte : R2.1**, non commencée. Aucune intervention manuelle requise pour clôturer R1 ; solliciter le propriétaire quand une future validation réelle le nécessite.

### 28 octobre 2026 — R2.1 terminé, reproduction des défauts de qualité

- Nouveau `WriterQualityRegressionTest` sur le corpus historique intact : **15 cas / 90 assertions, 11 réussites et quatre échecs attendus**. Rouges : prompts français non signalés, paroles perdues, citation française dense faussement signalée, faits inventés depuis un titre pauvre. Pas de changement du rédacteur dans cette étape.
- Diagnostic hashtags réellement brut rejoué et refus toujours correct, sans retry. Cas minimal reconstruit explicitement depuis le résultat normalisé, roundtrip exact ; conformité de structure distincte du sens. Oracle neutre borné à cette fixture ; il ne constitue pas un détecteur universel d'invention.
- 17 tests voisins / 60 assertions réussis ; PHPStan, style ciblés et diff propres. `shortflow_test` vérifiée et migrations déjà à jour ; aucune suite DB concurrente. Pas de suite complète, build ou nouvelle recette navigateur ; dernier socle entièrement vert R1 dans `abda42e`.
- Revue indépendante de provenance/contrats ; garde contre packs vides et alerte de dialogue universelle ajoutées. [Preuves, pédagogie, limites et commandes](r2-qualite-redacteur.md). Le grand R2 n'est pas terminé et aura sa revue globale en R2.7.
- Commit local à retrouver avec `git log --oneline --grep="test: reproduce captured writer quality failures"`, aucun push. Aucun modèle ou fournisseur appelé réellement, aucune génération payante/sociale, activation ou modification des données du développeur. Neuf tâches sur 76 cochées.
- **Prochaine tâche exacte : R2.2.** Les rouges de langue/citations se corrigent en R2.3, le contexte en R2.5 ; ne pas masquer les échecs dans l'intervalle. Aucune intervention manuelle nécessaire pour ces reproductions.

### 28 octobre 2026 — R2.2 terminé, contrat et limites des paroles avant coût

- Contrat plan/locuteur/réplique exacte/langue/ordre documenté ; portage structuré explicitement non implémenté. Aucune nouvelle API, champ, chaîne TTS/lipsync ou capacité fournisseur annoncée.
- Notice Twig commune : proposition de scénario, génération individuelle et reprise de clé, devis Studio/Séries/remplacement, reprise après pause, autorisation récurrente. Audio possible distinct des paroles contrôlées ; modèle muet et son des clips coupé expliqués. Récurrence : pas de relecture intermédiaire des futurs scripts promise.
- Dix cas fonctionnels ajoutés et un cas existant de remplacement renforcé. Suite complète : **692 / 8 318, quatre échecs R2.1 connus uniquement**, sans nouvelle régression ; 152 frontend, syntaxe JS, build, PHPStan ciblé, style et lints réussis, schéma test synchronisé. Avertissement Vite Three.js connu.
- Recette synthétique desktop 1440 × 900/mobile 390 × 844, clavier et console ; aucun POST accepté par le serveur de recette. Cache dev actualisé, serveur temporaire retiré. Revue indépendante sans blocage après clarification du texte récurrent ; grand R2 toujours non livré, revue globale future en R2.7.
- [Contrat et composants Symfony](scenes-parlantes-contrat.md), [résultats et limites](r2-qualite-redacteur.md). Dix tâches sur 76 cochées. Commit local à retrouver avec `git log --oneline --grep="feat: explain speech limits before generation costs"`, aucun push.
- **Prochaine tâche exacte : R2.3.** Aucun appel texte/vidéo réel ni essai payant, publication, activation ou nouvelle migration. Pas d'intervention manuelle requise pour valider ce point ; la qualité des voix devra être testée séparément avec autorisation.

### 28 octobre 2026 — R2.3 terminé, consignes et garde-fous de fidélité

- Interdiction contradictoire des dialogues retirée ; narration française, directions anglaises et citations originales distinguées. Repères de répliques transmis aux deux étapes texte, sans nouvelle API/script structuré du fournisseur vidéo.
- Nouveau service pur de contrôles : citations exactes fermées, langue inconnue, paroles absentes/modifiées/fragmentées/répétées ou désordonnées, tenue par personnage nommé, contradictions d'actions ciblées. Aucune réécriture des préparations ; alertes de capacité/locuteur conservées même si les mots sont présents.
- Pause récurrente avant toute vidéo vérifiée ; résumé d'alertes complet et borné à 255 caractères, plans éditables et réservations non consommées libérées. Pas d'activation réelle ni de rendu audio prétendu validé.
- 27 nouveaux cas unitaires et un cas fonctionnel ; suite complète **720 / 8 419, un rouge R2.5 uniquement**. Rejeu final **57 / 379** vert ; reproductions R2.1 inchangées **14 / 15** vertes, seul contexte pauvre encore rouge ; **152 frontend** verts. PHPStan, style, lints et schéma réussis. Pas de nouveau build/recette navigateur, sources frontend inchangées.
- Revue indépendante sans blocage après renfort sur les répétitions. Cache dev/worker actualisés après garde zéro rédaction active/en attente ; aucun appel modèle/vidéo/social réel. Limites, composants Symfony et commandes : [correctif](redacteur-langue-fidelite.md), [suivi R2](r2-qualite-redacteur.md).
- Onze tâches sur 76 cochées. Commit local à retrouver avec `git log --oneline --grep="fix: preserve requested speech and improve story checks"` ; aucun push. **Prochaine tâche exacte : R2.4**, puis R2.5 pour rendre le dernier cas vert. Revue globale de R2 toujours en R2.7.

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

### 28 octobre 2026 — R2.4 terminé, packs sociaux et diagnostic

- Tous les réseaux/champs et leurs limites sont explicités dans les consignes et le schéma existant. Un seul appel local borné à 180 s ; aucun second essai automatique. Séparation des hashtags et toutes les règles d'acceptation conservées, aucun pack partiel ou silencieusement réparé.
- Refus distingués (réseaux, format, textes, longueur, hashtags/profil) et erreurs moteur (modèle, indisponibilité, délai, interruption, JSON), avec action française dans le préparateur existant. Logger limité à une raison technique ; risque de chaîne d'exception brute signalé en revue indépendante, supprimé et couvert, puis revue finale sans blocage.
- Vingt nouveaux cas, y compris refus → diagnostic HTML à la réouverture → nouvelle demande manuelle. Les textes et réglages humains restent inchangés, aucune soumission créée ; verrou tiers et isolation multi-processus conservés. Détails et pédagogie Symfony : [packs sociaux](packs-sociaux-diagnostic.md).
- Suite complète **740 / 8 517, deux échecs** : libellé de concurrence corrigé en conservant « occupé », sans changer le test, puis **61 / 507 réussis** au rejeu final incluant tous les tests de concurrence. Le second échec est R2.5, sans exclusion ; pas de deuxième suite complète après le correctif de libellé. **152 frontend**, syntaxe JS, PHPStan, style, lints et schéma test réussis. Ne pas annoncer la suite complète verte.
- Aucun schéma/template/asset modifié, aucune nouvelle recette visuelle ou build ; parcours HTML testé, recette R2.2 historique. Cache/worker local actualisés hors rédaction active. Aucun appel modèle réel, coût, publication ou activation ; aucune action manuelle nécessaire pour R2.4.
- Douze tâches sur 76 cochées. Commit local à retrouver avec `git log --oneline --grep="fix: clarify editorial pack failures"` ; aucun push. **Prochaine tâche exacte : R2.5**. Revue globale R2 toujours en R2.7 ; benchmark réel des consignes en R2.6.

### 28 octobre 2026 — R2.5 terminé, contexte descriptif requis

- `PublicationTexts::requireContext` refuse les titres seuls, métadonnées, descriptions vides/incorrectes ou simples répétitions du titre. Une description/scène distincte avec deux mots/nombres et une lettre est requise avant verrou et HTTP. Ce contrôle de présence ne certifie pas la vérité ni la fidélité sémantique ; aucune qualité réelle Qwen annoncée.
- Diagnostic durable avec demande de précisions, aide du préparateur liée au champ et exemple. Saisie manuelle toujours disponible ; une nouvelle demande explicitement décrite peut réussir sans application automatique. Les anciennes propositions sans contexte sont bloquées à l'application, sans supprimer les textes existants. Pas de migration ni d'invalidation globale des préparations.
- Dix-huit nouveaux cas. Fixtures temporelles enrichies de descriptions factuelles, assertions conservées ; corpus R2.1 inchangé et ses quinze reproductions désormais vertes. Revue indépendante ciblée sans blocage. [Pédagogie, fichiers, flux et limites](redacteur-contexte-factuel.md).
- **129 / 989 ciblés verts**, puis **suite complète 758 / 8 619 verte**, code 0, **7 min 16 s / 84,5 Mio**. Première tentative interrompue code 137 sans bilan dans le conteneur hébergeant le worker horaire ; relance réussie dans un conteneur de test dédié, sources/dépendances copiées en Linux, garde `shortflow_test` et migrations avant tests ; aucune suite DB concurrente. **152 frontend**, syntaxe JS, PHPStan, CS-Fixer neuf fichiers, lints conteneur/19 YAML/103 Twig et schéma réussis.
- Recette synthétique 390 × 844 / 1440 × 900, aide/erreur lisibles, saisie et clavier jusqu'au bouton, textes personnels présents, aucune erreur console ni débordement horizontal observé. Serveur GET uniquement, aucun envoi ; compte temporaire test, conteneurs tests/recette et onglet retirés, viewport restauré. Assets inchangés, pas de nouveau build.
- Cache dev/worker de rédaction actualisés après garde zéro rédaction active/en attente ou occurrence en rédaction ; `running` vérifié. Aucun appel modèle réel, coût, publication, activation, push ou intervention manuelle nécessaire pour R2.5.
- Treize tâches sur 76 cochées. Commit local à retrouver avec `git log --oneline --grep="fix: require factual context for publication texts"`. **Prochain point exact : R2.6**, corpus fixe, répétitions et mesures du rédacteur ; revue globale du grand R2 en R2.7.

### 28 octobre 2026 — R2.6 terminé, évaluation réelle répétée

- Neuf cas synthétiques fixes joués deux fois sur Qwen3 8B local, sans correction entre passages : récit, tutoriel, produit, dialogue, danse, mémoire, deux packs sociaux et import titre seul. Application figée en `e7f85b9f738f27794e0156228ddbba92e9c7295e`. Programme sans Kernel/DB/vidéo/social, relais HTTP limité au moteur local ; conteneur CLI sans worker horaire ni port exposé, retiré automatiquement.
- **18 essais / 28 appels d'inférence** : 12 scénarios normalisés, quatre timeouts éditoriaux d'environ 180 s sans corps capturé, deux refus de contexte attendus sans HTTP. Campagne complète, programme **code 1** ; pas 18 réussites, ni preuve d'erreur de hashtags sur les packs expirés. Aucun délai augmenté, modèle changé ou correctif applicatif introduit pendant les mesures.
- Citations exactes et locuteurs conservés dans les deux dialogues, avec alerte paroles ; second dialogue plus fidèle. Yeux/colliers ajoutés correctement alertés. Actions physiques, causalité, préfixes contenant actions/états et propriétés ajoutées restent parfois non alertés ; corrections humaines proposées pour chaque scénario, non appliquées. Exclusions balle/carton respectées dans ces deux exemples, aucune généralisation à la mémoire de série ou aux médias.
- Mesures : environ 26 min 20 s, médiane murale des 12 scénarios **58,424257 s**, monotone **58,396488 s**, premier chargement Ollama **33,0442357 s**, écart maximal des horloges par essai **0,140863 s**. CPU i5-9300HF, RAM 15,92 Gio, RTX 2060 / 6 144 Mio, Ollama 0.40.11, Q4_K_M, contexte 4 096. Deux passages non représentatifs ; relais d'observation différent du streaming/worker, absence de corps partiel sur timeout. Aucune promesse de débit ou score commercial.
- [Bilan pédagogique, grille indépendante, durées et limites](r2-evaluation-redacteur.md), corpus et résultats complets dans docs/fixtures, nouveau programme scripts/evaluate-writer-local.php. Entrées/reprises, horodatages exacts et empreintes du programme/corpus/trois services vérifiés avant archivage. Revues indépendantes protocole/métrologie et sorties terminées ; ce ne sont pas la clôture globale R2.7.
- Programme exécuté, `php -l`, CS-Fixer et mode sans appel réussis, JSON/liens/repères/diff vérifiés. Aucun fichier applicatif/configuration/template/test/asset modifié ; pas de nouvelle suite complète, build ou recette UI. **758 / 8 619** et **152 frontend** restent les contrôles R2.5 historiques. Aucune migration, clé fournisseur, image/vidéo, publication, activation, dépense ou push. Le propriétaire a seulement été invité à garder le PC éveillé pendant la mesure.
- Quatorze tâches sur 76 cochées. Commit local à retrouver avec `git log --oneline --grep="test: evaluate local writer on repeated corpus"`. **Prochain point exact : R2.7**, revue globale et corrections/limitations des écarts avant validation de sortie du grand R2 ; ne pas commencer R3 directement.
