<?php
// includes/check_payment.php

// 1. Démarrer la session si ce n'est pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId  = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$hasPaid = false;

// 2. Vérification en BDD
if ($userId && isset($pdo)) {
    try {
        // Étape 1 : Récupérer l'ID de l'élève depuis la table `students` grâce à son `id_user`
        $stmtStudent = $pdo->prepare("SELECT id FROM students WHERE id_user = ? LIMIT 1");
        $stmtStudent->execute([$userId]);
        $studentId = $stmtStudent->fetchColumn();

        // Étape 2 : Si l'élève existe, vérifier ses paiements dans la table `paiement`
        if ($studentId) {
            $stmtPayment = $pdo->prepare("
                SELECT COUNT(*) 
                FROM paiement 
                WHERE eleve = ? 
                  AND montant_paye > 0 AND is_validated = 1
            ");
            $stmtPayment->execute([$studentId]);
            $hasPaid = ($stmtPayment->fetchColumn() > 0);
        }
    } catch (PDOException $e) {
        error_log("Erreur check_payment: " . $e->getMessage());
        $hasPaid = false;
    }
 
}
?>

<style>
.badge {
    font-size: 0.75rem;
    padding: 0.25em 0.5em;
    border-radius: 50%;
    background-color: red;
    color: white;
}

/* Style de verrouillage */
.sidebar-locked .nav-item:not(.unlocked) {
    pointer-events: none;
    opacity: 0.4;
    cursor: not-allowed;
}

.sidebar-locked .nav-item:not(.unlocked) a {
    color: #a0aec0 !important;
}
</style>

<!-- Modal affiché si l'accès est restreint -->
<?php if (!$hasPaid): ?>
<div class="modal fade" id="paymentRequiredModal" data-backdrop="static" data-keyboard="false" tabindex="-1"
    role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content text-center p-4" style="border-radius: 15px;">
            <div class="modal-body">
                <div class="mb-3">
                    <i class="fas fa-exclamation-circle text-warning" style="font-size: 50px;"></i>
                </div>
                <h4 class="modal-title font-weight-bold mb-2">Compte non activé</h4>
                <p class="text-muted mb-4">
                    Aucun paiement n'a été enregistré pour votre compte. Veuillez procéder au règlement pour activer vos
                    accès.
                </p>
                <a href="finances.php" class="btn btn-primary px-4 py-2 font-weight-bold">
                    <i class="fas fa-wallet mr-2"></i> Régler mes frais
                </a>
                <a href="../../../login/logout.php" class="btn btn-danger px-4 py-2 font-weight-bold">
                    <i class="fas fa-sign-out-alt"></i> Deconnexion
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    if (typeof $ !== 'undefined') {
        $('#paymentRequiredModal').modal('show');
    }
});
</script>
<?php endif; ?>