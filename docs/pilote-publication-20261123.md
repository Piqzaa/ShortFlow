# Pilote fermé de publication — 23 novembre 2026

Le pilote vérifie qu’un créateur peut importer une vidéo prête, confirmer une publication et retrouver son résultat, puis programmer un deuxième lot. Les résultats de la préparation locale ci-dessous restent distincts des essais sur téléphone et des envois du pilote.

## Formats pour le pilote

Conseiller un export **MP4 H.264 8 bits, 9:16, 30 images/s, AAC si la vidéo a du son, de 3 secondes à 3 minutes et de 100 Mio maximum**. La bibliothèque accepte aussi les WebM compatibles. Pour préparer un même média sur les trois destinations, utiliser le profil MP4 ci-dessus. Les vidéos MOV et HEVC demandent un nouvel export avant l’import.

| Fichier synthétique | Contrôle effectué |
| --- | --- |
| MP4 H.264/AAC, 720 × 1280, 30 i/s, 4 s | Probe d’import et vérification des trois destinations réussis. Lecture effective dans Chrome à 390 et 1280 px. |
| MP4 H.264/AAC, image encodée en paysage avec rotation 90°, 4 s | Rotation reconnue par l’application et les trois contrôles de destination. Chrome affiche et lit le portrait en 720 × 1280 aux deux largeurs. |
| MP4 H.264/AAC, 2 s | Compatible avec l’import et la règle locale YouTube Shorts. Instagram et TikTok indiquent 3 secondes minimum. |
| MOV H.264 et MP4 HEVC | Refusés par le probe. Le formulaire indique désormais le réglage d’export conseillé avant le choix du fichier. |

Les contrôles de destination valident le format local. L’acceptation distante et la lecture sur appareil physique restent à vérifier pendant la recette du pilote.

## Disponibilité des réseaux

Lecture des seuls compteurs locaux de `shortflow`, sans appel réseau ni lecture des jetons.

| Réseau | Preuve historique | État local et point à confirmer |
| --- | --- | --- |
| YouTube | Transfert réel traité avec visibilité non répertoriée demandée. [Bilan](platform-integrations.md) | Deux connexions enregistrées, deux jetons d’accès à renouveler. Confirmer la capacité d’actualisation ou de reconnexion, puis vérifier l’état et la visibilité du nouvel envoi autorisé. |
| Instagram | Un Reel programmé réellement publié. [Bilan](instagram-publication.md) | Une connexion avec échéance locale valide. Vérifier l’accès Meta et la disponibilité depuis Internet du lien média prévu pour le pilote. |
| TikTok | Transfert Sandbox réussi en `SELF_ONLY`. [Bilan](tiktok.md) | Une connexion avec échéance locale du renouvellement valide. Réserver le parcours aux comptes Sandbox autorisés tant que l’accès public reste à établir. |

Une date locale valide ne démontre pas que l’autorisation est toujours acceptée par la plateforme. Commencer avec un réseau dont l’accès vient d’être confirmé et dont la visibilité convient au participant.

## Déroulé du premier essai

1. Sur un iPhone et un Android, importer chacun un export compatible et vérifier l’orientation, l’image et le son. Relever le réglage d’export exact et tout message qui bloque.
2. Depuis **Tes vidéos**, ouvrir **Nouvelle publication**, choisir un compte et renseigner le texte. Relire la destination, la visibilité et le créneau avant la confirmation. Prendre un clip prévu pour cet essai et choisir une visibilité de test lorsque la plateforme la permet.
3. Dans **Suivre mes envois**, attendre l’état publié et vérifier le résultat sur le compte de test. En cas de doute après un départ, vérifier l’état distant avant de retenter. Noter le statut et le temps d’assistance nécessaire.
4. Lors d’une seconde session, utiliser **Préparer un lot**, choisir deux vidéos, régler leurs dates dans leurs brouillons, relire la sélection et suivre les deux départs. Relever les étapes où le créateur a besoin d’aide.

Avant d’inviter les participants, vérifier les comptes capables de réaliser cet essai et faire un exercice de restauration des métadonnées et des médias dans un environnement isolé. Le bilan doit indiquer l’appareil, le réseau et la visibilité, le temps jusqu’au premier envoi réussi, la réussite du second lot et les incidents rencontrés. Ajouter le besoin de plusieurs comptes d’un même réseau uniquement s’il se présente pendant l’essai.
