# Annexe : catalogue IA audité le 24 octobre 2026

Cette matrice décrit les **55 choix locaux**, répartis en **35 groupes d’exécution**, pas 55 modèles indépendants. Les capacités ci-dessous sont celles câblées dans VydFlow. « Premier cadre » ne signifie pas référence libre de personnage.

Les 27 endpoints Fal distincts ont été interrogés en lecture seule via `get_model_schema` et `get_pricing`. Les schémas Fal ont confirmé les champs de début/fin/personnage listés ; ils ne prouvent ni une qualité visuelle ni une disponibilité future. Les pages primaires LTX ont été consultées ; les pages Replicate consultables ici ne donnaient pas toutes leurs contraintes conditionnelles : leur validation complète reste à terminer, sans extrapoler depuis Fal.

| Identifiant durable | Fournisseur | Rôle actuellement câblé | Résolution | Durées locales (s) | Formats locaux | Image de fin |
|---|---|---|---|---|---|---|
| `vidu-q1-subject` | fal | personnage | 1080p | 5 | 9:16, 16:9, 1:1 | non |
| `kling-25` | fal | texte | 1080p | 5, 10 | 9:16, 16:9, 1:1 | non |
| `wan-25` | fal | texte | 720p | 5, 10 | 9:16, 16:9, 1:1 | non |
| `kling-3` | fal | texte | 1080p | 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15 | 9:16, 16:9, 1:1 | non |
| `wan-25-replicate` | replicate | texte | 720p | 5, 10 | 9:16, 16:9 | non |
| `veo-31-fast-replicate` | replicate | texte | 720p | 4, 6, 8 | 9:16, 16:9 | non |
| `pika-22` | fal | texte | 720p | 5 | 9:16, 16:9, 1:1 | non |
| `vidu-q1` | fal | texte | 1080p | 5 | 9:16, 16:9, 1:1 | non |
| `grok-video` | fal | texte | 720p | 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15 | 9:16, 16:9, 1:1 | non |
| `hailuo-02` | fal | texte | 768p | 6, 10 | 16:9 | non |
| `pixverse-56` | fal | texte | 720p | 5, 8, 10 | 9:16, 16:9, 1:1 | non |
| `pixverse-6` | fal | texte | 720p | 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15 | 9:16, 16:9, 1:1 | non |
| `seedance-25` | fal | texte | 720p | 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30 | 9:16, 16:9 | non |
| `pika-22-1080` | fal | texte | 1080p | 5 | 9:16, 16:9, 1:1 | non |
| `pixverse-6-1080` | fal | texte | 1080p | 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15 | 9:16, 16:9, 1:1 | non |
| `grok-15-1080` | fal | texte | 1080p | 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15 | 9:16, 16:9, 1:1 | non |
| `ltx-23-fast-720` | ltx | texte | 720p | 6, 8, 10 | 9:16, 16:9 | non |
| `ltx-23-fast-1080` | ltx | texte | 1080p | 6, 8, 10, 12, 14, 16, 18, 20 | 9:16, 16:9 | non |
| `ltx-23-fast-4k` | ltx | texte | 4k | 6, 8, 10 | 9:16, 16:9 | non |
| `ltx-25-fast-720` | ltx | texte | 720p | 6, 8, 10, 12, 14, 16, 18, 20 | 9:16, 16:9, 1:1 | non |
| `ltx-25-fast-1080` | ltx | texte | 1080p | 6, 8, 10, 12, 14, 16, 18, 20 | 9:16, 16:9, 1:1 | non |
| `ltx-25-fast-4k` | ltx | texte | 4k | 6, 8, 10 | 9:16, 16:9, 1:1 | non |
| `kling-25-image` | fal | premier cadre | 1080p | 5, 10 | auto, 9:16, 16:9, 1:1 | facultative |
| `kling-3-image` | fal | premier cadre | 1080p | 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15 | auto, 9:16, 16:9, 1:1 | facultative |
| `wan-25-image-720p` | fal | premier cadre | 720p | 5, 10 | auto, 9:16, 16:9, 1:1 | non |
| `wan-25-image-1080p` | fal | premier cadre | 1080p | 5, 10 | auto, 9:16, 16:9, 1:1 | non |
| `pika-22-image-720p` | fal | premier cadre | 720p | 5 | auto, 9:16, 16:9, 1:1 | non |
| `pika-22-image-1080p` | fal | premier cadre | 1080p | 5 | auto, 9:16, 16:9, 1:1 | non |
| `pika-frames-720p` | fal | premier cadre | 720p | 5, 10, 15, 20, 25 | auto, 9:16, 16:9, 1:1 | obligatoire |
| `pika-frames-1080p` | fal | premier cadre | 1080p | 5, 10, 15, 20, 25 | auto, 9:16, 16:9, 1:1 | obligatoire |
| `vidu-q1-image` | fal | premier cadre | 1080p | 5 | auto, 9:16, 16:9, 1:1 | non |
| `vidu-q1-frames` | fal | premier cadre | 1080p | 4 | auto, 9:16, 16:9, 1:1 | obligatoire |
| `grok-image-720` | fal | premier cadre | 720p | 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15 | auto, 9:16, 16:9, 1:1 | non |
| `grok-15-image-720p` | fal | premier cadre | 720p | 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15 | auto, 9:16, 16:9, 1:1 | non |
| `grok-15-image-1080p` | fal | premier cadre | 1080p | 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15 | auto, 9:16, 16:9, 1:1 | non |
| `hailuo-02-image` | fal | premier cadre | 768p | 6, 10 | auto, 9:16, 16:9, 1:1 | facultative |
| `pixverse-56-image-720p` | fal | premier cadre | 720p | 5, 8, 10 | auto, 9:16, 16:9, 1:1 | non |
| `pixverse-56-image-1080p` | fal | premier cadre | 1080p | 5, 8 | auto, 9:16, 16:9, 1:1 | non |
| `pixverse-56-frames-720p` | fal | premier cadre | 720p | 5, 8, 10 | 9:16, 16:9, 1:1 | obligatoire |
| `pixverse-56-frames-1080p` | fal | premier cadre | 1080p | 5, 8 | 9:16, 16:9, 1:1 | obligatoire |
| `pixverse-6-image-720p` | fal | premier cadre | 720p | 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15 | auto, 9:16, 16:9, 1:1 | non |
| `pixverse-6-image-1080p` | fal | premier cadre | 1080p | 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15 | auto, 9:16, 16:9, 1:1 | non |
| `pixverse-6-frames-720p` | fal | premier cadre | 720p | 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15 | 9:16, 16:9, 1:1 | obligatoire |
| `pixverse-6-frames-1080p` | fal | premier cadre | 1080p | 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15 | 9:16, 16:9, 1:1 | obligatoire |
| `seedance-25-image` | fal | premier cadre | 720p | 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30 | auto, 9:16, 16:9, 1:1 | facultative |
| `ltx-23-fast-720p-image` | ltx | premier cadre | 720p | 6, 8, 10, 12, 14, 16, 18, 20 | 9:16, 16:9 | facultative |
| `ltx-23-fast-1080p-image` | ltx | premier cadre | 1080p | 6, 8, 10, 12, 14, 16, 18, 20 | 9:16, 16:9 | facultative |
| `ltx-23-fast-4k-image` | ltx | premier cadre | 4k | 6, 8, 10 | 9:16, 16:9 | facultative |
| `ltx-25-fast-720p-image` | ltx | premier cadre | 720p | 6, 8, 10, 12, 14, 16, 18, 20 | 9:16, 16:9, 1:1 | facultative |
| `ltx-25-fast-1080p-image` | ltx | premier cadre | 1080p | 6, 8, 10, 12, 14, 16, 18, 20 | 9:16, 16:9, 1:1 | facultative |
| `ltx-25-fast-4k-image` | ltx | premier cadre | 4k | 6, 8, 10 | 9:16, 16:9, 1:1 | facultative |
| `wan-image-replicate-720p` | replicate | premier cadre | 720p | 5, 10 | auto, 9:16, 16:9, 1:1 | non |
| `wan-image-replicate-1080p` | replicate | premier cadre | 1080p | 5, 10 | auto, 9:16, 16:9, 1:1 | non |
| `veo-image-replicate-720p` | replicate | premier cadre | 720p | 4, 6, 8 | 9:16, 16:9 | facultative |
| `veo-image-replicate-1080p` | replicate | premier cadre | 1080p | 4, 6, 8 | 9:16, 16:9 | facultative |

## Capacités fournisseurs repérées mais non câblées

| Endpoint ou famille | Schéma/documentation vérifié | Ce que cela permet réellement | Travail restant |
|---|---|---|---|
| Kling 3 Pro image | `elements`, `multi_prompt`, `shot_type` | Personnages/objets en plus du premier cadre ; plusieurs plans dans une génération | Séparer personnages et cadres, conserver les règles exactes de l’endpoint, adapter prompts et devis |
| Kling 3 Pro texte | `multi_prompt`, `shot_type` | Plusieurs consignes de plans dans une sortie unique | Un mode multi-plan natif ne doit pas être appelé plan continu |
| PixVerse V6 | `generate_multi_clip_switch` | Le modèle peut changer de plans dans un seul appel | Actuellement envoyé à false ; choix explicite du mode et vérification des contraintes |
| Vidu Q1 référence | Jusqu’à 7 images d’après le schéma | Références de sujets, sans premier cadre imposé | L’interface ne transmet qu’une image aujourd’hui ; correspondance image/personnage à définir |
| LTX 2.5 | Multi-shot natif décrit par le fournisseur | Plusieurs plans cohérents dans une seule génération | Ne garantit pas l’absence de coupe ; rédiger une seule consigne adaptée au mode choisi |
| LTX 2.3 Pro | Endpoint `/v2/extend`, vidéo en entrée | Extension avec contexte temporel et audio | Nouvel adaptateur/opération et devis incluant le contexte ; ce modèle Pro n’est pas dans notre catalogue |

## Écarts et précautions

- **Pika 2.2** : les trois endpoints présents annoncent leur dépréciation au 29 octobre 2026. Les six variantes locales sont concernées. Les garder pour lire l’historique, les retirer des nouveaux choix/recommandations avant la date et demander un choix explicite de remplacement.
- **LTX 2.3 Fast texte 720p** : le catalogue limite à 6/8/10 s, alors que l’adaptateur fixe 25 FPS et la matrice officielle autorise également 12/14/16/18/20 s à cette résolution. La variante image 720p expose déjà ces durées. C’est une restriction incohérente, pas une erreur fournisseur.
- **Durées Pika/Vidu** : certaines limites locales sont plus étroites que le schéma public. Une restriction volontaire est acceptable si expliquée ; ne pas présenter le sous-ensemble comme limite absolue de toute la famille.
- **Veo 3.1 Fast Replicate** : ne pas activer des références de sujet sur la seule foi d’un README. Le schéma de Veo 3.1 non-Fast expose des conditions propres au format et à la durée ; celles-ci ne doivent pas être copiées au Fast sans vérification exacte.
- **Formats auto** : le modèle peut suivre l’aspect de l’image, mais notre assemblage ramène ensuite à carré/paysage/portrait standard. Une référence 4:5 n’est donc pas conservée telle quelle à l’export actuel.
- **Prix** : l’API de prix renvoie selon les endpoints secondes, vidéos, crédits ou milliers de tokens. Elle expose parfois un tarif de base différent de la configuration locale (résolution/audio/frais d’image). On ne doit pas remplacer automatiquement les estimations par cette valeur brute. Cet audit ne certifie pas les 55 tarifs conditionnels.

## Sources primaires

- [Kling 3 Pro image : paramètres et éléments](https://fal.ai/models/fal-ai/kling-video/v3/pro/image-to-video/api)
- [Vidu Q1 référence : schéma](https://fal.ai/models/fal-ai/vidu/q1/reference-to-video/api)
- [PixVerse V6 : schéma](https://fal.ai/models/fal-ai/pixverse/v6/text-to-video/api)
- [Pika texte : avertissement de dépréciation](https://fal.ai/models/fal-ai/pika/v2.2/text-to-video)
- [Pika image : avertissement de dépréciation](https://fal.ai/models/fal-ai/pika/v2.2/image-to-video)
- [Pikaframes : avertissement de dépréciation](https://fal.ai/models/fal-ai/pika/v2.2/pikaframes)
- [LTX 2.3 : résolutions, durées et FPS](https://docs.ltx.io/models/ltx-2-3)
- [LTX 2.5 : multi-shot et durées](https://docs.ltx.io/models/ltx-2-5)
- [LTX : extension vidéo](https://docs.ltx.io/api-documentation/api-reference/async-video-generation/submit-extend)
- [Veo Fast sur Replicate : documentation](https://replicate.com/google/veo-3.1-fast/readme)
- [Veo non-Fast : exemple de contraintes conditionnelles distinctes](https://replicate.com/google/veo-3.1/api/schema)

Aucune soumission de génération, aucune récupération de média utilisateur et aucune modification de compte effectuées pour établir cette matrice.

