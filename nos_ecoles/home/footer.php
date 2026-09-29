<!-- Footer Start -->
<!-- Lightbox CSS -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css" rel="stylesheet">
<!-- Lightbox JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/js/lightbox.min.js"></script>

<div class="container-fluid bg-dark text-white-50 footer pt-5 mt-5 wow fadeIn" data-wow-delay="0.1s" id="#contact">
    <div class="container py-5">
        <div class="row g-5">
            <div class="col-lg-3 col-md-6">
                <h3 class="text-white mb-4">Contactez-nous</h3>
                <?php if($ecole): ?>
                <p class="mb-2"><i class="fa fa-school me-3"></i><?= htmlspecialchars($ecole['nom_ecole']) ?></p>
                <p class="mb-2"><i class="fa fa-map-marker-alt me-3"></i>
                    <?= htmlspecialchars($ecole['adress']) ?>, <?= htmlspecialchars($ecole['ville']) ?>,
                    <?= htmlspecialchars($ecole['province_etat']) ?>, <?= htmlspecialchars($ecole['pays']) ?>
                </p>
                <?php if(!empty($ecole['telephone1']) || !empty($ecole['telephone2'])): ?>
                <p class="mb-2"><i class="fa fa-phone-alt me-3"></i><?= htmlspecialchars($ecole['telephone1']) ?> - <?= htmlspecialchars($ecole['telephone2']) ?></p>
                <?php endif; ?> 
                <?php if(!empty($ecole['email'])): ?>
                <p class="mb-2"><i class="fa fa-envelope me-3"></i><?= htmlspecialchars($ecole['email']) ?></p>
                <?php endif; ?>
                <?php else: ?>
                <p>Informations indisponibles pour cette école.</p>
                <?php endif; ?>
                <div class="d-flex pt-2">
                    <a class="btn btn-outline-light btn-social me-2" href="#" target="_blank"><i
                            class="fab fa-facebook-f"></i></a>
                    <a class="btn btn-outline-light btn-social me-2" href="#" target="_blank"><i
                            class="fab fa-youtube"></i></a>
                    <a class="btn btn-outline-light btn-social me-2" href="#" target="_blank"><i
                            class="fab fa-linkedin-in"></i></a>
                    <a class="btn btn-outline-light btn-social" href="#" target="_blank"><i
                            class="fab fa-instagram"></i></a>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <h3 class="text-white mb-4">Lien rapide</h3>
                <a class="btn btn-link text-white-50" href="#home">Accuel</a>
                <a class="btn btn-link text-white-50" href="#apropos_de_nous">A propos de nous</a>
                <a class="btn btn-link text-white-50" href="">Nous contacter</a>
                <!-- <a class="btn btn-link text-white-50" href="">Privacy Policy</a>
                <a class="btn btn-link text-white-50" href="">Terms & Condition</a> -->
            </div>
            <div class="col-lg-3 col-md-6">
                <h3 class="text-white mb-4">Photo Gallery</h3>
                <div class="row g-2 pt-2">
                    <div class="col-4">
                        <a href="img/classes-1.jpeg" data-lightbox="gallery" data-title="Classe 1">
                            <img class="img-fluid rounded bg-light p-1" src="img/classes-1.jpeg" alt="Classe 1">
                        </a>
                    </div>
                    <div class="col-4">
                        <a href="img/classes-2.jpeg" data-lightbox="gallery" data-title="Classe 2">
                            <img class="img-fluid rounded bg-light p-1" src="img/classes-2.jpeg" alt="Classe 2">
                        </a>
                    </div>
                    <div class="col-4">
                        <a href="img/classes-3.jpeg" data-lightbox="gallery" data-title="Classe 3">
                            <img class="img-fluid rounded bg-light p-1" src="img/classes-3.jpeg" alt="Classe 3">
                        </a>
                    </div>
                    <div class="col-4">
                        <a href="img/classes-4.jpeg" data-lightbox="gallery" data-title="Classe 4">
                            <img class="img-fluid rounded bg-light p-1" src="img/classes-4.jpeg" alt="Classe 4">
                        </a>
                    </div>
                    <div class="col-4">
                        <a href="img/classes-5.jpeg" data-lightbox="gallery" data-title="Classe 5">
                            <img class="img-fluid rounded bg-light p-1" src="img/classes-5.jpeg" alt="Classe 5">
                        </a>
                    </div>
                    <div class="col-4">
                        <a href="img/classes-6.jpeg" data-lightbox="gallery" data-title="Classe 6">
                            <img class="img-fluid rounded bg-light p-1" src="img/classes-6.jpeg" alt="Classe 6">
                        </a>
                    </div>

                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <h3 class="text-white mb-4">Masolo App</h3>
                <p>Contactez-nous par message Masolo App.</p>
                <div class="position-relative mx-auto" style="max-width: 400px;">
                    <input class="form-control bg-transparent w-100 py-3 ps-4 pe-5" type="text" placeholder="Téléphone">
                    <button type="button"
                        class="btn btn-primary py-2 position-absolute top-0 end-0 mt-2 me-2">Envoyer</button>
                </div>
            </div>
        </div>
    </div>
    <div class="container">
        <div class="copyright">
            <div class="row">
                <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                    © BoomRang Group | Tous droits réservés.
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <div class="footer-menu">
                        <!-- <a href="">Home</a>
                        <a href="">Cookies</a>
                        <a href="">Help</a>
                        <a href="">FQAs</a> -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Footer End -->


<!-- Back to Top -->
<a href="#" class="btn btn-lg btn-primary btn-lg-square back-to-top"><i class="bi bi-arrow-up"></i></a>
</div>

<!-- JavaScript Libraries -->
<script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="lib/wow/wow.min.js"></script>
<script src="lib/easing/easing.min.js"></script>
<script src="lib/waypoints/waypoints.min.js"></script>
<script src="lib/owlcarousel/owl.carousel.min.js"></script>

<!-- Template Javascript -->
<script src="js/main.js"></script>