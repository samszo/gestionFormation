<?php
include('keys.php');

$type = isset($_GET['type']) ? $_GET['type'] : "Appellations";
$search = isset($_GET['search']) ? $_GET['search'] : "codeur";
$nb = isset($_GET['nb']) ? $_GET['nb'] : 5;

// Vérifier si le token est déjà stocké et s'il est encore valide
$tokenFile = 'tokenFT.txt';
$tokenData = @json_decode(file_get_contents($tokenFile), true);
$token = null;

if ($tokenData && isset($tokenData['token']) && isset($tokenData['expires_at']) && $tokenData['expires_at'] > time()) {
    // Utiliser le token existant
    $token = $tokenData['token'];
} else {
    // Récupérer un nouveau token
    $authUrl = "https://entreprise.francetravail.fr/connexion/oauth2/access_token?realm=partenaire";
    $ch = curl_init($authUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($authData));
    $authResponse = curl_exec($ch);
    curl_close($ch);

    if ($authResponse === false) {
        die('Erreur cURL lors de la récupération du token : ' . curl_error($ch));
    }

    $authResponseData = json_decode($authResponse, true);
    if (isset($authResponseData['access_token']) && isset($authResponseData['expires_in'])) {
        $token = $authResponseData['access_token'];
        $expiresAt = time() + $authResponseData['expires_in'];

        // Stocker le token et sa date d'expiration
        file_put_contents($tokenFile, json_encode(array(
            'token' => $token,
            'expires_at' => $expiresAt
        )));
    } else {
        die('Erreur lors de la récupération du token : réponse invalide');
    }
}

// Afficher la réponse
echo $token;

?>