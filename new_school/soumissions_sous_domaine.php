<?php
if (!empty($_GET['sous_domaine'])) {
    $sous_domaine = urlencode($_GET['sous_domaine']);
    $url = $sous_domaine;
} else {
    $url = "https://www.kelasi.education";
    header("Location: $url");
    exit;
}
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyKelasi | Soumissions sous domaine</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-LN+7fdVzj6u52u30Kp6M/trliBMCMKTyK833zpbD+pXdCLuTusPj697FH4R/5mcr" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q" crossorigin="anonymous">
    </script>

</head>

<body>
    <div class="container" style="padding:5rem">
        <div class="text-center item-logo mb-3">
            <img src="../img/logo2.png" alt="logo"> <!-- Assuming logo2.png is preferred from login.html -->
        </div>
        <h2 class="mb-2">Félicitations !</h2>
        <ul>
            <li>
                <p class="lead">Votre compte promoteur à été créé avec succès.</p>
            </li>
            <li>
                <p class="lead">Votre domaine : <?php echo 'https://www.kelasi.education/mykelasi/@'. $url ?></p>
            </li>
        </ul>
        <button class="btn btn-primary" onclick="window.location.href='../my_school/@<?php echo $url; ?>'">Se connecter !</button>

    </div>
</body>

</html>