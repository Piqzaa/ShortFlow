# Téléchargement, catalogue HD/4K et connexion Fal par navigateur

Lot du 19 octobre 2026. Objectif : retrouver ses fichiers facilement et choisir un modèle, sa résolution, son coût et son accès fournisseur sans perdre une préparation existante.

## Ce qui est disponible localement

- Le menu des vidéos se ferme au clic extérieur et avec Échap (retour du focus au bouton). Le téléchargement figure dans ce menu et sur la fiche vidéo, pour les fichiers importés comme générés.
- Le catalogue contient 21 configurations fournisseur/modèle : Fal, Replicate et désormais LTX direct. Une configuration fixe ses paramètres et son tarif : un modèle disponible en plusieurs résolutions produit plusieurs choix explicites, sans modifier les anciennes générations.
- Filtres 720p, 1080p et 4K ; tri des estimations pour une durée commune choisie. Une durée incompatible ne reçoit aucun faux prix et reste en fin de liste. Tous les modèles ne proposent pas toutes les résolutions.
- Fal : nouvelles variantes Pika 2.2 Full HD, PixVerse V6 Full HD et Grok Imagine 1.5 Full HD. LTX : 2.3 Fast et 2.5 Fast, avec configurations 720p, 1080p et 4K, audio et durées compatibles explicites.
- Une clé est nécessaire par fournisseur, pas par modèle. La clé Fal existante couvre les modèles Fal du catalogue sous réserve de crédits et de droits fournisseur. Replicate et LTX demandent leur propre clé.
- Fal propose également un parcours de connexion navigateur OAuth pour son MCP officiel. Il est indépendant de la connexion ChatGPT ; aucune autorisation ChatGPT n'est transférée. La clé API existante est conservée et le mode d'accès est choisi explicitement dans le Studio. Replicate et LTX utilisent leur API par clé dans ce lot.

## Limites réelles

Seul le premier essai Fal par clé API a été effectué en réel dans le lot précédent. Les nouvelles variantes HD/4K, LTX et OAuth/MCP sont implémentés et vérifiés avec HTTP simulé. Après le signalement du propriétaire, la déclaration publique OAuth a été corrigée et vérifiée réellement : Fal répond 201 à l'inscription du client et 200 à l'obtention d'un code d'autorisation. La première connexion OAuth réelle est désormais confirmée par le propriétaire ; les générations MCP payantes restent à tester séparément. Voir le correctif PKCE dans ia-favoris-oauth.md. Une réponse inconnue bloque l'opération. Aucun essai payant supplémentaire n'a été lancé ; `AI_GENERATION_ENABLED=0` reste en place.

Fal choisit un compte MCP actif commun aux applications connectées. VydFlow mémorise son identifiant, l'affiche au récapitulatif et le vérifie immédiatement avant l'envoi. Une modification détectée bloque l'envoi. Cette vérification et le POST ne sont pas atomiques chez Fal : éviter de changer le compte actif pendant une génération en attente. Les demandes déjà envoyées conservent leur reçu d'origine pour le suivi. Aucun repli automatique vers la clé API ou un autre compte.

Les vidéos restent limitées par l'import privé existant (100 Mo), y compris en 4K ; la durée et le poids réels dépendent du fournisseur. Choisir 4K demande ce format au fournisseur : les tests simulés ne prouvent pas la qualité d'une sortie réelle. Aucun agrandissement artificiel n'a été ajouté.

## Fichiers et responsabilités

- `VideoController` réutilise le contrôle de propriété et le stockage privé pour une réponse `BinaryFileResponse` en pièce jointe ; lecture et téléchargement conservent les requêtes Range, les réponses HEAD et `no-store`. Le nom téléchargé est nettoyé.
- `options_controller.js` améliore les menus HTML natifs, sans empêcher Renommer/Supprimer/Télécharger.
- `AiCatalog`, `AiIdeaType` et `creation_controller.js` portent respectivement les capacités/prix, la validation serveur et les filtres/estimations immédiats. Le serveur reste la référence même sans JavaScript.
- `LtxVideoApi` adapte les demandes et le suivi asynchrones LTX. `AiVideoDownload` copie le résultat borné dans le stockage privé, sans transmettre de clé au CDN ni suivre de redirection.
- `FalOAuth` gère la découverte vérifiée sur des hôtes fixes, le code d'autorisation, les jetons chiffrés et leur renouvellement verrouillé par propriétaire. Seul le code public apparaît dans la page ; le code secret d'appareil reste chiffré en session. Déconnecter retire l'accès local ; la révocation fournisseur se fait chez Fal.
- `FalMcpClient` limite les outils et le transport JSON-RPC/SSE à l'hôte Fal officiel, avec délais, taille bornée et erreurs neutres. Aucun serveur MCP arbitraire ni interprétation de consignes distantes.
- `FalMcpVideoApi` réutilise les paramètres natifs Fal et chiffre le reçu de suivi. Les URL du reçu ne deviennent jamais des cibles HTTP directes.
- `AiGeneration`, `AiKeys` et `AiGenerations` conservent le mode d'accès et le compte confirmé jusqu'au worker Messenger. La migration additive `Version20261019030000` préserve les anciens travaux en mode API.

## Correctif OAuth et lisibilité du choix des modèles

Le premier code déclarait un tableau `response_types` vide et omettait `redirect_uris`. Fal refusait ces métadonnées avec HTTP 400 (`invalid_client_metadata`), avant toute autorisation de compte. La déclaration contient maintenant `response_types: [code]` et l'URL absolue de la page de connexion VydFlow ; le flux d'appareil continue à utiliser son code, sans redirection ni nouvel échange de code OAuth. Les erreurs de découverte, déclaration et obtention du code donnent l'étape et le statut, jamais le corps fournisseur ni ses secrets.

Résolution affichée dans le sélecteur, les cartes, l'estimation, le récapitulatif et l'historique. Les anciens libellés Pro/Q1/Standard étaient des noms de gamme : Kling 2.5 Pro et Kling 3 Pro sont présentés en 1080p, Vidu Q1 en 1080p, Hailuo 02 Standard en 768p (filtre dédié). Ceci précise l'affichage sans changer les endpoints, les prix ni les paramètres des travaux existants. Références : [Kling 3](https://fal.ai/kling-3), [Vidu Q1](https://fal.ai/models/fal-ai/vidu/q1/text-to-video), [Hailuo Standard](https://fal.ai/models/fal-ai/minimax/hailuo-02/standard/text-to-video) ; Kling 2.5 a aussi été vérifié en réel en 1080 × 1920 dans notre essai autorisé.

Les cartes sans fournisseur connecté ont une présentation grisée et un bouton Connecter. Leur sélection montre une invitation ciblée qui conserve le modèle et l'idée, même sans prompt ou durée terminés. Dans Symfony, le groupe de validation `connection` autorise cette préparation partielle mais conserve CSRF, types/choix et limites de texte ; le groupe `Default` et les validations métier complètes restent obligatoires avant toute préparation de génération. Une connexion enregistrée ne garantit ni des crédits ni les droits sur un modèle : les contrôles serveur restent en place.

« Recommandé pour débuter » est un conseil éditorial limité au parcours Kling 2.5 testé en réel, pas un classement de qualité. « Le moins cher ici » est calculé parmi les résultats filtrés compatibles avec la durée de comparaison, toutes configurations affichées confondues. Il peut y avoir des ex æquo ; aucune durée incompatible ne reçoit ce badge. Le prix dépend de l'audio et de la résolution, qui restent visibles.

## Flux Symfony à comprendre

Le contrôleur reçoit le formulaire, Symfony valide le modèle/durée/format/mode, puis Doctrine enregistre une préparation. Le récapitulatif demande le consentement au coût. Après confirmation, Messenger effectue les contrôles puis soumet une seule demande et consulte son reçu jusqu'au résultat. Une erreur ambiguë après soumission demande une vérification ; elle ne relance jamais automatiquement une génération facturable.

Le branchement API/MCP appartient aux services injectés, pas aux templates. Le chiffrement protège les accès stockés ; l'autorisation propriétaire protège chaque ressource. Le contrôleur et Twig ne doivent recevoir ni jeton ni clé déchiffrée.

## Comment tester

1. Dans Vidéos, ouvrir les trois points, cliquer ailleurs, puis vérifier Échap. Télécharger et contrôler le fichier obtenu.
2. Dans Studio, ouvrir le catalogue, filtrer 4K et comparer 6 secondes : LTX 2.3 Fast à 1,44 USD, LTX 2.5 Fast à 1,80 USD estimés, hors taxes/conversion. Choisir un modèle LTX : 5 secondes doit être effacé car incompatible.
3. Dans Connexions → IA, retrouver séparément Fal, Replicate et LTX. La clé Fal reste enregistrée après une connexion navigateur ; dans Studio, choisir le mode souhaité.
4. Pour valider OAuth réellement, le propriétaire ouvre Connecter mon compte Fal, autorise le code sur le site Fal puis revient vérifier. Cette étape ne génère pas de vidéo. Tout essai de génération reste soumis à une autorisation et une estimation distinctes.

Les tests fonctionnels utilisent exclusivement `shortflow_test`, migrations appliquées. Les exécuter dans un seul processus : leurs jeux de données se nettoient mutuellement si plusieurs suites tournent en parallèle. Les mocks couvrent autorisation, chiffrement, renouvellement, révocation, compte actif modifié, reçu d'origine, import privé et absence de double soumission. Les tests unitaires vérifient les contrats natifs et le transport MCP ; les tests frontend vérifient menu et comparaison.

## Sources officielles vérifiées le 19 octobre

- [Fal MCP, OAuth et compte actif](https://fal.ai/docs/documentation/setting-up/mcp).
- [Pika 2.2](https://fal.ai/models/fal-ai/pika/v2.2/text-to-video), [PixVerse V6](https://fal.ai/pixverse-v6), [Grok Imagine 1.5](https://fal.ai/models/xai/grok-imagine-video/v1.5/text-to-video).
- [LTX : tarifs](https://docs.ltx.io/pricing/), [modèles 2.3](https://docs.ltx.io/models/ltx-2-3), [2.5](https://docs.ltx.io/models/ltx-2-5), [suivi asynchrone](https://docs.ltx.io/async-jobs).

L'inventaire général des autres prestataires reste dans [fournisseurs-video-ia.md](fournisseurs-video-ia.md). Une présence dans cet inventaire ne signifie pas que son connecteur VydFlow est livré.

## Remplacement du flux d'appareil et favoris

Le flux d'appareil décrit historiquement ci-dessus a été remplacé après le refus d'activation dans le navigateur : VydFlow utilise désormais OAuth navigateur avec PKCE S256 et retour automatique. Catalogue regroupé en 15 cartes / 21 configurations, favoris persistants par compte et accès direct aux modèles 4K : voir [ia-favoris-oauth.md](ia-favoris-oauth.md). La première connexion réelle est confirmée par le propriétaire après le correctif PKCE et l'acceptation des identifiants Fal opaques. Aucune nouvelle génération payante.
