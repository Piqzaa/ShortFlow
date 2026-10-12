# Architecture

Le monolithe reçoit une requête via public/index.php et le runtime Symfony. App\Kernel charge les bundles, configuration et services. Le routeur sélectionne LandingController pour GET/POST /. Le controller construit WaitlistType, qui gère CSRF et validation. Sur POST valide, le limiteur autorise au maximum cinq tentatives par IP sur quinze minutes ; le honeypot écarte les robots simples ; WaitlistRegistration normalise l’email et utilise Doctrine pour insérer WaitlistEntry. L’index unique protège aussi contre deux requêtes concurrentes. Une redirection 303 évite le renvoi du formulaire lors d’un rafraîchissement. Le message reste identique pour un doublon.

Twig génère le HTML et échappe les données. Vite compile CSS, polices et JavaScript dans public/build. Stimulus initialise le contrôleur studio ; les imports Three.js et GSAP sont différés. Le smartphone utilise une géométrie procédurale et un poster SVG original. Aucun modèle ou média externe requis. Le fallback HTML/CSS existe dès le premier rendu. Les ressources GPU, écouteurs, animations et éventuelle vidéo sont libérés à la déconnexion.

PostgreSQL stocke uniquement l’email unique et la date. Le cache local Symfony stocke temporairement les compteurs antispam ; il convient à une instance locale, et sera partagé pour un déploiement multi-instance. Aucun serveur mail ni worker. Le serveur PHP intégré est réservé au développement. Avant production : serveur adapté, HTTPS, APP_ENV=prod, secrets réels, politique de données finalisée, protection réseau et sauvegardes.

## Extension progressive
User, MediaAsset, Post et PostTarget apparaîtront en V1 métier. Puis SocialAccount, PlatformCapability et PublicationAttempt pourront supporter des adaptateurs réseau et l’idempotence. Subscription, CreditWallet et GenerationJob ne seront ajoutés que lorsque paiement et IA seront autorisés. Aucun de ces modèles futurs n’est implémenté actuellement.
