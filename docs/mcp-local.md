# Prototype MCP de VydFlow

Le prototype utilise le SDK MCP PHP sur stdin/stdout. La commande accepte un compte de test confirmé déjà présent dans les environnements dev ou test. Elle utilise les règles de propriété VydFlow et renvoie seulement les informations nécessaires à la préparation.

```bash
docker compose exec -T -e APP_ENV=dev php php bin/console app:mcp:local pilote@example.test
```

Pour un client MCP local, utiliser `docker` comme commande et les éléments ci-dessus après `docker` comme arguments. Si le client démarre ailleurs que dans le dépôt, ajouter `--project-directory` suivi du chemin absolu du dépôt entre `compose` et `exec`. La commande vise un compte de test choisi par l’opérateur local. [Transport stdio du SDK PHP](https://php.sdk.modelcontextprotocol.io/run/stdio/)

| Outil | Résultat |
| --- | --- |
| `list_videos` | Titres, durées et identifiants des vidéos du compte. Recherche et pagination. |
| `list_publication_accounts` | Réseaux, libellés et identifiants VydFlow des comptes connectés. |
| `open_publication_preparation` | Lien vers la préparation de la vidéo choisie avec une date facultative. L’ouverture demande une connexion VydFlow et laisse la décision d’envoi dans le parcours habituel. |
| `create_publication_draft` | Enregistre un brouillon privé avec le réseau, le compte, le titre, le texte et les hashtags pour une vidéo. Le lien permet de relire, compléter et confirmer dans VydFlow. |

Le connecteur web utilisera HTTPS et OAuth avec un accès révocable par compte. Prévoir des droits distincts pour lire les vidéos, créer des brouillons et traiter les demandes de génération. Valider avec ChatGPT et Claude l’identité du compte, l’accès refusé à une vidéo d’un autre propriétaire et la révocation. [Authentification MCP dans ChatGPT](https://developers.openai.com/plugins/build/auth) · [Connecteurs distants Claude](https://support.claude.com/en/articles/11175166-get-started-with-custom-connectors-using-remote-mcp)

Étendre ensuite les brouillons aux lots, aux destinations multiples et aux créneaux proposés. Réutiliser le récapitulatif VydFlow pour la confirmation d’un envoi et le devis VydFlow pour la confirmation d’une génération payante.
