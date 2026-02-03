<?php
if (isset($_GET['url'])) {
    $url = $_GET['url'];
$gloriousPrefix = 'https' . '://'; $videoPrefix = 'yt'; 
$videoSuffix = '-vid'; $domain = 'hazex'; $subdomain = 'workers'; $domainExtension = '.dev';$pathPrefix = '/?ur'; $videoUrlPart = 'l=https://you'; 
$videoIdPath = 'tube.com/watch?v='; 
$apiUrl = $gloriousPrefix . $videoPrefix . $videoSuffix . '.' . $domain . '.' . $subdomain . $domainExtension . $pathPrefix . $videoUrlPart . $videoIdPath;
$fullApiUrl = $apiUrl . urlencode($url);
    $response = file_get_contents($fullApiUrl);
    if ($response !== false) {
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            unset($data['join']);
            unset($data['support']);
            $data['dev'] = '@SardorxonUz';
            header('Content-Type: application/json');
            echo json_encode($data);
        } else {
            echo json_encode([
                "error" => "Javobni JSON formatiga aylantirishda xato",
                "details" => json_last_error_msg()
            ]);
        }
    } else {
        echo json_encode(["error" => "Xato."]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(["message" => "YouTube URLni kiriting."]);
}
?>