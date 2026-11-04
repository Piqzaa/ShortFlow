# R7.3b — portraits depuis une idée : cadrage court

5 novembre 2026. Le propriétaire autorise un cadrage court, sans génération ni dépense. **R7.3b reste ouvert : aucune génération de portrait implémentée.** R7.3a est terminé techniquement en local simulé ; R7.2/R7.3 restent ouverts, les premiers essais humains sont reportés. R7.3c et les essais audiovisuels réels restent distincts.

## Parcours proposé

1. Dans « Mes personnages », choisir « Depuis une idée » et décrire le personnage, ou reprendre volontairement le texte d’une série. Rien n’est lancé à l’ouverture de la série.
2. Relire une proposition de fiche : nom, apparence, tenue et style. Cette proposition reste modifiable ; elle ne crée pas automatiquement un portrait ni une distribution complète.
3. Pour un portrait, afficher le prompt exact, le modèle image, le compte fournisseur facturé, le format, la quantité et le coût estimé. **Une seule image par demande dans la première version** ; les variantes sont des demandes distinctes, jamais une série d’essais payants automatique.
4. Confirmer séparément le coût de cette image. La confirmation d’une vidéo ne vaut pas consentement pour générer un portrait.
5. Retrouver la demande et son résultat dans un brouillon privé. Distinguer attente, traitement, résultat à vérifier et incident ; ne pas présenter un résultat inconnu comme un échec autorisant un nouvel envoi.
6. Examiner le portrait. « Utiliser ce portrait » crée une fiche via le stockage et la bibliothèque existants. Un résultat généré n’est pas accepté automatiquement. Remplacer le portrait d’une fiche existante exige une action explicite, sa révision courante et une prévisualisation.
7. Retrouver cette fiche dans le sélecteur R7.3a et choisir sa présence par scène. Les épisodes et devis déjà préparés gardent leurs copies ; actualiser un brouillon reste volontaire et exige une nouvelle confirmation vidéo.

Le repli demeure l’import manuel actuel : si la connexion, le budget ou le modèle ne sont pas disponibles, l’utilisateur conserve sa description et peut importer une image créée ailleurs. Les vues supplémentaires restent importées dans cette première version ; leur génération cohérente n’est pas promise par la création d’un portrait.

## Modèle, connexion et coût

**Aucun modèle image, endpoint ou prix n’est retenu dans ce cadrage.** Le catalogue actuel décrit des opérations vidéo ; il ne suffit pas à autoriser une opération image, même avec une connexion fournisseur déjà enregistrée.

La prochaine étape consiste à vérifier la documentation officielle d’un candidat : génération depuis texte, réponse et récupération asynchrones, dimensions, prix par image et règles d’accès. Examiner la réutilisation d’une connexion existante avant d’ajouter un fournisseur ; vérifier explicitement la compatibilité API ou connexion navigateur. Aucun prix vidéo, compte connecté ou crédit disponible ne doit être assimilé à une autorisation d’achat d’image.

L’implémentation commencera avec ce contrat image documenté et des réponses simulées. Toute dépense réelle demandera un accord distinct sur le modèle, le compte facturé, le nombre d’images et le plafond de dépense. Ce document n’autorise aucun essai payant.

## Protections à conserver

- Propriétaire, CSRF, révision et validation serveur sur les écritures ; aucune image privée lisible par un autre compte.
- Empreinte du devis image incluant prompt, paramètres, quantité, modèle, tarif et identité de connexion. Un changement invalide le consentement précédent.
- Double clic et redélivrance ne créent pas deux demandes. Une réponse incertaine sans identifiant distant suspend le traitement ; aucune nouvelle génération automatique. Avec un identifiant connu, reprendre le suivi plutôt que renvoyer la demande.
- Un résultat reçu après modification du brouillon reste attaché à sa demande initiale ; il ne remplace jamais la fiche actuelle sans relecture.
- Taille, type réel, dimensions, normalisation et quota partagé existants contrôlés avant validation de la fiche. Les résultats en attente doivent aussi être bornés et inventoriés par la maintenance avant d’autoriser leur nettoyage.
- Le texte est conservé après incident ou manque de place. Une erreur d’import ne doit pas inviter à repayer une génération dont le résultat existe déjà.

## Raccords et ordre d’implémentation

`AiCharacter` exige aujourd’hui un portrait : garder les demandes non validées dans un brouillon distinct, plutôt que créer une fausse fiche avec une image de remplacement. Réutiliser `AiCharacters` et `AiReferenceStorage` à la validation, puis `AiCharacterSelection` pour les scènes. Les copies figées et l’historique de R7.3a restent intacts. Ne pas détourner `AiGeneration` ou les prix vidéo pour une opération image.

Ordre : contrat fournisseur documenté → brouillon et devis image → confirmation et traitement simulé idempotent → récupération/validation dans une fiche → recette propriétaire, panne, résultat tardif, double clic et stockage → revue de R7.3b. Aucune classe, migration ou dépendance spéculative ajoutée pour ce document.

Pour comprendre le découpage Symfony : le formulaire collecte l’idée ; le service contrôle le devis et le consentement ; Messenger porte le traitement asynchrone ; seul le résultat explicitement accepté rejoint l’entité de personnage existante. Cette séparation empêche de confondre une demande payante avec une fiche déjà utilisable.

## État et reprise

Cadrage livré uniquement. Aucun code applicatif, schéma, service ou test modifié ; aucune suite ou recette navigateur rejouée pour ce document. Les résultats antérieurs restent ceux de leurs lots. Contrôle documentaire par `git diff --check`, sans nouvelle revue indépendante revendiquée.

**Prochain point exact : vérifier et documenter le contrat d’un modèle image, puis préparer le premier sous-lot simulé.** Aucun appel fournisseur, coût, email réel, activation, publication, migration, cache dev actualisé, worker redémarré, push ou déploiement.
