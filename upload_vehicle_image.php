<?php
header("Content-Type: application/json; charset=UTF-8");

function reply($http, $data) {
    http_response_code($http);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    reply(405, ["status"=>"error", "message"=>"Use POST"]);
}

$cloudName = trim((string)getenv("CLOUDINARY_CLOUD_NAME"));
$apiKey = trim((string)getenv("CLOUDINARY_API_KEY"));
$apiSecret = trim((string)getenv("CLOUDINARY_API_SECRET"));

if ($cloudName === "" || $apiKey === "" || $apiSecret === "") {
    reply(500, [
        "status"=>"error",
        "message"=>"Cloudinary configuration is missing in Railway Variables",
        "cloud_name_set"=>$cloudName !== "",
        "api_key_set"=>$apiKey !== "",
        "api_secret_set"=>$apiSecret !== ""
    ]);
}

if (!isset($_FILES["vehicle_image"])) {
    reply(400, ["status"=>"error", "message"=>"Vehicle image is required"]);
}

$file = $_FILES["vehicle_image"];
if (($file["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK ||
    empty($file["tmp_name"]) ||
    !is_uploaded_file($file["tmp_name"])) {
    reply(400, ["status"=>"error", "message"=>"PHP did not receive a valid image upload"]);
}

if (($file["size"] ?? 0) <= 0 || $file["size"] > 10 * 1024 * 1024) {
    reply(413, ["status"=>"error", "message"=>"Image must be 10 MB or smaller"]);
}

if (!function_exists("curl_init") || !class_exists("CURLFile") || !function_exists("finfo_open")) {
    reply(500, ["status"=>"error", "message"=>"PHP cURL, fileinfo and CURLFile support are required"]);
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file["tmp_name"]);
finfo_close($finfo);
$allowed = ["image/jpeg", "image/png", "image/webp"];
if (!in_array($mime, $allowed, true) || @getimagesize($file["tmp_name"]) === false) {
    reply(415, ["status"=>"error", "message"=>"Only valid JPG, PNG or WEBP images are allowed"]);
}

/*
 * Cloudinary signs request parameters alphabetically, excluding file,
 * api_key and signature. For folder + timestamp this string must be:
 * folder=rentx/vehicles&timestamp=<timestamp><API_SECRET>
 */
$folder = "rentx/vehicles";
$timestamp = time();
$params = ["folder"=>$folder, "timestamp"=>(string)$timestamp];
ksort($params, SORT_STRING);
$parts = [];
foreach ($params as $key=>$value) {
    $parts[] = $key . "=" . $value;
}
$stringToSign = implode("&", $parts);
$signature = sha1($stringToSign . $apiSecret);

$url = "https://api.cloudinary.com/v1_1/" . rawurlencode($cloudName) . "/image/upload";
$post = [
    "file" => new CURLFile($file["tmp_name"], $mime, basename((string)($file["name"] ?? "vehicle_image"))),
    "folder" => $folder,
    "timestamp" => $timestamp,
    "api_key" => $apiKey,
    "signature" => $signature
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS=>$post,
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_CONNECTTIMEOUT=>20,
    CURLOPT_TIMEOUT=>60,
    CURLOPT_SSL_VERIFYPEER=>true,
    CURLOPT_SSL_VERIFYHOST=>2
]);
$body = curl_exec($ch);
$curlError = curl_error($ch);
$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($body === false) {
    reply(502, ["status"=>"error", "message"=>"Could not connect to Cloudinary"]);
}

$data = json_decode($body, true);
if ($http < 200 || $http >= 300 || !is_array($data) || empty($data["secure_url"])) {
    $msg = is_array($data) ? (string)($data["error"]["message"] ?? "Cloudinary upload failed") : "Cloudinary returned an invalid response";
    // Do not return credentials or the computed signature to the client.
    reply(502, [
        "status"=>"error",
        "message"=>$msg,
        "http_code"=>$http,
        "diagnostic"=>"Check that Railway CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY and CLOUDINARY_API_SECRET are a matching set from the same Cloudinary account."
    ]);
}

reply(200, [
    "status"=>"success",
    "message"=>"Vehicle image uploaded successfully",
    "image_url"=>$data["secure_url"],
    "public_id"=>$data["public_id"] ?? "",
    "width"=>$data["width"] ?? 0,
    "height"=>$data["height"] ?? 0,
    "format"=>$data["format"] ?? "",
    "bytes"=>$data["bytes"] ?? 0
]);
?>
