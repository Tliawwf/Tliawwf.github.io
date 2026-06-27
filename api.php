<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$dataFile = __DIR__ . '/data/submissions.json';

if (!is_dir(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0755, true);
}
if (!file_exists($dataFile)) {
    file_put_contents($dataFile, '[]');
}

function readData() {
    global $dataFile;
    $raw = file_get_contents($dataFile);
    return json_decode($raw, true) ?: [];
}

function writeData($data) {
    global $dataFile;
    file_put_contents($dataFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// ---- GET：获取所有投稿 ----
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $list = readData();
    echo json_encode([
        'success' => true,
        'count'   => count($list),
        'data'    => $list
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- POST：提交新投稿 ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $song   = isset($input['song'])   ? trim($input['song'])   : '';
    $artist = isset($input['artist']) ? trim($input['artist']) : '';
    $reason = isset($input['reason']) ? trim($input['reason']) : '';

    if ($song === '' || mb_strlen($song) > 60) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '歌曲名无效'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (mb_strlen($artist) > 40 || mb_strlen($reason) > 200) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '内容超出长度限制'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 简单频率限制：同一 IP 10 秒内只能提交一次
    $ipFile = __DIR__ . '/data/.ratelimit';
    $ipList = [];
    if (file_exists($ipFile)) {
        $ipList = json_decode(file_get_contents($ipFile), true) ?: [];
    }
    $now = time();
    $myIP = $_SERVER['REMOTE_ADDR'];

    // 清理 10 秒前的记录
    $ipList = array_filter($ipList, function($v) use ($now) {
        return ($now - $v) < 10;
    });

    if (isset($ipList[$myIP])) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => '操作太频繁，请稍后再试'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $ipList[$myIP] = $now;
    file_put_contents($ipFile, json_encode($ipList));

    $submission = [
        'id'     => intval(microtime(true) * 1000),
        'song'   => $song,
        'artist' => $artist,
        'reason' => $reason,
        'time'   => date('m-d H:i')
    ];

    $list = readData();
    array_unshift($list, $submission);
    writeData($list);

    echo json_encode([
        'success' => true,
        'message' => '投稿成功',
        'data'    => $submission
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
