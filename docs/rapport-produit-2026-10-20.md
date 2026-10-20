# VydFlow — audit produit, génération IA et modèle économique

Rapport du 20 octobre 2026. État du code : `d06bcb7`, après le parcours image vers vidéo et le suivi visuel des générations.

## Décision recommandée

**Concentrer la suite sur une vidéo courte terminée et programmée, avec un parcours guidé et un fournisseur à connecter une fois.** La couverture des modèles est déjà suffisamment large pour tester cette proposition. L’assemblage, le montage guidé et la simplicité du premier résultat apporteraient davantage de valeur maintenant que l’ajout systématique de fournisseurs.

Le BYOK est cohérent pour une bêta avec des créateurs réguliers déjà à l’aise avec les outils IA. Pour une clientèle débutante large, l’obligation de créer et financer un compte externe reste une friction importante, même avec une connexion navigateur. Si cette clientèle devient la priorité, une offre avec génération intégrée mérite une étude ultérieure. Cela changerait l’économie et l’exploitation du produit : aucune revente de crédits, paiement ou offre hybride n’est implémentée par cet audit.

Le positionnement proposé : **« Crée tes vidéos courtes, prépare leur montage et programme-les sur tes réseaux depuis un seul studio. »** Aujourd’hui, préciser ce qui est effectivement disponible en bêta et ce qui reste prévu.

## Méthode et limites

- Lecture du code actuel : navigation, landing, compte, bibliothèque, formulaires, calendrier, publication, connexions et moteur IA ; documentation et résultats des vérifications précédentes.
- Recherche sur les pages officielles de produits et de facturation, consultées le 20/10/2026. Les fonctionnalités décrites sont leurs offres annoncées ; leurs rendus et performances n’ont pas été comparés par des générations payantes.
- Audit heuristique des parcours, pas une étude de conversion ni des entretiens utilisateurs. Les propositions de cible et d’offre sont des hypothèses à valider.
- Aucun nouveau benchmark de charge, test d’intrusion, essai fournisseur, publication, souscription ou accès à un compte concurrent. Aucun changement de l’application dans ce lot ; seul ce rapport est ajouté.
- Les tests précédents du suivi ont réussi avec APIs simulées. Cela ne prouve pas que toutes les configurations du catalogue ont été validées avec de l’argent réel.

## 1. Où en est le produit ?

| Domaine | État observé | Conséquence pour le lancement |
|---|---|---|
| Comptes | Confirmation email, récupération, profil/photo, fuseau, sécurité | Bonne base ; exploitation des emails, gestion complète des données et support à finaliser |
| Vidéos | Import privé, aperçu, lecture, téléchargement, suppression, sélection multiple | Parcours utilisable ; recherche et classement de la galerie à compléter |
| Publications | Préparation commune, choix des réseaux, confirmation, travail asynchrone, suivi par destination | Réutiliser cette base ; ne pas multiplier les écrans de publication |
| Calendrier | Programmation ponctuelle, fuseau, choix visuel avec recherche, agenda mobile | À conserver ; renforcer la modification et les problèmes de connexion avant échéance |
| Réseaux | Envois YouTube, Instagram et TikTok Sandbox réussis selon les essais documentés | Ces essais ne valent pas validation d’ouverture à des clients externes |
| Statistiques | Compteurs datés par destination ; lectures réelles YouTube et Instagram documentées | TikTok réel reste désactivé ; éviter les graphiques et agrégations prématurés |
| IA | 54 configurations, 34 groupes de choix, 33 configurations image, 19 acceptant une fin | Les groupes comprennent modes et variantes : ce ne sont pas 34 familles indépendantes |
| Connexions IA | Fal par clé et navigateur/MCP ; Replicate et LTX par clé | Un compte Fal couvre déjà beaucoup de choix ; trois fournisseurs ne sont pas obligatoires |
| Fiabilité IA | Confirmation du coût, compte choisi, suivi, récupération privée, reprise sans double envoi | Atouts importants ; compatibilité et coût par résultat utilisable à éprouver |
| Montage / récits | Montage annoncé à venir ; pas d’assemblage de projets multi-plans ou de voix off autonome | Principal manque pour produire une vidéo sociale complète |
| Séries | Vision documentée et emplacement « À venir » | Attendre une recette de production ponctuelle fiable avant de la rendre récurrente |
| Abonnement | Emplacement prévu, aucun paiement actuel | La valeur payante et les quotas doivent être validés avant d’ajouter le paiement |

Les réussites réelles confirmées par le propriétaire restent précieuses. Le dernier essai image vers vidéo est réussi ; son message ne précise pas le modèle et le mode API/MCP employés. Ce rapport n’en déduit pas une validation réelle de toutes les combinaisons.

## 2. Comment se positionnent les concurrents ?

Les concurrents se répartissent entre création, production de récits et gestion des réseaux. VydFlow touche déjà ces trois usages, ce qui rend son articulation plus importante que son nombre de fonctions.

| Produit | Offre publique observée | Leçon pour VydFlow |
|---|---|---|
| [Runway](https://runway.com/pricing) | Abonnement avec crédits, plusieurs modèles, outils créatifs et projets. Standard affiché à 15 USD/mois, ou équivalent 12 USD/mois avec facturation annuelle | L’utilisateur achète un environnement créatif et un budget de génération ; la comparaison avec un simple sélecteur de modèles serait difficile |
| [Krea](https://www.krea.ai/pricing) | Unités de calcul, accès aux modèles selon le plan, génération et améliorations de médias | Garder un catalogue avancé, mais proposer un chemin court vers le résultat |
| [Freepik, désormais Magnific dans la page consultée](https://www.magnific.com/pricing) | Suite créative, crédits, éditeurs et outils audio/vidéo ; prix localisés et offres variables | Le forfait mutualise plusieurs besoins. Ne pas comparer un nombre de crédits entre services sans connaître ce qu’il permet réellement |
| [Higgsfield](https://higgsfield.ai/) | Entrées par effets, studios et usages ; ses outils de cinéma reposent sur plusieurs modèles | Des recettes nommées par objectif sont plus parlantes que des identifiants de modèles. Le tarif complet n’étant pas lisible dans la page publique consultée, aucun chiffre n’est retenu |
| [Invideo](https://invideo.io/pricing/) | Plans à crédits, éditeur et accès différencié aux modèles/fonctions | La vidéo finie et l’édition font partie de la proposition ; prix promotionnels annuels à distinguer du paiement mensuel |
| [Fliki](https://fliki.ai/pricing) | Parcours depuis une idée ou un script, voix, scènes, médias et exports ; crédits inclus selon l’offre | Les vidéos longues peuvent être un assemblage de médias, voix et séquences ; elles ne sont pas nécessairement générées intégralement par un modèle vidéo |
| [Syllaby](https://syllaby.io/pricing-plan/) | Vidéos faceless, scripts, personnages, calendrier et programmation ; Starter affiché à 9 USD/mois avec 100 crédits | Concurrent plus proche de la vision finale : production répétable et organisation des publications |
| [Buffer](https://buffer.com/pricing) | Publication et organisation ; gratuit jusqu’à 3 canaux/10 posts programmés par canal ; Essentials affiché à 5 USD/mois/canal avec paiement annuel | La programmation seule subit une concurrence accessible et déjà installée |
| [Metricool](https://metricool.com/pricing/) | Calendrier, publication et analyse ; offre gratuite limitée, Starter annoncé à partir de 16 EUR/mois selon la formule affichée | La valeur payante de VydFlow devra être visible au-delà d’un calendrier et de quelques compteurs |

Ces chiffres sont des repères publics consultés à la date du rapport, pas des devis ni une comparaison de vidéos équivalentes. Promotions, taxes, quotas, modèles et périodicité changent le résultat. Aucun classement de qualité des modèles n’est déduit de ces pages.

**Conséquence stratégique :** choisir une spécialité. Une proposition crédible est la production régulière de vidéos courtes avec validation et publication multi-réseaux. Un projet qui tente simultanément de remplacer un studio cinéma, un éditeur complet et une suite d’analyse sociale deviendrait trop large pour la V1.

## 3. API, MCP et abonnement : quelle friction réelle ?

### Le paiement externe n’est pas forcément un deuxième abonnement

Dans VydFlow, le futur abonnement rémunérerait le logiciel, son stockage, les traitements et son organisation. La génération est facturée par le fournisseur. Fal décrit ses appels API comme une consommation de crédits prépayés sans abonnement associé ; Replicate décrit une facturation à l’usage, avec des modalités prépayées ou différées selon le compte. [Fal](https://fal.ai/learn/tools/how-to-generate-ai-videos-with-an-api), [Replicate](https://replicate.com/docs/topics/billing).

Il faut donc expliquer **« abonnement VydFlow + consommation IA auprès de ton fournisseur »**, plutôt que laisser croire à deux abonnements fixes obligatoires. Il reste deux comptes, deux espaces de facturation et une étape de financement externe : la difficulté ne disparaît pas avec cette distinction.

Ne pas promettre que l’abonnement grand public d’un générateur finance son API. Runway précise explicitement que ses crédits web et API sont séparés. C’est un exemple concret de confusion à prévenir pour toute future intégration. [Facturation Runway](https://help.runwayml.com/hc/en-us/articles/15124877443219-How-do-credits-work).

### MCP ne supprime pas le problème de facturation

MCP est le protocole d’accès aux outils ; la connexion par navigateur et l’autorisation sont une autre partie du parcours. OAuth peut aussi servir à une API. Un serveur MCP ne garantit ni connexion sans clé, ni réutilisation d’un abonnement, ni permissions adaptées à une application tierce.

Dans l’intégration Fal actuelle, la connexion navigateur évite de copier une clé, mais l’utilisateur doit encore disposer du bon compte et des crédits. API et navigateur sont deux accès conservés séparément. **Les présenter comme deux obligations serait une erreur ; un seul accès adapté suffit.**

Les concurrents proposent eux-mêmes MCP : Krea documente notamment la connexion par compte depuis des agents. Cela confirme l’intérêt du canal, mais l’existence d’un connecteur dans un autre produit ne valide pas automatiquement son intégration, ses permissions ou son modèle de coût dans VydFlow. [Krea MCP](https://www.krea.ai/mcp).

### Effet probable selon le public

| Public | Tolérance probable au BYOK | Ce qui doit justifier VydFlow |
|---|---|---|
| Débutant qui veut une seule vidéo | Faible : comptes, crédits et erreurs d’accès ajoutent plusieurs décisions avant le premier résultat | Essai très guidé ; une offre intégrée pourrait mieux convenir à terme |
| Créateur régulier déjà utilisateur de Fal | Meilleure : son compte existe et il connaît son budget | Montage, réutilisation, calendrier et publication sans transferts manuels |
| Indépendant produisant pour son activité | Possible si le résultat est rapide et répétable | Recettes, cohérence visuelle, légendes et suivi fiables |
| Agence | Possible, mais besoins de comptes multiples, permissions et validation client | Ne pas viser ce segment dès maintenant sans gestion de marques/équipes |

Ce sont des hypothèses produit, pas des taux de conversion mesurés.

### Parcours recommandé pour une première connexion

1. Demander ce que la personne veut faire : publier une vidéo existante, animer une image, créer un plan. L’import/publication reste utilisable sans connexion IA.
2. Pour l’IA, proposer **Fal en chemin recommandé**, avec « Connecter mon compte Fal ». Les autres fournisseurs restent accessibles dans « Autres connexions ».
3. Une courte explication : « Ton compte Fal paie les générations. Ton abonnement VydFlow couvre le studio et l’organisation. » Montrer un exemple de coût daté, pas une promesse de prix fixe.
4. Autoriser le fournisseur ; s’il manque des crédits, donner un lien et une explication précise. Ne pas annoncer « prêt » sur la seule présence d’une clé chiffrée.
5. Revenir à l’idée, aux images et au modèle choisis, sans recommencer le formulaire.
6. Récapitulatif compact : fournisseur, compte utilisé, modèle, durée, résolution, audio et estimation. Une confirmation explicite avant dépense.

La clé API devient une solution alternative pour les utilisateurs avancés. Enregistrer une méthode préférée une fois, puis la résumer dans le studio. Tout changement de compte ou de méthode doit rester visible ; aucune bascule silencieuse en cas d’erreur.

## 4. Le Studio IA est-il devenu trop complexe ?

Le moteur a gagné des protections utiles : imports privés, capacités par modèle, estimation, confirmation et reprise. **La complexité est surtout dans les décisions présentées à l’utilisateur.** Il doit relier point de départ, images, format, ajustement, modèle, résolution, fournisseur, méthode et durée.

### À simplifier à l’écran

- Garder un écran principal : idée/image → quelques réglages → résultat. Limiter la sélection mise en avant à trois choix éditoriaux vérifiés, par exemple « Économique », « Équilibré », « Avec audio ». Ce ne sont pas des garanties universelles de qualité.
- Conserver tous les modèles disponibles dans « Tous les modèles ». L’accès expert reste ouvert ; la première expérience ne doit pas imposer de parcourir 34 groupes.
- Nom du modèle visible, fournisseur en information secondaire ; regrouper texte/image et résolutions sous une famille lorsque cela reste compréhensible. Le moteur peut garder ses identifiants précis sans les imposer à la navigation.
- Remplacer le message permanent sur la 4K par une aide contextuelle sur la résolution. Priorité au 1080p pour un export social si le modèle et le budget le permettent ; préserver 720p pour économiser et 4K pour les besoins explicites.
- Conserver les aperçus de cadrage et le choix début/fin : ils évitent une erreur facturable. Présenter la fin comme une option révélée quand le modèle la propose.
- Mettre le premier exemple de prompt et l’aide près du champ. Le français doit suffire pour commencer ; toute reformulation/traduction IA future devra être annoncée, modifiable et chiffrée si elle est payante.
- Montrer le résultat en cours à côté du formulaire plutôt qu’un aperçu décoratif ; conserver l’historique complet dans sa page dédiée.
- Préparer des exemples originaux et des recettes cohérentes. Des visuels générés par nous ou autorisés permettent une démo sans donner artificiellement des crédits.

### À ajouter avant d’élargir encore le catalogue

- Diagnostic de connexion non facturable lorsqu’un endpoint officiel le permet : enregistrée, vérifiée, expirée, crédits à consulter. Ne jamais lancer une génération pour tester une clé sans consentement.
- Archivage/suppression des idées préparées et gestion des références inutilisées. L’historique IA n’a pas encore d’action de nettoyage équivalente aux brouillons de publication.
- Message d’erreur orienté action : accès, budget fournisseur, refus de contenu, fichier trop lourd, résultat disponible mais import impossible. Préserver un identifiant de support privé et la récupération du même job.
- Préférences simples de format, résolution et modèle favori, appliquées seulement lorsqu’elles sont compatibles.
- Notifications de résultat/échec en quittant la page : d’abord dans l’app, puis email facultatif. Un loader sur la page ouverte ne couvre pas ce besoin.
- Suivi de coût estimé par création/projet et historique daté. Sans lecture officielle de facture, ne pas appeler ce total « montant débité » ni le présenter comme plafond ferme.

## 5. Vidéos de 30–60 secondes, narration et personnages

Les outils concurrents offrent des parcours de production, pas seulement un champ prompt. LTX décrit le passage script → storyboard → scènes → édition ; Fliki combine scènes, médias et voix ; Invideo décrit l’ajout de voix et d’éléments de montage. [LTX Studio](https://ltx.io/studio/platform/ai-movie-maker), [Fliki](https://fliki.ai/pricing), [Invideo](https://invideo.io/make/AI-VIDEO-GENERATOR/).

Pour VydFlow, créer un **projet vidéo** composé de plans est la suite logique :

> Objectif et durée finale → script court → liste des plans → images/imports/générations → sélection des versions → assemblage → voix/sous-titres facultatifs → validation → publication ou calendrier.

Commencer par assembler des vidéos existantes et quelques plans générés. Les premières recettes peuvent être une présentation de produit, une courte histoire ou un extrait avec sous-titres. Une bande de vignettes avec réorganisation et réglages limités suffit ; une timeline professionnelle complète n’est pas nécessaire au lancement.

Pour les personnages qui parlent, distinguer audio natif du modèle, voix off sur les plans et synchronisation labiale spécialisée. Le champ « Audio généré » actuel ne promet ni une voix française stable, ni une identité vocale conservée entre plans. La cohérence des personnages nécessite des références et un contrôle des sorties ; une image de fin peut guider une transition sans garantir à elle seule tout un récit.

**Exemple budgétaire pédagogique :** au tarif actuellement intégré de 0,35 USD pour un plan Kling 2.5 de 5 secondes, six plans coûtent environ 2,10 USD pour 30 secondes générées, si chaque premier essai convient. Deux essais par plan donnent environ 4,20 USD. Trente vidéos construites ainsi donnent 126 USD de générations, avant logiciel, voix/musique, taxes et conversion. C’est une hypothèse arithmétique, pas le coût garanti d’une vidéo utilisable ni un débit constaté. Elle n’implique pas qu’un plan plus long est tarifé comme six demandes de 5 secondes.

Faire valider les images et ne régénérer que le plan rejeté peut réduire les essais vidéo inutiles. Utiliser un mélange d’imports, d’images animées et de plans vidéo est une autre option à proposer selon le rendu recherché. Afficher le budget du projet avant de lancer plusieurs dépenses.

## 6. Audit du reste de l’application

| Surface | Conserver | Modifier ou ajouter | Priorité |
|---|---|---|---|
| Landing | Identité visuelle et animations avec fallback | Actualiser les disponibilités ; montrer le studio actuel et un parcours de résultat ; expliquer la facturation externe avant inscription | Immédiate |
| Première utilisation | Entrée simple par Vidéos et email confirmé | Deux choix : « J’ai déjà une vidéo » / « Je veux créer » ; étapes de connexion au moment utile et guide facultatif | Haute |
| Bibliothèque | Galerie, sélection multiple, téléchargement, menus et brouillons séparés | Recherche et filtres pertinents dans la galerie principale ; tri récent/nom, origine import/IA ; état « utilisé dans une programmation » | Haute |
| Brouillons | Création durable après travail explicite, reprise et suppression | Sauvegarde plus fluide du compositeur et indicateur clair ; préserver la confirmation finale, distincte de la sauvegarde | Haute |
| Connexions | Un espace pour réseaux et IA | Deux rubriques lisibles, état vérifié/reconnexion ; masquer les détails de transport derrière une aide ; aucune obligation de connecter tout le catalogue | Haute |
| Préparation | Réseaux, textes et date sur un écran, aperçu et récapitulatif | Texte commun puis exceptions par réseau ; résumé des comptes ; aide aux déclarations IA et audience ; éviter de répéter tous les champs pour un gros lot | Haute |
| Calendrier | Recherche visuelle, agenda téléphone, fuseau | Modifier une heure rapidement avec confirmation ; liens vers problèmes ; si ajout de glisser-déposer, alternative clavier et confirmation | Moyenne |
| Publications | Résultat par destination, métriques datées, liens réseau | Regrouper visuellement les destinations d’une même vidéo ; faire ressortir l’action nécessaire et la reprise ciblée | Moyenne |
| Mon compte | Profil, préférences, sécurité ; menu mobile adapté | Préférences de création et notifications ; gestion des données et facturation opérationnelles avant commercialisation | Haute avant vente |
| Aide | Guides près des connexions | Aide contextuelle courte et support accessible ; détails techniques et diagnostics dans une rubrique dédiée | Haute |

### Constats précis dans le code

1. **Disponibilités contradictoires.** `templates/landing/index.html.twig` annonce encore calendrier, programmation et IA à venir. `templates/legal/terms.html.twig` décrit TikTok/calendrier/IA comme absents ; `privacy.html.twig` affirme qu’aucun envoi IA n’a lieu et ignore des traitements actuels. Le README et la roadmap contiennent aussi d’anciens états. Préparer un état courant de référence et mettre les textes à jour, en distinguant bêta testée et ouverture publique. Finaliser les documents avant commercialisation.
2. **Une recherche existe, mais pas partout.** `VideoAssetRepository::search()` sert au choix du calendrier ; `WorkspaceController` et `studio/workspace.html.twig` utilisent une galerie paginée sans recherche. Le problème reviendra avec davantage de vidéos : réutiliser cette requête plutôt qu’inventer un second moteur.
3. **Déclarations des vidéos IA peu reliées à leur origine.** `PublicationComposerController`, vers les lignes 130–132, initialise `synthetic` à `false` pour les trois réseaux, y compris lorsqu’une vidéo provient du studio. Remonter l’origine connue pour guider la déclaration, avec les distinctions de chaque plateforme et une validation de l’utilisateur ; ne pas cocher aveuglément toutes les vidéos IA comme « contenu réaliste » sur YouTube.
4. **Audience YouTube.** Le même bloc initialise « conçue pour les enfants » à Oui, conformément à la demande précédente du propriétaire. Pour un SaaS généraliste, refaire de ce choix une préférence explicite ou une décision guidée par contenu. Ce n’est pas une préférence esthétique ; aucun changement automatique n’est fait ici.
5. **État d’une clé.** `AiKeys::save()` contrôle son format et la chiffre ; `hasAccess()` vérifie essentiellement la présence d’un accès. Cela ne démontre pas l’autorisation d’exécuter un modèle ni un solde suffisant. Le texte actuel le reconnaît ; l’état visuel pourrait mieux distinguer « enregistré » et « vérifié ».
6. **Catalogue daté.** `AiGenerations` bloque une confirmation lorsque le tarif a plus de 14 jours ou ne correspond plus à l’estimation. C’est un garde-fou à conserver, mais une procédure de maintenance et une alerte exploitant sont indispensables. Avec 54 configurations, la maintenance devient une fonction du produit.
7. **Taille, codec et récupération après paiement.** `AiVideoDownload` plafonne à 100 Mo et `VideoProbe` accepte des formats/codecs/pixels précis ; aucune normalisation vidéo générale n’existe. Un fournisseur pourrait produire un fichier facturé mais incompatible. Tester chaque configuration proposée en production, prévoir une récupération explicite, puis un transcodage maîtrisé si nécessaire. Ne pas simplement relever les limites sans examiner stockage, mémoire et bande passante.
8. **Nettoyage des références.** `AiReferenceStorage` garde les images privées et limite leur volume ; le retrait d’une référence dans le formulaire n’est pas une gestion complète du stockage. Prévoir nettoyage des imports abandonnés et suppression respectant les jobs encore récupérables.
9. **Traitements en concurrence.** `messenger.yaml` route génération, publications programmées et statistiques dans la même file ; le worker consomme celle-ci et `PublicationLock` est partagé par propriétaire. Risque sous charge : des traitements IA longs retardent d’autres tâches. Mesurer puis séparer les priorités/files ou workers au sein du monolithe, tout en préservant les verrous d’idempotence. Ce rapport n’affirme pas qu’un retard a été reproduit.
10. **Une priorité produit par écran.** « Vidéos », « Studio », « Activité », « Préparations » et les anciens chemins bibliothèque peuvent se recouper. Garder la navigation actuelle à cinq entrées ; harmoniser les retours et traiter les anciens parcours comme fallbacks. Ne pas supprimer des routes avant de vérifier leurs liens et tests.

## 7. Ce que je retirerais de l’expérience principale

- Les mentions « à venir » devenues fausses et les formulations de développement comme « activation séparée pendant cette phase de test » lorsque ce n’est plus l’état du compte.
- Le jargon MCP/BYOK dans les premières décisions. Garder ces explications disponibles pour les personnes qui les cherchent ; conserver la transparence sur le compte facturé.
- Le bandeau 4K permanent et les informations de catalogue qui prennent la place du premier résultat.
- La double présentation constante clé API/connexion navigateur. Une méthode préférée affichée, l’autre accessible dans les réglages.
- L’emplacement Séries grisé si aucun accès d’explication utile n’est offert ; il peut rester dans une page « À venir » ou revenir quand son premier lot est prêt.
- La course à tous les fournisseurs, les graphiques analytiques avancés, le système d’équipes et un éditeur complet avant validation du parcours central.

Il s’agit de retraits de bruit et de priorités, pas de suppression des données, des intégrations existantes, des animations de landing ou des protections de confirmation.

## 8. Modèle économique recommandé

| Option | Expérience | Coût et exploitation pour VydFlow | Recommandation |
|---|---|---|---|
| Abonnement logiciel + compte IA personnel | Coût IA lisible, choix large, connexion externe à franchir | Peu d’avance de trésorerie IA ; support multi-fournisseurs et stockage restent à financer | Conserver pour la bêta, avec un parcours Fal prioritaire |
| Abonnement incluant un quota de génération | Première utilisation plus directe | Coûts variables, risques d’abus, gestion des erreurs/dépenses, limitations et support de facturation | À étudier si le public débutant est confirmé ; pas d’implémentation implicite |
| Hybride : génération incluse ou compte personnel | Plusieurs publics couverts | Deux systèmes de facturation et risque de confusion | Piste ultérieure seulement ; compte débité toujours explicite et aucun basculement silencieux |

**Pour facturer le logiciel, démontrer d’abord le gain de travail** : réutiliser un projet, appliquer une recette, préparer les textes et programmer les destinations sans télécharger/réimporter chaque étape. Le BYOK protège la marge de génération mais ne crée pas à lui seul une raison de payer.

La grille VydFlow n’est pas fixée dans cet audit. Un prix mensuel doit intégrer stockage, rendus de montage, emails, supervision, support, frais de paiement et récupération d’échecs, même si l’utilisateur finance Fal. Éviter « illimité » et distinguer nombre de réseaux, stockage, rendus et séries des dépenses IA externes.

Rendre l’essai possible avec une vidéo importée et un exemple de projet, avant de demander une carte ou un compte fournisseur. Une éventuelle démonstration gratuite ne doit pas devenir une génération fournisseur non financée ou non consentie.

## 9. Ce qui reste à vérifier avant d’ouvrir à des clients

**TikTok : priorité de validation externe.** Le fonctionnement Sandbox ne garantit pas la levée des restrictions privées. TikTok exige un audit pour cet accès et documente des contraintes de parcours : choix manuel de visibilité, absence de présélection de certaines interactions et contrôle du créateur. Préserver les choix manuels actuels. Une promesse de séries entièrement automatiques devra être vérifiée avant d’être vendue ; la validation humaine par occurrence est un point de départ prudent. L’application publique et ses usages doivent être présentés honnêtement à la revue, sans supposer qu’un outil privé sera accepté. [Direct Post](https://developers.tiktok.com/docs/en/content-posting-api-get-started), [règles de partage](https://developers.tiktok.com/docs/en/content-sharing-guidelines).

Meta/Google : documenter l’état réel des permissions, la portée pour des utilisateurs externes, les renouvellements et les quotas depuis les consoles et leurs exigences courantes. Ne pas transformer la réussite de notre compte de test en promesse universelle. Les règles YouTube évoluent : la référence actuelle `videos.insert` indique que les projets non vérifiés ne sont pas automatiquement limités au privé ; ne pas recopier une ancienne généralité contraire. La vérification OAuth et les limites d’usage restent des sujets distincts. [Référence Google](https://developers.google.com/youtube/data/reference/rest/v3/videos/insert).

Avant facturation publique : domaine stable, hébergement durable, emails de production, sauvegarde/restauration testée, alertes worker/files en échec, stockage privé et clés récupérables, procédure de suppression/export, conditions à jour et facturation/résiliation utilisables. Le tunnel de test n’est pas cette infrastructure. Cette liste relève de l’état de préparation observé ; ce rapport n’est pas une validation juridique ou un audit de sécurité exhaustif.

## 10. Ordre de travail proposé

| Lot | Contenu concret | Critère de sortie | Effort relatif |
|---|---|---|---|
| A — Cohérence et premier résultat | Textes de disponibilité, connexions guidées, choix simples, facturation compréhensible, aide aux déclarations | Une personne extérieure comprend ce qui est inclus et prépare une création sans assistance constante | Faible à moyen |
| B — Projet et montage guidé | Assembler quelques clips importés/générés, ordre/coupe simple, format, sous-titres et recette progressive ; source conservée | Une vidéo courte complète est exportée puis préparée pour publication sans manipulation de fichiers externe | Important, à découper |
| C — Récit guidé | Script, plans, voix off facultative, références, reprise d’un seul plan et budget de projet | 30–60 s cohérentes, coût visible, aucune régénération globale imposée | Important ; après B |
| D — Série à valider | Réutiliser une recette éprouvée, préparer avant l’échéance, vérifier les occurrences, pause/limites | Plusieurs occurrences peuvent être préparées, validées et suivies sans doublon | Important ; après un parcours ponctuel fiable |
| E — Ouverture commerciale | Revue plateformes, infrastructure, documents, quotas, support et abonnement | Test externe complet et facturation exploitable, avec récupération des incidents | Chantier transversal à préparer dès A |

L’extension du catalogue continue seulement lorsqu’un besoin concret l’exige : modèle absent, coût réellement meilleur ou capacité nouvelle. Chaque ajout doit avoir contrat et tarif datés, tests simulés, contrôle de récupération et essai réel autorisé avant promesse de fiabilité en production.

## 11. Comment décider avec des utilisateurs plutôt qu’avec nos intuitions ?

Organiser cinq à huit essais avec des personnes extérieures, sans les guider à chaque clic : quelques créateurs déjà équipés en IA et quelques débutants. Aucune invitation ni prise de contact n’est envoyée par cet audit.

Deux tâches : importer et programmer une vidéo ; puis préparer une génération depuis une image, comprendre son coût et continuer vers une publication. Tout essai facturable reste explicitement accepté par la personne sur son compte, ou prévu dans un dispositif de test financé à part.

Observer : temps jusqu’au premier résultat, endroits où l’aide est nécessaire, capacité à identifier qui facture, abandon lors de la connexion/du financement, choix de modèle, réussite de récupération et intérêt pour refaire une deuxième vidéo. Recueillir la volonté de payer après la réalisation de la tâche, en comparant au workflow habituel.

Décisions proposées, à traiter comme seuils internes de bêta : viser au moins quatre personnes sur cinq capables d’importer/programmer sans aide et d’expliquer correctement la facturation ; si trois débutants sur cinq se bloquent sur le financement fournisseur, revoir ce parcours ou le segment ciblé avant d’ajouter des modèles. Un petit panel détecte des obstacles ; il ne prouve pas une conversion commerciale.

L’indicateur central à construire est **le nombre de vidéos réellement terminées puis publiées par un utilisateur qui revient**, avec le temps et le coût d’essais nécessaires. Le nombre de modèles et le nombre de générations lancées ne suffisent pas à mesurer cette valeur.

## Proposition pour le prochain lot

Faire **A**, puis commencer **B par un assemblage simple de clips déjà présents**, avec prévisualisation et export privé. Ajouter progressivement les sous-titres et une recette ; préparer en parallèle le dossier des accès publics. Conserver le BYOK pour cette bêta, avec Fal comme chemin recommandé et les autres accès disponibles en avancé. Mesurer la friction avant de décider d’une offre IA intégrée.

Cela rapproche directement VydFlow de sa promesse de production régulière, tout en gardant le périmètre du monolithe et la confirmation des dépenses et publications.
