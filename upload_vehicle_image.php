<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// =====================================================
// ONLY POST REQUEST
// =====================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "Only POST requests are allowed"
    ]);
    exit;
}

// =====================================================
// CHECK IMAGE
// =====================================================
if (!isset($_FILES['vehicle_image'])) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Vehicle image is required"
    ]);
    exit;
}

$file = $_FILES['vehicle_image'];

// =====================================================
// CHECK UPLOAD ERROR
// =====================================================
if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
    $uploadError = $file['error'] ?? UPLOAD_ERR_NO_FILE;

    $uploadMessages = [
        UPLOAD_ERR_INI_SIZE   => "Image exceeds the server upload_max_filesize limit",
        UPLOAD_ERR_FORM_SIZE  => "Image exceeds the form upload size limit",
        UPLOAD_ERR_PARTIAL    => "Image was only partially uploaded",
        UPLOAD_ERR_NO_FILE    => "No image file was received",
        UPLOAD_ERR_NO_TMP_DIR => "Server temporary upload directory is missing",
        UPLOAD_ERR_CANT_WRITE => "Server could not write the uploaded image",
        UPLOAD_ERR_EXTENSION  => "A PHP extension stopped the image upload"
    ];

    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => $uploadMessages[$uploadError] ?? "Image upload failed",
        "upload_error" => $uploadError
    ]);
    exit;
}

// =====================================================
// CHECK TEMP FILE AND SIZE (MAX 10 MB)
// =====================================================
if (
    empty($file['tmp_name']) ||
    !is_uploaded_file($file['tmp_name'])
) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Uploaded image temporary file is invalid"
    ]);
    exit;
}

$maxSize = 10 * 1024 * 1024;

if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxSize) {
    http_response_code(413);
    echo json_encode([
        "status" => "error",
        "message" => "Image must be larger than 0 bytes and 10 MB or less"
    ]);
    exit;
}

// =====================================================
// CHECK MIME TYPE
// =====================================================
if (!class_exists('finfo')) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "PHP fileinfo extension is not enabled"
    ]);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
if ($finfo === false) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Unable to inspect uploaded image type"
    ]);
    exit;
}

$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowedTypes = [
    "image/jpeg",
    "image/png",
    "image/webp"
];

if (!in_array($mimeType, $allowedTypes, true)) {
    http_response_code(415);
    echo json_encode([
        "status" => "error",
        "message" => "Only JPG, PNG and WEBP images are allowed"
    ]);
    exit;
}

// Validate actual image data as well as MIME type.
if (@getimagesize($file['tmp_name']) === false) {
    http_response_code(415);
    echo json_encode([
        "status" => "error",
        "message" => "The selected file is not a valid image"
    ]);
    exit;
}

// =====================================================
// CHECK REQUIRED PHP EXTENSIONS
// =====================================================
if (!function_exists('curl_init')) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "PHP cURL extension is not enabled on the server"
    ]);
    exit;
}

if (!class_exists('CURLFile')) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "PHP CURLFile support is unavailable"
    ]);
    exit;
}

// =====================================================
// CLOUDINARY ENVIRONMENT VARIABLES
// Set these in Railway Variables.
// =====================================================
$cloudName = trim((string) getenv("CLOUDINARY_CLOUD_NAME"));
$apiKey = trim((string) getenv("CLOUDINARY_API_KEY"));
$apiSecret = trim((string) getenv("CLOUDINARY_API_SECRET"));

if ($cloudName === "" || $apiKey === "" || $apiSecret === "") {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Cloudinary configuration is missing. Check Railway Variables."
    ]);
    exit;
}

// =====================================================
// CLOUDINARY SIGNED UPLOAD
// =====================================================
$folder = "rentx/vehicles";
$timestamp = time();

$cloudinaryUrl =
    "https://api.cloudinary.com/v1_1/" .
    rawurlencode($cloudName) .
    "/image/upload";

// Cloudinary signed parameters are sorted alphabetically.
// Do not include api_key, file, or signature in the signature string.
$signatureString =
    "folder=" . $folder .
    "&timestamp=" . $timestamp .
    $apiSecret;

$signature = sha1($signatureString);

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

// =====================================================
// CLOUDINARY CONNECTION ERROR
// =====================================================
if ($response === false) {
    http_response_code(502);
    echo json_encode([
        "status" => "error",
        "message" => "Unable to connect to Cloudinary",
        // Useful for Railway logs/debugging; avoid exposing secrets.
        "error" => $curlError
    ]);
    exit;
}

// =====================================================
// DECODE CLOUDINARY RESPONSE
// =====================================================
$cloudinaryData = json_decode($response, true);

if (
    $httpCode < 200 ||
    $httpCode >= 300 ||
    !is_array($cloudinaryData) ||
    empty($cloudinaryData['secure_url'])
) {
    http_response_code(502);

    $cloudinaryMessage = "Cloudinary upload failed";
    if (is_array($cloudinaryData) && !empty($cloudinaryData['error']['message'])) {
        $cloudinaryMessage = (string) $cloudinaryData['error']['message'];
    }

    echo json_encode([
        "status" => "error",
        "message" => $cloudinaryMessage,
        "http_code" => $httpCode
    ]);
    exit;
}

// =====================================================
// SUCCESS RESPONSE — MATCHES ANDROID EXPECTATIONS
// Android expects status == "success" and image_url.
// =====================================================
echo json_encode([
    "status" => "success",
    "message" => "Vehicle image uploaded successfully",
    "image_url" => $cloudinaryData['secure_url'],
    "public_id" => $cloudinaryData['public_id'] ?? "",
    "width" => $cloudinaryData['width'] ?? 0,
    "height" => $cloudinaryData['height'] ?? 0,
    "format" => $cloudinaryData['format'] ?? "",
    "bytes" => $cloudinaryData['bytes'] ?? 0
], JSON_UNESCAPED_SLASHES);

exit;
?>
