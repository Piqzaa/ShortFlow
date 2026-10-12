# Intégrations plateformes — aucune active

TikTok, Instagram Reels et YouTube Shorts sont représentés uniquement pour expliquer la vision. Aucun token, endpoint de publication, compte connecté ou SDK réseau n’est configuré. V1 prévoit une publication manuelle.

En V2, vérifier les documentations officielles et les autorisations au moment du développement. Ne promettre aucune capacité sans validation. Prévoir un adaptateur par réseau, des capacités explicites, des tokens chiffrés au repos, scopes minimaux, renouvellement/revocation et aucune exposition au navigateur ou aux logs. Utiliser un identifiant stable de tentative pour éviter les doublons à la reprise, et conserver l’état et les erreurs utiles sans secrets.

La liste d’attente n’envoie aucun mail dans cette phase. S3, Stripe, génération IA et OAuth restent hors périmètre. Aucun compte externe requis en local.
