# Audit global VydFlow — décision et plan proposé, 27 octobre 2026

Suite au choix du propriétaire : l'ordre de travail, les décisions commerciales à confirmer et l'avancement sont désormais dans **[la roadmap active](roadmap.md)**. Le présent document reste le diagnostic et sa proposition initiale ; il ne constitue pas un second suivi concurrent.

## Avis principal

VydFlow a un socle local assez complet pour une bêta accompagnée : import privé, montage guidé, préparation par vidéo, comptes multiples, calendrier, suivi par destination, Studio et séries supervisées. La priorité est maintenant de rendre ces capacités plus compréhensibles et de mieux établir la qualité de leur résultat. Une réécriture, un nouveau framework, un nouvel onglet de validation ou une multiplication des générateurs n'apporteraient pas une réponse démontrée aux problèmes relevés.

Trois réserves structurent la suite : **la rédaction n'est pas assez fiable pour déléguer une génération payante sans relecture ; le Studio et les séries restent longs et techniques pour un débutant ; le dépôt n'est pas encore une exploitation publique prête à accueillir des clients.** Ces réserves concernent des objectifs différents : elles n'interdisent pas les recettes locales supervisées, mais doivent être levées avant les promesses commerciales correspondantes.

## Organisation et preuves

Quatre rôles ont travaillé sur le même état de code `d3e4700` :

| Rôle | Travail et livrable |
| --- | --- |
| Responsable UX produit | Première utilisation, Studio/Séries, guidage, états, simplicité, cohérence éditoriale. [Rapport UX](audit-global-20261027-ux.md) |
| Lead technique | Propriété, coûts, traitements, performance, exploitation et données. [Rapport technique](audit-global-20261027-technique.md) |
| Produit/acquisition | Landing, SEO, aide/contact, confiance, six concurrents sur sources officielles actuelles. [Rapport marché et SEO](audit-global-20261027-marche-seo.md) |
| Agent principal, synthèse et vérification | Navigateur desktop/tablette/téléphone, cas techniques ciblés, nouvelles propositions Qwen locales, arbitrage et croisement des conclusions. [Rapport interface](audit-global-20261027-interface.md), [rapport qualité](audit-global-20261027-qualite.md) |

L'analyse combine sources, données synthétiques et observation navigateur. Les points de compréhension restent à confronter à de vrais créateurs. Aucun taux de réussite novice, résultat SEO, comparaison de qualité vidéo avec un concurrent, test de charge ou certification d'accessibilité/sécurité n'est revendiqué. Le statut réel des portails sociaux, l'hébergement, la messagerie et les sauvegardes privées ne sont pas inspectés.

L'application n'est pas modifiée. Seuls les rapports sont ajoutés au dépôt ; scripts et sorties synthétiques restent dans `artifacts` ignoré. Aucun appel vidéo payant, envoi social, activation de récurrence, email externe, déploiement, commit ou push dans cet audit.

## Réponses aux questions du propriétaire

### Responsive et interface

Le responsive fonctionne sur l'échantillon contrôlé de 320 à 1280 px : menu présent, pas de débordement horizontal durable confirmé. Cela corrige l'idée d'une application devenue globalement inutilisable sur mobile. En revanche, à 390 px, une préparation de trois plans représente environ 5 688 px de hauteur et le préparateur à deux réseaux environ 3 206 px. Le contrôle porte sur les états synthétiques observés, pas sur chaque combinaison d'erreur ou de données.

**Oui à une interface plus compacte, mais par regroupement et progression.** Conserver la taille des textes et des cibles, montrer résumé français/durée/référence pour chaque scène, ouvrir ses détails à la demande et garder la prochaine action accessible. Conserver un seul écran commun Studio/Séries avec les mêmes composants. Pas de nouvel éditeur complet, pas de septième onglet, pas de retour à une page « À valider » isolée.

Deux défauts précis accompagnent ce chantier : la préparation d'un épisode affiche actuellement « Vidéos » comme section active après un transfert de contrôleur ; un libellé technique anglais « Subjects » reste rendu après le formulaire. La navigation du bas devient également petite à 320 px, avec des libellés de 9 px ; le menu offre heureusement une autre entrée.

### L'utilisateur est-il assez guidé ?

**Import/publication : plutôt oui pour une bêta accompagnée. Création et séries : encore insuffisant pour un débutant autonome.** Les confirmations, brouillons durables et choix de comptes constituent de bons repères. Le créateur doit encore comprendre trop tôt fournisseur, modèle, référence, durée finale/native, scènes et raccords.

Le Studio propose déjà trois modèles recommandés et plusieurs panneaux progressifs ; ne pas présenter tout comme déplié. La série utilise pourtant un choix de modèle plus technique. Le mode « Plan unique » et le bouton qui transforme réellement les plans doivent être rapprochés. La connexion fournisseur ne doit pas laisser croire qu'elle est nécessaire pour demander une simple proposition de texte locale. Le résumé français doit être marqué « à revérifier » si le prompt technique a changé et que ce résumé n'a pas été régénéré.

Sur l'installation locale, le besoin de garder ordinateur/services disponibles aux heures prévues doit être expliqué avant une activation de récurrence. Sur un futur hébergement, l'explication devra correspondre à son fonctionnement réel.

### Qualité des vidéos et séries

Les tests simulés valident l'orchestration et les protections ; ils ne prouvent ni les voix, ni la cohérence des visages, ni la fidélité d'une chorégraphie. Les références d'images et les réparations ciblées constituent des outils utiles, sans garantie visuelle.

Le nouveau cas de dialogue révèle un défaut concret : le résumé conserve les deux répliques françaises, mais les prompts effectifs les perdent et restent entièrement français, sans alerte. Les consignes du rédacteur interdisent en outre les dialogues. Une capacité « audio » dans le catalogue ne suffit donc pas pour promettre des personnages qui prononcent fidèlement un texte. Voir les sorties et limites du [rapport qualité](audit-global-20261027-qualite.md).

Les textes sociaux nécessitent aussi un lot de fiabilisation : un seul pack normalisé sur quatre tentatives synthétiques, avec un contexte inventé dans ce pack. Un diagnostic capturé montre un JSON complet mais une description YouTube remplie de hashtags, correctement rejetée par le validateur. Ne pas confondre ce contrôle protecteur avec une bonne qualité de proposition. L'échantillon est trop petit pour donner un taux d'échec commercial, mais montre des défauts à corriger avant d'appliquer automatiquement ces textes.

Le code examiné ne fournit pas de parcours dédié de référence vidéo de chorégraphie ou de synchronisation labiale/audio externe dans `AiCatalog`/`FalVideoApi`. Demander de danser dans un prompt n'équivaudra pas à reproduire précisément une tendance musicale. Distinguer un futur lot « scène parlante » d'un lot « mouvements guidés » ; choisir et tester un chemin exact avant d'agrandir le catalogue. La recherche du 26 octobre reste un contexte historique, pas une nouvelle validation des endpoints ou de leurs tarifs.

### Vitesse

Les optimisations du dernier lot réduisent réellement les lectures : Studio 9→4, détail de série de huit épisodes 38→14, calendrier 16→9. Elles sont utiles et conservées. Il reste deux sujets différents : les longues séries chargent un historique non borné, et la rédaction locale peut prendre plusieurs minutes sur ce poste. La file partagée peut aussi expirer une demande encore en attente ; une reproduction en mémoire a confirmé ce défaut.

Les assets partagés pourraient être séparés ultérieurement si les mesures le justifient. Three.js est déjà chargé dynamiquement pour la landing ; le supprimer de toute l'identité ne constitue pas une priorité fondée. Pas de score Lighthouse/Core Web Vitals public disponible dans cet audit.

### Landing, SEO, blog et contact

**Conserver l'identité et les animations ; revoir les informations et l'accès au produit.** Les textes « Séries à venir », « Montage à venir » et la description SEO ne correspondent plus à certaines capacités locales. Il faut distinguer capacité implémentée, accès bêta et disponibilité publique. Sur mobile, l'entrée de connexion est cachée dans le header et le lien direct restant apparaît seulement au footer : corriger aussi le retour des utilisateurs déjà inscrits.

La landing montre surtout des illustrations de démonstration, bien identifiées. Une démonstration réelle autorisée et une explication courte du fonctionnement et des coûts aideraient davantage qu'une nouvelle animation. Le compte fournisseur et les coûts IA séparés doivent être compréhensibles avant l'inscription au parcours IA.

**Pas de blog en premier.** Priorité aux pages ou sections fonctionnement, coûts/bêta, réseaux compatibles et aide/contact. On peut regrouper plusieurs thèmes au départ ; aucune nécessité de cinq nouveaux liens de navigation. Préparer titres/descriptions propres, URL publiques, canonical et sitemap lors du choix du domaine et de l'indexation. Ensuite, quelques guides originaux liés aux questions réelles des testeurs ; aucun volume d'articles ne garantit du trafic.

**Un email support accessible et réellement surveillé suffit au début**, avec une page aide/contact, adresse copiable et lien mailto depuis landing et studio. Le formulaire est facultatif. Un simple email enfoui dans les mentions légales est trop peu visible ; ne pas promettre de délai de réponse ou de chat que l'on ne peut pas tenir.

### Les concurrents font-ils mieux ?

Sur certaines capacités annoncées, oui : Buffer/Metricool ont des outils de programmation plus étendus ; Submagic/OpusClip mettent davantage en avant la finition ou la réutilisation ; AutoShorts/Shortspilot annoncent des séries plus directement automatisées. Leurs sources publiques ne permettent pas de conclure à une meilleure qualité moyenne ou une meilleure fiabilité réelle. [Comparaison datée et liens officiels](audit-global-20261027-marche-seo.md).

VydFlow doit tester un positionnement précis : **créateur indépendant francophone, import ou création dans un même parcours, contrôle du fournisseur et des dépenses, préparation supervisée jusqu'à la publication**. La souplesse du compte fournisseur est aussi une friction ; ne pas la présenter comme toujours plus simple ou moins chère. « Tout-en-un », « viral », « moins cher » ou « meilleure IA » ne sont pas des avantages démontrés.

## Points supplémentaires importants avant commercialisation

- Accès des comptes tiers : essais sur les comptes du propriétaire et Sandbox ne prouvent pas l'accès de tous les clients. Établir une matrice vérifiée par plateforme, type de compte et permission ; conserver une sortie fichier claire.
- Production : configuration actuelle locale, serveur PHP intégré et Mailpit ; fournir un environnement de production séparé et des emails configurables/délivrables. Aucun déploiement autorisé par cet audit.
- Données : restaurer ensemble base, médias privés et accès chiffrés ; tester la reprise avec consommateurs arrêtés et sorties externes désactivées. Définir suppression, conservation et traitement du support avec les prestataires réellement retenus.
- Exploitation : surveillance du web, files, échecs métier, tâches bloquées et disque ; un worker vivant ne prouve pas qu'il termine les tâches à l'heure.
- Économie : le client paie son fournisseur vidéo, mais VydFlow garde stockage, trafic, transcodage, transcription, rédaction et support. Mesurer ces coûts avant prix/quotas ; les offres de la roadmap restent des hypothèses.
- Accessibilité : claviers, états d'erreur, zoom, lecteur d'écran, médias et reduced motion sur appareil réel. Les contrôles positifs de cet échantillon ne constituent pas une conformité complète.
- Validation : faire observer trois à cinq créateurs sur des tâches concrètes ; mesurer où ils demandent de l'aide et ce qu'ils comprennent du devis, du compte choisi et de la date confirmée. Ce protocole est proposé, pas exécuté ici.

## Plan de suite proposé, à valider ensemble

| Ordre | Lot concret | Résultat attendu | Taille relative |
| --- | --- | --- | --- |
| 1 | Qualité et fiabilité du rédacteur | Attente distincte de l'exécution, cas FR/EN et répliques perdus signalés, fidélité des gestes et textes évaluée sur corpus fixe, relecture conservée | Moyen |
| 2 | Studio/Séries plus progressifs | Même écran, cartes de scènes compactes, prochain geste visible, modèles cohérents, mode unique clarifié, navigation correcte, textes personnels conservés | Moyen |
| 3 | Promesse publique, aide et SEO de base | Landing cohérente, connexion mobile visible, coûts et accès expliqués, aide/contact utilisables, premières pages utiles | Petit à moyen |
| 4 | Recette de compréhension | Créateurs réalisent import/programme et préparation de série sans aide excessive ; corrections ciblées issues des observations | Moyen, dépend des testeurs |
| Avant ouverture publique | Accès réseaux + environnement de production + emails + restauration + surveillance | Périmètre bêta effectivement exploitable et annoncé avec exactitude | Chantier distinct avec choix externes |
| Après preuves | Petit essai vidéo borné puis offre/quotas/facturation ; blog ciblé | Qualité audiovisuelle évaluée, économie définie et acquisition basée sur de vrais usages | À cadrer séparément |

Le premier lot ne signifie pas activer la publication autonome ni lancer du calcul vidéo. Il doit rendre les défauts visibles et les attentes correctes. Une scène parlante complète nécessite ensuite un contrat explicite pour personnage, réplique, langue, capacité du modèle et validation du rendu : elle ne se résout pas en retirant seulement « no dialogue » d'un prompt.

À conserver pendant tous les lots : contrôle propriétaire, sources/version figées, textes humains, choix du compte social, coût explicite, bornes de récurrence, pauses et distinction entre reprise de suivi et nouvelle soumission payante. Chaque lot d'implémentation futur pourra recevoir ses tests et son commit ; cet audit s'arrête au diagnostic et au plan.
