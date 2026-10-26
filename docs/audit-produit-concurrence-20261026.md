# Audit produit : publication, organisation et comptes sociaux

Suite de l’audit : le lot multi-comptes et la sélection indépendante par vidéo ont ensuite été implémentés. Les constats ci-dessous décrivent l’état avant ces changements ; voir [comptes-sociaux-multiples.md](comptes-sociaux-multiples.md) et [publications-par-video.md](publications-par-video.md).

État au 26 octobre 2026. Revue du code Symfony/Twig/Stimulus et documentations officielles concurrentes. Deux revues indépendantes en lecture seule : parcours UX et architecture des comptes sociaux. Pas d’accès aux comptes sociaux réels, de génération payante ou d’essai de publication pour cet audit. Une fonction annoncée par un concurrent n’a pas été essayée dans son produit.

## Fonctionnement actuel de VydFlow

Deux entrées convergent vers le même préparateur de publication : vidéos importées dans la bibliothèque, ou vidéos générées/assemblées puis validées depuis le Studio et les Séries. Le brouillon durable fige les vidéos, les textes et les comptes attendus. Le calendrier et les workers réutilisent ces préparations. Le suivi et les statistiques sont distincts pour YouTube, Instagram et TikTok.

Les séries portent aujourd’hui un thème, une identité, les modèles, le montage et les réglages éditoriaux. Leur préparation récurrente peut produire les épisodes dans le cadre déjà autorisé, mais validation vidéo et confirmation de publication restent des étapes distinctes. Les vidéos importées ne disposent pas encore d’un regroupement durable sous un nom de série.

Les protections présentes sont utiles au produit : brouillons privés, CSRF et propriété ; sources figées ; prévention des doubles confirmations ; pause/reprise ; contrôles de coût fournisseur ; refus d’envoyer vers un autre compte après changement de connexion. Leur conservation passe avant l’ajout de nouveaux réglages.

## Ce que proposent les concurrents

| Produit | Fonction documentée | Enseignement pour VydFlow |
| --- | --- | --- |
| Buffer | Plusieurs comptes d’un même réseau peuvent être connectés ; choix des canaux dans le compositeur et adaptation des textes par réseau. | Plusieurs comptes enregistrés et une destination clairement choisie par publication répondent à un usage courant. |
| Metricool | Une marque regroupe une connexion de chaque réseau ; plusieurs marques permettent plusieurs connexions d’un même réseau. | Un regroupement par activité/client est possible plus tard ; il n’est pas nécessaire pour un premier choix de compte. |
| Publer | Les créneaux de file d’attente sont propres à chaque compte ; le créateur choisit le compte avant de programmer. | Une future file récurrente doit être liée au compte choisi, et pas seulement au nom du réseau. |
| Revid | Génération, édition, programmation/publication sur les trois réseaux et production Auto-Mode réunies. | Les transitions création → validation → publication doivent rester lisibles et permettre de reprendre un projet. |
| AutoShorts.ai | Une série stocke le sujet et les réglages créatifs et peut choisir un compte par plateforme ; les vidéos programmées peuvent partir sans clic de validation. | Afficher clairement les différences entre préparation, validation requise et publication programmée. VydFlow conserve son parcours supervisé actuel. |

Sources officielles consultées le 26 octobre : [comptes Buffer](https://support.buffer.com/articles/connecting-your-channels-to-buffer-HvWLgAJvL9), [programmation Buffer](https://support.buffer.com/article/642-scheduling-posts), [marques Metricool](https://help.metricool.com/whats-a-brand-in-metricool-and-how-does-it-work-5l78b), [files Publer](https://help.publer.com/en/article/how-to-automatically-schedule-posts-jj58si/), [fonctions Revid](https://www.revid.ai/features), [séries AutoShorts](https://autoshorts.ai/faceless-video-automation), [validation AutoShorts](https://autoshorts.ai/guides/review-before-posting). Comparaison fonctionnelle uniquement : pas de promesse d’équivalence de qualité, de quotas ou de capacités sociales réelles.

## Détails manquants et priorités

| Priorité / gravité | Constat local | Effet utilisateur | Correction proposée |
| --- | --- | --- | --- |
| P1 / moyenne | La sélection multiple du compositeur utilisait un réseau et une date communs. | Impossible de préparer les trois exemples demandés en une sélection. | Lot en cours livré séparément : un brouillon par vidéo, avec réseaux et date propres ; confirmation individuelle. |
| P1 / élevée lors d’une évolution naïve | Une connexion unique par propriétaire et réseau ; les callbacks peuvent remplacer le compte précédent. | Deux activités/chaînes ne peuvent pas être conservées ensemble. | Plusieurs connexions enregistrées, puis un compte choisi par réseau et préparation ; migration et routage explicite de bout en bout. |
| P1 / moyenne | L’Activité reçoit publications et brouillons, pas les épisodes prêts à valider ou séries en pause. | Une vidéo préparée automatiquement peut attendre sans être visible dans le point d’entrée principal. | Bloc global « À faire » : vidéos à valider, rédactions interrompues, séries en pause, liens vers l’action existante. |
| P1 / moyenne | Le calendrier interroge les préparations de publication, pas les occurrences des séries avant création de brouillon. | La production attendue est absente tant que la vidéo n’est pas validée et préparée pour publication. | Montrer « production prévue » et « à valider », visuellement distincts d’une publication confirmée. |
| P2 / moyenne | Certains affichages d’import, d’envoi et de statistiques héritent du fuseau Twig Paris. | Une personne ayant choisi un autre fuseau voit des horaires incohérents selon l’écran. | Dates d’activité dans `app.user.timezone`, créneaux dans le fuseau conservé par la préparation ; afficher le fuseau. |
| P2 / moyenne | Absence de duplication d’une préparation réussie en nouveau brouillon. | Recommencer les textes et réglages pour un contenu proche. | Copier les textes/réglages avec nouvelle source/date ; retirer tout identifiant/consentement d’envoi et confirmer à nouveau. |
| P2 / moyenne | L’import vidéo est unitaire alors que la bibliothèque permet une sélection multiple. | Préparer une semaine de contenus impose des imports successifs manuels. | File séquentielle d’import avec état par fichier et reprise des seuls échecs, réutilisant la validation privée existante. |
| P2 / moyenne | Les statistiques n’ont pas de recherche par titre, période ni tri par résultat. | Difficile de retrouver les meilleurs contenus après plusieurs publications. | Filtres et tri sur les relevés déjà disponibles ; les courbes historiques demanderaient un stockage distinct. |
| P3 / faible | Bibliothèque recherchable par titre, mais sans filtres « jamais publiée », format ou tri au choix. | Les sources, exports et contenus déjà diffusés deviennent difficiles à retrouver. | Réutiliser les informations existantes pour ces filtres avant d’ajouter des dossiers complexes. |
| P2 / moyenne | Les vidéos importées ne peuvent pas être regroupées en série éditoriale. | Une série filmée par le créateur doit être organisée avec les brouillons et le calendrier. | Ajouter ultérieurement un regroupement de contenus importés, en conservant les préparations indépendantes et les validations. |

Points de code vérifiés : `DashboardController::index` et `PublicationDraftRepository::dashboard` ; `PublicationDraftController::calendar` ; `config/packages/twig.yaml`, `templates/library/show.html.twig`, `templates/publication/_statistics.html.twig` ; `PublicationDraftController` et ses actions ; `upload_controller.js` ; `PublicationOverview` et `templates/publication/index.html.twig` ; `VideoAssetRepository::search` ; `AiSeries` et `AiSeriesStudio`.

## Multi-comptes : périmètre minimal recommandé

Premier lot recommandé : plusieurs chaînes YouTube, comptes Instagram et comptes TikTok enregistrés ; **un compte choisi par réseau pour chaque préparation**. Une vidéo peut donc partir sur une chaîne YouTube choisie, un Instagram choisi et un TikTok choisi. Les réglages et résultats actuels restent indexés par réseau.

Publier le même contenu simultanément sur deux chaînes YouTube serait un autre lot : il faut alors des destinations `(réseau, compte)` et des résultats/idempotences par destination. Ce n’est pas un simple ajout de cases à cocher.

### Pourquoi il faut modifier le serveur

- `YouTubeConnection`, `InstagramConnection` et `TikTokConnection` sont liés en `OneToOne` au propriétaire. Leurs services font `findOneBy(owner)`.
- YouTube remplace la connexion ; Instagram et TikTok mettent à jour la connexion existante lors d’un changement de compte.
- `MultiNetworkPublishing::destinations` expose une destination par réseau. Le brouillon conserve déjà `accounts[network] = identifiant distant`, ce qui peut rester compatible avec le premier lot.
- Les workers, reprises et statistiques doivent trouver la connexion du propriétaire correspondant à l’identité attendue, sans repli vers un compte courant.
- Les récurrences stockent les réseaux mais ne figent pas encore le compte social choisi avec chaque occurrence.

### Découpage recommandé

1. Migration additive conservant les connexions actuelles : `ManyToOne(owner)` et unicité `(owner, identité distante)` dans les trois tables existantes.
2. Connexions : « Ajouter un compte », « Reconnecter ce compte » et « Déconnecter ce compte ». Les actions portent une connexion précise, avec propriété et CSRF. Un retour OAuth vers un autre compte ne doit pas écraser la cible de reconnexion.
3. Préparateur : choisir le compte et afficher son nom/identité, puis conserver l’instantané attendu avec le brouillon. Garder le choix manuel visible lors d’une reprise d’échec.
4. Workers, statistiques, reprise et programmation : résolution par propriétaire et identité attendue ; un compte absent ou expiré bloque cette destination avec une action de reconnexion.
5. Séries : figer les comptes choisis avec les réglages/occurrences. Pour les anciennes occurrences devenues ambiguës, demander un choix lors de la préparation, sans changer une programmation confirmée.

Tests indispensables : anciens brouillons, maintien des connexions actuelles, deuxième compte, reconnexion du même compte, mauvais compte renvoyé par OAuth, compte d’un autre propriétaire, changement de nom, retrait/expiration avant échéance, double confirmation, reprise et statistiques du bon compte. Les règles d’unicité actuelles des publications devront être revues seulement si l’envoi simultané à plusieurs comptes d’un même réseau est ajouté.

## Ordre de travail conseillé

1. Terminer le choix réseaux/date par vidéo et ses vérifications.
2. Livrer les connexions multiples avec choix d’un compte par réseau, puis leurs instantanés dans les séries.
3. Rendre visibles les validations attendues dans l’Activité et le calendrier de production.
4. Harmoniser les fuseaux, dupliquer les brouillons et importer plusieurs vidéos.
5. Améliorer ensuite recherche, filtres et regroupements éditoriaux.

Ce rapport n’annonce pas les connexions multiples comme disponibles. Aucune limite commerciale, facturation, activation de publication autonome ou connexion réelle supplémentaire n’est introduite par cet audit.
