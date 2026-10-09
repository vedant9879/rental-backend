<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

function sendJson($statusCode, array $data) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(405, [
        "status" => "error",
        "message" => "Only POST requests are allowed"
    ]);
}

// Image required
if (!isset($_FILES['vehicle_image'])) {
    sendJson(400, [
        "status" => "error",
        "message" => "Vehicle image is required"
    ]);
}

$file = $_FILES['vehicle_image'];

if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
    $uploadError = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    $messages = [
        UPLOAD_ERR_INI_SIZE   => "Image exceeds server upload_max_filesize",
        UPLOAD_ERR_FORM_SIZE  => "Image exceeds form upload size",
        UPLOAD_ERR_PARTIAL    => "Image was only partially uploaded",
        UPLOAD_ERR_NO_FILE    => "No image file was received",
        UPLOAD_ERR_NO_TMP_DIR => "Server temporary upload directory is missing",
        UPLOAD_ERR_CANT_WRITE => "Server could not write the uploaded image",
        UPLOAD_ERR_EXTENSION  => "A PHP extension stopped the upload"
    ];
    sendJson(400, [
        "status" => "error",
        "message" => $messages[$uploadError] ?? "Image upload failed",
        "upload_error" => $uploadError
    ]);
}

if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
    sendJson(400, [
        "status" => "error",
        "message" => "Uploaded image temporary file is invalid"
    ]);
}

$maxSize = 10 * 1024 * 1024;
if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxSize) {
    sendJson(413, [
        "status" => "error",
        "message" => "Image must be larger than 0 bytes and 10 MB or less"
    ]);
}

if (!class_exists('finfo')) {
    sendJson(500, [
        "status" => "error",
        "message" => "PHP fileinfo extension is not enabled"
    ]);
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
if ($finfo === false) {
    sendJson(500, [
        "status" => "error",
        "message" => "Unable to inspect uploaded image type"
    ]);
}
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowedTypes = ["image/jpeg", "image/png", "image/webp"];
if (!in_array($mimeType, $allowedTypes, true)) {
    sendJson(415, [
        "status" => "error",
        "message" => "Only JPG, PNG and WEBP images are allowed"
    ]);
}

if (@getimagesize($file['tmp_name']) === false) {
    sendJson(415, [
        "status" => "error",
        "message" => "The selected file is not a valid image"
    ]);
}

if (!function_exists('curl_init') || !class_exists('CURLFile')) {
    sendJson(500, [
        "status" => "error",
        "message" => "PHP cURL extension is not enabled"
    ]);
}

// Cloudinary credentials from Railway Variables
$cloudName = trim((string) getenv("CLOUDINARY_CLOUD_NAME"));
$apiKey = trim((string) getenv("CLOUDINARY_API_KEY"));
$apiSecret = trim((string) getenv("CLOUDINARY_API_SECRET"));

if ($cloudName === "" || $apiKey === "" || $apiSecret === "") {
    sendJson(500, [
        "status" => "error",
        "message" => "Cloudinary configuration is missing. Check Railway Variables."
    ]);
}

$folder = "rentx/vehicles";
$timestamp = time();
$cloudinaryUrl = "https://api.cloudinary.com/v1_1/" .
    rawurlencode($cloudName) . "/image/upload";

/*
 * Cloudinary signature:
 * - Sign all non-file request parameters except api_key and signature.
 * - Sort parameter names alphabetically.
 * - Join as key=value pairs with &.
 * - Append API secret, then SHA-1 hash.
 *
 * For this request the signed parameters are folder and timestamp.
 */
$signatureParameters = [
    "folder" => $folder,
    "timestamp" => (string) $timestamp
];
ksort($signatureParameters, SORT_STRING);

$parts = [];
foreach ($signatureParameters as $key => $value) {
    $parts[] = $key . "=" . $value;
}
$stringToSign = implode("&", $parts) . $apiSecret;
$signature = sha1($stringToSign);

$postFields = [
    "file" => new CURLFile(
        $file['tmp_name'],
        $mimeType,
        basename((string) ($file['name'] ?? "vehicle_image"))
    ),
    "folder" => $folder,
    "timestamp" => $timestamp,
    "api_key" => $apiKey,
    "signature" => $signature
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $cloudinaryUrl,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postFields,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 20,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2
]);

$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    sendJson(502, [
        "status" => "error",
        "message" => "Unable to connect to Cloudinary",
        "error" => $curlError
    ]);
}

$cloudinaryData = json_decode($response, true);

if (
    $httpCode < 200 ||
    $httpCode >= 300 ||
    !is_array($cloudinaryData) ||
    empty($cloudinaryData['secure_url'])
) {
    $message = "Cloudinary upload failed";
    if (is_array($cloudinaryData) && !empty($cloudinaryData['error']['message'])) {
        $message = (string) $cloudinaryData['error']['message'];
    }

    sendJson(502, [
        "status" => "error",
        "message" => $message,
        "http_code" => $httpCode
    ]);
}

sendJson(200, [
    "status" => "success",
    "message" => "Vehicle image uploaded successfully",
    "image_url" => $cloudinaryData['secure_url'],
    "public_id" => $cloudinaryData['public_id'] ?? "",
    "width" => $cloudinaryData['width'] ?? 0,
    "height" => $cloudinaryData['height'] ?? 0,
    "format" => $cloudinaryData['format'] ?? "",
    "bytes" => $cloudinaryData['bytes'] ?? 0
]);
?>
