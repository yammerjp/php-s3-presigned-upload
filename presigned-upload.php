<?php

// ref: https://technoledge.net/amazon-s3-signature-version-2to4-migration-php/?utm_source=pocket_saves

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
  http_response_code(403);
  exit;
}

$json = file_get_contents('php://input');
// Converts json data into a PHP object 
$file = json_decode($json, true);

$now = time();

// アップロード先のバケット
$region = getenv('AWS_REGION');
$bucket = getenv('AWS_BUCKET');

// APIキー
$accessKey = getenv('AWS_ACCESS_KEY_ID');
$secretKey = getenv('AWS_SECRET_ACCESS_KEY');

$fileName = $file['name'];
$fileType = $file['type'];
$fileSize = $file['size'];

// アップロード先のKey(パス)
$fileKey = 'tmp/20230923/' . $fileName;

$acl = "public-read";

// ポリシー作成 (配列)
$policy = [
    // アップロード期限
    'expiration' => gmdate('Y-m-d\TH:i:s.000\Z', $now + 60),

    'conditions' => [
        // アップロード先のバケット
        ['bucket' => $bucket],
        // ファイルパス
        ['key' => $fileKey],
        // アップロードを許可するコンテンツタイプ
        ['Content-Type' => $fileType],
        // アップロードを許可するファイルサイズ (下限/上限)
        ['content-length-range', $fileSize, $fileSize],
        // アップロードしたファイルのACL
        ['acl' => $acl],
        // アップロード成功時のレスポンスをXMLで返すオプション
        ['success_action_status' => '201'],
        // ハッシュ化アルゴリズム (固定) ※新規追加
        ['x-amz-algorithm' => 'AWS4-HMAC-SHA256'],
        // 許可するポリシーの種類 ※新規追加
        ['x-amz-credential' => implode('/', [$accessKey, gmdate('Ymd', $now), $region, 's3', 'aws4_request'])],
        // ポリシー生成時の日時 ※新規追加
        ['x-amz-date' => gmdate('Ymd\THis\Z', $now)],
    ],
];

// ポリシー文字列
$stringToSign = base64_encode(json_encode($policy));

// 署名生成
$dateKey = hash_hmac('sha256', gmdate('Ymd', $now), 'AWS4' . $secretKey, true);
$dateRegionKey = hash_hmac('sha256', $region, $dateKey, true);
$dateRegionServiceKey = hash_hmac('sha256', 's3', $dateRegionKey, true);
$signingKey = hash_hmac('sha256', 'aws4_request', $dateRegionServiceKey, true);

// ハッシュ化されたバイナリはBase64エンコードではなく、16進数の文字列で出力
$signature = hash_hmac('sha256', $stringToSign, $signingKey, false);

// POSTデータ生成
$data = [
    'bucket' => $bucket, // ※新規追加
    'key' => $fileKey,
    'Content-Type' => $file['type'],
    'acl' => $acl,
    'success_action_status' => '201',
    'policy' => $stringToSign,
    'x-amz-credential' => implode('/', [$accessKey, gmdate('Ymd', $now), $region, 's3', 'aws4_request']), // ※AWSAccessKeyIdの代わり
    'x-amz-signature' => $signature, // ※signatureの代わり
    'x-amz-algorithm' => 'AWS4-HMAC-SHA256', // ※新規追加
    'x-amz-date' => gmdate('Ymd\THis\Z', $now), // ※新規追加
];

header("Content-Type: application/json;");

echo json_encode([
    'upload_url' => 'https://' . $bucket . '.s3.amazonaws.com',
    'data' => $data,
    'public_url' => 'https://' . $bucket . '.s3.amazonaws.com/' . $fileKey,
]);
