# Fournisseurs vidéo IA — inventaire du 19 octobre 2026

Le propriétaire demande un catalogue large, incluant les offres connues et économiques, avec connexion navigateur/MCP ou clé API selon les possibilités réelles. Inventaire de candidats, pas liste de connecteurs disponibles dans VydFlow ; aucune nouvelle génération payante pendant cette étude. Le premier essai Fal a réussi, voir [ia-premier-essai.md](ia-premier-essai.md).

## Comment lire cet inventaire

Un modèle (Kling, Veo, Wan…) et un fournisseur (Fal, Google…) sont distincts. Plusieurs fournisseurs peuvent vendre le même modèle avec des prix, versions, paramètres et conditions différents. Une connexion ne rend pas automatiquement tous leurs modèles compatibles avec VydFlow.

MCP est un protocole d'appel d'outils ; OAuth est un mécanisme d'autorisation. Un MCP peut demander une clé API. Une API peut aussi avoir OAuth sans MCP. « MCP non vérifié » signifie que cette étude n'a pas confirmé un serveur officiel exécutant des générations vidéo ; ce n'est pas une preuve d'absence. Une intégration communautaire ne suffit pas à promettre une connexion officielle sans clé.

Cet inventaire couvre les principales familles pertinentes pour VydFlow et plusieurs options économiques ; il n'est pas exhaustif de toutes les sociétés et revendeurs du marché. Les accès et tarifs doivent être revérifiés avant chaque intégration.

## Plateformes proposant plusieurs modèles

| Fournisseur et source officielle | Intérêt | API / clé | MCP et authentification documentée |
| --- | --- | --- | --- |
| [Fal](https://fal.ai/docs/documentation/setting-up/mcp/other-clients) | Catalogue large : Kling, Wan, Seedance, Pika, LTX et autres familles selon endpoints | Oui ; déjà intégrée dans VydFlow, premier essai réel réussi | Officiel distant, connexion navigateur OAuth sans copie de clé ; alternative API conservée |
| [Replicate](https://replicate.com/docs/reference/mcp) | Modèles vidéo commerciaux et modèles ouverts ; second fournisseur VydFlow | Oui ; adaptateur implémenté, tests simulés | Officiel ; parcours MCP demandant une clé Replicate, ne pas le présenter comme sans clé |
| [WaveSpeedAI](https://wavespeed.ai/docs/mcp) | Autre catalogue large, candidat pour comparer les offres | Oui | Officiel distant, OAuth navigateur ou clé ; outil d'estimation par paramètres |
| [Krea](https://www.krea.ai/docs/developers/mcp) | Catalogue image/vidéo et comptes d'équipes | Oui | Officiel distant, OAuth sans clé ou jeton API ; **soldes différents selon l'authentification** |
| [Higgsfield](https://higgsfield.ai/blog/ai-video-api-pricing-vs-subscriptions) | Modèles vidéo et outils créatifs ; [API officielle](https://open.higgsfield.ai/quick-start) | Oui, solde API distinct | [MCP officiel](https://higgsfield.ai/creator-hub/help-center/integrations) avec connexion de compte ; droits, crédits du forfait et compatibilité d'un client VydFlow à valider |
| [Runware](https://runware.ai/docs/tools/mcp) | Catalogue image/vidéo, recherche de modèles, schémas et prix | Oui | Officiel distant ; OAuth **avec saisie d'une clé Runware au premier accès**, ou MCP local avec clé |
| [Magnific / ancien accès développeur Freepik](https://docs.magnific.com/introduction) | API vidéo et outils image ; docs.freepik.com redirige vers Magnific | Oui | MCP officiel annoncé sans clé ; transport, facturation et droits applicatifs à valider avant intégration |
| [Together AI](https://www.together.ai/models?0_category_equal=Video) | Catalogue vidéo, dont [Wan](https://www.together.ai/blog/wan-2-7-now-available-on-together-ai) et [Vidu](https://www.together.ai/models/vidu-q1) | Oui | MCP officiel de génération vidéo non vérifié |

## Créateurs de modèles et plateformes directes

| Fournisseur et source officielle | Famille / usage | Accès à envisager |
| --- | --- | --- |
| [Google Gemini API](https://ai.google.dev/gemini-api/docs/veo) | Veo ; variantes qualité/rapidité, audio et fonctions selon modèle | API directe par clé ; MCP vidéo officiel non vérifié ; accès Vertex distinct à cadrer séparément |
| [Runway](https://docs.dev.runwayml.com/guides/models/) | Gen, Act, Aleph et catalogue tiers selon endpoints | API par clé ; MCP vidéo officiel non vérifié |
| [MiniMax](https://github.com/MiniMax-AI/MiniMax-MCP) | Hailuo ; API vidéo, image et voix | API et MCP officiels ; **clé obligatoire dans le MCP documenté**, région de la clé et hôte à faire correspondre |
| [PixVerse](https://docs.platform.pixverse.ai/start-here-2394989m0) | Vidéo, références et effets selon modèle | API ; [MCP officiel](https://docs.platform.pixverse.ai/pixverse-mcp-972890m0) avec clé API |
| [Vidu](https://platform.vidu.com/help-center) | Vidéo, références et cohérence visuelle selon modèle | API ; [MCP officiel local](https://platform.vidu.com/docs/api-reference/other-documentation/mcp/mcp-stdio) avec clé API |
| [Luma](https://lumalabs.ai/api) | Ray, génération et transformations vidéo | API avec clé ; MCP officiel vidéo non vérifié ; sélectionner une version encore disponible |
| [Lightricks / LTX](https://docs.ltx.io/models) | Texte/image/audio vers vidéo et transformations | API ; MCP officiel vidéo non vérifié ; certaines versions proposent des tarifs économiques |
| [ByteDance via BytePlus ModelArk](https://docs.byteplus.com/en/docs/modelark/model-pricing) | Seedance | API ; droits, régions et modèles activés à vérifier ; MCP vidéo officiel non vérifié |
| [Alibaba Cloud Model Studio](https://www.alibabacloud.com/help/en/model-studio/cli/video) | Wan et autres familles disponibles dans la région choisie | API avec clé ; région/modèle/hôte cohérents ; MCP vidéo officiel non vérifié |
| [xAI](https://docs.x.ai/developers/model-capabilities/imagine) | Grok Imagine Video | API avec clé ; MCP vidéo officiel non vérifié |
| [Pika](https://pika.art/support) | Vidéo Pika et effets | API développeur annoncée ; [endpoints Fal documentés](https://fal.ai/models/fal-ai/pika/v2.2/text-to-video/api). Offre MCP Pika.me distincte : périmètre à vérifier avant de promettre les mêmes modèles et crédits |
| [Kling / Kuaishou](https://fal.ai/models/fal-ai/kling-video/v2.5-turbo/pro/text-to-video) | Kling | Accès via Fal déjà testé dans VydFlow ; conditions d'une API directe et MCP officiel direct non vérifiés dans cette étude |

Les avatars et présentateurs sont une catégorie complémentaire pour les vidéos parlées : [HeyGen](https://developers.heygen.com/) propose API et [MCP OAuth officiel](https://github.com/heygen-com/skills/blob/master/INSTALL_FOR_AGENTS.md), avec crédits de forfait distincts de l'API ; [Synthesia](https://docs.synthesia.io/reference/synthesia-api-quickstart) documente une API par clé pour générer des vidéos avec script/avatar. Ils ne remplacent pas automatiquement un modèle de scène cinématographique. Leurs voix, consentements et coûts nécessitent un lot dédié.

**Retrait à prendre en compte :** OpenAI indique l'arrêt de Videos API, sora-2 et sora-2-pro au **24 septembre 2026**, sans remplacement dans sa [page de dépréciations](https://developers.openai.com/api/docs/deprecations/). Ne pas ajouter Sora comme connecteur OpenAI actif à partir d'anciennes pages de modèles/prix. Une ancienne fiche chez un intermédiaire ne suffit pas non plus à garantir sa disponibilité.

## Repères de prix publics

USD, hors taxes/conversion et coûts d'entrées éventuels ; tarifs consultés le 19 octobre 2026. Pas de classement absolu : qualité, audio, résolution, image de départ, durée minimale et droits diffèrent. Un tarif par seconde n'implique pas que toute durée soit autorisée. Les calculs suivants ne constituent pas un nouveau devis actif du catalogue VydFlow.

| Offre | Configuration ou base de calcul | Tarif public |
| --- | --- | --- |
| [LTX 2.3 Fast direct](https://docs.ltx.io/pricing/) | Texte vers vidéo, 720p | **0,03 USD/s** ; LTX 2.5 Fast à 720p est à 0,09 USD/s |
| [PixVerse V6 direct](https://docs.platform.pixverse.ai/pixverse-api-pricing-2394995m0) | 720p, sans audio | **0,04 USD/s**, soit 0,20 USD pour 5 s ; audio : 12 crédits/s au lieu de 9, avec 225 crédits = 1 USD |
| [Runway Gen-4 Turbo direct](https://docs.dev.runwayml.com/guides/pricing/) | 5 crédits/s ; crédit à 0,01 USD | **0,05 USD/s** ; entrée/résolution/capacités à valider sur le modèle choisi |
| [Google Veo 3.1 Lite](https://ai.google.dev/gemini-api/docs/pricing) | 720p, vidéo avec audio, modèle preview | **0,05 USD/s** ; Fast 720p : 0,10 USD/s ; Standard : 0,40 USD/s |
| [Fal Kling 2.5 Turbo Pro](https://fal.ai/models/fal-ai/kling-video/v2.5-turbo/pro/text-to-video) | Sans audio, essai VydFlow de 5 s | **0,07 USD/s**, estimation de notre essai : 0,35 USD ; facture réelle non consultée |

Les offres brouillon/basse résolution peuvent coûter moins, mais ne doivent pas être comparées à un rendu final 1080p avec audio. Vérifier aussi minimum de recharge et séparation abonnement web / crédit API. Ne pas reprendre une promotion expirée ou extrapoler un tarif d'une version à une autre. Aucun gagnant « meilleur du marché » n'est établi par ce tableau : l'essai réel actuel valide uniquement le parcours Fal, pas une comparaison qualitative.

## Parcours retenu pour VydFlow

Présentation utilisateur : choisir le modèle → choisir où l'exécuter → connecter son fournisseur → régler la vidéo → vérifier coût et compte facturé → confirmer → retrouver la vidéo dans la bibliothèque. Le transport technique ne doit pas devenir une étape obligatoire à comprendre.

Dans Connexions, proposer « Connecter mon compte » quand une autorisation navigateur adaptée est validée, et « Utiliser une clé API » quand disponible. Ne pas afficher un bouton OAuth fictif pour un MCP qui exige une clé. Montrer le compte/espace et le type de solde utilisé, particulièrement Krea, Higgsfield et HeyGen. Une connexion existante dans ChatGPT n'est pas transférable à VydFlow.

Choix du modèle conservé, pas de bascule automatique payante vers un autre modèle ou fournisseur. Filtres : connecté, texte/image vers vidéo, audio, format, durée et coût estimé. Ajouter une image de référence et les suites de plans sera nécessaire pour les récits cohérents de 30–60 secondes ; multiplier des clips indépendants ne garantit pas une histoire ou des personnages cohérents.

## Ordre d'intégration proposé

1. Valider **Fal OAuth/MCP dans VydFlow**, avec BYOK conservé. Premier fournisseur déjà testé réellement ; vérifier que l'autorisation peut fonctionner dans le worker hors session et sur le bon compte facturé.
2. Ajouter **WaveSpeed ou Krea** après essai de connexion sans dépense et comparaison de leurs schémas/prix. Conserver Replicate, déjà implémenté par API, sans le présenter comme validé en génération réelle.
3. Élargir les familles testées et l'entrée image : Kling, Wan, Veo, Seedance, Hailuo, PixVerse, LTX, Vidu et Pika selon accès disponibles. Valider chaque endpoint/durée/audio/prix, même s'il apparaît dans un catalogue distant.
4. Ajouter les accès directs qui répondent à une demande ou un avantage vérifié : Google, PixVerse, LTX, Runway, MiniMax, etc. Puis avatars/parole pour les récits. Aucun connecteur spéculatif créé dans cette étude.

Le souhait de couverture large et de choix API/MCP est validé par le propriétaire ; cet ordre reste une recommandation. Les autorisations de connexion réelle, dépenses et publication ne sont pas étendues implicitement.

## Mise en œuvre Symfony à cadrer dans le prochain lot

Réutiliser AiCatalog, AiGenerations, Messenger, validation du média et bibliothèque privée. Ajouter seulement le fournisseur et le mode d'accès effectivement livrés. Client OAuth/MCP côté serveur : retour vérifié, state/PKCE selon protocole, jetons chiffrés, renouvellement, révocation et rattachement au propriétaire/compte facturé. Un jeton MCP ne doit pas être supposé valable sur l'API REST.

Serveurs et outils autorisés explicitement ; pas d'URL MCP arbitraire ni exécution de commandes fournies par l'utilisateur. Pour un MCP local nécessitant une clé, préférer l'API directe lorsqu'il n'apporte rien au produit. Les générations restent des appels déterministes avec consentement au coût ; aucun agent LLM supplémentaire nécessaire pour simplement appeler les outils MCP.

Tests attendus lors de l'implémentation : réponses API/MCP simulées, échange/renouvellement/révocation OAuth, isolation propriétaire, compte facturé, maintien de l'idée, double clic, délai ambigu sans seconde soumission payante, perte d'accès pendant la consultation, copie privée, estimation et confirmation. Cette étude documentaire ne modifie pas le code ni les accès ; aucun test applicatif relancé.

## État de livraison après le lot HD/4K

Trois fournisseurs sont implémentés localement : Fal, Replicate et LTX direct, 21 configurations au total. Fal dispose aussi du client OAuth/MCP, vérifié avec HTTP simulé mais avec première connexion OAuth réelle confirmée par le propriétaire, sans génération MCP payante. Les autres fournisseurs de cet inventaire restent des candidats, sans bouton de connexion fictif. Détails : [ia-connexions-hd.md](ia-connexions-hd.md).
