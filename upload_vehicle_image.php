<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");


// =====================================================
// ONLY POST REQUEST
// =====================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    echo json_encode([
        "status" => "error",
        "message" => "Only POST requests are allowed"
    ]);

    exit();
}


// =====================================================
// CHECK IMAGE
// =====================================================

if (!isset($_FILES['vehicle_image'])) {

    echo json_encode([
        "status" => "error",
        "message" => "Vehicle image is required"
    ]);

    exit();
}


$file = $_FILES['vehicle_image'];


// =====================================================
// CHECK UPLOAD ERROR
// =====================================================

if ($file['error'] !== UPLOAD_ERR_OK) {

    echo json_encode([
        "status" => "error",
        "message" => "Image upload failed",
        "upload_error" => $file['error']
    ]);

    exit();
}


// =====================================================
// CHECK FILE SIZE
// Maximum 10 MB
// =====================================================

$maxSize = 10 * 1024 * 1024;

if ($file['size'] > $maxSize) {

    echo json_encode([
        "status" => "error",
        "message" => "Image size must be 10 MB or less"
    ]);

    exit();
}


// =====================================================
// CHECK IMAGE MIME TYPE
// =====================================================

$finfo = finfo_open(FILEINFO_MIME_TYPE);

$mimeType = finfo_file(
    $finfo,
    $file['tmp_name']
);

finfo_close($finfo);


$allowedTypes = [
    "image/jpeg",
    "image/png",
    "image/webp"
];


if (!in_array($mimeType, $allowedTypes, true)) {

    echo json_encode([
        "status" => "error",
        "message" => "Only JPG, PNG and WEBP images are allowed"
    ]);

    exit();
}


// =====================================================
// GET CLOUDINARY VARIABLES
// From Railway Environment Variables
// =====================================================

$cloudName = getenv("CLOUDINARY_CLOUD_NAME");

$apiKey = getenv("CLOUDINARY_API_KEY");

$apiSecret = getenv("CLOUDINARY_API_SECRET");


if (
    empty($cloudName) ||
    empty($apiKey) ||
    empty($apiSecret)
) {

    echo json_encode([
        "status" => "error",
        "message" => "Cloudinary configuration is missing"
    ]);

    exit();
}


// =====================================================
// CLOUDINARY FOLDER
// =====================================================

$folder = "rentx/vehicles";


// =====================================================
// CLOUDINARY UPLOAD URL
// =====================================================

$cloudinaryUrl =
    "https://api.cloudinary.com/v1_1/" .
    rawurlencode($cloudName) .
    "/image/upload";


// =====================================================
// PREPARE UPLOAD DATA
// =====================================================

$postFields = [

    "file" => new CURLFile(
        $file['tmp_name'],
        $mimeType,
        $file['name']
    ),

    "folder" => $folder
];


// =====================================================
// CREATE CURL REQUEST
// =====================================================

$ch = curl_init($cloudinaryUrl);


curl_setopt(
    $ch,
    CURLOPT_POST,
    true
);


curl_setopt(
    $ch,
    CURLOPT_POSTFIELDS,
    $postFields
);


// =====================================================
// CLOUDINARY BASIC AUTHENTICATION
// API KEY : API SECRET
// =====================================================

curl_setopt(
    $ch,
    CURLOPT_USERPWD,
    $apiKey . ":" . $apiSecret
);


// =====================================================
// CURL OPTIONS
// =====================================================

curl_setopt(
    $ch,
    CURLOPT_RETURNTRANSFER,
    true
);


curl_setopt(
    $ch,
    CURLOPT_TIMEOUT,
    60
);


curl_setopt(
    $ch,
    CURLOPT_CONNECTTIMEOUT,
    20
);


// =====================================================
// EXECUTE CLOUDINARY UPLOAD
// =====================================================

$response = curl_exec($ch);


$curlError = curl_error($ch);


$httpCode = curl_getinfo(
    $ch,
    CURLINFO_HTTP_CODE
);


curl_close($ch);


// =====================================================
// CURL ERROR
// =====================================================

if ($response === false || !empty($curlError)) {

    echo json_encode([
        "status" => "error",
        "message" => "Unable to connect to Cloudinary",
        "error" => $curlError
    ]);

    exit();
}


// =====================================================
// DECODE CLOUDINARY RESPONSE
// =====================================================

$cloudinaryData = json_decode(
    $response,
    true
);


// =====================================================
// CHECK CLOUDINARY RESPONSE
// =====================================================

if (
    $httpCode < 200 ||
    $httpCode >= 300 ||
    !isset($cloudinaryData['secure_url'])
) {

    echo json_encode([
        "status" => "error",
        "message" => "Cloudinary upload failed",
        "http_code" => $httpCode,
        "cloudinary_response" => $cloudinaryData
    ]);

    exit();
}


// =====================================================
// GET IMAGE URL
// =====================================================

$imageUrl =
    $cloudinaryData['secure_url'];


// =====================================================
// GET CLOUDINARY PUBLIC ID
// =====================================================

$publicId =
    $cloudinaryData['public_id'] ?? "";


// =====================================================
// GET IMAGE INFORMATION
// =====================================================

$width =
    $cloudinaryData['width'] ?? 0;

$height =
    $cloudinaryData['height'] ?? 0;

$format =
    $cloudinaryData['format'] ?? "";

$bytes =
    $cloudinaryData['bytes'] ?? 0;


// =====================================================
// SUCCESS RESPONSE
// =====================================================

echo json_encode([

    "status" => "success",

    "message" =>
        "Vehicle image uploaded successfully",

    "image_url" =>
        $imageUrl,

    "public_id" =>
        $publicId,

    "width" =>
        $width,

    "height" =>
        $height,

    "format" =>
        $format,

    "bytes" =>
        $bytes

]);

?>
