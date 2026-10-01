# Rapport d'Audit Fonctionnel, Stratégie QA & Roadmap Produit - MyKelasi

**Projet :** MyKelasi (ERP Éducatif Multi-Établissements)
**Rôles :** Tech Lead, Ingénieur QA & Product Manager
**Cible :** Afrique Francophone (RDC et sous-région)
**Date :** Octobre 2024
**Statut :** Recommandations Pré-Go-Live (V1.0 à V1.2)

---

## 📋 Sommaire Executif

MyKelasi est une plateforme ERP de gestion scolaire multi-tenant développée en PHP/MySQL avec Bootstrap, jQuery et Chart.js. L'application interconnecte 4 portails majeurs :
- `/admin` : Direction et Secrétariat de l'école.
- `/my_school` : Espace Promoteurs / Gestionnaires multi-écoles.
- `/customs/teacher` : Espace Enseignants (Cahier de texte, leçons, évaluations, présence).
- `/customs/students` : Espace Élèves (Consultation des cours, horaires, présence, paiements).

Ce document établit la stratégie de qualité logicielle (QA), identifie les exigences fonctionnelles manquantes critiques pour le lancement officiel (Go-Live V1.0), et définit la feuille de route produit (V1.1 & V1.2) orientée vers l'expérience utilisateur et la valeur métier.

---

## 1. 🧪 Stratégie de Test & Matrice de Recette (QA)

En tant qu'ERP éducatif gérant des données personnelles de mineurs et des flux financiers, MyKelasi doit présenter un niveau d'étanchéité et de fiabilité irréprochable. La matrice ci-dessous détaille les cas de tests fonctionnels, d'intégration et E2E ciblant spécifiquement les vulnérabilités courantes des applications scolaires.

### Matrice des Cas de Tests (Edge Cases & RBAC)

| ID Test | Périmètre / Module | Scénario de Test | Type de Test | Données / Manipulations | Résultat Attendu |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **QA-RBAC-01** | Contrôle d'Accès Inter-Écoles (Multi-tenancy) | Validation de l'isolation du `code_ecole` | Sécurité / IDOR | Un admin de l'École A tente d'accéder/modifier un élève (`student_id`) appartenant à l'École B via modification de l'URL (`/admin/students-show.php?id=X`). | **Accès refusé 403** ou redirection vers le dashboard avec un message d'erreur. Pas de fuite de données. |
| **QA-RBAC-02** | Escalade de Privilèges Verticale | Tente d'accès aux URLs d'administration par un rôle restreint | Sécurité / RBAC | Un élève ou un enseignant connecté tente d'accéder directement à `/admin/add-depenses.php` ou `/my_school/finance.php`. | Redirection automatique vers la page de login ou la page d'accueil du portail correspondant sans exécuter le script. |
| **QA-RBAC-03** | Usurpation de Session Cross-Portail | Chevauchement des sessions utilisateur | Session / Intégration | Connecter un Admin, puis ouvrir un nouvel onglet vers `/customs/students/` sans se déconnecter. | Les sessions doivent être strictement cloisonnées (ex: `$_SESSION['user_role']` vérifié systématiquement). |
| **QA-FIN-01** | Paiement Mobile Money (MaxiCash) | Manipulation des paramètres de callback | Financement / Edge Case | Un élève modifie les paramètres GET de la callback (`status=success&amount=1000`) sans effectuer de paiement réel. | Le système rejette le traitement en l'absence de signature cryptographique vérifiée (HMAC) côté serveur. |
| **QA-FIN-02** | Double Soumission de Paiement Cash | Validation concurrente de paiement | Intégration / Concurrence | Soumission simultanée du formulaire `paiement_cash.php` (clic rapide multiple ou requêtes parallèles). | Protection contre les doubles inscriptions en base (utilisation de transactions PDO / contraintes d'unicité). |
| **QA-FIN-03** | Incohérence Solde & Montants Négatifs | Saisie de valeurs financières aberrantes | Fonctionnel / Edge Case | Saisie d'un montant négatif ou d'un solde supérieur au montant fixé du frais scolaire. | Validation stricte côté serveur (`montant > 0` et `montant <= reste_a_payer`). |
| **QA-EDU-01** | Affectation Enseignant / Cours / Classe | Assignation croisée et suppression de cours | Intégration | Suppression d'un cours lié à des devoirs, notes et horaires existants. | Intégrité référentielle respectée (cascade propre ou blocage explicite si des notes sont associées). |
| **QA-EDU-02** | Prise de Présence & Modification | Clôture des présences d'une journée | Fonctionnel | Un enseignant tente de modifier les présences d'un cours datant de plusieurs semaines. | Verrouillage temporel de la saisie des présences (ex: modification autorisée uniquement sous 24h/48h). |
| **QA-UPL-01** | Upload de Médias Pédagogiques | Téléversement de fichiers malveillants | Sécurité / Upload | Tentative d'upload d'un fichier `.php`, `.exe`, `.phtml` ou un fichier renommé `cours.php.jpg` dans les leçons. | Rejet immédiat par le serveur (validation type MIME + extension whitelistée) et aucun privilège d'exécution dans `/uploads`. |
| **QA-UPL-02** | Injection XSS via Titres/Contenus | Saisie de scripts dans les titres de cours ou devoirs | Sécurité / XSS | Insertion de `<script>alert('xss')</script>` dans la création d'un chapitre ou d'un devoir. | Échappement à l'affichage via `htmlspecialchars()` sans altérer les données en base. |
| **QA-E2E-01** | Parcours Complet Élève | De l'inscription au règlement de la scolarité | E2E | Inscription élève -> Affectation classe -> Consultation horaire -> Suivi de cours -> Paiement frais. | Continuité du flux sans rupture, calcul exact du solde restant et génération du reçu. |
| **QA-E2E-02** | Parcours Complet Enseignant | De la création de cours au suivi des présences | E2E | Connexion -> Choix de la classe -> Ajout d'une leçon (médias) -> Enregistrement des absences. | Mises à jour visibles immédiatement sur l'espace élève rattaché à la classe. |

---

## 2. 🚨 Fonctionnalités Critiques Manquantes (Go-Live / V1 Must-Have)

Avant de procéder au déploiement officiel en production, plusieurs fonctionnalités essentielles doivent être intégrées pour garantir la conformité légale, la sécurité opérationnelle et la continuité de service.

### 1. Protection des Données Personnelles de Mineurs & Conformité RGPD / Législation Locale
- **Consentement Parental à l'Inscription :** Collecte obligatoire de l'accord explicite du parent/tuteur lors de l'enregistrement d'un élève mineur.
- **Politique de Confidentialité & Droit à l'Oubli :** Possibilité d'anonymiser ou d'archiver les dossiers d'élèves ayant quitté l'établissement tout en respectant l'obligation légale de conservation des registres scolaires.

### 2. Module Sécurisé de Réinitialisation de Mot de Passe ("Mot de passe oublié")
- **État Actuel :** Risque lié aux mots de passe temporaires prévisibles.
- **Exigence V1 :** Envoi par e-mail/SMS d'un token à durée de vie limitée (ex: 15 minutes) généré via `random_bytes()`, hashé en base de données, avec invalideur à la première utilisation.

### 3. Piste d'Audit & Journalisation des Événements Sensibles (Audit Logs)
- **Besoin Métier :** Traçabilité absolue des actions administratives et financières.
- **Mise en œuvre :** Création d'une table `audit_logs` enregistrant :
  - `user_id`, `code_ecole`, `action` (ex: *Création élève*, *Validation paiement*, *Modification note*), `ip_address`, `timestamp`.
- **Interface Admin :** Consultation des journaux d'activité par la direction pour éviter les fraudes internes.

### 4. Moteur d'Exportation & Impression Officielle (PDF / Excel)
- **Exports Reçus & Relevés :** Génération au format PDF normé (avec en-tête de l'école, logo, QR code de vérification) pour :
  - Reçus de paiement de frais scolaires.
  - Cartes d'élèves avec photo et code-barres.
  - Listes de classes et fiches de présence imprimables.
- **Exports CSV / Excel :** Exportation des listes d'élèves et des états financiers pour la comptabilité de l'école.

### 5. Gestion des Années Scolaires & Clôture d'Exercice (Passage de Classe)
- **Gestion du Changement d'Année :** Mécanisme de bascule d'année académique (ex: 2023-2024 -> 2024-2025).
- **Réinscription & Migration :** Passage automatique des élèves admis en classe supérieure, redoublement ou archivage des diplômés, sans écraser les historiques financiers/académiques des années précédentes.

### 6. Politiques de Sécurité Renforcées (Sessions & Authentification)
- **Double Authentification (2FA) optionnelle** pour les rôles d'administration et de gestion financière (`/admin` et `/my_school`).
- **Politique de Mots de Passe Forts :** Exigence de 8 caractères minimum, mélange de lettres/chiffres/symboles.
- **Blocage Temporaire :** Blocage de compte après 5 tentatives d'authentification infructueuses (protection Brute-Force).

---

## 3. 🚀 Roadmap Produit (Évolutions V1.1 et V1.2)

Cette roadmap est conçue pour apporter une **forte valeur ajoutée métier** aux établissements scolaires, aux enseignants, aux élèves et tout particulièrement aux **parents**, tout en tenant compte du contexte technologique et de la connectivité en Afrique Francophone.

```
+-------------------------------------------------------------------------------+
|                                ROADMAP MYKELASI                               |
+-------------------------------------------------------------------------------+
|   VERSION 1.0 (Go-Live)   |    VERSION 1.1 (Q1 - UX & Comm)   | VERSION 1.2 (Q2 - Pedago & Mobile) |
| - Audit & Correctifs QA   | - Espace / Portail Parent     | - Bulletins Automatisés (Bulletins) |
| - Audit Logs & Exports    | - SMS / WhatsApp Notifications| - App Mobile Hybride (Offline-First)|
| - Mots de passe sécurisés | - Paiement Mobile Money Direct| - Analytics & Tableaux de bord   |
+-------------------------------------------------------------------------------+
```

---

### 🟢 Version 1.1 : Portail Parents & Communication Temps Réel

#### 1. Espace Dédié aux Parents / Tuteurs (`/customs/parents`)
- **Vue Multi-Enfants :** Un parent ayant plusieurs enfants inscrits dans la même école (ou dans des écoles différentes utilisant MyKelasi) accède à un tableau de bord unique.
- **Suivi en Temps Réel :**
  - Notification immédiate des absences ou retards de l'enfant.
  - Aperçu de l'état financier (échéancier des frais, solde dû, historique des versements).
  - Consultation des notes et devoirs à venir.

#### 2. Module de Notifications Multi-Canaux (SMS & WhatsApp API)
- **Contexte Local :** L'accès à internet mobile pouvant être intermittent, le SMS et WhatsApp sont les canaux privilégiés.
- **Cas d'usage :**
  - **Alerte Présence :** SMS automatique au parent dès qu'un élève est marqué "Absent" au premier cours.
  - **Rappel d'Échéance Financière :** Notification de relance 5 jours avant l'échéance du minerval.
  - **Annonces Urgentissimes :** Fermeture exceptionnelle, réunions de parents d'élèves.

#### 3. Paiement Direct des Frais par les Parents (Mobile Money)
- Integration poussée d'APIs locales (Orange Money, M-Pesa, Airtel Money, Wave) pour permettre au parent de régler la scolarité depuis son téléphone mobile avec génération instantanée du reçu électronique.

---

### 🔵 Version 1.2 : Pédagogie Avancée, Bulletins Automatisés & Application Mobile

#### 1. Générateur Dynamique de Bulletins Scolaires Conformes
- **Calcul Automatique des Moyennes :** Prise en compte des pondérations par matière, périodes, examens semestriels/trimestriels.
- **Génération de Bulletins en 1-Clic :** Impression globale des bulletins de toute une classe au format réglementaire (Ministère de l'Éducation).
- **Appréciations & Titularité :** Interface permettant au prof titulaire de saisir les appréciation générales et les décisions du conseil de classe.

#### 2. Application Mobile Hybride pour Enseignants & Parents (Offline-First)
- **Mode Hors-Ligne (Offline Sync) :** Permet aux enseignants d'encoder les notes et les présences même en l'absence de réseau dans les salles de classe, avec synchronisation automatique dès le retour de la connexion.
- **Push Notifications :** Alertes instantanées sur smartphone.

#### 3. Dashboard Analytique & Détection Précoce du Décrochage
- **Graphiques Décisionnels pour le Promoteur/Directeur :**
  - Taux de recouvrement des frais scolaires par classe/section.
  - Courbe d'assiduité globale et taux de réussite moyen par discipline.
- **Indicateur de Risque Élève :** Algorithme simple identifiant les élèves en risque de décrochage (combinaison d'absences répétées et de baisse des notes).

---

## 4. 🛠️ Recommandations Techniques d'Architecture

Pour soutenir cette montée en charge et garantir la pérennité de MyKelasi :

1. **Migration progressive vers PHP 8.1+ / 8.2+ :**
   - Gain de performance significatif (30%+).
   - Utilisation des types stricts, des propriétés `readonly`, et de la gestion améliorée des erreurs/exceptions.
2. **Refonte de la Couche Base de Données :**
   - Normalisation des clés étrangères avec contraintes `ON DELETE RESTRICT` sur les tables financières.
   - Indexation des colonnes à forte fréquence de recherche (`code_ecole`, `class_id`, `student_id`, `created_at`).
3. **Mise en Place d'une Architecture API RESTful (`/api/v1/`) :**
   - Découplage progressif du backend PHP pour alimenter le portail Web et la future application mobile via des tokens JWT sécurisés.

---

## 📑 Conclusion & Prochaines Étapes

MyKelasi possède une base fonctionnelle solide et une architecture multi-tenant prometteuse. La mise en application de ce plan de recettes QA et le déploiement des fonctionnalités V1 prioritaires permettront d'assurer un **Go-Live serein, sécurisé et conforme**. Les versions V1.1 et V1.2 positionneront MyKelasi comme un ERP incontournable sur le marché de l'éducation en Afrique Francophone.
