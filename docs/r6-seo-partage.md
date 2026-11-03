# R6.5 — référencement et partage

Le 3 novembre 2026, le propriétaire retient **vydflow.com**. Ce choix ne certifie ni achat, ni DNS, ni mise en ligne. Préparation locale uniquement ; aucune indexation activée.

## Comportement

- Origine canonique : `SEO_PUBLIC_ORIGIN=https://vydflow.com`, indépendante du Host reçu, sans paramètres de suivi.
- Trois pages éligibles : accueil, fonctionnement et contact. Titres/descriptions HTML et Open Graph, URL, langue française, nom VydFlow et carte Twitter `summary`. Aucun avis, résultat, image sociale ou compte social inventé. Sans image dédiée, le rendu exact de l’aperçu dépend du réseau ; aucune prévisualisation externe n’est revendiquée.
- `SEO_INDEXING_ENABLED=0` par défaut. Métadonnée robots et en-tête HTTP `X-Robots-Tag: noindex, nofollow` ; sitemap XML vide, sans URL de studio, récupération, connexion, juridique ou test.
- En configuration activée, seules les trois routes sur l’origine HTTPS exacte sont éligibles. Autre domaine, réponse en erreur ou redirection restent exclus par en-tête. Le sitemap contient alors uniquement ces trois URL et robots.txt annonce son adresse.
- Robots autorise l’exploration pour permettre la lecture du noindex : bloquer l’exploration empêcherait les moteurs de le lire. L’authentification Symfony reste la protection des données privées. [Documentation Google](https://developers.google.com/search/docs/crawling-indexing/block-indexing).

## Pédagogie Symfony

`PublicSeo` reçoit le routeur et deux variables d’environnement par injection. Il centralise les routes éligibles et construit leurs adresses depuis l’origine de confiance. Twig reçoit ce service dans ses globals et produit les balises dans le head commun, en réutilisant les blocs titre/description de chaque page.

`SeoController` produit deux réponses GET sans accès à la base ni fournisseur : texte pour robots, XML pour sitemap. `SeoSubscriber` intervient sur la réponse HTTP principale et ajoute le noindex même aux réponses sans template ou aux redirections privées. Aucun mécanisme d’accès existant n’est remplacé.

Pour tester : `docker compose exec -T php php vendor/bin/phpunit tests/Unit/PublicSeoTest.php tests/Functional/PublicSeoTest.php`. Les tests unitaires simulent l’activation sans modifier les variables locales ; les tests fonctionnels lisent les pages et vérifient le mode fermé. Aucune migration, génération, publication, dépense, email ou requête fournisseur.

L’activation future exige une décision distincte, le domaine effectivement configuré, HTTPS et environnement production sans debug. Changer l’origine SEO ne change pas automatiquement `APP_PUBLIC_URL`, les liens email ou les callbacks OAuth (R8). Aucun cache dev ni worker redémarré pour ce lot.

## Contrôles

Résultats : 3 tests SEO / 41 assertions et 16 tests compte/public / 187 assertions verts ; 120 Twig, 19 YAML, conteneur, PHPStan et style ciblé verts. Premier passage SEO : route sitemap absente du cache test ancien ; cache test renouvelé, passage final vert. Aucune modification du cache dev. La suite compte lit/écrit uniquement sa base de test ; aucun bilan de compteurs dev ou rollback global n’est revendiqué ici.

Revue indépendante en lecture seule sans blocage. Ses demandes de renforcement ont été traitées : requête désactivée avec route explicite, test HTTP d’ouverture en kernel sans debug, aperçus textuels documentés. Activation simulée uniquement dans le processus PHPUnit, variables restaurées en finally ; aucun drapeau local persistant changé. Les GET fonctionnels utilisent BrowserKit, pas Internet.

Aucun nouveau build frontend ni suite globale : aucun JS/CSS changé. La revue globale R6.6, incluant la recette responsive/clavier/sans JS, reste à faire. Les contrôles SEO portent sur le head et les réponses HTTP ; aucune nouvelle recette visuelle ou matérielle revendiquée.
