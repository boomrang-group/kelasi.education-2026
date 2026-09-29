# Rapport d'Audit de Sécurité Applicative - MyKelasi

## 1. Résumé Exécutif

**Niveau de risque global : Critique**

L'audit de sécurité approfondi du projet MyKelasi a révélé des failles structurelles majeures. Bien que des efforts de sécurisation soient visibles (utilisation systématique de PDO, échappement global avec `htmlspecialchars`), l'implémentation de fonctionnalités critiques comme le paiement en ligne et l'authentification contient des erreurs de conception permettant un contournement complet des contrôles.

### Nombre de problèmes par gravité
- **Critique** : 2
- **Élevé** : 4
- **Moyen** : 5
- **Faible** : 2
- **Total** : 13

### Les 5 risques les plus urgents
1. **Paiement MaxiCash trivialement contournable** : La validation des transactions ne repose sur aucun mécanisme de signature.
2. **Authentification compromise par un fallback legacy** : La vérification des mots de passe accepte des comparaisons en texte brut.
3. **Absence de protection CSRF généralisée** : Plusieurs formulaires d'administration sont vulnérables.
4. **Risques majeurs d'IDOR sur les services administratifs** : Absence de validation de scope (école) sur des paramètres ID pivots.
5. **Exposition des secrets de configuration** : Identifiants de base de données en dur dans le code.

---

## 2. Cartographie du Projet

### Architecture et Technologies
- **Langage** : PHP 7.4+ (natif)
- **Base de données** : MySQL/MariaDB (Extension PDO)
- **Frontend** : HTML5, CSS3 (Bootstrap 3/5), JavaScript (jQuery)
- **Structure** : Multi-tenant basé sur un `code_ecole` en session.

### Points d'entrée principaux
- **Authentification** : `login/index.php`
- **Portails** : `/admin` (Direction), `/manager` (Super-admin), `/my_school` (Promoteurs), `/customs/teacher` (Enseignants), `/customs/students` (Élèves).
- **Public** : `index.php` (Landing), `nos_ecoles/home/index.php` (Page école), `new_school/index.php` (Inscription promoteur), `nos_ecoles/home/inscription.php` (Inscription élève).
- **Formulaires & Uploads** :
    - Ajout élève : `admin/service/add-student.php`
    - Ajout enseignant : `admin/service/add-teacher.php`
    - Création cours/médias : `customs/teacher/view/creer_cours.php`
    - Upload documents : `admin/service/upload.php`
- **Paiements** :
    - Cash : `admin/service/approve_cash.php`
    - Mobile Money : `customs/students/view/maxicash/`
- **Communication** : `admin/chat.php`, `admin/service/chat_send.php`

---

## 3. Tableau des Vulnérabilités

| ID | Gravité | Type | Zone Concernée | Description & Impact | Correctif Recommandé |
|:---|:---|:---|:---|:---|:---|
| **V01** | **Critique** | Fraude Financière | `customs/students/view/maxicash/callback.php` | Trust de paramètres GET (`status`, `amount`) sans signature. Permet de valider n'importe quelle dette en manipulant l'URL. | Implémenter une vérification de signature (HMAC) via le secret marchand. |
| **V02** | **Critique** | Broken Auth | `login/index.php` | Comparaison `hash_equals` utilisée si le hash stocké < 60 chars. Permet la connexion avec des mots de passe en clair. | Supprimer le fallback et n'utiliser que `password_verify()`. |
| **V03** | **Élevé** | IDOR | `admin/service/add-student.php` | Pas de vérification que le `class_id` envoyé appartient au `code_ecole` de l'admin. Inscription possible d'élèves dans d'autres écoles. | Valider la propriété de la ressource cible avant insertion. |
| **V04** | **Élevé** | Stored XSS | `admin/service/upload.php` | `html_entity_decode` sur des entrées utilisateur affichées ensuite sans échappement suffisant. | Supprimer le décodage ou utiliser HTMLPurifier. |
| **V05** | **Élevé** | CSRF | `new_school/index.php` | Aucun jeton CSRF sur l'inscription promoteur. Risque de création de comptes forcée. | Ajouter des jetons CSRF systématiques. |
| **V06** | **Élevé** | Secrets Exposure | `database/db_connect.php` | Mot de passe DB en dur dans le code comme fallback. | Utiliser uniquement `getenv()` ou un fichier `.env` protégé. |
| **V07** | **Moyen** | Incohérence DB | `database/kugu2744_mykelasi.sql` | Rôle 'manager' absent de l'ENUM `users.role` SQL mais utilisé en PHP. | Mettre à jour l'ENUM SQL. |
| **V08** | **Moyen** | Insecure Email | `nos_ecoles/home/email.php` | Utilisation de `mail()` sans SMTP authentifié. Risque d'interception et de spam. | Passer à PHPMailer + SMTP TLS. |
| **V09** | **Moyen** | DoS / Upload | Scripts d'upload | Manque de vérification de taille de fichier côté serveur en PHP avant le traitement. | Vérifier `$_FILES['...']['size']` par rapport à une limite stricte. |
| **V10** | **Moyen** | Info Leak | `/database/` | Fichiers SQL et backups potentiellement accessibles si le listing répertoire est actif. | Ajouter un `.htaccess` avec `Deny from all`. |
| **V11** | **Moyen** | Broken Auth | `nos_ecoles/home/inscription.php` | Mots de passe générés trop prévisibles (`jd2008`). | Utiliser `random_bytes()` pour générer des mots de passe temporaires forts. |
| **V12** | **Faible** | Debug Leak | `my_school/dashboard.php` | `ini_set('display_errors', 1)` actif dans des fichiers de production. | Centraliser la gestion des erreurs et désactiver l'affichage en prod. |
| **V13** | **Faible** | Outdated Dep | `/js/`, `/lib/` | Versions anciennes de Bootstrap (3.x) et OwlCarousel. | Planifier une mise à jour des dépendances frontend. |

---

## 4. Section Spéciale : Code suspect

- **Fichier** : `login/index.php`
- **Extrait** : `hash_equals($stored, $password)`
- **Niveau de confiance** : 100% (Mauvaise pratique confirmée).
- **Action** : Supprimer immédiatement. Ce code permet de contourner le hashage sécurisé.

---

## 5. Recommandations Priorisées

### Actions immédiates (Aujourd'hui)
1. **Paiements** : Désactiver `customs/students/view/maxicash/callback.php` jusqu'à sécurisation.
2. **Auth** : Corriger la ligne 77 de `login/index.php`.
3. **Secrets** : Changer le mot de passe DB et le retirer du code source.

### Actions court terme (1-7 jours)
1. **CSRF** : Sécuriser `new_school/index.php` et `login/forgot.php`.
2. **Backups** : Sécuriser le dossier `/database/`.

---

## Verdict Final
**PROJET EXPLOITABLE - RISQUE CRITIQUE.**
Le module de paiement MaxiCash et le fallback d'authentification rendent la plateforme dangereuse pour une mise en production avec des données réelles ou des transactions financières.

**Expert Senior en Audit de Sécurité**
