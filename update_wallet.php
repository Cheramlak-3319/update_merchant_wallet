<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Only POST requests are allowed"]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['wallets'])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid input. Expecting JSON with 'wallets' array"]);
    exit;
}

$conn = mysqli_connect("127.0.0.1", "root", "9Bcts_2015", "sugarcrm");
if (!$conn) {
    echo json_encode(["error" => "Could not connect to database"]);
    exit;
}

$log = "update_wallet.log";

/**
 * DB-supported fields ONLY
 * pin removed
 */
$dbUpdatableFields = [
    'name',
    'mobile',
    'active',
    'userid',
    'fullname',
    'region',
    'city',
    'zone',
    'wereda'
];

/**
 * Accepted but NOT stored (future-proof)
 */
$virtualFields = [
    'nfccard_serialno',
    'nfccard_no'
];

$updatedWallets = [];

foreach ($input['wallets'] as $w) {
    if (!isset($w['id'])) continue;

    // Block unknown fields
    $allowed = array_merge(['id'], $dbUpdatableFields, $virtualFields);
    $blocked = array_diff(array_keys($w), $allowed);

    if (!empty($blocked)) {
        echo json_encode([
            "error" => "Blocked fields detected",
            "fields" => array_values($blocked)
        ]);
        exit;
    }

    $updates = [];

    foreach ($dbUpdatableFields as $field) {
        if (isset($w[$field])) {
            $updates[] = "$field = '" . mysqli_real_escape_string($conn, $w[$field]) . "'";
        }
    }

    // Log NFC values (DB has no columns)
    if (isset($w['nfccard_serialno']) || isset($w['nfccard_no'])) {
        file_put_contents(
            $log,
            PHP_EOL . date('Y-m-d H:i:s') . " NFC DATA for {$w['id']} → " .
            json_encode([
                'serial' => $w['nfccard_serialno'] ?? null,
                'card'   => $w['nfccard_no'] ?? null
            ]),
            FILE_APPEND
        );
    }

    if (empty($updates)) continue;

    $sql = "UPDATE dube_wallet SET " . implode(', ', $updates) .
           " WHERE id = '" . mysqli_real_escape_string($conn, $w['id']) . "'";

    mysqli_query($conn, $sql);

    $res = mysqli_query(
        $conn,
        "SELECT id, name, mobile, userid, fullname, region, city, zone, wereda, wallettype
         FROM dube_wallet WHERE id = '" . mysqli_real_escape_string($conn, $w['id']) . "'"
    );

    $updatedWallets[] = mysqli_fetch_assoc($res);
}

mysqli_close($conn);

echo json_encode([
    "message" => "Wallets updated successfully",
    "wallets" => $updatedWallets
], JSON_PRETTY_PRINT);
