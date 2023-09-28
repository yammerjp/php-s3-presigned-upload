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

// ���åץ�����ΥХ��å�
$region = getenv('AWS_REGION');
$bucket = getenv('AWS_BUCKET');

// API����
$accessKey = getenv('AWS_ACCESS_KEY_ID');
$secretKey = getenv('AWS_SECRET_ACCESS_KEY');

$fileName = $file['name'];
$fileType = $file['type'];
$fileSize = $file['size'];

// ���åץ������Key(�ѥ�)
$fileKey = 'tmp/20230923/' . $fileName;

$acl = "public-read";

// �ݥꥷ������ (����)
$policy = [
    // ���åץ��ɴ���
    'expiration' => gmdate('Y-m-d\TH:i:s.000\Z', $now + 60),

    'conditions' => [
        // ���åץ�����ΥХ��å�
        ['bucket' => $bucket],
        // �ե�����ѥ�
        ['key' => $fileKey],
        // ���åץ��ɤ���Ĥ��륳��ƥ�ĥ�����
        ['Content-Type' => $fileType],
        // ���åץ��ɤ���Ĥ���ե����륵���� (����/���)
        ['content-length-range', $fileSize, $fileSize],
        // ���åץ��ɤ����ե������ACL
        ['acl' => $acl],
        // ���åץ����������Υ쥹�ݥ󥹤�XML���֤����ץ����
        ['success_action_status' => '201'],
        // �ϥå��岽���르�ꥺ�� (����) �������ɲ�
        ['x-amz-algorithm' => 'AWS4-HMAC-SHA256'],
        // ���Ĥ���ݥꥷ���μ��� �������ɲ�
        ['x-amz-credential' => implode('/', [$accessKey, gmdate('Ymd', $now), $region, 's3', 'aws4_request'])],
        // �ݥꥷ�������������� �������ɲ�
        ['x-amz-date' => gmdate('Ymd\THis\Z', $now)],
    ],
];

// �ݥꥷ��ʸ����
$stringToSign = base64_encode(json_encode($policy));

// ��̾����
$dateKey = hash_hmac('sha256', gmdate('Ymd', $now), 'AWS4' . $secretKey, true);
$dateRegionKey = hash_hmac('sha256', $region, $dateKey, true);
$dateRegionServiceKey = hash_hmac('sha256', 's3', $dateRegionKey, true);
$signingKey = hash_hmac('sha256', 'aws4_request', $dateRegionServiceKey, true);

// �ϥå��岽���줿�Х��ʥ��Base64���󥳡��ɤǤϤʤ���16�ʿ���ʸ����ǽ���
$signature = hash_hmac('sha256', $stringToSign, $signingKey, false);

// POST�ǡ�������
$data = [
    'bucket' => $bucket, // �������ɲ�
    'key' => $fileKey,
    'Content-Type' => $file['type'],
    'acl' => $acl,
    'success_action_status' => '201',
    'policy' => $stringToSign,
    'x-amz-credential' => implode('/', [$accessKey, gmdate('Ymd', $now), $region, 's3', 'aws4_request']), // ��AWSAccessKeyId������
    'x-amz-signature' => $signature, // ��signature������
    'x-amz-algorithm' => 'AWS4-HMAC-SHA256', // �������ɲ�
    'x-amz-date' => gmdate('Ymd\THis\Z', $now), // �������ɲ�
];

header("Content-Type: application/json;");

echo json_encode([
    'upload_url' => 'https://' . $bucket . '.s3.amazonaws.com',
    'data' => $data,
    'public_url' => 'https://' . $bucket . '.s3.amazonaws.com/' . $fileKey,
]);
