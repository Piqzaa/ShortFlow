# Studio simple, connexions et recherche vidéo — 20 octobre 2026

## Objectif

Réduire les décisions avant la première création et retrouver une vidéo sans parcourir toute la galerie. Lot explicitement autorisé après l’audit produit. Aucun nouveau modèle, fournisseur, paiement, montage ou automatisation ajouté.

## Parcours et composants

- `ai/_recommendations.html.twig` propose Économique (Pika 2.2), Équilibré (Kling 2.5) et Avec audio (Kling 3), tous via Fal. Les identifiants existants restent inchangés. Image/début-fin adaptent les choix : Pikaframes pour la transition à deux images. Ces recommandations ne constituent pas un classement universel de qualité ni une promesse de moindre prix pour tout réglage.
- `creation_controller.js` conserve le vrai champ modèle et ses validations serveur. La sélection native avancée est ouverte sans JS, puis repliée si aucun modèle particulier n’est repris ; recherche/favoris/résolutions et catalogue complet restent accessibles. Une sélection recommandée ne lance rien et ne change pas l’accès Fal choisi. Le coût reste estimé à partir des réglages et confirmé au récapitulatif.
- `AiStudioController` propose la connexion navigateur pour une nouvelle idée sans clé Fal enregistrée ; la présence d’une clé propose l’API. Une méthode déjà enregistrée dans l’idée n’est jamais remplacée. Aucune bascule après une erreur ni modification d’un job confirmé. Le choix d’un autre fournisseur utilise son seul accès pris en charge, une clé API.
- `ai/keys.html.twig` présente la connexion navigateur Fal d’abord, la clé API Fal directement, puis Replicate/LTX dans des cartes toujours visibles. `_key_provider.html.twig` réutilise les formulaires existants avec CSRF, chiffrement et aide. Une entrée depuis un modèle Replicate/LTX donne un lien vers sa carte. Enregistrer n’est pas présenté comme vérifier les droits/crédits ; aucun appel distant de validation ajouté.
- `WorkspaceController` et l’ancien `VideoController::index` réutilisent `VideoAssetRepository::search()`, déjà employé par le calendrier. Recherche par nom insensible à la casse, `%` et `_` traités littéralement, propriétaire obligatoire et pagination bornée. Le total de stockage reste celui de toute la bibliothèque, indépendamment du filtre.
- `library/_search.html.twig`, galerie et ancien écran bibliothèque conservent recherche/pagination, date, fuseau et contexte de choix de vidéo. Un filtre vide de résultats n’est pas présenté comme une bibliothèque vide. Effacer retire le filtre mais garde le créneau.
- Styles existants enrichis : recommandations et formulaires accessibles sur téléphone, labels, focus, aucune nouvelle dépendance.
- Landing et pages d’information : IA, calendrier et brouillons décrits comme intégrés à la bêta ; TikTok toujours soumis à Sandbox/autorisations, montage/séries à venir. Facturation du fournisseur séparée du futur abonnement ; aucun abonnement VydFlow actuellement. Confidentialité décrit prompts, références, accès chiffrés, destinataires et limites actuelles de suppression, sans inventer de purge ou de nouvelle garantie juridique. Les documents restent en préparation.

## Flux Symfony à comprendre

La recherche est un GET sans mutation : le contrôleur borne la saisie et transmet au repository qui impose le propriétaire. Twig échappe la saisie réaffichée ; les formulaires ne sont pas imbriqués dans la sélection de vidéos. Stimulus améliore le choix du modèle, mais les contraintes du FormType restent l’autorité pour préparer la création. L’accès navigateur ne signifie pas génération gratuite : il évite la copie d’une clé ; le coût et le compte fournisseur restent confirmés séparément.

## Comment tester

1. Nouvelle idée sans accès : trois suggestions visibles, Connexion Fal proposée après choix. Changer de modèle ne lance aucune requête fournisseur.
2. Cliquer Équilibré à 5 s : estimation 0,35 USD ; ouvrir les autres modèles, rechercher LTX ou Pika, choisir une résolution. Reprendre une idée avec son accès explicite : aucun remplacement.
3. Passer en image et activer une fin : suggestions adaptées, images privées et validations conservées. Préparer uniquement ; aucun essai payant nécessaire pour ce contrôle.
4. Sans JS : choix natif ouvert, formulaires et validations fonctionnels.
5. Sur Connexions IA, vérifier les trois fournisseurs visibles, ouvrir l’aide Fal ou le remplacement de clé ; vérifier le retour à l’idée. Les secrets ne sont pas réaffichés.
6. Bibliothèque : rechercher un nom, puis un titre contenant `%_`, passer de page, effacer et tester aucun résultat. Depuis une date du calendrier, date/fuseau sont conservés jusqu’à la préparation. Un autre compte ne voit jamais les résultats du propriétaire.
7. Contrôler téléphone/tablette, clavier et console. Résultats exacts des vérifications dans `docs/verification.md`.

Aucun essai fournisseur, nouvelle publication ou changement de limite de dépense effectué dans ce lot. Le rapport produit reste une proposition ; les lots suivants demandent leur autorisation.


## Ajustement — identifier et retirer les clés enregistrées

Objectif : voir immédiatement quels accès sont enregistrés et pouvoir retirer une clé sans chercher dans plusieurs menus.

- `AiKeys::masked()` retrouve exclusivement la clé du propriétaire connecté, déchiffre côté serveur et ne renvoie que les quatre derniers caractères précédés de `••••`. Les anciennes clés en bénéficient aussi, sans migration. Si le chiffrement est illisible ou indisponible, un masque neutre est présenté ; le retrait reste disponible.
- `AiStudioController::keys()` transmet seulement ces repères au template, avec les métadonnées existantes. La réponse reste privée et non stockable. Aucun jeton MCP n’est utilisé pour cet affichage.
- `ai/keys.html.twig` affiche Fal, Replicate et LTX directement. `_key_provider.html.twig` affiche statut, repère, date et formulaire de retrait ; seul le remplacement et l’aide sont repliés. Le retrait est également affiché si le coffre n’est plus configuré. `ai-studio.css` aligne les actions et adapte le bouton au téléphone.
- Flux Symfony : GET authentifié → service propriétaire → repère masqué → Twig échappé. POST de retrait → CSRF + confirmation serveur → suppression de la seule clé du fournisseur pour ce propriétaire → redirection. Retirer Fal API ne retire pas Fal MCP ; aucune génération n’est déclenchée et aucune révocation distante n’est effectuée.
- Tests : ouvrir Connexions → IA et clés API, comparer uniquement la terminaison de sa clé ; ouvrir Remplacer pour un champ vide. Sur un compte de test, confirmer puis retirer une clé et vérifier que l’autre fournisseur reste enregistré. Sans confirmation ou sans CSRF, le retrait doit être refusé. Les tests automatisés couvrent également une clé illisible et l’isolation des comptes.

À comprendre : masquer un champ en CSS ou le remplir d’astérisques dans le navigateur ne protège pas un secret déjà envoyé. Ici, la clé complète reste côté serveur ; Twig ne reçoit que le repère destiné à l’affichage.
