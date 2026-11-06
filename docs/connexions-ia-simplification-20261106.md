# Connexions IA accessibles — 6 novembre 2026

## Vérification des fournisseurs actuels

Le catalogue VydFlow distingue trois fournisseurs : Fal, Replicate et LTX. Les noms de modèles ou fabricants ne sont pas des connexions supplémentaires.

| Fournisseur | Documentation publique consultée | Décision |
| --- | --- | --- |
| Fal | [Connexion MCP par navigateur/OAuth](https://fal.ai/docs/documentation/setting-up/mcp/chat-agents) ; adaptateur OAuth existant dans VydFlow. | Garder la connexion guidée en premier. Pas de nouvel accès de compte ni d’autorisation réelle lancée. |
| Replicate | [Documentation MCP](https://replicate.com/docs/reference/mcp) et [serveur hébergé officiel](https://mcp.replicate.com/) : l’écran d’authentification demande encore une clé Replicate. | Ce connecteur n’enlève pas la saisie de clé. Ne pas ajouter un bouton promettant un accès sans clé ; garder l’intégration actuelle par clé. |
| LTX | [Authentification officielle](https://docs.ltx.io/authentication) : requêtes API authentifiées par clé liée au compte. | Aucun accès délégué sans clé identifié dans les sources consultées ; garder la connexion par clé. |

Ces conclusions concernent les intégrations documentées consultées à cette date, pas une impossibilité définitive chez les fournisseurs. Aucune déduction d’accès API à partir du simple login sur leur site. Aucun nouveau fournisseur ou adaptateur n’a été ajouté.

## Écran local livré

Fal apparaît en premier, avec un bouton Connecter mon compte Fal ou Gérer ma connexion Fal. Le statut indique uniquement la présence d’une autorisation enregistrée ; il ne prétend pas vérifier à distance crédits, validité et compte actif. Guide de trois étapes replié, jargon MCP retiré de cette première vue et de l’explication de compte actif sur l’écran suivant. Facturation des générations et absence d’abonnement bêta restent explicites.

Les configurations non enregistrées par clé sont regroupées dans Options avancées. Définition courte de la clé API, sans demander de clé pour la connexion Fal guidée. La section s’ouvre si le Studio vise un fournisseur précis ou après un POST. Les clés déjà enregistrées restent visibles et leur retrait demeure directement accessible hors des blocs repliés. Formulaires, URLs, CSRF, chiffrement, masques, retours à l’idée et distinction entre Fal navigateur et Fal API inchangés.

## Vérifications

- Cinq fonctionnels clés/retours/propriété : 75 assertions ; un parcours OAuth Fal simulé : 43 assertions. Aucun compte réel connecté ou déconnecté, aucune génération soumise.
- 37 Twig et build verts. JS/PHP applicatifs inchangés ; pas de nouveau passage global frontend/PHPStan/suite PHP ni revue indépendante. Avertissement de gros chunk Vite existant.
- Douze vues HTML navigateur sans JS à 320/390/768/1280 : Fal accessible, clés repliées par défaut, section ouverte pour Replicate/LTX demandés, retrait des clés hors details, aucun débordement. Capture mobile relue. Aucun formulaire soumis dans ce navigateur ; réseau intercepté.
- Cache dev actualisé ; quatre GET authentifiés HTTP200 (base, fournisseurs Replicate/LTX, entrée Fal) dans un banc isolé en lecture seule : 27 compteurs inchangés, zéro HTTP fournisseur dans le banc. Seules les documentations publiques ont été consultées pour la recherche.

Aucune clé réelle affichée ou exportée, aucune modification de contenu propriétaire, génération/coût/upload fournisseur/migration/worker redémarré/publication/push/déploiement. R7 reste ouvert. Prochain lot à valider séparément : estimations en euros ; séries importées et tests utilisateurs restent au programme.
