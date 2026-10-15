# Intégrations plateformes — premier envoi YouTube validé localement

> Mise à jour du 16 octobre 2026 : publication immédiate prioritaire après comptes/bibliothèque. Connexion OAuth et premier transfert YouTube réels autorisés : traitement réussi, visibilité demandée non répertoriée, sans vérification visuelle dans YouTube Studio (docs/verification.md). Le worker Docker est automatique. Arrêt pour bilan avant Instagram/TikTok/IA. La cible du lancement comprend ensuite calendrier, IA BYOK, montage guidé et séries récurrentes avec validation par défaut et automatisation explicitement activée selon les capacités autorisées. docs/roadmap.md fait référence pour l’ordre et la vision. L’ancienne répartition V1 manuelle/V2 API ci-dessous est historique ; les capacités externes restent à revalider dans chaque lot.

## Étude préliminaire et points à résoudre
- TikTok : le Direct Post non audité reste privé. Vérifier audit, scope video.publish, UX de consentement, capacités du créateur et restrictions de planification avant implémentation : https://developers.tiktok.com/docs/en/content-posting-api-get-started et https://developers.tiktok.com/docs/en/content-sharing-guidelines.
- YouTube : vérifier OAuth, quotas et visibilité sur l’API actuelle. Les pages officielles consultées sont contradictoires sur la restriction privée des projets non vérifiés ; ne pas promettre la publication publique sans clarification et essai : https://developers.google.com/youtube/v3/docs/videos et https://developers.google.com/youtube/v3/docs/videos/insert.
- Instagram : choix du parcours de connexion, type de compte éligible, permissions/revue, transfert du média et confirmation à documenter à partir de Meta avant développement. Non validé dans ce lot.
- IA : fal fournit une file asynchrone, des contrôles d’accès aux médias et des licences par modèle ; les fichiers sont publics par URL par défaut. Replicate supprime par défaut les sorties API après une heure. Prévoir une copie privée durable, des domaines autorisés et vérifier conditions BYOK, tarifs, remboursement/échec, quotas et licence avant sélection. Sources : https://fal.ai/docs/documentation/model-apis/inference/queue, https://fal.ai/docs/documentation/model-apis/faq, https://fal.ai/legal/api-services et https://replicate.com/docs/topics/predictions/data-retention/.

## Notes historiques

TikTok, Instagram Reels et YouTube Shorts sont représentés uniquement pour expliquer la vision. Aucun token, endpoint de publication, compte connecté ou SDK réseau n’est configuré. V1 prévoit une publication manuelle.

En V2, vérifier les documentations officielles et les autorisations au moment du développement. Ne promettre aucune capacité sans validation. Prévoir un adaptateur par réseau, des capacités explicites, des tokens chiffrés au repos, scopes minimaux, renouvellement/revocation et aucune exposition au navigateur ou aux logs. Utiliser un identifiant stable de tentative pour éviter les doublons à la reprise, et conserver l’état et les erreurs utiles sans secrets.

La liste d’attente n’envoie aucun mail dans cette phase. S3, Stripe, génération IA et OAuth restent hors périmètre. Aucun compte externe requis en local.
