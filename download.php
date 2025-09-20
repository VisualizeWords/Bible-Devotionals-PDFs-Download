 <?php
// --- SETTINGS ---
$pdfDir    = __DIR__ . "/pdfs/";        // folder with your PDFs
$userFile  = __DIR__ . "/users.json";   // stores user history
$tokenFile = __DIR__ . "/tokens.json";  // stores download tokens

// Allowed PDF filenames
$pdfFiles = [
    "faith.pdf",
    "peace.pdf",
    "grace.pdf",
    "Morning-Devotional-Faith_Strength.pdf",
    "Powerful-Psalms-And-Prayers-For-Healing-Peace-And-Protection.pdf"
];

// --- Helper functions ---
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}
function loadData($file) {
    return file_exists($file) ? json_decode(file_get_contents($file), true) : [];
}
function saveData($file, $data) {
    file_put_contents($file, json_encode($data));
}

// --- STEP 1: Generate download link ---
if (isset($_GET['user']) && isset($_GET['file'])) {
    $userId = preg_replace("/[^a-zA-Z0-9_-]/", "", $_GET['user']);
    $requestedFile = basename($_GET['file']); // only filename, no paths

    $users  = loadData($userFile);
    $tokens = loadData($tokenFile);

    // Validate file
    if (!in_array($requestedFile, $pdfFiles)) {
        die("❌ Invalid file requested.");
    }

    // Prevent same user downloading same file twice
    if (isset($users[$userId]) && in_array($requestedFile, $users[$userId])) {
        die("⏳ You have already downloaded this file.");
    }

    // Generate one-time token
    $token = generateToken();
    $tokens[$token] = [
        "user" => $userId,
        "file" => $requestedFile,
        "used" => false,
        "created_at" => time()
    ];
    saveData($tokenFile, $tokens);

    $link = "https://" . $_SERVER['HTTP_HOST'] . "/download.php?token=$token";
    echo "✅ Your secure download link: <a href='$link'>$link</a>";
    exit;
}

// --- STEP 2: Handle actual download ---
if (isset($_GET['token'])) {
    $tokens = loadData($tokenFile);
    $users  = loadData($userFile);

    $token = $_GET['token'];

    if (!isset($tokens[$token])) {
        die("❌ Invalid or expired link.");
    }
    if ($tokens[$token]["used"]) {
        die("⏳ This link has already been used.");
    }

    $userId = $tokens[$token]["user"];
    $file   = $tokens[$token]["file"];
    $filePath = $pdfDir . $file;

    if (!file_exists($filePath)) {
        die("❌ File not found.");
    }

    // Mark token as used
    $tokens[$token]["used"] = true;
    saveData($tokenFile, $tokens);

    // Record that user downloaded this file
    if (!isset($users[$userId])) {
        $users[$userId] = [];
    }
    $users[$userId][] = $file;
    saveData($userFile, $users);

    // Serve file securely (users can’t bypass this)
    header('Content-Description: File Transfer');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
}

echo "⚠️ No valid action. Use ?user=USERID&file=FILENAME.pdf to request a file.";