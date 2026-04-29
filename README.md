# DFS Abonnements

Module PrestaShop 9 de gestion des commandes d'abonnement **Les Fromages Gourmands** pour le site [maison-lorho.fr](https://maison-lorho.fr).

**Auteur :** Cyrille Mohr — Digital Food System  
**Contact :** contact@digitalfoodsystem.com  
**Compatibilité :** PrestaShop 9.0.x

---

## Fonctionnalités

- ✅ Création de deux états de commande : **Abonnement** et **Abonnement terminé**
- ✅ Page BO **Commandes > Abonnements** avec la grille native de PrestaShop (toutes colonnes + enrichissements modules existants), filtrée sur les états abonnement
- ✅ Bloc **Marketing abonnements** : envoi groupé du mail "Abonnement prêt" + renvoi individuel du mail "Abonnement terminé"
- ✅ Génération automatique de **codes promo** (30 € pour 6 mois, 75 € pour 12 mois) à l'activation de l'abonnement
- ✅ **3 modèles d'emails** propres et personnalisables (prêt / terminé / code promo)
- ✅ **Journal des actions** pour traçabilité et anti-doublon
- ✅ **Configuration complète** des IDs produits et montants depuis le BO

## Installation

1. Copier le dossier `dfs_abonnements/` dans `/modules/` de PrestaShop
2. Installer via le Gestionnaire de modules du BO
3. Configurer les IDs produits dans la page de configuration du module

## Structure

```
dfs_abonnements/
├── dfs_abonnements.php           # Classe principale
├── composer.json
├── config/
│   ├── routes.yml                # Routes Symfony
│   └── services.yml              # Services Symfony
├── src/Controller/Admin/
│   ├── AdminDfsAbonnementsController.php       # Page Abonnements + grille
│   └── AdminDfsAbonnementsConfigController.php # Configuration
├── sql/
│   ├── install.sql
│   └── uninstall.sql
├── mails/fr/
│   ├── dfs_abonnement_pret.html/txt
│   ├── dfs_abonnement_termine.html/txt
│   └── dfs_abonnement_promo.html/txt
└── views/templates/admin/
    ├── abonnements.html.twig
    └── configure.html.twig
```

## Produits d'abonnement (IDs par défaut)

| ID | Produit | Code promo |
|----|---------|------------|
| 23 | Les Fromages Gourmands - 1 mois | Aucun |
| 24 | Les Fromages Gourmands - 3 mois | Aucun |
| 25 | Les Fromages Gourmands - 6 mois | 30 € |
| 26 | Les Fromages Gourmands - 12 mois | 75 € |

Ces valeurs sont configurables dans le BO sans toucher au code.

## Licence

Academic Free License 3.0 (AFL-3.0)
