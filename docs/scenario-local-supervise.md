# Scénario local supervisé — 23 octobre 2026

Évolution du 24 octobre : la rédaction est désormais traitée en arrière-plan et retrouvable au retour. Le parcours et les composants qui remplacent la requête longue de ce premier pilote sont décrits dans [corrections-studio-series-recurrence.md](corrections-studio-series-recurrence.md).

Les limites de 55 secondes et la mémoire de trois épisodes mentionnées ci-dessous décrivent le pilote initial. Le délai actuel est de **180 secondes par appel**, jusqu'à deux appels successifs ; la récurrence transmet les **cinq derniers résumés disponibles**. Voir [le diagnostic](series-publications-redacteur.md) et [la récurrence supervisée](recurrence-supervisee.md). La recette locale multi-thèmes est disponible avec `docker compose exec -T php php scripts/try-story-local.php qwen3:8b --suite --repeat --warm` : dix sujets synthétiques et deux répétitions, sans accès à la base métier ni appel vidéo, résultats dans un nouveau dossier ignoré `artifacts/story-local/`. Les consignes par format, le préfixe d'identité, les alertes et leurs limites sont décrits dans [le lot de fiabilité](redacteur-local-fiabilite.md).

## Objectif et périmètre livré

Depuis le même écran de préparation du studio ou d’un épisode modifiable : écrire une idée courte, choisir la durée et le modèle vidéo, puis demander « Proposer mon scénario ». L’ambiance Automatique respecte le sujet et propose un humour léger si aucun ton n’est précisé. Une proposition affiche un titre, une histoire et les actions en français ; les prompts vidéo sont consultables dans des détails repliés.

« Utiliser ces scènes » remplace explicitement les textes et durées du formulaire, sans lancer de vidéo. Les champs restent modifiables et le récapitulatif existant conserve la confirmation du coût. Un découpage personnalisé valide (par exemple 5 + 15 + 10 secondes) est conservé par le rédacteur ; sinon il propose un découpage natif pour le total souhaité. L’aperçu seul n’est pas enregistré. Après application, le studio conserve les champs dans la session via son mécanisme existant ; pour un épisode, ouvrir le récapitulatif enregistre la préparation. L’ambiance est un réglage de proposition, pas encore un défaut durable de série.

Le choix visible « Plusieurs scènes » produit des clips assemblés avec changements de cadrage. « Prolonger l’action » réutilise la dernière image du précédent clip avec une référence et un modèle image compatible. Ce raccord n’est ni une extension vidéo native du fournisseur, ni la garantie d’un plan continu ou d’un personnage identique. Les informations de continuité ne sont plus cachées avec le son. Le son reste conservé par défaut.

Ce lot ajoute la rédaction sur demande. Il n’active ni génération vidéo récurrente, ni lancement groupé d’épisodes, ni publication autonome, ni API de texte payante.

## Fichiers et composants Symfony

| Fichier | Rôle |
| --- | --- |
| `src/Service/LocalStoryWriter.php` | Deux appels Ollama : histoire/identité/étapes, puis prompts vidéo. Contrôle des tailles, nombre de clips, ordre/durées exacts, réponse terminée et doublons textuels stricts. |
| `src/Controller/AiStoryProposalController.php` | Route POST privée, CSRF, propriétaire, épisode modifiable, durée native et limitation de fréquence ; ajoute les informations de série et un court extrait des trois épisodes précédents. |
| `config/services.yaml`, `.env` | Client HTTP distinct sans capture de clés vidéo ; activation, adresse et modèle administrateur. Aucun fournisseur distant de secours. |
| `assets/controllers/story_writer_controller.js` | Chargement, erreurs, aperçu et application volontaire. Une préparation modifiée pendant ou après la rédaction invalide la proposition. |
| `templates/ai/_story_proposal.html.twig` | Aperçu lisible en français ; prompts techniques repliables, bouton d’application explicite. |
| `AiIdeaType.php`, `preparation_controller.js`, templates et styles | Choix de récit visible, aide selon la compatibilité ; moteur de préparation et montage inchangés. |
| `scripts/try-story-local.php` | Comparaison synthétique des modèles avec le service réel, sans base métier ni fournisseur vidéo. |

Flux : Stimulus envoie idée/durée/modèle/ambiance et identifiant d’épisode → le contrôleur vérifie l’accès et calcule un découpage natif exact via `AiEpisodeFlow` → le service construit une histoire puis les prompts → réponse JSON privée → aperçu → application dans la collection existante → préparation, consentement et workers vidéo déjà présents.

À comprendre en Symfony : le contrôleur contrôle la requête et les autorisations ; le service réalise la rédaction sans accès aux clés BYOK ; le FormType et le service de préparation demeurent l’autorité avant génération payante. Une réponse JSON peut améliorer un formulaire Twig avec Stimulus sans ajouter un deuxième frontend ni une nouvelle entité Doctrine.

## Configuration locale

Les valeurs versionnées laissent `AI_STORY_ENABLED=0`. Sur le poste de développement, l’activation demandée est dans `.env.local` : `AI_STORY_ENABLED=1`. Modèle de ce pilote : `AI_STORY_MODEL=qwen3:8b`, téléchargé depuis le registre officiel ; Qwen3 4B reste installé. `AI_STORY_URL=http://host.docker.internal:11434` rejoint Ollama existant depuis Docker. Aucun changement de pare-feu ni nouvelle écoute publique effectué. Une installation Symfony sans Docker peut utiliser `http://127.0.0.1:11434`.

Les adresses permises sont HTTP vers localhost, 127.0.0.1, host.docker.internal ou ollama. Le navigateur ne choisit ni l’adresse ni le modèle de texte. Aucun téléchargement automatique, achat ou fallback texte distant. Quatre propositions par utilisateur sur quinze minutes, verrou non bloquant par conteneur PHP pour une rédaction à la fois, deux appels bornés à 55 secondes chacun, réponse limitée à 100 Ko par appel. La session est libérée avant l’inférence. Le verrou n’est pas distribué entre plusieurs conteneurs ; ce pilote n’est pas dimensionné pour un SaaS multi-utilisateur.

Le texte circule uniquement entre l’application et le moteur local. Les photos ne sont pas envoyées au modèle de texte ; la rédaction utilise les descriptions des personnages, puis le moteur vidéo existant utilise les références privées. La mémoire est un extrait des scènes enregistrées de trois épisodes précédents, pas une mémoire narrative complète et validée.

## Comparaison réellement exécutée

Poste et moteur : mêmes Ryzen 7 7800X3D et Ollama 0.24.0 que l’essai précédent. Sujet synthétique identique : « Milo mon chat blanc joueur découvre les objets du quotidien. », trois clips de 10 + 15 + 15 secondes, ambiance Automatique. Les durées ci-dessous comprennent les deux appels du nouveau service, sans rendu FFmpeg parallèle. Aucune mesure de RAM ou de débit multi-utilisateur dans cette comparaison.

| Modèle | Temps observé | Lecture humaine de cette seule proposition |
| --- | --- | --- |
| Qwen3 4B | 57,927 s | Français/anglais et durées structurés, mais succession de couteaux malgré la demande d’une situation simple ; chute peu crédible. |
| Qwen3 8B | 80,453 s | Situation plus simple autour d’une balle, étapes cohérentes ; humour faible, formulation maladroite et identité pas répétée dans chaque prompt. |

Un premier essai 4B de 40,909 s utilisait des consignes antérieures et ne constitue pas une comparaison à instructions égales. Qwen3.5 9B a été essayé au téléchargement : Ollama a refusé son manifeste, exigeant une version plus récente ; aucun essai 9B ni mise à jour d’Ollama effectué. Le téléchargement compatible Qwen3 8B a abouti.

Le 8B est retenu pour le pilote supervisé sur la base limitée de cet exemple, pas comme vainqueur d’une évaluation narrative. Les contrôles structurels ne vérifient pas automatiquement la langue, l’humour, la vraisemblance, la fidélité sémantique ou les répétitions proches. La génération récurrente autonome exige encore une vraie évaluation sur des thèmes variés, une mémoire durable et des règles de reprise et de dépense. Le calcul local évite une facture texte par requête, mais exige RAM/CPU et hébergement adaptés.

## Tester

1. Avec Ollama et le modèle installés, ouvrir le studio ou un épisode à préparer ; écrire une idée, choisir un modèle vidéo et une durée compatible.
2. Demander le scénario : attendre l’aperçu, consulter un prompt puis appliquer les scènes. Vérifier titre, durées, ordre et son conservé ; aucun clip vidéo ne doit partir.
3. Modifier l’idée ou une scène pendant une demande, ou changer les réglages après l’aperçu : la proposition périmée ne doit pas écraser les modifications.
4. Tester un moteur arrêté : message compréhensible, saisies conservées, rédaction manuelle disponible. Sans JavaScript, le découpage et la préparation manuels restent utilisables.
5. Comparaison synthétique : `docker compose exec -T php php scripts/try-story-local.php qwen3:8b`. Ce script ne télécharge pas le modèle et ne contacte pas un fournisseur vidéo.

Tests automatisés, vérification desktop/mobile et limites exactes : [verification.md](verification.md).

## Suite recommandée

Évaluer et améliorer la rédaction sur plusieurs thèmes avant d’enlever la supervision. Ensuite, stocker une mémoire narrative et un défaut d’ambiance, préparer les futurs épisodes à l’avance et raccorder leur validation au calendrier existant. La récurrence devra distinguer date de production et date de publication, posséder pause et limites, et obtenir un consentement durable précis aux dépenses BYOK avant toute activation.
