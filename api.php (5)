<?php
header("Content-Type: application/json; charset=utf-8");

// === Cache sozlamalari ===
$cache_dir = __DIR__ . "/cache";

if (!is_dir($cache_dir)) {
    mkdir($cache_dir, 0777, true);
}

function curl_get($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_USERAGENT,
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0 Safari/537.36'
    );
    $html = curl_exec($ch);
    curl_close($ch);
    return $html;
}

function get_file_size($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT,
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0 Safari/537.36'
    );
    curl_exec($ch);
    $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    curl_close($ch);
    return ($size > 0) ? round($size / 1024 / 1024, 2) . ' MB' : "Noma'lum";
}

function absolute_url($base, $relative) {
    if (preg_match('#^https?://#i', $relative)) {
        return $relative;
    }
    $parsed_base = parse_url($base);
    $scheme = $parsed_base['scheme'] ?? 'https';
    $host = $parsed_base['host'] ?? '';
    if (strpos($relative, '/') === 0) {
        return $scheme . '://' . $host . $relative;
    }
    $path = isset($parsed_base['path']) ? dirname($parsed_base['path']) : '';
    return $scheme . '://' . $host . '/' . ltrim($path . '/' . $relative, '/');
}

// Parametr
$music = isset($_GET['music']) ? trim($_GET['music']) : '';
if ($music === '') {
    echo "Admin: @webuz_coder.\n";
    echo "Apini ishlatish uchun namuna:\n";
    echo "https://67dd52691a28e.xvest5.ru/api/musiqa/api.php?music=yagzon\n";
    exit;
}

// Cache fayl nomi
$cache_file = $cache_dir . "/" . md5($music) . ".json";

// Agar cache mavjud bo‘lsa → shuni qaytar
if (file_exists($cache_file)) {
    echo file_get_contents($cache_file);
    exit;
}

// Aks holda yangidan olish
$search_url = "https://uzxit.net/index.php?do=search&subaction=search&story=" . urlencode($music);
$html = curl_get($search_url);
if (!$html) {
    echo json_encode(["holat" => false, "error" => "HTML yuklab bo‘lmadi"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Sahifadagi linklarni yig‘ish
libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->loadHTML($html);
$links = $dom->getElementsByTagName('a');

$song_pages = [];
foreach ($links as $link) {
    $href = $link->getAttribute('href');
    if (strpos($href, 'news/') !== false || strpos($href, 'mp3') !== false) {
        $full_url = absolute_url($search_url, $href);
        $song_pages[] = $full_url;
    }
}
$song_pages = array_unique($song_pages);

$results = [];
foreach ($song_pages as $page_url) {
    $page_html = curl_get($page_url);
    if (!$page_html) continue;

    // mp3 linklarni olish
    preg_match_all('/https?:\\\\?\/\\\\?\/[^\s"\']+\.mp3/i', $page_html, $matches);
    $mp3_links = array_unique($matches[0]);

    // \/ belgilarini olib tashlash
    $mp3_links = array_map(function ($url) {
        return str_replace('\/', '/', $url);
    }, $mp3_links);

    foreach ($mp3_links as $mp3) {
        preg_match('/<title>(.*?)<\/title>/si', $page_html, $title_match);
        $title = isset($title_match[1]) ? trim(html_entity_decode($title_match[1])) : basename($mp3);

        $size = get_file_size($mp3);

        $results[] = [
            "nomi"   => $title,
            "hajmi"  => $size,
            "yuklash" => $mp3
        ];
    }
}

$response = json_encode([
    "holat"        => true,
    "qidiruv_sozi" => $music,
    "manba"        => $search_url,
    "mp3_soni"     => count($results),
    "natijalar"    => $results
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

// Javobni cache ga yozish
file_put_contents($cache_file, $response);

// Foydalanuvchiga chiqarish
echo $response;