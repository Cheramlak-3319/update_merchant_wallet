<?php
header('Content-Type: application/json');

// Only POST requests allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Only POST requests are allowed"]);
    exit;
}

// Get JSON input
$wallet = json_decode(file_get_contents('php://input'), true);

if (!$wallet || !isset($wallet['userid'])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid input. Expecting JSON with 'userid'"]);
    exit;
}

// Database connection
$conn = mysqli_connect("127.0.0.1", "root", "9Bcts_2015", "sugarcrm");
if (!$conn) {
    echo json_encode(["error" => "Could not connect to database"]);
    exit;
}

// Logging file
$log = "update_wallet.log";

// Fields allowed to update in DB (pin removed)
$updatableFields = ['name', 'mobile', 'active', 'userid', 'fullname', 'region', 'city', 'zone', 'wereda'];

// NFC fields (only logged)
$virtualFields = ['nfccard_serialno', 'nfccard_no'];

$updates = [];

// Build update query
foreach ($updatableFields as $field) {
    if (isset($wallet[$field])) {
        $updates[] = "$field = '" . mysqli_real_escape_string($conn, $wallet[$field]) . "'";
    }
}

// Always update date_modified
$updates[] = "date_modified = NOW()";

if (empty($updates)) {
    echo json_encode(["error" => "No valid fields to update"]);
    exit;
}

// Run the update
$sql = "UPDATE dube_wallet SET " . implode(', ', $updates) . 
       " WHERE userid = '" . mysqli_real_escape_string($conn, $wallet['userid']) . "'";
mysqli_query($conn, $sql);

// Log NFC info if present
if (isset($wallet['nfccard_serialno']) || isset($wallet['nfccard_no'])) {
    file_put_contents(
        $log,
        PHP_EOL . date('Y-m-d H:i:s') . " NFC DATA for userid {$wallet['userid']} → " .
        json_encode([
            'serial' => $wallet['nfccard_serialno'] ?? null,
            'card'   => $wallet['nfccard_no'] ?? null
        ]),
        FILE_APPEND
    );
}

// Fetch updated wallet
$result = mysqli_query(
    $conn,
    "SELECT id, userid, name, mobile, fullname, active, region, city, zone, wereda, wallettype
     FROM dube_wallet
     WHERE userid = '" . mysqli_real_escape_string($conn, $wallet['userid']) . "'"
);
$updatedWallet = mysqli_fetch_assoc($result);

mysqli_close($conn);

// Response
echo json_encode([
    "message" => "Wallet updated successfully",
    "wallet" => $updatedWallet
], JSON_PRETTY_PRINT);
?>
