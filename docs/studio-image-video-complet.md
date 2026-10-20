# Studio image vers vidéo : modèles, début/fin et cadrage

Extension locale autorisée le 20 octobre 2026 après le premier lot Kling. L’utilisateur lancera ensuite son essai réel ; aucune génération payante n’est exécutée pendant ce développement. Le premier lot a été enregistré dans les commits `28ba9e5` et `87cce93` avant cette extension.

## Parcours

Studio → Animer une image → choisir le format et l’ajustement → importer l’image de départ → cocher éventuellement Ajouter une image de fin → choisir modèle, résolution et durée → récapitulatif avec les images réellement préparées → confirmation habituelle. Les imports restent privés et ne lancent pas de génération. Les formats sont visibles avant l’import. Deux aperçus côte à côte sur ordinateur, une colonne sur téléphone.

La case de fin filtre les modèles incompatibles. Choisir un parcours qui exige deux images active cette case ; la décocher permet de revenir à un modèle à image unique. Une image de fin importée reste en session si l’option est décochée, mais ne sera pas attachée à une génération à image unique. Aucune image n’est ignorée silencieusement dans un job confirmé.

## Couverture vérifiée

54 configurations vidéo au total, regroupées en 34 choix de modèle/parcours. Parmi elles, 33 configurations image et 19 acceptant une image de fin. Les groupes séparent désormais explicitement texte et image, même si LTX ou Replicate emploient le même identifiant fournisseur.

| Famille / fournisseur | Résolution exposée en image | Durée exposée | Image de fin |
|---|---|---|---|
| Kling 2.5 Turbo Pro / Fal | 1080p | 5, 10 s | Facultative |
| Kling 3.0 Pro / Fal | 1080p, audio | 3–15 s | Facultative |
| Wan 2.5 / Fal | 720p, 1080p | 5, 10 s | Non |
| Pika 2.2 / Fal | 720p, 1080p | Image seule : 5 s ; Pikaframes : 5/10/15/20/25 s | Pikaframes exige début + fin |
| Vidu Q1 / Fal | 1080p | Image seule : 5 s ; début + fin : 4 s | Parcours dédié |
| Grok Imagine Video / Fal | 720p, audio | 1–15 s | Non |
| Grok Imagine 1.5 / Fal | 720p, 1080p, audio | 1–15 s | Non |
| Hailuo 02 Standard / Fal | 768p | 6, 10 s | Facultative |
| PixVerse 5.6 / Fal | 720p, 1080p | 720p : 5/8/10 s ; 1080p : 5/8 s | Parcours Transition dédié |
| PixVerse V6 / Fal | 720p, 1080p, audio | 1–15 s | Parcours Transition dédié |
| Seedance 2.5 / Fal | 720p, audio | 4–30 s | Facultative |
| LTX 2.3 Fast / LTX | 720p, 1080p, 4K, audio | 720/1080 : 6–20 s, pairs ; 4K : 6/8/10 s | Facultative |
| LTX 2.5 Fast / LTX | 720p, 1080p, 4K, audio | 720/1080 : 6–20 s, pairs ; 4K : 6/8/10 s | Facultative |
| Wan 2.5 / Replicate | 720p, 1080p, audio | 5, 10 s | Non |
| Veo 3.1 Fast / Replicate | 720p, 1080p, audio | 4, 6, 8 s | Facultative |

Cette couverture porte sur les familles déjà proposées dans VydFlow, pas sur tous les produits de tous les fournisseurs. Les configurations exposent des sous-ensembles vérifiés des possibilités natives : Pika image seule reste à 5 s, Pikaframes utilise ici exactement deux images, et les réglages avancés (plus de keyframes, voix référencées, mouvement de caméra natif, etc.) restent distincts.

## Format : aperçu et octets cohérents

- Vertical 9:16, paysage 16:9 et carré 1:1 selon le modèle. Format d’origine proposé lorsque le contrat ne requiert pas une résolution ou un ratio explicites.
- **Recadrage centré** : l’image remplit le cadre, les bords excédentaires sont coupés. **Image entière** : des bandes noires complètent le cadre si les proportions diffèrent.
- Les aperçus Stimulus utilisent le même ratio et le même ajustement que le réencodage FFmpeg serveur. Le récapitulatif affiche les octets préparés via une route privée, pas seulement un masque CSS.
- En format d’origine, le début reste inchangé ; la fin est ajustée aux dimensions du début. Les originaux privés ne sont jamais écrasés.
- Le cadrage est enregistré avec le job et repris avec l’idée. Les adaptateurs transmettent seulement les paramètres documentés : aucun aspect_ratio inventé pour Kling/Pika ; Seedance reçoit toujours auto avec l’image déjà cadrée ; LTX reçoit sa résolution explicite et Replicate ses champs propres.

## Fichiers et rôles

- `AiCatalog` : identifiants durables, capacités d’image de fin, type de durée, champs fournisseur et tarification datée par configuration. Les anciens IDs restent liés à leurs contrats précédents.
- `AiIdeaType` : champs de fin et d’ajustement, validation croisée avec le modèle, formulaire inachevé toujours sauvegardable. `AiReferenceType` : libellé adapté au début ou à la fin, CSRF de chaque formulaire nommé.
- `AiStudioController` : imports nommés séparés, conservation des réglages compatibles, contrôle propriétaire sur les deux références, prévisualisation JPEG privée et préparation/reprise du job.
- `AiReferenceStorage` : transformation bornée par FFmpeg, sans shell, métadonnées ni fichier public ; JPEG transmis inline uniquement après confirmation. La route d’aperçu applique les mêmes transformations sans stocker de copies inutiles.
- `AiGeneration` : image de fin et ajustement immuables après préparation. Migration additive `Version20261020020000`, champs nuls/par défaut pour préserver les anciennes générations ; retour arrière bloqué si les nouveaux champs sont utilisés.
- `AiGenerations` : refus d’un job sans fin requise, d’une fin incompatible ou d’un fichier manquant avant envoi.
- `FalVideoApi` : champs image_url, start_image_url, tail_image_url, end_image_url, first_image_url ou tableau image_urls/transitions selon le schéma. Le même constructeur est utilisé par `FalMcpVideoApi`, sans bascule de compte.
- `ReplicateVideoApi` : image et last_frame avec le bon modèle/résolution. `LtxVideoApi` : image_uri/last_frame_uri et routes v2/image-to-video pour création et suivi.
- `creation_controller.js` : compatibilité des modèles, deux imports concurrents, aperçu de cadrage et estimation avec frais d’entrée. `image_reference_controller.js` distingue les emplacements début/fin et ignore les réponses tardives.
- `_reference_input.html.twig`, Studio/récapitulatif, carte modèle et CSS : composant d’import commun, deux aperçus et badges de capacité.

À comprendre en Symfony : les formulaires nommés isolent deux imports du même FormType sans collision d’identifiants HTML ou de données. Le service de stockage contrôle les fichiers, l’entité garde la configuration confirmée, et chaque adaptateur traduit cette configuration vers un contrat fournisseur précis. Le contrôleur de navigateur n’est jamais l’autorité de sécurité ou de facturation.

## Prix et limites

Les prix de base du catalogue Fal ne suffisent pas toujours : Kling 3 dépend de l’audio, Wan de la résolution, PixVerse 5.6 de la durée, Pika distingue image seule et Pikaframes. Grok ajoute un frais fixe d’entrée (0,002 USD pour l’ancien, 0,01 USD pour 1.5), inclus avant arrondi au centime dans l’estimation et les tris. Les valeurs sont datées du 20/10/2026, indicatives hors taxes/conversion ; Seedance reste estimé à partir de sa facturation aux tokens. L’activation globale payante reste désactivée après les essais précédents.

Les limites privées précédentes restent valides : 10 Mo à l’import, 300–4 096 px par côté, rapport 2:5 à 5:2 ; image conservée normalisée à 2 048 px par côté et 2 Mo ; quota de références de 100 Mo par propriétaire. Le cadrage envoyé est limité à 2 Mo par image. Une entrée 1080p peut être utilisée par un modèle qui produit nativement de la 4K ; cela ne restitue pas les détails absents de l’original. Gestion/suppression des anciennes références et essais réels de chaque fournisseur restent à compléter avant ouverture large.

## Comment tester

1. Choisir Kling 3 Image, importer début/fin de proportions différentes. Choisir 9:16 puis 16:9 ; tester Recadrage centré et Image entière. Le cadre des deux aperçus doit suivre.
2. Vérifier le récapitulatif, puis Réutiliser cette idée : références, format et ajustement sont conservés. Aucune demande fournisseur avant confirmation.
3. Cocher/décocher la fin ; vérifier les modèles filtrés et les parcours obligatoires Pika/Vidu/PixVerse. Vérifier LTX Image 4K et son invitation à connecter LTX si absent.
4. Rechercher un modèle, comparer les coûts, vérifier favori et API/MCP. Les coûts d’entrée Grok doivent être inclus.
5. Avec un autre compte, les deux images et l’aperçu cadré restent inaccessibles. Fausse référence ou fin manquante : message de validation, pas d’erreur 500 ni d’appel payant.
6. Pour l’essai réel choisi par le propriétaire, confirmer séparément modèle, durée, résolution, image(s), compte facturé et estimation. Ne pas relancer automatiquement en cas de résultat incertain.

## Sources officielles vérifiées le 20/10/2026

Schémas Fal récupérés avec `get_model_schema`, conservés dans `tests/Fixtures/fal-image-contracts.json`. Tarifs vérifiés avec `get_pricing` puis les pages exactes des modèles pour les conditions résolution/audio/durée.

- [Kling 3 Image](https://fal.ai/models/fal-ai/kling-video/v3/pro/image-to-video/api), [Pikaframes](https://fal.ai/models/fal-ai/pika/v2.2/pikaframes), [Vidu début/fin](https://fal.ai/models/fal-ai/vidu/q1/start-end-to-video/api).
- [Grok 1.5 Image](https://fal.ai/models/xai/grok-imagine-video/v1.5/image-to-video), [PixVerse V6 Transition](https://fal.ai/models/fal-ai/pixverse/v6/transition/api), [Seedance 2.5 Image](https://fal.ai/models/bytedance/seedance-2.5/image-to-video/api).
- [LTX image asynchrone](https://docs.ltx.io/api-documentation/api-reference/async-video-generation/submit-image-to-video), [capacités LTX](https://docs.ltx.io/models), [prix LTX](https://docs.ltx.io/pricing/), [formats inline LTX](https://docs.ltx.io/input-formats).
- [Wan Replicate](https://replicate.com/wan-video/wan-2.5-i2v/api/schema), [Veo Replicate](https://replicate.com/google/veo-3.1-fast/api/schema), tarifs lus dans leur section Pricing publique via navigateur.
