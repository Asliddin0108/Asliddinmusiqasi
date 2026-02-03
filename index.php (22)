<?php
$protocol = 'https://';
$subdomain = 'social-dl';
$domain = 'hazex.workers.dev';
$path = '/?url=';
$apiUrl = $protocol . $subdomain . '.' . $domain . $path;

if (isset($_GET['url'])) {
    $url = $_GET['url'];
    $fullApiUrl = $apiUrl . urlencode($url);
    $response = file_get_contents($fullApiUrl);
    if ($response !== false) {
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            unset($data['join']);
            unset($data['support']);
            $data['dev'] = '@webuz_coder';
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        } else {
            echo "Javobni JSON formatiga aylantirishda xato: " . json_last_error_msg();
        }
    } else {
        echo "Xato.";
    }
} else {
    header('Content-Type: application/json; charset=utf-8');
    $info = [
        "Admin" => "@webuz_coder",
        "Apini ishlatish uchun namuna" => "https://67dd52691a28e.xvest5.ru/api/Instagram/index.php?url=https://www.instagram.com/reel/DMX-c--onhV/?igsh=OWdyejhlbXJ5OG85"
    ];
    echo json_encode($info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}
?>