# Simplification du parcours et séries autonomes — analyse du 23 octobre 2026

Analyse demandée après le message sur les raccords. Document de recommandation : aucune refonte, installation de modèle, récurrence, dépense ou publication activée ici.

Diagnostic historique avant la préparation commune. Pour les lots livrés ensuite : [préparation unifiée](preparation-unifiee-essai-local.md) et [scénario local supervisé](scenario-local-supervise.md).

## Diagnostic confirmé dans le code

- `AiStoryStartType` propose toujours « Action continue », même si la recette ne contient ni modèle image compatible ni référence. `AiEpisodeFlow::initialize()` rejette ensuite le choix. Le contrôle serveur est nécessaire ; l’interface doit prévenir le blocage et proposer la correction.
- `AiEpisodeController` ne propose pas de changement de modèle/référence à cette étape. La recette vient de la série ou de sa préparation antérieure (`AiEpisode::getPreparationRecipe()`). L’utilisateur doit remonter un parcours dont les réglages sont parfois déjà figés.
- `AiSeriesType` appelle « Durée par épisode » un choix limité aux durées natives d’un clip. La durée finale jusqu’à 60 secondes appartient à une autre page : ambiguïté de vocabulaire et de données.
- Le studio sépare génération individuelle et composition à plans ; l’utilisateur passe par proposition, choix du découpage, scènes puis estimation. Les contraintes techniques apparaissent avant son intention de création.
- Les moteurs BYOK, plans, montage, bibliothèque, brouillons, calendrier et publications existent. La rédaction narrative, la mémoire des épisodes et la création récurrente des occurrences restent à construire.

## Parcours cible recommandé

Une entrée « Créer une vidéo », dans le studio comme depuis un épisode. Un écran de préparation :

1. Idée/contexte et durée finale souhaitée.
2. Personnage récurrent : référence privée et description communes, modifiables sur place avant lancement.
3. Proposition de modèle compatible et de découpage, affichant fournisseur, accès et coût estimé. Le modèle reste remplaçable ici. Aucun changement silencieux de fournisseur ni de compte facturé.
4. Plans proposés, avec « Personnaliser les scènes » pour modifier prompts, ordre et durées. Une rédaction par gabarit doit être présentée comme telle ; une rédaction IA n’est annoncée que si elle existe.
5. Récapitulatif et confirmation du coût ; suivi puis validation de la vidéo, avec le calendrier existant.

Les détails de raccord, cadrage et accès passent dans les réglages avancés lorsqu’ils ne demandent aucune décision. Le son reste conservé par défaut. Un personnage récurrent sans référence déclenche une aide à l’import au bon endroit. Le raccord ne devient disponible qu’après validation des capacités ; aucune option inutilisable ne mène à une erreur tardive. Un texte vers vidéo demeure possible pour les créations sans référence, sans promettre une identité constante.

La durée finale est distincte des durées de clips. Une durée exacte impossible est explicitement signalée avec choix proche. La limite actuelle de huit sources reste visible lorsque pertinente. Changer modèle ou références avant lancement recalcule le découpage et l’estimation ; les textes saisis doivent être conservés autant que possible et une redistribution proposée avant toute suppression. Une demande déjà soumise n’est pas convertie ou relancée.

## Série récurrente : exemple Milo

Configuration lisible : « Milo, chat roux astronaute, découvre une planète chaque semaine », sa référence, une durée finale de 45 secondes, mardi 18 h dans le fuseau choisi, destinations et mode de validation. Le modèle et le budget sont visibles avant activation ; les détails peuvent rester repliés.

Avant chaque échéance : relire la fiche du personnage et le résumé des épisodes validés, proposer une aventure différente, écrire les scènes et leurs prompts, adapter les durées au modèle, enregistrer un brouillon puis réutiliser le moteur actuel. Le créneau désigne la publication ; la préparation et la génération doivent commencer en avance.

Deux degrés d’autonomie, livrés progressivement :

- Propositions automatiques gratuites ou peu coûteuses, sans dépense vidéo : l’utilisateur approuve le scénario et le coût, puis la vidéo avant programmation.
- Génération récurrente sous accord explicite, nombre de demandes limité et budget estimé suivi ; publication automatique seulement si choisie séparément et compatible avec les accès des réseaux. Pas de garantie de plafond de facturation fournisseur à partir d’une simple estimation.

Une occurrence unique par série/date évite les doublons. Retard, validation absente, accès manquant ou coût non autorisé suspendent le départ avec un état explicite. Un échec ambigu n’est pas renvoyé automatiquement.

Changer 45 en 60 secondes ou déplacer le créneau s’applique aux occurrences futures non commencées. Les épisodes en génération, validés ou déjà programmés conservent leurs réglages ; une modification spécifique est explicite. La continuité narrative utilise un résumé des faits validés, les personnages et les sujets déjà traités, plutôt que de recopier toute l’histoire.

## Coûts et choix du scénariste

| Option | Ce qu’elle apporte | Limite/coût |
| --- | --- | --- |
| Gabarits et règles Symfony | Prompts et répartitions automatiques sans appel IA | Variété et adaptation d’un thème libre limitées |
| Modèle de texte local avec Ollama | Scénarios et plans structurés, sans facturation API par appel local | Matériel, mémoire, énergie, disponibilité et licence du modèle à vérifier ; qualité à mesurer |
| API de texte | Scénarios sans exploiter son propre modèle | Appels facturés/quotas et intégration BYOK ou coût logiciel à définir |
| Génération vidéo existante | Clips puis assemblage | Les fournisseurs continuent de facturer leurs générations ; pas de gratuité universelle |

Ollama permet une exécution locale et des réponses JSON conformes à un schéma. Le serveur doit encore valider durées, nombre de plans, longueurs et contenu : le JSON valide ne garantit pas la qualité du scénario. Un essai local peut être pertinent, mais ne constitue pas une infrastructure SaaS disponible en permanence. Sur le poste, ordinateur/Docker/moteur local doivent fonctionner ; pour le SaaS commercial, hébergement et exploitation sont à chiffrer. Aucun besoin d’ajouter n8n ou un second moteur de tâches.

Sources officielles consultées le 23 octobre 2026 : [sorties structurées Ollama](https://docs.ollama.com/capabilities/structured-outputs), [exécution locale et ressources](https://docs.ollama.com/faq). Le choix d’un modèle précis demande encore un essai de qualité/performance et une vérification de sa licence.

## Ordre recommandé pour un développeur solo

1. **Simplification UX**, valeur élevée, complexité moyenne : écran commun, modèle/référence sur place, durée finale claire, découpage prérempli et réglages avancés. Valider studio et épisode à confirmer, avec/sans référence, conservation des saisies, clavier/mobile et absence de demande payante sans consentement.
2. **Assistant de scénario à la demande**, valeur élevée, complexité moyenne : fiche de série, résumé narratif, proposition structurée modifiable et vérifications serveur. Comparer gabarits et petit essai local avant de retenir une solution. Réutiliser les plans actuels.
3. **Récurrence supervisée**, valeur élevée, complexité moyenne à élevée : occurrences en avance, fuseaux, règle de changement des réglages, validation et programmation existante. Tester avec horloge contrôlée, API simulées et aucune dépense réelle.
4. **Autonomie complète choisie**, après recette des étapes précédentes : accord de dépenses récurrentes, limites, pause et publication automatique selon les capacités réelles des plateformes.

Symfony conserve les règles et validations ; Doctrine conserve fiche, occurrences et historique ; Messenger exécute les tâches longues. Le modèle de texte propose le récit, tandis que l’application choisit les opérations autorisées et contrôle le coût avant le moteur vidéo existant.
