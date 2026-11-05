# R7.3b — recette finale des portraits depuis une idée

5 novembre 2026. **R7.3b terminé sur le périmètre technique local simulé.** Le parcours idée → devis image → confirmation distincte → portrait privé → acceptation → fiche → scènes → devis vidéo a été exercé. Grand R7, R7.2/R7.3 et R7.3c restent ouverts. Aucune génération réelle activée ou effectuée, aucun retour humain inventé.

## Parcours vérifié

Le nouveau scénario `testIdeaToAcceptedPortraitSceneAndFrozenVideoQuoteAcrossReplacement` de `AiPortraitFlowTest` utilise les formulaires natifs de création de brouillon, confirmation du portrait, acceptation et préparation Studio. Il crée un utilisateur et une clé synthétiques dans `shortflow_test`, avec Fal entièrement remplacé par un `MockHttpClient` fermé aux autres routes. Les quatre autres clients fournisseur sont également remplacés. Aucun message n’est consommé par un worker réel : les deux passages du service portrait sont invoqués dans le banc.

1. Une idée de Milo est enregistrée et son devis image relu. Le consentement image est envoyé séparément.
2. Une seule demande image reçoit un identifiant de queue simulé, puis un résultat JPEG violet de 1024 × 1024 produit localement. Le téléchargement et la normalisation privée sont réellement exécutés ; aucune fiche n’existe avant acceptation.
3. L’acceptation explicite crée une fiche. Son portrait normalisé est sélectionnable dans Studio, version 1.
4. Une vidéo de deux scènes de cinq secondes est préparée par formulaire avec Milo présent dans chaque scène. Les deux références du devis sont le portrait accepté. Aucun consentement vidéo ni objet `AiGeneration` n’est créé.
5. Une modification volontaire de la fiche importe un autre fichier bleu, change nom/description et produit la version 2. Le premier devis reste intégralement identique. Une nouvelle préparation choisit explicitement cette version et référence le nouveau fichier dans ses deux scènes.
6. Après vidage de l’EntityManager et relecture de PostgreSQL, l’archivage de la fiche conserve les deux devis. Une nouvelle acceptation de l’ancienne demande retourne la même fiche sans annuler son archivage ni remplacer son portrait actuel.
7. Les deux fichiers, vieillis de quarante jours dans le seul dossier synthétique, restent protégés par l’inventaire de maintenance. Après rollback et nettoyage, les 27 compteurs de tables sont égaux et le dossier privé de ce propriétaire synthétique a été retiré.

Le banc conserve les deux devis, leurs empreintes, les quatre HTML produits et les deux véritables fichiers normalisés. Les fichiers violet/bleu permettent de distinguer les versions ; ils ne prouvent aucune qualité ou identité visuelle IA. Les scènes restent des préparations : aucun appel vidéo, clip ou assemblage nouveau n’est revendiqué.

## Revue du périmètre R7.3b

Relecture locale du cadrage, du contrat et des services/formulaires/routes/stockage, rapprochée des preuves précédentes et du nouveau scénario. Aucune nouvelle revue indépendante n’est revendiquée ; la revue indépendante du **grand R7** reste à faire à sa clôture.

| Attendu | Résultat et limite |
| --- | --- |
| Idée conservée avant portrait | Brouillon privé distinct d’`AiCharacter`, prompt local ou personnalisé et devis relisible. Nom/apparence/style restent renseignés et validés par l’utilisateur. Un extrait du texte d’une série peut être copié volontairement dans l’idée ; aucune extraction automatique de distribution depuis un scénario n’est livrée. |
| Coût image distinct | Format fixe, modèle/connexion API/date/prix et empreinte de devis. Sauvegarder ne soumet rien ; confirmation dédiée avec propriétaire, CSRF et case explicite. L’identité de clé ne certifie pas le compte facturé ni ses crédits. |
| Demande et reprise | Verrou propriétaire, contrainte unique, copie figée et état revendiqué avant HTTP. Les doublons, pannes, changements de clé/tarif et reprises sont couverts par le lot précédent. Sans reçu fiable, l’envoi reste suspendu ; aucune relance payante automatique. |
| Résultat privé contrôlé | Un résultat, dimensions réelles, JSON et téléchargement bornés, hôtes autorisés, redirections refusées, aucune clé dans le GET média, normalisation et quota partagé. Résultat multiple ou invalide non accepté silencieusement. |
| Validation avant réutilisation | Acceptation explicite seulement ; nouvelle fiche, acceptation répétée idempotente, import manuel toujours disponible. Portrait écarté non utilisable ; nettoyage différé selon les protections historiques. |
| Personnage réutilisable | Nouveau raccord vérifié par les vrais formulaires Studio et devis, puis par relecture DB : modification/archivage sans réécriture des références de scènes existantes. Le mécanisme commun de bibliothèque/sélection R7.3a est réutilisé. |
| Autorisation et exploitation | Drapeau portrait par défaut désactivé, indépendant de la vidéo. Aucun fichier d’environnement changé et aucun worker démarré/redémarré. Exploiter la nouvelle file portrait reste un travail d’exploitation distinct, pas une activation implicite. |

Les limites de permissions, crédits, rendu réel et connexion navigateur image du [contrat](r7-personnages-idee-contrat.md) restent inchangées. La simulation ne certifie pas un crash entre deux processus, une facturation réelle ou la cohérence de visage/tenue/voix.

## Vérifications effectuées ici

**1 nouveau scénario / 52 assertions**, après correction d’une valeur de banc : le formulaire « plusieurs scènes » demande au moins deux plans, et refusait correctement `planCount=1` avec 422. Le scénario final utilise deux plans ; aucun défaut applicatif ni assouplissement de validation n’a été nécessaire. PHPStan du banc avec sa migration requise et style ciblé verts. Le premier appel PHPStan ciblé omettait la migration et ne pouvait pas résoudre sa classe ; l’analyse avec cette dépendance est verte.

Un rapport transactionnel : **27 compteurs égaux après rollback**, retrait du dossier de références synthétique vérifié, **quatre échanges HTTP simulés dont un seul POST image**, aucun POST vidéo. Quatre HTML et deux JPEG normalisés vérifiés par empreintes.

**24 vues de relecture** : quatre HTML, sans JS à 320/390/768/1280 et avec JS à 390/1280 ; six captures. Aucun débordement, ressource en échec, erreur JS ou image **chargée** cassée. Les aperçus dans les détails fermés peuvent rester différés par le navigateur : un `naturalWidth=0` sur une image `complete=false` n’est pas compté comme un chargement cassé. Le manifeste garde cet état par image. Les deux fichiers servis sont les sorties normalisées du scénario. Le banc n’autorise que des GET locaux et ne soumet aucun formulaire vidéo.

Les **29 PHP / 376 assertions, 196 frontend/check/build, 126 Twig, 19 YAML et contrôles applicatifs du précédent sous-lot** restent des résultats antérieurs, non rejoués ici. Aucun code applicatif, template, CSS, configuration ou schéma changé dans cette recette : pas de suite globale, frontend/build, cache dev ou migration supplémentaire. [Manifeste de cette recette](fixtures/portraits-recette-20261105.json).

## Décision et suite

Pour rejouer uniquement cette recette :

```powershell
docker compose exec -T php php vendor/bin/phpunit tests/Functional/AiPortraitFlowTest.php --filter testIdeaToAcceptedPortraitSceneAndFrozenVideoQuoteAcrossReplacement
```

Repère Symfony : `KernelBrowser` traverse les véritables routes, contrôleurs, formulaires et validations. `AiPortraits` porte la demande et son acceptation ; `AiCharacters` modifie la fiche ; `AiEpisodeFlow` prépare les scènes et leur devis. Seuls les échanges fournisseur sont remplacés par des réponses contrôlées. Doctrine conserve une copie des données de personnage dans la préparation : relire la fiche actuelle ne remplace donc pas automatiquement ce que la scène avait choisi. Le test vide l’EntityManager pour vérifier les données enregistrées en base, puis annule sa transaction et retire ses fichiers synthétiques.

Le raccord manquant est vérifié ; **R7.3b est clôturé techniquement en local simulé**, avec les limites ci-dessus. La génération réelle reste désactivée. **Prochain travail technique : R7.3c**, consolider la recette des personnages (modèles, anciennes données, personnages présents, confirmations). Ses essais audiovisuels réels appartiennent aux portes distinctes R7.4–R7.5, après accord sur compte et budget ; aucune qualité commerciale n’en est déduite. R7.2/R7.3 restent ouverts, essais humains reportés.

Aucun appel fournisseur réel, clé réelle consultée, coût, email réel, activation, publication, migration, cache dev actualisé, worker démarré/redémarré, push ou déploiement.
