<?php
header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Only POST requests are allowed"]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['wallet'])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid input. Expecting JSON with 'wallet' object"]);
    exit;
}

$wallet = $input['wallet'];

// Database connection
$conn = mysqli_connect("127.0.0.1", "root", "9Bcts_2015", "sugarcrm");
if (!$conn) {
    echo json_encode(["error" => "Could not connect to database"]);
    exit;
}

// Log file (will gitignore)
$log = "update_wallet.log";

// Fields allowed to update in DB
$updatableFields = [
    'name',
    'mobile',
    'active',
    'fullname',
    'region',
    'city',
    'zone',
    'wereda'
];

// NFC fields (log only)
$nfclogFields = ['nfccard_serialno', 'nfccard_no'];

// Block unknown fields
$allowed = array_merge(['userid'], $updatableFields, $nfclogFields);
$blocked = array_diff(array_keys($wallet), $allowed);
if (!empty($blocked)) {
    echo json_encode([
        "error" => "Blocked fields detected",
        "fields" => array_values($blocked)
    ]);
    exit;
}

// Build UPDATE query
$updates = [];
foreach ($updatableFields as $field) {
    if (isset($wallet[$field])) {
        $updates[] = "$field = '" . mysqli_real_escape_string($conn, $wallet[$field]) . "'";
    }
}

// Set date_modified
$updates[] = "date_modified = NOW()";

if (!empty($updates) && isset($wallet['userid'])) {
    $sql = "UPDATE dube_wallet SET " . implode(', ', $updates) .
           " WHERE userid = '" . mysqli_real_escape_string($conn, $wallet['userid']) . "'";
    mysqli_query($conn, $sql);
}

// Log NFC info
$nfclog = [];
foreach ($nfclogFields as $field) {
    if (isset($wallet[$field])) {
        $nfclog[$field] = $wallet[$field];
    }
}
if (!empty($nfclog) && isset($wallet['userid'])) {
    file_put_contents(
        $log,
        PHP_EOL . date('Y-m-d H:i:s') . " NFC DATA for userid {$wallet['userid']} → " . json_encode($nfclog),
        FILE_APPEND
    );
}

// Fetch updated wallet
$result = mysqli_query(
    $conn,
    "SELECT userid, name, mobile, active, fullname, region, city, zone, wereda, wallettype
     FROM dube_wallet WHERE userid = '" . mysqli_real_escape_string($conn, $wallet['userid']) . "'"
);
$updatedWallet = mysqli_fetch_assoc($result);

mysqli_close($conn);

// Response
echo json_encode([
    "message" => "Wallet updated successfully",
    "wallet" => $updatedWallet
], JSON_PRETTY_PRINT);
