# Catalogue regroupé, favoris et connexion Fal OAuth PKCE

Lot du 19 octobre 2026, après le refus d'activation signalé par le propriétaire. Aucun essai de génération payant dans ce lot.

## Objectif et résultat

Le démarrage du flux d'appareil Fal fonctionnait, mais pas son activation dans le navigateur du propriétaire. Une réponse réussie de l'endpoint de code ne prouvait donc pas la connexion complète. VydFlow utilise maintenant le parcours navigateur standard OAuth authorization_code avec PKCE S256 : redirection vers Fal, consentement du propriétaire, puis retour automatique vers VydFlow. Il n'y a plus de code d'appareil à recopier.

Vérification réelle limitée : déclaration publique de client HTTP 201, entrée d'autorisation HTTP 302 vers authkit.fal.ai sans erreur OAuth. Aucun compte autorisé automatiquement, aucun jeton utilisateur récupéré pendant cette vérification, aucun POST de génération. Le propriétaire a ensuite confirmé la réussite de la connexion réelle et du retour dans VydFlow. Une génération facturable par MCP reste à tester séparément, sur autorisation explicite. La clé API existante reste conservée et utilisable séparément.

Le catalogue garde ses 21 identifiants de configurations d'exécution, présentés dans 15 cartes modèle/fournisseur. Pika, PixVerse V6 et les familles LTX regroupent leurs résolutions dans une seule carte. Les variantes de Grok versionnées restent des modèles distincts ; Wan chez Fal et chez Replicate reste distinct car la facturation et les accès diffèrent.

Complément du 20 octobre : une étoile ★ identifie aussi les favoris dans la liste native du formulaire de création, pour toutes les résolutions d'un groupe. Le formulaire reçoit les favoris du propriétaire et Stimulus met à jour les libellés après sauvegarde, sans modifier la sélection ou l'identifiant envoyé au fournisseur. Voir également [l'audit du Studio IA](audit-studio-ia.md) pour les prochaines améliorations proposées.

Les modèles LTX 2.3 Fast et LTX 2.5 Fast proposent déjà la 4K dans le catalogue actuel. Le bouton « Voir les modèles 4K » montre ces deux cartes avec la variante 4K, retire les filtres qui masqueraient les fournisseurs non connectés et compare sur 6 secondes compatibles. Il ne change pas une préparation de génération et ne connecte pas de compte. Les tests LTX restent simulés ; une sortie 4K réelle n'a pas été générée dans ce lot. Les fournisseurs et leur facturation sont distincts : une clé Fal n'est pas une clé LTX.

Le premier retour OAuth réel a révélé un autre contrôle trop strict : les identifiants personnels Fal peuvent contenir `|`. VydFlow les traite désormais comme des valeurs opaques bornées et sans caractères de contrôle, utilisées uniquement pour la comparaison de compte et le stockage chiffré, jamais comme URL ou chemin. Un compte `ready` avec un identifiant inconnu affiche une erreur d'intégration distincte d'un compte bloqué. La connexion a réussi après cette correction, selon confirmation du propriétaire.

## Composants et fichiers

- `FalOAuth` découvre et vérifie les endpoints officiels, déclare le client public, génère state et verifier aléatoires, puis conserve la préparation OAuth chiffrée et liée au propriétaire pendant 10 minutes. Le navigateur reçoit uniquement le challenge SHA-256, jamais le verifier. Le retour valide le propriétaire, l'expiration et state, consomme la préparation avant l'échange, puis réutilise le stockage chiffré et le contrôle du compte MCP existants. Un callback rejoué ne peut pas échanger une deuxième fois.
- `AiStudioController::falConnect/falCallback` porte le départ POST avec CSRF, le retour GET et les messages neutres. L'URL de retour est calculée sur l'adresse VydFlow utilisée au départ ; ne pas passer de localhost au tunnel pendant la connexion. Les redirections de retour sont internes et bornées aux écrans autorisés.
- `AiPrivacyListener` retire code/state et la query string de la requête Fal avant le routage ; l'échange reçoit temporairement une valeur sensible. Le profiler reste désactivé sur les routes IA et le client HTTP dédié ne capture pas de tokens. Les serveurs mandataires doivent également éviter de journaliser les query strings OAuth.
- `AiCatalog::groups()` construit les cartes depuis le catalogue existant, en regroupant uniquement un même fournisseur et endpoint. Il ne réaffecte aucun ancien identifiant ni ne change de paramètres/coût lors du suivi d'une génération.
- `User::aiFavorites` conserve les identifiants stables des groupes. La migration additive `Version20261019040000` démarre avec une liste vide sans toucher aux vidéos, clés ou générations existantes. La route de favoris vérifie CSRF et liste autorisée, verrouille le propriétaire et enregistre l'état souhaité (ajouter/retirer), plutôt qu'un basculement ambigu en cas de double clic. Un utilisateur ne modifie que ses propres favoris.
- `templates/ai/_model_card.html.twig` affiche une carte, ses résolutions et son favori. Le template Studio garde les filtres, le formulaire et l'accès 4K. Les formulaires de favoris sont hors du formulaire de génération, sans formulaire HTML imbriqué.
- `creation_controller.js` actualise les cartes, conserve les variantes disponibles dans les filtres, recalcule les prix et enregistre un favori sans quitter la page ni perdre le prompt. En cas d'échec réseau, il conserve l'ancien état et affiche une reprise. Le filtre de favoris peut afficher un état vide explicite. Le tri et le badge économique portent toujours sur la variante et la durée affichées, pas sur une variante cachée moins chère.

## Flux Symfony à comprendre

Twig reçoit des groupes d'affichage ; le formulaire soumis conserve un identifiant de configuration validé côté serveur. Regrouper les cartes ne doit pas modifier le contrat d'exécution. Stimulus améliore le choix et évite le rechargement pour les favoris ; Doctrine conserve les préférences par compte. La route OAuth ne génère aucune vidéo : la confirmation du coût et le worker de génération restent des étapes distinctes.

## Vérification manuelle

1. Fermer l'ancien onglet d'activation Fal et recommencer depuis VydFlow → Connexions → IA → Fal. Autoriser sur Fal puis revenir automatiquement sur la même adresse VydFlow. Ne pas réutiliser l'ancien code d'appareil.
2. Ouvrir le catalogue : une carte Pika, avec 720p et 1080p. Choisir une résolution met à jour le coût et les durées disponibles ; changer un filtre seul ne remplace pas la configuration du formulaire.
3. Cliquer « Voir les modèles 4K » : deux cartes LTX doivent apparaître, chacune en 4K. Le coût de comparaison est indiqué pour 6 s. Sans clé LTX, les cartes sont grisées et proposent la connexion appropriée.
4. Ajouter une carte aux favoris, activer « Mes favoris », recharger et vérifier sa conservation. Dans un autre compte, elle ne doit pas être favorite. Retirer le dernier favori affiche un état vide, sans perdre l'idée.

Sources : [parcours MCP OAuth officiel Fal](https://fal.ai/docs/documentation/setting-up/mcp/other-clients), métadonnées publiques [du relais](https://mcp.fal.ai/.well-known/oauth-protected-resource/mcp-relay) et [de l'émetteur OAuth](https://authkit.fal.ai/.well-known/oauth-authorization-server), [capacités LTX 2.3](https://docs.ltx.io/models/ltx-2-3), [LTX 2.5](https://docs.ltx.io/models/ltx-2-5). Résultats exacts des contrôles dans [verification.md](verification.md).
