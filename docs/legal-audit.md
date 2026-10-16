# VydFlow — base juridique et audit du 16 octobre 2026

## Périmètre et résultat

Le propriétaire confirme une micro-entreprise et une cible particuliers + professionnels. Coordonnées publiables fournies dans le chat, sans vérification auprès d’un registre : Alexandre Berrel, SIRET 10864041800019, APE 6201Z, adresse professionnelle à Schiltigheim et téléphone. Le SIREN correspond aux neuf premiers chiffres du SIRET. Contact retenu : dev@alexbuild.fr ; pas de publication de l’adresse Gmail personnelle dans les pages.

Les pages sont une **version de travail**, marquée comme telle et avec X-Robots-Tag noindex/nofollow. Cela n’interdit pas leur accès et ne constitue pas une protection de confidentialité. Aucune page ni application n’a été déployée. Aucun prix, médiateur, hébergeur, régime de TVA, certification, contrat de sous-traitance ou garantie de disponibilité n’a été inventé. Aucun skill juridique spécialisé n’était disponible ; le guide ux-flow-coding a servi au parcours, la recherche juridique utilise les sources officielles ci-dessous. Le travail ne constitue pas une certification de conformité.

## Pages et composants Symfony

- `/informations-legales` : index des documents.
- `/mentions-legales` : identité EI, coordonnées, activité et responsables ; hébergeur/registre/TVA/médiateur à finaliser.
- `/cgu` : fonctionnement réel, droits sur les contenus, publication confirmée, règles des réseaux, récupération/fin du service à cadrer.
- `/cgv` : offre non commercialisée, points B2C et B2B séparés. Cadrage, pas un contrat de vente final.
- `/confidentialite` : route existante préservée, politique reconstruite à partir du code actuel, incluant Instagram et les limites de conservation.
- `/cookies` : traceurs techniques, pas de bandeau inutile, contrôle HTTPS de production encore à réaliser.
- `/suppression-donnees` : actions existantes, demande par email et distinction copie locale/contenu publié sur le réseau. Pas de faux formulaire ni purge automatique annoncée.

`LegalController` associe les routes à une liste fermée de templates. `PrivacyController` garde la route historique. Les contrôleurs ne lisent ni base ni secrets. `config/packages/twig.yaml` centralise les coordonnées publiques dans legal_identity. Twig échappe ces valeurs. `templates/legal/base.html.twig` fournit titre, navigation, statut et contact ; les documents sont en HTML lisible sans JavaScript. `assets/styles/legal.css` reprend les tokens existants. Liens dans landing, compte et studio ; retour studio pour une session vérifiée. Information courte au moment de l’inscription, sans confondre contrat et consentement RGPD général. Aucune acceptation juridique versionnée n’est encore enregistrée.

Flux à comprendre : route → contrôleur → Twig avec les coordonnées publiques → Response et en-tête noindex. Aucun changement de schéma ni des permissions du studio. Ce sont des documents statiques et leur navigation, pas un moteur juridique.

## Inventaire des traitements réellement présents

| Traitement | Données / finalité | Stockage et destinataires actuels | Point à finaliser |
|---|---|---|---|
| Compte | Email, hash du mot de passe, date, confirmation | PostgreSQL local, exploitant | Base contractuelle à formaliser ; droits et suppression du compte |
| Confirmation/récupération | Empreinte, finalité, expiration, consommation ; email transactionnel | Base locale et Mailpit local | Expiration 24 h/30 min ≠ purge ; transport de production et nettoyage |
| Bibliothèque | Vidéos et métadonnées, affichage/gestion | Fichiers privés locaux, propriétaire | Durées, export, sauvegardes ; rôle de sous-traitant selon données des clients |
| Connexions | Chaîne/profil, jetons chiffrés, expiration | Base locale ; Google/Meta lors des appels autorisés | Contrats/rôles/transferts, révocation distante et synchronisation |
| Publications | Source, textes/options, destination, état, identifiants distants | Base locale ; réseau choisi après confirmation | Durée de l’historique et séparation des données API à purger |
| Sécurité | Session, compteurs IP/email pseudonymisé | Session/cache serveur | Justification intérêt légitime, durée/purge des sessions, logs et attributs cookies |
| Liste d’attente | Email/date, sans email automatique | PostgreSQL local, exploitant | Revoir finalité/base légale et conservation ; aucune campagne ni migration en clients implicite |
| Facturation/support | Pas de paiement ni outil de support intégré | Non mis en place | Inventaire futur, base légale, archivage légal, prestataires et demandes de droits |

La base légale de chaque finalité doit être validée et indiquée avant la collecte publique. Pistes à examiner : contrat pour le service demandé, intérêt légitime documenté pour la sécurité, obligation légale pour les futures pièces comptables, consentement séparé si prospection/traceurs optionnels. La simple présence d’un lien vers une politique ne prouve pas un consentement. Aucun délai légal de conservation uniforme n’est attribué à toutes les données.

## Décisions du propriétaire encore nécessaires

- Hébergeur réel : identité légale, adresse/téléphone, localisation du calcul, base, stockage, sauvegardes et sous-traitants ; domaine et email final.
- Extrait/mention d’immatriculation exacte, régime de TVA et éventuel numéro, assurance professionnelle le cas échéant. Micro-entreprise ≠ automatiquement franchise en base de TVA.
- Médiateur compétent référencé, désignation/convention et coordonnées exactes ; aucune inscription ni dépense autorisée ici.
- Tarifs B2C TTC/B2B adaptés à la TVA, quotas, périodicité, engagement, renouvellement, essai, résiliation et remboursements ; territoires/langues, éligibilité et support.
- Durées concrètes justifiées, sauvegardes et capacité d’export/suppression ; prestataire email/paiement/mesure d’audience éventuelle.

## Liste de contrôle avant ouverture publique

### Documents et contrat

- [ ] Compléter mentions légales avec registre, TVA applicable et hébergeur ; conserver un contact joignable.
- [ ] Finaliser CGU/CGV personnalisées ; versions datées, preuve d’acceptation, conservation/remise sur support durable, procédure de modification.
- [ ] Cadrer l’offre numérique, compatibilité, quotas, mises à jour, garanties légales applicables et éventuel encadré réglementaire requis. Ne pas supprimer les droits impératifs par une clause « bêta » ou « aucune responsabilité ».
- [ ] Rétractation B2C : information et formulaire type, qualification du service, demande d’exécution anticipée distincte si retenue, remboursement adapté. Pas de renonciation automatique à l’ouverture d’un compte.
- [ ] Fonctionnalité de rétractation en ligne requise pour les contrats concernés depuis le 19 juin 2026 ; implémentation distincte de la résiliation électronique des abonnements. Ces boutons n’existent pas encore et ne doivent pas être simulés par de simples liens.
- [ ] Médiateur désigné et procédure après réclamation préalable. Ne pas réutiliser l’ancien lien européen ODR/RLL supprimé en 2025.
- [ ] B2B : règles de facturation, échéances, pénalités/indemnité de recouvrement, droits applicables à certains petits professionnels ; ne pas transposer mécaniquement au B2C.
- [ ] Qualifier obligations d’accessibilité du service de commerce électronique et éventuelle exemption selon les critères réels. Ne pas afficher de déclaration « conforme RGAA » sans audit.
- [ ] Préparer facturation, archivage et obligations de facturation électronique selon calendrier et situation réelle ; Stripe seul ne remplace pas cette analyse.
- [ ] Qualifier le rôle du service au regard des obligations des intermédiaires/hébergement et prévoir signalement/traitement des contenus illicites si applicable. Ne pas revendiquer une qualification juridique sans analyse de l’architecture finale.

### RGPD et exploitation

- [ ] Registre des traitements pour l’activité régulière, rôles responsable/sous-traitant par finalité, bases légales et information au point de collecte.
- [ ] DPA/accord article 28 lorsque VydFlow traite pour le compte d’un client : instructions, catégories, confidentialité, sécurité, sous-traitants ultérieurs, assistance droits/incidents, restitution/effacement et audit. Annexe opérationnelle réelle, pas une certification.
- [ ] Contrats des prestataires et cartographie des transferts hors EEE ; mécanismes/garanties applicables et accès à l’information. Ne pas promettre « tout reste en France » alors qu’un utilisateur publie chez Google/Meta.
- [ ] Durées définies et purges testées : comptes non confirmés/inactifs, codes expirés/utilisés, historique, liste d’attente, sessions/logs, sauvegardes, données supprimées et archives nécessaires.
- [ ] Procédure d’accès/rectification/effacement/opposition/portabilité, identification proportionnée, suivi des délais légaux ; export et suppression sans altérer les données d’un autre propriétaire.
- [ ] Sécurité de production, contrôle d’accès, chiffrement/gestion des clés, sauvegardes et restauration, logs minimisés, réponse aux violations (qualification du risque, registre, notification CNIL/personnes lorsque requise).
- [ ] Inventaire cookies sur le vrai domaine, durée, Secure/HttpOnly/SameSite, session et CSRF ; pas d’outil optionnel avant consentement. Aucun marketing ajouté par défaut.

### Plateformes

- [ ] YouTube : liens conditions/politique Google, information sur l’usage des YouTube API Services et acceptation de la politique selon les règles API, sans assimiler toute acceptation au consentement RGPD.
- [ ] YouTube : distinguer les données utilisateur des données API. Mettre en œuvre la suppression des données API demandée dans les délais contractuels (au plus tard sept jours), contrôle des révocations externes et purge/rafraîchissement suivant les règles Google. La déconnexion actuelle efface les tokens, mais l’historique et l’identité distante persistent ; ne pas annoncer une conformité API complète.
- [ ] Meta : valider les informations/politiques et le parcours de suppression exigés par l’application choisie, rôles/revue, HTTPS et essai autorisé. La page d’instructions n’est pas un callback automatique de suppression ni une approbation Meta.
- [ ] IA BYOK future : revoir prestataires, coûts directs, droits sur les contenus, données envoyées, entraînement éventuel selon les contrats et besoins d’information spécifiques avant activation.

## Sources officielles consultées le 16 octobre 2026

- [Service Public — mentions du site d’une entreprise](https://entreprendre.service-public.gouv.fr/vosdroits/F37351), fiche vérifiée le 13 octobre 2026 : EI, coordonnées, immatriculation, TVA applicable et hébergeur. L’ancien lien F31228 pointe désormais sur les cookies ; ne pas reprendre uniquement un extrait de recherche ancien.
- [Service Public — CGV](https://entreprendre.service-public.gouv.fr/vosdroits/F33527) et [commerce en ligne](https://entreprendre.service-public.gouv.fr/vosdroits/F23455) : informations B2C/B2B, commande, garanties et rétractation en ligne.
- [Légifrance — services numériques](https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000006069565/LEGISCTA000044132865/2026-06-24/) : garanties des contenus/services numériques ; analyse à appliquer à l’offre finale.
- [DGCCRF — obligations de médiation](https://www.economie.gouv.fr/mediation-conso/vous-etes-un-professionnel/vos-principales-obligations-0) et [médiation, suppression de l’ancienne plateforme ODR](https://www.economie.gouv.fr/dgccrf/les-fiches-pratiques/la-mediation-de-la-consommation-ce-que-vous-devez-savoir).
- [CNIL — transparence](https://www.cnil.fr/fr/conformite-rgpd-information-des-personnes-et-transparence), [durées de conservation](https://www.cnil.fr/fr/passer-laction/les-durees-de-conservation-des-donnees) et [cookies, Service Public](https://entreprendre.service-public.gouv.fr/vosdroits/F31228).
- [CNIL — clauses responsable/sous-traitant](https://www.cnil.fr/fr/clauses-contractuelles-types-entre-responsable-de-traitement-et-sous-traitant) et [notification des violations](https://www.cnil.fr/fr/services-en-ligne/notifier-une-violation-de-donnees-personnelles).
- [Service Public — accessibilité](https://entreprendre.service-public.gouv.fr/vosdroits/R77385) et [facturation électronique](https://entreprendre.service-public.gouv.fr/vosdroits/F39785).
- [Google — YouTube API Developer Policies](https://developers.google.com/youtube/terms/developer-policies), sections III.A, III.D et III.E : politiques, révocation, conservation et demandes de suppression.

## Tester et limites

`php scripts/check.php` dans le conteneur prépare/migre exclusivement shortflow_test avant PHPUnit. `LegalPagesTest` contrôle l’accès public aux sept routes, les liens, les coordonnées et le statut de brouillon/noindex. Ces tests ne valident ni la réalité des coordonnées, ni la conformité juridique des clauses. `npm run check` / `npm run build` vérifient le frontend ; vérifier lecture/clavier/mobile et retours selon la session. Résultats exacts dans docs/verification.md.

La finalisation attend les décisions ci-dessus et la mise en place des procédures réelles. Une relecture juridique personnalisée avant commercialisation est recommandée, notamment sur les garanties numériques, DPA et conditions B2B/B2C. Aucune promesse « 100 % conforme » n’est formulée.
