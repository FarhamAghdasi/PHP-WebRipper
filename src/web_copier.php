<?php
// Recursive Function to Crawl Website and Download Files
function crawl($url, $outputDir, $context, $filterPatterns) {
    global $visitedUrls, $logFile;

    // Filter URLs based on user-defined patterns
    foreach ($filterPatterns as $pattern) {
        if (strpos($url, $pattern) !== false) {
            fwrite($logFile, "Blocked URL: $url \n");
            return;
        }
    }

    // Check if URL was already visited
    if (in_array($url, $visitedUrls)) {
        return;
    }
    $visitedUrls[] = $url;

    // Fetch HTML Content
    $html = file_get_contents($url, false, $context);
    if ($html === FALSE) {
        fwrite($logFile, "Error fetching URL: $url \n");
        return;
    }

    // Parse URL and Create Unique HTML File Path
    $urlParts = parse_url($url);
    $hostDir = $outputDir . '/' . $urlParts['host'];
    if (!file_exists($hostDir)) {
        mkdir($hostDir, 0777, true);
    }
    
    $fileName = !empty($urlParts['path']) ? $urlParts['path'] : '/index.html';
    $fileName = str_replace(['/', '\\'], '_', $fileName);
    if (!preg_match('/\.html?$/', $fileName)) {
        $fileName .= '.html';
    }
    
    $filePath = $hostDir . '/' . $fileName;
    file_put_contents($filePath, $html);
    fwrite($logFile, "Downloaded: $url to $filePath \n");

    // Use regex to find all links (a, script, link tags)
    preg_match_all('/(href|src)="([^"]+)"/i', $html, $matches);
    $links = $matches[2];

    // Recursively Crawl and Download Found Links
    foreach ($links as $link) {
        if (strpos($link, 'http') === false) {
            $link = $urlParts['scheme'] . '://' . $urlParts['host'] . '/' . ltrim($link, '/');
        }
        crawl($link, $outputDir, $context, $filterPatterns);
    }
}

// Save Directory Structure to .txt File
function saveDirectoryStructure($dir, $structureFile) {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $filePath = "$dir/$file";
            if (is_dir($filePath)) {
                file_put_contents($structureFile, "[DIR] $filePath \n", FILE_APPEND);
                saveDirectoryStructure($filePath, $structureFile);
            } else {
                file_put_contents($structureFile, "[FILE] $filePath \n", FILE_APPEND);
            }
        }
    }
}

// Proxy Setup (if provided)
function getProxyContext($proxy) {
    if ($proxy) {
        return stream_context_create([
            'http' => [
                'proxy' => $proxy,
                'request_fulluri' => true,
            ],
            'https' => [
                'proxy' => $proxy,
                'request_fulluri' => true,
            ],
        ]);
    }
    return stream_context_create();
}
?>
