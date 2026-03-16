<?php
// Si le formulaire est envoyé
if(isset($_POST['nom']) && isset($_POST['message']) && isset($_POST['note'])){

    $nom = htmlspecialchars($_POST['nom']);
    $message = htmlspecialchars($_POST['message']);
    $note = intval($_POST['note']);

    // On prépare la ligne à enregistrer
    $ligne = $nom . "|" . $note . "|" . $message . "\n";

    // On ajoute dans le fichier
    file_put_contents("avis.txt", $ligne, FILE_APPEND);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Avis</title>
    <link rel="stylesheet" href="Avis.css">
</head>

<body>

<header>
    <nav>
        <ul>
            <li><a href="Accueil.html">Accueil</a></li>
            <li><a href="A propos.html">A propos</a></li>
            <li><a href="Inscription.html">Inscription</a></li>
            <li><a href="Mes_informations.html">Mes offres</a></li>
            <li><a href="Avis.php">Avis</a></li>
        </ul>
    </nav>

    <h1>Les avis de nos étudiants</h1>
</header>

<section class="avis-section">

<?php
// On affiche les avis enregistrés
if(file_exists("avis.txt")){
    $lignes = file("avis.txt");

    foreach($lignes as $ligne){
        list($nom, $note, $message) = explode("|", $ligne);

        echo "<div class='avis'>";
        echo "<h2>";

        // Affichage des étoiles
        for($i = 0; $i < $note; $i++){
            echo "⭐";
        }

        echo "</h2>";
        echo "<p>\"$message\"</p>";
        echo "<h4>- $nom</h4>";
        echo "</div>";
    }
}
?>

</section>

<section class="form-avis">
    <h2>Laisser un avis</h2>

    <form method="post">

        <label>Nom</label>
        <input type="text" name="nom" required>

        <label>Votre avis</label>
        <textarea name="message" required></textarea>

        <label>Note</label>
        <div class="etoiles">
            <input type="radio" name="note" value="1" required> ⭐
            <input type="radio" name="note" value="2"> ⭐⭐
            <input type="radio" name="note" value="3"> ⭐⭐⭐
            <input type="radio" name="note" value="4"> ⭐⭐⭐⭐
            <input type="radio" name="note" value="5"> ⭐⭐⭐⭐⭐
        </div>

        <button type="submit">Envoyer</button>

    </form>
</section>

<footer>
    <p>© 2026 Mon école de code</p>
</footer>

</body>
</html>
