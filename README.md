# MyKelasi - Système de Gestion Scolaire

MyKelasi est une plateforme complète de gestion pour les établissements scolaires, permettant la gestion des élèves, des enseignants, des cours, des évaluations et des finances.

## 🚀 Installation

1.  Clonez le repository sur votre serveur web (Apache/Nginx).
2.  Configurez votre base de données MySQL.
3.  Renommez `.env.example` en `.env` et remplissez vos informations de connexion.
4.  Importez le schéma SQL situé dans `database/kugu2744_mykelasi.sql`.
5.  Assurez-vous que les dossiers dans `uploads/` ont les permissions d'écriture nécessaires.

## 🔒 Sécurité (Audit OWASP Top 10 effectué)

Ce projet a subi un audit de sécurité complet et intègre les protections suivantes :

-   **Prévention des Injections SQL** : Utilisation systématique de PDO et des requêtes préparées.
-   **Protection XSS** : Échappement des données en sortie avec `htmlspecialchars()`.
-   **Sécurité des Uploads** : Whitelisting des extensions, vérification des types MIME et désactivation de l'exécution PHP dans les dossiers d'upload via `.htaccess`.
-   **Contrôle d'Accès (RBAC)** : Vérification stricte des rôles (Admin, Promoteur, Prof, Elève) sur chaque page sensible.
-   **Headers de Sécurité** : Implémentation de CSP, X-Frame-Options, X-Content-Type-Options, etc.
-   **Protection CSRF** : Mise en place de tokens sur les formulaires critiques.
-   **Gestion des Sessions** : Régénération des IDs de session après authentification.

## 📂 Structure du Projet

-   `/admin` : Interface d'administration de l'école.
-   `/my_school` : Interface pour les promoteurs (propriétaires d'écoles).
-   `/customs/teacher` : Espace enseignant (gestion des cours, devoirs, quiz).
-   `/customs/students` : Espace élève.
-   `/database` : Scripts de connexion et schémas SQL.
-   `/uploads` : Fichiers médias et documents (sécurisés).

## 🛠️ Technologies

-   PHP 7.4+ (Recommandé PHP 8.0+ pour plus de sécurité)
-   MySQL / MariaDB
-   Bootstrap & jQuery
-   Chart.js pour les tableaux de bord

## 📝 Licence

Tous droits réservés.
