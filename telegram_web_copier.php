<?php
// Include the web copier script and config
include 'src/web_copier.php';
include 'config/config.php';

// Telegram Bot Token and API URL
$botToken = '12345';  // Replace with your bot token
$apiUrl = "https://api.telegram.org/bot$botToken/";

// Webhook to receive user commands and URL input
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];

    // Check for '/start' command
    if (isset($message['text']) && $message['text'] == '/start') {
        sendMessage($chatId, "Welcome! Please send me the URL of the website you want to copy.");
    } elseif (isset($message['text'])) {
        // Handle URL input
        $websiteUrl = $message['text'];
        sendMessage($chatId, "Copying website from: $websiteUrl...");

        // Start the website copier
        $outputDir = "output_$chatId";  // Unique output directory for each user
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0777, true);
        }
        $logFile = fopen("$outputDir/log.txt", "a");

        // Start downloading the website
        crawl($websiteUrl, $outputDir, getProxyContext(null), $filters);

        // Save directory structure
        saveDirectoryStructure($outputDir, "$outputDir/structure.txt");
        fclose($logFile);

        // Zip the output directory
        $zipFile = "$outputDir/website_copy.zip";
        zipDirectory($outputDir, $zipFile);

        // Send the zip file and log back to the user
        sendFile($chatId, $zipFile, "Here is your zipped website.");
        sendFile($chatId, "$outputDir/log.txt", "Here is the log file.");
        sendMessage($chatId, "Website copy completed successfully!");
    }
}

// Send a message back to the user
function sendMessage($chatId, $message) {
    global $apiUrl;
    $url = $apiUrl . "sendMessage?chat_id=$chatId&text=" . urlencode($message);
    file_get_contents($url);
}

// Send a file back to the user
function sendFile($chatId, $filePath, $caption) {
    global $apiUrl;
    $post_fields = [
        'chat_id' => $chatId,
        'caption' => $caption,
        'document' => new CURLFile(realpath($filePath))
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type:multipart/form-data"]);
    curl_setopt($ch, CURLOPT_URL, $apiUrl . "sendDocument");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_exec($ch);
    curl_close($ch);
}

// Zip a directory
function zipDirectory($source, $destination) {
    $zip = new ZipArchive();
    if ($zip->open($destination, ZipArchive::CREATE) === TRUE) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source));
        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($source) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
        $zip->close();
    } else {
        echo "Failed to create ZIP file";
    }
}
?>
