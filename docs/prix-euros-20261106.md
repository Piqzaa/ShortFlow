# Estimations en euros et actualisation BCE — 6 novembre 2026

## Résultat

Les recommandations Studio/Séries, options de résolution, catalogue Studio, total des plans et récapitulatifs de lancement utilisent une estimation en euros. La date du taux et la devise de facturation sont indiquées. Le récapitulatif conserve le montant USD dans « Détail du coût fournisseur » ; les confirmations individuelles montrent aussi le montant d’origine.

Les prix du catalogue, calculs et montants persistés, empreintes des devis, consentements, budgets et payloads fournisseur restent en USD. La conversion ne modifie pas les tarifs des modèles. Les données historiques et budgets réservés en dollars restent libellés dans leur devise d’origine. Il ne s’agit pas d’un prix garanti en euros ni d’un changement de monnaie de facturation.

Source officielle lue : [BCE, taux de référence](https://www.ecb.europa.eu/stats/policy_and_exchange_rates/euro_reference_exchange_rates/html/index.en.html), publication du **6 novembre 2026, 1 EUR = 1,1165 USD**. Le taux est indicatif ; frais, taxes et taux réel de l’établissement de paiement peuvent différer. Exemples : 0,41 USD ≈ 0,37 EUR ; 0,62 USD ≈ 0,56 EUR. Les totaux USD sont convertis avant arrondi, au lieu d’additionner des arrondis en euros par plan.

## Actualisation sans intervention quotidienne

- Le worker `background` existant vérifie le taux lorsqu’il est inactif, au plus toutes les cinq minutes localement. Il ne contacte pas la BCE si le taux du jour est déjà disponible.
- Quand un taux doit être recherché, la requête publique BCE est limitée à une tentative par six heures, échecs compris. Verrou non bloquant partagé entre processus ; écriture atomique du cache dans `var/finance`, ignoré par Git. Aucun worker supplémentaire.
- Requête GET vers l’unique URL XML BCE, sans identifiant ni clé, sans redirection et avec délai maximal de dix secondes. XML borné, réseau XML désactivé et DOCTYPE refusé ; date future, taux nul/invalide et régression de date rejetés.
- Une panne conserve le dernier taux. Après dix jours calendaires, ou en l’absence de taux valide, l’interface revient explicitement aux dollars et signale la conversion indisponible. Les ouvertures de page ne font aucune requête de taux.
- `config/ai_exchange_rate.json` conserve une référence de démarrage vérifiée. Le cache actualisé prend ensuite le relais. L’application et son worker doivent tourner pour l’actualisation ; au redémarrage, la recherche reprend sans saisie utilisateur.

Commande de dépannage ou d’exploitation : `php bin/console app:ai:exchange-rate:refresh`. Pas besoin de l’exécuter chaque jour. Les tarifs USD des modèles restent un sujet de maintenance séparé ; la commande ne les met pas à jour.

## Vérifications et activation locale

- Un GET réel du fichier XML public BCE via la commande a réussi : taux 1,1165 daté du 6 novembre, cache créé. Aucune API IA ni authentification fournisseur utilisée.
- Quatre unitaires / 28 assertions : conversion inverse, arrondis et petits montants, taux manquant/corrompu/futur/périmé, ciblage du seul worker background inactif, absence de soumission de modèle, conservation du dernier taux et limitation des reprises après échec.
- Trois fonctionnels / 83 assertions : durées/prix 720/1080 en USD conservés, préparation manuelle et IA, empreinte requise et confirmation idempotente sans appel fournisseur. Pas de suite PHP globale ni revue indépendante.
- 210 frontend, check et build verts ; 41 Twig, conteneur, style et PHPStan des six nouveaux fichiers PHP/banc verts. Avertissement de gros chunk Vite existant. Première vérification Twig échouée sur un ancien cache ignorant les nouvelles fonctions ; caches reconstruits. Import inutilisé et inférence de pureté du banc corrigés avant les contrôles finaux.
- Quatre états Chrome 390/1280 Studio/récapitulatif : 720p 0,37 EUR puis 1080p 0,56 EUR, totaux, date BCE, montant 1,23 USD conservé dans le détail, aucune erreur JS/console ni débordement ; capture mobile relue. Réseau intercepté, aucun POST propriétaire.
- Quatre GET dev authentifiés HTTP200 dans un banc isolé en lecture seule : 27 compteurs inchangés et zéro HTTP fournisseur ; taux courant confirmé. Aucun appel BCE depuis les pages.
- Cache dev actualisé. Garde préalable : zéro message, génération active, épisode actif et montage actif. Seul `background-worker`, déjà actif, redémarré ; présence du listener dans le conteneur effectif vérifiée. Autres workers inchangés.

Aucune génération, dépense fournisseur, clé affichée/exportée, modification de contenu propriétaire, migration, publication, push ou déploiement. Les euros restent une présentation, sans nouveau consentement ni lancement déduit. R7 reste ouvert ; relecture propriétaire puis simplification des Séries à valider séparément.
