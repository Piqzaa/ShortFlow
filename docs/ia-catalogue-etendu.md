# Catalogue vidéo étendu — 19 octobre 2026

Objectif : couvrir davantage de familles dès le premier catalogue, en conservant le parcours privé déjà testé. Livraison locale : **12 choix fournisseur/modèle, 11 modèles, 2 fournisseurs**. Une seule clé Fal donne accès aux configurations Fal sous réserve des droits et du solde de son compte. Le seul essai réel réalisé reste Kling 2.5 ; les nouvelles configurations sont vérifiées avec HTTP simulé et les schémas publics. Aucun nouvel appel payant ni connexion OAuth ajouté.

## Configurations ajoutées

| Modèle via Fal | Durée | Format proposé | Audio | Estimation USD |
| --- | --- | --- | --- | --- |
| Pika 2.2 | 5 s | Vertical, paysage, carré | Non | 0,20 / 5 s |
| Vidu Q1 | 5 s fixes | Vertical, paysage, carré | Non | 0,40 / plan |
| Grok Imagine Video | 1–15 s | Vertical, paysage, carré | Natif | 0,07 / s en 720p |
| Hailuo 02 Standard | 6 ou 10 s | Paysage uniquement dans cette configuration | Non | 0,045 / s |
| PixVerse 5.6 | 5 / 8 / 10 s | Vertical, paysage, carré | Non | Respectivement 0,45 / 0,90 / 0,99 en 720p |
| PixVerse V6 | 1–15 s | Vertical, paysage, carré | Natif | 0,06 / s en 720p |
| Seedance 2.5 | 4–30 s | Vertical ou paysage | Natif | Environ 0,473 / s en 720p ; facturation réelle aux tokens |

Prix hors taxes et conversion, vérifiés le 19 octobre. Seedance utilise le repère approximatif publié par Fal, soit une estimation de **14,19 USD pour 30 s**, pas une garantie de facture ou de plafond. La formule aux tokens et les dimensions réellement produites déterminent le débit fournisseur. Aucune durée `auto` ni format `auto` proposé, aucun brouillon ou enrichissement payant silencieux. Le carré Seedance est exclu de cette configuration pour ne pas extrapoler le repère 16:9 à une autre surface. L'accès B2B peut demander une activation fournisseur ; un identifiant utilisateur pseudonymisé stable est envoyé sans adresse email. Aucune demande d'activation réelle faite pendant ce lot.

Pika reste volontairement à 5 s : le prix 10 s n'est pas extrapolé à partir de la fiche 5 s. Hailuo n'expose pas de paramètre de ratio dans ce schéma ; le choix vertical est bloqué, pas simulé par le prompt. Vidu a une durée fixe : aucun champ `duration` inventé n'est envoyé. LTX 2.3 Fast n'est pas ajouté : la fiche consultée donne deux tarifs 1080p différents (0,06 et 0,04 USD/s), à clarifier avant activation. Seedance 1.0 Lite est retiré et redirigé par le fournisseur : ne pas l'ajouter sous son ancien nom. Veo Fast Fal est annoncé en retrait au 17 novembre : aucun nouveau connecteur pour cet endpoint dans ce lot ; le choix Replicate existant est conservé sans modification.

## Fichiers et rôles Symfony

- `src/Service/AiCatalog.php` décrit les choix autorisés, limites et réglages fixes. L'estimation sait utiliser une table par durée (PixVerse 5.6) ou un tarif indicatif à la seconde. La liste des durées est dérivée des configurations ; le formulaire n'a plus une limite globale de 15 s.
- `src/Form/AiIdeaType.php` ajoute les durées 1–30 s réellement couvertes et valide la limite en octets de PixVerse. La validation de formulaire affiche l'erreur près du prompt ; `AiGenerations` recontrôle avant confirmation et avant soumission.
- `src/Service/FalVideoApi.php` adapte les types natifs : durée entière, chaîne ou absente, champs audio propres au modèle et paramètres contrôlés. Seedance demande explicitement H.264 et un débit standard. Les URL de consultation doivent appartenir au domaine Fal et à l'application correspondant au modèle ; aucune redirection, chemin parent, query string ou autre application acceptée.
- `templates/ai/studio.html.twig` et `assets/controllers/creation_controller.js` ajoutent le filtre 30 s, affichent les prix par durée et maintiennent l'estimation en accord avec le serveur. Une durée sans prix documenté ne reçoit pas de coût inventé. Aucun nouveau fournisseur connecté et aucun nouveau service cloud.
- `tests/Fixtures/fal-video-contracts.json` est un instantané réduit des dix schémas Fal publics (source et date incluses), sans clé, compte ou média privé. `tests/Unit/FalVideoApiTest.php` contrôle les paramètres natifs contre cet instantané, les URL refusées et les prix non linéaires. Les tests fonctionnels couvrent aussi l'import privé des sept nouvelles configurations et l'absence de double soumission.

Flux : idée → choix explicite → validation et estimation → récapitulatif → confirmation → Messenger → adaptateur natif → consultation de la même demande → copie privée. Le drapeau global des générations payantes reste désactivé après l'essai unique précédent. Aucune migration : les anciens identifiants et préparations gardent leur signification.

À comprendre en Symfony : le catalogue sert de source aux champs `ChoiceType`, mais le service doit revalider même si le formulaire est contourné. Un adaptateur transforme les paramètres métier en champs fournisseur ; chaque modèle ne nécessite pas un nouveau worker ou une nouvelle entité. Les tests HTTP simulés prouvent le comportement de VydFlow, pas la qualité du rendu ou les droits d'un vrai compte.

## Comment vérifier sans dépense

1. Studio → explorer le catalogue → filtrer « Au moins 30 s » : Seedance 2.5 apparaît. Choisir 30 s verticales : estimation 14,19 USD ; préparer le récapitulatif n'appelle pas Fal.
2. Choisir PixVerse 5.6 puis 10 s : afficher 0,99 USD, pas 0,90 USD. Choisir Hailuo : le vertical incompatible est vidé, il faut choisir paysage explicitement.
3. Un prompt PixVerse de plus de 2 048 octets (par exemple 600 emojis) est refusé avant préparation, même avec moins de 1 500 caractères. Aucune soumission payante.
4. Tests : migrations `--env=test` avant `php vendor/bin/phpunit tests/Functional/AiStudioTest.php` et `php vendor/bin/phpunit tests/Unit/FalVideoApiTest.php`. Tous les appels fournisseurs y sont simulés ; base exclusivement `shortflow_test`. Frontend : `npm run check`, `npm run test:ui`, `npm run build`.

Résultats et QA responsive consignés dans [verification.md](verification.md). Connexion navigateur Fal/OAuth, autres fournisseurs, image de référence, montage et séries restent des lots suivants ; cet élargissement du catalogue n'annonce pas leur disponibilité.

## Sources officielles

- [Pika : schéma](https://fal.ai/models/fal-ai/pika/v2.2/text-to-video/api), [prix](https://fal.ai/models/fal-ai/pika/v2.2/text-to-video).
- [Vidu Q1 : schéma](https://fal.ai/models/fal-ai/vidu/q1/text-to-video/api), [prix](https://fal.ai/models/fal-ai/vidu/q1/text-to-video).
- [Grok : schéma](https://fal.ai/models/xai/grok-imagine-video/text-to-video/api), [prix](https://fal.ai/models/xai/grok-imagine-video/text-to-video).
- [Hailuo : schéma](https://fal.ai/models/fal-ai/minimax/hailuo-02/standard/text-to-video/api), [prix](https://fal.ai/models/fal-ai/minimax/hailuo-02/standard/text-to-video).
- [PixVerse 5.6 : schéma](https://fal.ai/models/fal-ai/pixverse/v5.6/text-to-video/api), [prix par palier](https://fal.ai/models/fal-ai/pixverse/v5.6/text-to-video).
- [PixVerse V6 : schéma](https://fal.ai/models/fal-ai/pixverse/v6/text-to-video/api), [prix](https://fal.ai/pixverse-v6).
- [Seedance 2.5 : schéma](https://fal.ai/models/bytedance/seedance-2.5/text-to-video/api), [prix et formule aux tokens](https://fal.ai/models/bytedance/seedance-2.5/text-to-video).

Lecture publique OAuth : le document de ressource protégée `https://mcp.fal.ai/.well-known/oauth-protected-resource/mcp-relay` désigne `https://authkit.fal.ai` comme serveur d'autorisation. Ses métadonnées annoncent code + PKCE S256, refresh token et enregistrement dynamique. Le document OAuth à la racine de mcp.fal.ai donne un autre issuer : ne pas le substituer au serveur annoncé par la ressource. Aucune application enregistrée, aucun jeton obtenu ou accès ChatGPT transféré pendant cette lecture. Le client applicatif, la révocation et le contrôle du compte facturé restent à implémenter et tester.
