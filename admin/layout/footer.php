<footer class="footer-wrap-layout1 py-3">
    <div class="copyright text-center text-muted font-13">
        © Copyrights <?= date('Y'); ?> <a href="#" class="font-weight-bold text-dark text-decoration-none">BoomRang
            Group</a>.
        Tous droits réservés. Développé par <a href="https://boomrang-group.com" target="_blank"
            rel="noopener noreferrer" class="text-primary font-weight-bold">Site officiel</a>
    </div>
</footer>

<!-- ============== Notification FAB (flottant) ============== -->
<button id="notif-fab" class="notif-fab" title="Notifications">
    <i class="fas fa-bell"></i>
    <span id="notif-badge" class="notif-badge d-none">0</span>
</button>

<!-- ============== Offcanvas / Modal des notifications ============== -->
<div class="modal fade" id="notifModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-right" role="document" style="max-width: 420px;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-bell mr-2"></i> Notifications récentes
                </h5>
                <button type="button" class="close" data-dismiss="modal"
                    aria-label="Fermer"><span>&times;</span></button>
            </div>
            <div class="modal-body p-0">
                <div id="notif-feed" class="list-group list-group-flush">
                    <div class="p-3 text-muted">Chargement…</div>
                </div>
            </div>
            <div class="modal-footer">
                <small class="text-muted mr-auto" id="notif-last-since"></small>
                <button type="button" class="btn btn-light" data-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<style>
.notif-fab {
    position: fixed;
    right: 18px;
    bottom: 24px;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: #0d6efd;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 22px rgba(0, 0, 0, .25);
    z-index: 1055;
    border: none;
    outline: none;
}

.notif-fab:hover {
    filter: brightness(1.05);
}

.notif-fab i {
    font-size: 20px;
}

.notif-badge {
    position: absolute;
    top: -6px;
    right: -6px;
    background: #dc3545;
    color: #fff;
    font-weight: 700;
    font-size: .75rem;
    min-width: 22px;
    height: 22px;
    line-height: 22px;
    border-radius: 999px;
    text-align: center;
    padding: 0 6px;
    box-shadow: 0 0 0 2px #fff;
}

/* modal à droite façon drawer */
.modal-right .modal-content {
    min-height: 100vh;
    border: 0;
    border-radius: 0;
}

.modal-right {
    margin: 0 0 0 auto;
}
</style>

<script>
(function() {
    // On garde côté session serveur un "since" pour compter depuis la dernière consultation
    let polling = null;

    function fmt(dt) {
        try {
            return new Date(dt).toLocaleString();
        } catch (e) {
            return dt;
        }
    }

    function updateBadge(total) {
        const b = document.getElementById('notif-badge');
        if (!b) return;
        if (!total || total <= 0) {
            b.classList.add('d-none');
            b.textContent = '0';
        } else {
            b.classList.remove('d-none');
            b.textContent = (total > 99 ? '99+' : total);
        }
    }

    function fetchCounts() {
        $.getJSON('service/notify_stats.php', function(resp) {
            if (resp && resp.status === 'ok') {
                updateBadge(resp.total || 0);
            }
        }).fail(function() {
            /* silencieux */
        });
    }

    function fetchFeed() {
        $('#notif-feed').html('<div class="p-3 text-muted">Chargement…</div>');
        $.getJSON('service/notify_feed.php', function(resp) {
            if (!(resp && resp.status === 'ok')) {
                $('#notif-feed').html('<div class="p-3 text-danger">Erreur de chargement.</div>');
                return;
            }
            const items = resp.items || [];
            const since = resp.since || null;
            if (since) {
                $('#notif-last-since').text('Depuis : ' + fmt(since));
            }

            if (!items.length) {
                $('#notif-feed').html('<div class="p-3 text-muted">Aucune nouvelle notification.</div>');
                return;
            }
            let html = '';
            items.forEach(function(it) {
                html += `
          <a class="list-group-item list-group-item-action" href="${it.link || '#'}">
            <div class="d-flex w-100 justify-content-between">
              <h6 class="mb-1">
                ${ it.icon ? `<i class="${it.icon} mr-2"></i>` : '' }
                ${ it.title ? it.title : 'Notification' }
                ${ it.badge ? `<span class="badge badge-pill badge-info ml-1">${it.badge}</span>` : '' }
              </h6>
              <small class="text-muted">${it.when || ''}</small>
            </div>
            <p class="mb-1">${it.text || ''}</p>
            ${ it.meta ? `<small class="text-muted">${it.meta}</small>` : '' }
          </a>`;
            });
            $('#notif-feed').html(html || '<div class="p-3 text-muted">Rien à afficher.</div>');
        }).fail(function() {
            $('#notif-feed').html('<div class="p-3 text-danger">Erreur de chargement.</div>');
        });
    }

    // Ouvre le modal
    $('#notif-fab').on('click', function() {
        $('#notifModal').modal('show');
        fetchFeed();
        // Notifie au serveur qu'on a "vu" → reset du since pour les prochains comptes
        $.post('service/notify_seen.php');
        // Met à jour le badge à 0 immédiatement côté UI (optimiste)
        updateBadge(0);
    });

    // Polling counts toutes les 30 secondes
    function startPolling() {
        fetchCounts();
        if (polling) clearInterval(polling);
        polling = setInterval(fetchCounts, 30000);
    }
    startPolling();

    // Quand on ferme le modal on pourrait re-fetch les counts (pas obligatoire)
    $('#notifModal').on('hidden.bs.modal', function() {
        fetchCounts();
    });
})();
</script>