# Abonnement VydFlow · Stripe en mode test

Ouvre **Mon compte → Abonnement** pour comparer Free et Abonné. VydFlow affiche l’offre active, le stockage utilisé et, le cas échéant, la date de fin de la période payée. Le bouton de souscription ouvre Stripe Checkout. Le bouton de gestion ouvre le portail Stripe pour les factures, le moyen de paiement et l’annulation.

## Configurer un bac à sable Stripe

1. Dans le bac à sable, créer un prix **mensuel récurrent en EUR**. Le tarif affiché vient du prix Stripe sélectionné. Les captures locales utilisent un exemple fictif à 9,00 €.
2. Activer le portail client du bac à sable. Autoriser l’accès aux factures, la mise à jour de la carte et **l’annulation en fin de période**. VydFlow vérifie ce dernier réglage avant d’ouvrir le portail. Pour utiliser une configuration dédiée, renseigner son identifiant `bpc_…` dans `STRIPE_TEST_PORTAL_CONFIGURATION_ID` dans le fichier privé `.env.local`. [Portail Stripe](https://docs.stripe.com/customer-management/configure-portal)
3. Dans le fichier privé `.env.local`, configurer `STRIPE_TEST_SECRET_KEY`, `STRIPE_TEST_PRICE_ID` et `STRIPE_TEST_WEBHOOK_SECRET`. Le serveur accepte uniquement une clé API de test.
4. Rediriger les événements vers `POST /webhooks/stripe`. Pour une recette sur le PC, lancer Stripe CLI et utiliser son secret de signature. Quand le point d’entrée est hébergé, utiliser le secret du webhook enregistré pour cette adresse. [Guide Stripe des webhooks](https://docs.stripe.com/webhooks)

```bash
stripe listen --events checkout.session.completed,customer.subscription.created,customer.subscription.updated,customer.subscription.deleted,invoice.paid,invoice.payment_failed --forward-to localhost:8000/webhooks/stripe
```

## Recette

- Démarrer en Free et terminer Checkout avec les données de test Stripe. Le retour sur VydFlow indique une confirmation en attente si la notification signée arrive après le navigateur. Actualiser l’état, puis vérifier le quota de 3 Go et l’accès au portail. [Cartes de test Stripe](https://docs.stripe.com/testing)
- Annuler dans le portail avec effet à la fin de la période. Vérifier que VydFlow affiche l’échéance et garde les 3 Go jusqu’à cette date. Après l’expiration, le quota Free s’applique. Les médias existants restent accessibles et l’écran de stockage permet de libérer de la place.
- Le banc fonctionnel simule aussi le renouvellement payé, la facture impayée, un webhook dupliqué ou ancien, une signature invalide et une interruption de l’API. La réconciliation repart de l’état Stripe courant et applique les changements au seul compte lié au client Stripe.

## Recette réelle dans le bac à sable · 24–26 novembre 2026

Le prix de test actif de 9 € par mois a été lu depuis Stripe. Un compte fictif distinct a effectué le paiement hébergé avec une carte de test. La notification signée a activé le quota de 3 Go dans VydFlow, puis le portail client de test s’est ouvert pour ce compte. Après reprise du relais le 26 novembre, l’annulation en fin de période s’est propagée à VydFlow avec conservation de l’échéance payée. Le retour à Free sur expiration et le renouvellement sont couverts par les tests simulés.
