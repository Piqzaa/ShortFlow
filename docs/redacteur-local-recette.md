# Remise en route et recette du rédacteur local — 24 octobre 2026

Cette première recette précède le [lot de fiabilité du même jour](redacteur-local-fiabilite.md), qui modifie ensuite les consignes et ajoute des diagnostics. Les observations ci-dessous conservent l'état initial.

## Objectif et installation vérifiée

Remettre le rédacteur existant en fonctionnement sur ce poste, puis évaluer plusieurs sujets sans génération vidéo, publication sociale ni activation de série. Les anciens résultats provenaient d'un autre matériel : ils ne prouvaient pas que ce poste était configuré.

Constat initial : Docker et Ollama arrêtés, aucun modèle Ollama installé, rédaction désactivée, 24 migrations locales manquantes, assets non construits et image PHP 8.4.26. Les dépendances PHP étaient déjà disponibles.

Docker Desktop et Ollama ont été démarrés ; Qwen3 8B a été installé explicitement. Les 24 migrations relues ont été appliquées en dev et test, sans suppression des données dev. Les schémas sont synchronisés. Les dépendances frontend ont été installées et les assets reconstruits. Les images PHP et montage ont été construites, puis le serveur et les quatre workers ont été remis en route avec PHP 8.4.27.

La configuration privée active seulement `AI_STORY_ENABLED=1`, avec `AI_STORY_MODEL=qwen3:8b` et `AI_STORY_URL=http://host.docker.internal:11434`. Cette adresse est accessible depuis Docker. `AI_GENERATION_ENABLED` reste à `0`. Aucune récurrence n'est enregistrée et les files dev sont vides après vérification.

## Composants et modifications

- `.env.local`, ignoré : activation du service texte existant ; aucune clé vidéo ou sociale ajoutée.
- `compose.yaml` : arrêt normal du `story-worker` porté de trois à sept minutes, pour laisser terminer deux appels dont le délai configuré est de 180 secondes chacun. Un arrêt forcé ou une mise en veille reste une interruption possible.
- `scripts/try-story-local.php` : modèle par défaut aligné sur Qwen3 8B ; option `--suite` pour quatre sujets synthétiques. Chaque résultat ou erreur, son entrée et sa durée sont conservés dans un dossier unique ignoré. Le programme continue après un échec individuel et termine avec le code 1 si au moins un cas échoue. Aucun accès à Doctrine, aux clés BYOK ou aux services vidéo.
- `README.md` : installation du moteur et du modèle sur un nouveau poste, configuration, worker dédié, recette et arrêt du worker de rédaction lors d'une mise à jour.
- `scenario-local-supervise.md` : distinction entre les limites du pilote initial et les délais/mémoires actuels.

## Méthode de recette narrative

Matériel observé : Intel Core i5-9300HF, environ 16 Go de RAM, NVIDIA RTX 2060 avec 6 Go de VRAM ; Ollama 0.40.6. Pendant les essais, `ollama ps` indique environ 6 Go alloués, une répartition 30 % CPU / 70 % GPU et un contexte de 4096 tokens. Ces pourcentages décrivent la répartition du modèle rapportée par Ollama, pas une mesure de charge instantanée.

La commande utilise réellement `LocalStoryWriter`, avec ses deux appels et ses validations de structure. Une seule proposition est demandée pour chacun des quatre sujets. La suite PHP tourne en parallèle pendant une partie de la recette : les durées ne constituent pas un benchmark isolé. Le premier cas inclut le chargement initial. Aucun média n'est envoyé au modèle texte.

```powershell
docker compose exec -T php php scripts/try-story-local.php qwen3:8b --suite
```

Résultats synthétiques conservés localement dans `artifacts/story-local/20261024-102147-008245/` :

| Cas | Durée observée | Résultat technique | Lecture humaine |
| --- | --- | --- | --- |
| Milo, premier chargement | 180,047 s | Délai dépassé | Aucun scénario utilisable renvoyé ; l'erreur est explicite |
| Rempotage pédagogique, 3 × 5 s | 94,120 s | Structure acceptée | Invention d'un chat jardinier absent du sujet, plante qui s'étire ; démonstration non fidèle |
| Robot bleu, continuité, 3 × 5 s | 223,517 s | Structure acceptée | Prompts anglais, mais identité réduite à « The robot » à la fin ; reflet autonome et chute difficile à interpréter, sans résolution nette |
| Milo avec mémoire balle/carton | 2054,406 s, exclu des performances | Structure acceptée | Balle et carton évités dans cet exemple ; prompts français malgré la consigne d'anglais, yeux verts inventés et miroir qui se brise sans cause |

La dernière durée inclut une veille Windows confirmée par les événements système : mise en veille à 12 h 30 min 58 s, reprise à 13 h 03 min 49 s, heure de Paris. Elle ne mesure donc pas un temps d'inférence continu. Un délai réseau configuré ne garantit pas un temps mural de bout en bout lorsque le poste ou Docker est suspendu. Aucun réglage de veille du propriétaire n'a été modifié.

Trois réponses structurées sur quatre ne signifient pas trois scénarios publiables. Ce petit échantillon confirme des limites déjà identifiées : suivi du sujet, langue des prompts, répétition de l'identité et plausibilité de la chute. Il ne mesure ni un taux de qualité représentatif ni la capacité à servir plusieurs utilisateurs. La mémoire évite ici deux objets explicitement listés ; cela ne valide pas une détection générale des répétitions.

Après la suite PHP, le modèle a été préchargé par une requête locale sans prompt, puis le premier sujet a été redemandé une seule fois : **48,467 secondes**, réponse structurée acceptée, conservée dans `milo-reprise.json` au même emplacement. Cette durée exclut le préchargement et ne doit pas être comparée directement au premier appel froid. Les prompts sont cette fois en anglais, mais inventent des yeux verts, une tunique noire et un collier en cuir ; la balle disparaît dans une fissure sans véritable résolution. La reprise confirme le fonctionnement technique et les limites de fidélité, sans valider l'autonomie.

## Vérifications et limites

- `npm run check`, `npm run test:ui` : 105 tests réussis ; `npm run build` réussi. Avertissements Vite existants sur ressources publiques et taille du chunk Three.js.
- Contrôles PHP finaux dans une copie Linux isolée, avec l'image de montage PHP 8.4.27 et exclusivement `shortflow_test` : 19 YAML, 83 Twig et conteneur valides, PHPStan niveau 5 sans erreur, CS-Fixer 0/253, **481 tests / 6284 assertions réussis**, 307,404 secondes, 70,50 Mo.
- La première copie de contrôle omettait deux médias de test et utilisait l'image PHP sans moteur de sous-titres : trois échecs et deux avertissements. Les chemins de fixtures et l'image ont été corrigés pour la vérification ; aucune assertion métier n'a été affaiblie. La suite finale ci-dessus est réussie. Le conteneur temporaire a été supprimé ensuite.
- Configuration Compose, syntaxe PHP du script et `git diff --check` valides.
- Navigateur : site local accessible et redirection du studio privé vers la connexion vérifiée. Pas de connexion au compte du propriétaire ni de scénario soumis depuis son interface ; le service texte réel est testé par le script, le parcours Messenger/formulaire par la suite fonctionnelle simulée.
- Aucune vidéo payante, publication, série réelle activée, nouvelle API texte distante, modification des consignes du modèle, commit ou push.

## Flux Symfony et utilisation

Dans l'application : contrôleur privé et CSRF → `StoryProposals` enregistre la demande Doctrine → message `WriteStoryProposal` → `story-worker` → `LocalStoryWriter` et Ollama → résultat conservé → aperçu Stimulus → application volontaire des scènes. La génération vidéo est une confirmation distincte. Le script de recette appelle directement le service de rédaction et ne teste donc pas à lui seul le parcours HTTP ou le consommateur.

À comprendre : Git transfère l'intégration Symfony et les migrations, pas le moteur installé sur Windows, les poids du modèle, les secrets locaux ni l'état de la base. Un consommateur Messenger disponible ne prouve pas que son moteur externe fonctionne. Enfin, une validation de JSON vérifie la forme de la réponse ; elle ne vérifie ni la qualité narrative ni la fidélité au sujet.

Docker, Ollama et le modèle installé sont nécessaires pour utiliser « Proposer mon scénario ». La rédaction manuelle reste disponible. La relecture humaine demeure nécessaire ; la prochaine amélioration concerne le respect du sujet et de la langue, puis une nouvelle évaluation avant toute autonomie éditoriale.
