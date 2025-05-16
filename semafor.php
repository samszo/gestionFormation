<?php
include('keys.php');

$type = isset($_GET['type']) ? $_GET['type'] : "Appellations";
$search = isset($_GET['search']) ? $_GET['search'] : "codeur";
$nb = isset($_GET['nb']) ? $_GET['nb'] : 5;

// Vérifier si le token est déjà stocké et s'il est encore valide
$tokenFile = 'token.txt';
$tokenData = @json_decode(file_get_contents($tokenFile), true);
$token = null;

if ($tokenData && isset($tokenData['token']) && isset($tokenData['expires_at']) && $tokenData['expires_at'] > time()) {
    // Utiliser le token existant
    $token = $tokenData['token'];
} else {
    // Récupérer un nouveau token  
    $ch = curl_init($paramsSemafor['url_token']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($authDataSemafor));
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

// Ajouter le token à l'en-tête de la requête
//$url = "https://recherche-referentiel-v2.staging.analytics.diagotech.dev/search/"
$url = $paramsSemafor['url_search']
    .$type."?query=".urlencode($search)
    ."&nb_results=".$nb;
$headers = array(
    'Authorization: Bearer ' . $token,
    "Accept: application/json"    
);
// Initialiser une session cURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Définir les options cURL
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
//curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
// Exécuter la session cURL
$response = curl_exec($ch);

/*exemple ligne de commande
```sh
ACCESS_TOKEN="eyJhbGciOiJSUzI1NiIsInR5cCIgOiAiSldUIiwia2lkIiA6ICJIcEo3ekNtcmJrOGo0b2RTNUVMLVVZemRLbEdoak4wb3hOTmZKY1NWN2h3In0.eyJleHAiOjE3NDE3NDQyMDYsImlhdCI6MTc0MTcwODIwNiwianRpIjoiM2FkNGQ0ZWEtNjgzMy00ZjI2LWI3YzctYTFhMmVlMjIwZGY1IiwiaXNzIjoiaHR0cHM6Ly9hbmFseXRpY3MtYXV0aC5taWRnYXJkLmRpYWdvdGVjaC5kZXYvcmVhbG1zL2VzaS1hdXRoLWtleWNsb2FjayIsImF1ZCI6WyJlc2ktYmFjayIsImVzaS1yZWNoLXJlZiIsImFjY291bnQiXSwic3ViIjoiZDMxM2Q5MDYtZWFjMS00MmEyLTgxZWUtZjQ4NTFmZjMyNmY3IiwidHlwIjoiQmVhcmVyIiwiYXpwIjoiRVhUX3NlbWFmb3Jfc2FtdWVsX3N6b25pZWNreSIsImFjciI6IjEiLCJhbGxvd2VkLW9yaWdpbnMiOlsiKiJdLCJyZWFsbV9hY2Nlc3MiOnsicm9sZXMiOlsib2ZmbGluZV9hY2Nlc3MiLCJ1bWFfYXV0aG9yaXphdGlvbiIsImRlZmF1bHQtcm9sZXMtZXNpLWF1dGgiXX0sInJlc291cmNlX2FjY2VzcyI6eyJlc2ktYmFjayI6eyJyb2xlcyI6WyJlc2ktYmFjay1jb25zdW1lciJdfSwiZXNpLXJlY2gtcmVmIjp7InJvbGVzIjpbImVzaS1yZWNoLXJlZi1jb25zdW1lciJdfSwiYWNjb3VudCI6eyJyb2xlcyI6WyJtYW5hZ2UtYWNjb3VudCIsIm1hbmFnZS1hY2NvdW50LWxpbmtzIiwidmlldy1wcm9maWxlIl19fSwic2NvcGUiOiJwcm9maWxlIGVtYWlsIiwiZW1haWxfdmVyaWZpZWQiOmZhbHNlLCJjbGllbnRIb3N0IjoiMTAuMi41LjAiLCJwcmVmZXJyZWRfdXNlcm5hbWUiOiJzZXJ2aWNlLWFjY291bnQtZXh0X3NlbWFmb3Jfc2FtdWVsX3N6b25pZWNreSIsImNsaWVudEFkZHJlc3MiOiIxMC4yLjUuMCIsImNsaWVudF9pZCI6IkVYVF9zZW1hZm9yX3NhbXVlbF9zem9uaWVja3kifQ.RNdRwtG5Z5p4S_ZnH1j_3SCYtW985argBPkt_Vk2KqeCH897h1AUO_ZVACXIlCOJ-9IhcRogFKGIvI5ftwOKa72wpWAKuoQWC-2RQJV9TQWptTNLFrVMyd4z7Qd8hl8PdHQ285HYTTIk0zOeZgUBVzpZmc3KKXl5YEkIKcKNq2c2qx6i1BjXpb7Jyp3JyWqQ9S_1gbi3t4ug8nLmJvNwYHUs5ZYOJQ24pkNSHMZJljNp7rrYGEESOhZfftIKb6UftAXIeSuTn2oPrxFu2sgp-F2PuW_pUxLkgAn9qRsEfR9ViKLVT6J7M03B4waNNJNu0L6fJHoMEQYh_XSTIysecg"
curl -X 'GET' \
  'https://semafor-recette.diagoriente.beta.gouv.fr/search/Appellations?query=cr%C3%A8che&nb_results=7' \
  -H 'accept: application/json' \
  -H "Authorization: Bearer $ACCESS_TOKEN"
```
*/

// Vérifier les erreurs
if ($response === false) {
    echo 'Erreur cURL : ' . curl_error($ch);
} else {
    // Afficher la réponse
    echo $response;
}

// Fermer la session cURL
curl_close($ch);
?>
