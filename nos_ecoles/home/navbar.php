<?php require 'paserelle_url.php'; ?>

<nav class="navbar navbar-expand-lg bg-white navbar-light sticky-top px-4 px-lg-5 py-lg-0">
    <a href="#home" class="navbar-brand">
        <img src="https://kelasi.education/wp-content/uploads/2025/02/cropped-expert-comptable-qui-accompagne-un-createur-dentreprise-1-1.png"
            alt="" srcset="">
        <!-- <h1 class="m-0 text-primary"><i class="fa fa-book-reader me-3"></i>Kelasi</h1> -->
        <!-- <h1 class="m-0 text-primary"><i class="fa fa-book-reader me-3"></i><?php echo $nom ?></h1> -->
    </a>
    <button type="button" class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarCollapse">
        <a class="navbar-brand fw-bold" href="#">
            <img src="../../img/logo.png" alt="Chargement logo..." srcset="">
        </a>
        <div class="navbar-nav mx-auto">
            <a href="#home" class="nav-item nav-link active">Accueil</a>
            <a href="#apropos_de_nous" class="nav-item nav-link">A propos de nous</a>
            <a href="repetitorat.php?ecole=<?php echo $url_session_ecole; ?>"
                                        class="nav-item nav-link"
                                        target="_blank">Répetitorat</a>
            <a href="notation.php?ecole=<?php echo $url_session_ecole; ?>" class="d-none nav-item nav-link"
                target="_blank">Notation</a>
            <div class="d-none nav-item dropdown">
                <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">Pages</a>
                <div class="dropdown-menu rounded-0 rounded-bottom border-0 shadow-sm m-0">
                    <a href="facility.html" class="dropdown-item">School Facilities</a>
                    <a href="team.html" class="dropdown-item">Popular Teachers</a>
                    <a href="call-to-action.html" class="dropdown-item">Become A Teachers</a>
                    <a href="appointment.html" class="dropdown-item">Make Appointment</a>
                    <a href="testimonial.html" class="dropdown-item">Testimonial</a>
                    <a href="404.html" class="dropdown-item">404 Error</a>
                </div>
            </div>
            <a href="#contact" class="nav-item nav-link">Contact Us</a>
        </div>
        <a href="../../login/" class="btn btn-primary rounded-pill px-3 d-none d-lg-block"
            target="_blank">Connexion<i class="fa fa-arrow-right ms-3"></i></a>
    </div>
</nav>