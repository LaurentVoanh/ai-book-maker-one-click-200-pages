<?php
define('MISTRAL_API_KEY', ' YOUR API KEY');
define('MISTRAL_ENDPOINT', 'https://api.mistral.ai/v1/chat/completions');
define('MISTRAL_MODEL', 'pixtral-12b-2409');

session_start();
if (isset($_GET['reset'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}
session_start();

if (!isset($_SESSION['story'])) {
    $_SESSION['story'] = "";
    $_SESSION['title'] = "";
    $_SESSION['summary'] = [];
    $_SESSION['current_page'] = 1;
    $_SESSION['current_chapter'] = 1;
}

function callMistralAPI($prompt)
{
    $data = [
        'model' => MISTRAL_MODEL,
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'temperature' => 0.7,
        'max_tokens' => 8000
    ];

    $ch = curl_init(MISTRAL_ENDPOINT);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . MISTRAL_API_KEY,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    return $result['choices'][0]['message']['content'] ?? '';
}

// Étape 1 : Saisie du sujet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subject'])) {
    $_SESSION['title'] = trim($_POST['subject']);
    $summary_prompt = "Écris un sommaire détaillé d'un roman passionant, cultivé et profond, intitulé '{$_SESSION['title']}', avec une liste des chapitres et des 200 pages correspondantes.";
    
    $summary = callMistralAPI($summary_prompt);
    $_SESSION['summary'] = explode("\n", $summary);
    
    file_put_contents($_SESSION['title'] . ".json", json_encode($_SESSION['summary']));
    
    header("Location: index.php?start=1");
    exit;
}

// Étape 2 : Génération des pages
if (isset($_GET['generate'])) {
    $chapter = $_SESSION['current_chapter'];
    $page = $_SESSION['current_page'];

    if ($page > 200) {
        echo json_encode(['done' => true]);
        exit;
    }

    $context = isset($_SESSION['summary'][$page - 1]) ? $_SESSION['summary'][$page - 1] : "Suite du roman.";
    $text_prompt = "Écris la suite de l’histoire, dans un style littéraire puissant, profond et poétique, inspiré de Louis-Ferdinand Céline. Tu as une verve incroyable, tu eleve a chaque phrase plus haut l'intelligence et la culture et l'art et tu pousse toujours a chaque phrase plus profondement les sentiments en dechirant le coeur du letceur. Tu n'es jamais repetitif, ni formel, tu es tres éloquant, voila ta Page ecrire $page et le contexte global : $context";

    $new_text = callMistralAPI($text_prompt);
    $_SESSION['story'] .= "\n\nChapitre $chapter - Page $page:\n$new_text";

    $_SESSION['current_page']++;
    if ($_SESSION['current_page'] % 20 == 0) {
        $_SESSION['current_chapter']++;
    }

    echo json_encode(['text' => $new_text, 'page' => $page]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Générateur de Roman IA</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; background-color: #222; color: #fff; text-align: center; }
        #container { width: 80%; margin: auto; padding: 20px; background: #333; border-radius: 10px; box-shadow: 0 0 10px #000; }
        #loader { display: none; font-size: 24px; color: #ffcc00; }
        #story { text-align: left; white-space: pre-line; font-size: 18px; padding: 10px; }
        button { padding: 10px 20px; background: #ffcc00; border: none; font-size: 18px; cursor: pointer; border-radius: 5px; }
        input { padding: 10px; width: 80%; margin: 10px 0; }
    </style>
</head>
<body>
    <div id="container">
        <h1>📖 Générateur de Roman IA</h1>
<a href="index.php?reset=1">
    <button style="background: #ff4444; color: white; margin-top: 10px;">🆕 Nouvelle Histoire</button>
</a>

        <?php if (empty($_SESSION['title'])): ?>
            <form method="post">
                <input type="text" name="subject" placeholder="Entrez le sujet de votre roman..." required>
                <button type="submit">Générer le Sommaire</button>
            </form>
        <?php else: ?>
            <h2>Roman : <?= htmlspecialchars($_SESSION['title']) ?></h2>
            <button id="start">Lancer la génération</button>
            <div id="loader">✍️ Génération en cours...</div>
            <div id="story"></div>
        <?php endif; ?>
    </div>

    <script>
        $(document).ready(function() {
            let count = 0;
            $("#start").click(function() {
                $("#loader").show();
                function fetchPart() {
                    $.get("index.php?generate=1", function(data) {
                        let result = JSON.parse(data);
                        if (result.done) {
                            $("#loader").hide();
                            return;
                        }
                        $("#story").append("<p><strong>Page " + result.page + ":</strong> " + result.text + "</p>");
                        count++;
                        if (count < 200) {
                            setTimeout(fetchPart, 1000);
                        } else {
                            $("#loader").hide();
                        }
                    });
                }
                fetchPart();
            });
        });
    </script>
</body>
</html>
