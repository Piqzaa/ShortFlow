# Publication immédiate — cadrage du 15 octobre 2026

Le propriétaire place cette fonction avant le studio IA : sélectionner une ou plusieurs vidéos de sa bibliothèque et les publier depuis VydFlow vers un, deux ou trois réseaux parmi TikTok, YouTube et Instagram. Le nettoyage d’interface est réalisé. Le présent document reste le cadrage multi-réseaux ; le premier lot YouTube est implémenté et en validation locale. Sa configuration, ses limites et son guide pédagogique sont dans docs/youtube.md. Les essais réels restent à réaliser.

## Place dans la vision de lancement

Vision élargie validée le 15 octobre : la publication est la dernière étape commune aux vidéos importées et générées, avec montage facultatif, textes adaptés, calendrier et séries récurrentes. Le calendrier, le montage, l’IA et les séries appartiennent à des lots suivants ; le présent lot reste centré sur la publication immédiate. Roadmap de référence : docs/roadmap.md. Le lot YouTube est développé localement (docs/youtube.md), sans essai réel validé ; arrêt pour bilan avant Instagram/TikTok/IA. Les séries doivent préparer les vidéos avant l’heure cible, bloquer la diffusion sans validation lorsqu’elle est requise, et permettre pause/reprise sans doublons.

## Parcours cible

1. Connecter ses comptes via les autorisations officielles, une fois ; afficher le compte destinataire et les permissions accordées, permettre la déconnexion.
2. Sélectionner des vidéos dans sa bibliothèque, avec compteur et désélection. Chaque vidéo reste une publication distincte : trois vidéos vers deux réseaux donnent six destinations suivies.
3. Choisir les réseaux et comptes, une légende commune modifiable par vidéo/réseau, puis les champs spécifiques nécessaires : titre YouTube, visibilité, audience, interactions et déclarations TikTok notamment. Aucune visibilité TikTok présélectionnée.
4. Vérifier la compatibilité réelle de chaque vidéo pour chaque réseau avant tout envoi. Une vidéo admise dans la bibliothèque n’est pas automatiquement publiable partout ; ne pas convertir ni rogner sans choix explicite.
5. Présenter un récapitulatif des vidéos, comptes et options puis lancer avec une action explicite. Aucun transfert anticipé au fournisseur.
6. Afficher l’état par vidéo et destination : en attente, transfert, traitement, publiée ou échec. Un résultat partiel reste lisible ; une reprise ne republie pas les destinations déjà réussies. En cas de réponse réseau incertaine, rapprocher le statut avant de retenter.

« En même temps » signifie un lancement commun avec des traitements indépendants. Les API ne permettent pas une transaction atomique entre les trois réseaux ni une heure exacte d’apparition commune.

## Ordre de réalisation proposé

Premier lot vertical : connexions et publication YouTube, sélection multiple et suivi inclus. Ensuite Instagram Reels, puis TikTok Direct Post, en conservant le même parcours. Cet ordre est un choix de développement, pas une garantie d’accès aux API. Calendrier et IA viennent ensuite.

Symfony : les formulaires contrôlent sélection, propriété et champs ; Doctrine conserve les demandes et états par destination ; Messenger exécute les vrais transferts en arrière-plan ; un adaptateur concret par réseau traite ses règles. Ajouter transport et worker au moment de ce lot asynchrone, pas pendant le nettoyage. Tokens chiffrés côté serveur, OAuth avec state, scopes minimaux, secrets hors Git, aucun token dans le navigateur ou les logs. Empêcher doubles clics et doublons côté serveur ; ne pas supposer que toutes les API proposent une clé d’idempotence.

## Accès externes à préparer

- YouTube : projet développeur, API activée et client OAuth, comptes de test et URI de retour autorisées. La référence actuelle videos.insert indique le scope youtube.upload et précise que les projets non vérifiés ne sont pas restreints au privé ; ne pas reprendre automatiquement l’ancienne règle d’audit des uploads. Revalider accès, consentement et quotas au moment de l’intégration.
- Instagram : vérifier le parcours Instagram Login et l’éligibilité Business/Creator, les permissions de publication, le mode développement et les exigences de revue. La page Meta directe n’a pas pu être lue avec l’outil web lors du cadrage ; cette intégration demande une vérification complémentaire avant codage.
- TikTok : application, scope video.publish, audit pour visibilité publique. En mode non audité, les règles officielles indiquent publication SELF_ONLY, comptes privés et jusqu’à cinq utilisateurs actifs par jour. L’expérience doit respecter les choix et déclarations du créateur. Le service destiné au public ne doit pas être présenté à l’audit comme un simple outil interne.
- TikTok impose PULL_FROM_URL pour les vidéos déjà stockées sur le serveur, sur un domaine/préfixe vérifié. Le localhost et la route de lecture privée actuelle ne sont donc pas suffisants : prévoir un accès média temporaire limité au fichier nécessaire dans un environnement accessible et autorisé, sans rendre la bibliothèque publique. Pas de tunnel ou déploiement dans le lot actuel.

Aucune application externe créée, aucun identifiant réel demandé ou utilisé, aucun compte social connecté, aucune vidéo envoyée. Le développement local pourra tester les clients HTTP avec des réponses simulées ; une publication réelle ne sera annoncée comme validée qu’après un essai autorisé sur les comptes de test.

## Nettoyage effectué et vérification

templates/studio/index.html.twig : retrait du badge permanent Email confirmé et de la mini-roadmap. templates/studio/base.html.twig : retrait du badge Bêta et des entrées inactives Studio IA/Calendrier/Connexions. templates/account/base.html.twig : retrait de la signature bêta redondante. templates/library/show.html.twig : retrait de la promesse de calendrier prochain. Les limites, erreurs, confirmations d’action et l’indication de confidentialité restent utiles et sont conservées. Les aperçus illustratifs de la landing restent identifiés.

Ce nettoyage concerne le rendu Twig uniquement : ni le contrôle ROLE_VERIFIED ni les services métier ne changent. Pour tester : ouvrir le studio connecté, vérifier les deux liens actifs, importer/ouvrir une vidéo et vérifier l’absence des mentions retirées ; vérifier également une page de connexion. Les résultats des contrôles exécutés sont consignés dans docs/verification.md.

## Sources officielles consultées le 15 octobre 2026

- https://developers.google.com/youtube/v3/docs/videos/insert
- https://developers.tiktok.com/docs/en/content-posting-api-get-started
- https://developers.tiktok.com/docs/en/content-sharing-guidelines
- Meta à revalider : https://developers.facebook.com/docs/instagram-platform/instagram-api-with-instagram-login/content-publishing/
