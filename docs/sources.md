# Références

- Composer scripts : https://getcomposer.org/doc/articles/scripts.md#managing-the-process-timeout — désactivation du délai uniquement sur le script check, après interruption réelle de la suite à 300 secondes.

- ffprobe : https://ffmpeg.org/ffprobe.html — inspection des conteneurs/pistes via JSON ; l’application n’exécute aucun transcodage.
- Symfony Process 7.4 : https://symfony.com/doc/7.4/components/process.html — arguments sans shell et délai de processus.
- HttpFoundation 7.4 : https://symfony.com/doc/7.4/components/http_foundation.html — UploadedFile et BinaryFileResponse (HEAD/Range).

- Symfony Security 7.4 : https://symfony.com/doc/7.4/security.html — consultée pour le fournisseur Doctrine, form_login, limiteur, logout et empreinte de mot de passe en session.
- Symfony Mailer 7.4 : https://symfony.com/doc/7.4/mailer.html — consultée pour SMTP local, transport de test et envoi synchrone.
- Étude préliminaire fournisseurs et réseaux : sources et limites dans docs/platform-integrations.md ; aucun modèle IA, tarif commercial ou accès social définitif retenu.

- Installation officielle Symfony 7.4 : https://symfony.com/doc/7.4/setup.html — consultée pour les prérequis et l’initialisation.
- Inspiration de narration 3D : https://lusion.co/ — consultée ; aucun actif ni code repris.
- Les polices sont distribuées localement avec leur licence dans les paquets @fontsource/dm-sans et @fontsource/space-grotesk.
