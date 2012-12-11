<?php
include (__DIR__.'/libs/Lito/Amule/loader.php');
include (__DIR__.'/template-head.php');

echo '<pre>';

# URL with a top music list
$url = 'http://www.charly1300.com/euroairplay.htm';

# Print URL
$Api->debug($url, 'URL', true);

# Set aMule download folder
$incoming = '/home/user/.aMule/Incoming/';

# Create a HTML cache to avoid download for each request
$cache_html = $settings['cache'].md5($url).'.html';

# Check cache
if (!is_file($cache_html) || (strtotime('5 days ago') > filemtime($cache_html))) {
    file_put_contents($cache_html, encode2utf(file_get_contents($url)));
}

# Check previously downloaded files
$cache_downloads = $settings['cache'].'downloads.txt';

if (is_file($cache_downloads)) {
    $downloads = file($cache_downloads, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
} else {
    $downloads = array();
}

# Disable XML errors
libxml_use_internal_errors(true);

# DOM parsers
$DOM = new DOMDocument();
$DOM->loadHTMLFile($cache_html);

$XPath = new DOMXPath($DOM);

# Get all sons rows
$Divs = $XPath->query('//div[@class="line"]');

if ($Divs->length === 0) {
    die('No list found');
}

# For each song
foreach ($Divs as $Div) {
    # Get song name and artist from ALT attr in IMG tag
    $title = $Div->getElementsByTagName('img')->item(0)->getAttribute('alt');

    # Convert song title in a simple string
    $search = fixSearch($title);

    if (empty($search)) {
        $Api->debug(array($title), 'Empty Title', true);
        continue;
    }

    # Execute the song search, check libs/Lito/Amule/Amule.php for more information
    $results = $Api->search('mp3 '.$search);

    if (empty($results)) {
        $Api->debug(array($title), 'No results', true);
        continue;
    }

    # Results are sorted by sources DESC (first result is the most shared)
    $top = $results[0];

    # If the file was downloaded previosly, continue
    if (in_array($top['name'], $downloads)) {
        $Api->debug($top['name'], 'File Previously Downloaded', true);
        continue;
    }

    # If the file already was downloaded, continue
    if (is_file($incoming.$top['name'])) {
        $Api->debug($top['name'], 'File Already Exists', true);
        continue;
    }

    # Send the download request to amule daemon
    $Api->download($top['id']);
}

# Save downloads cache
file_put_contents($cache_downloads, implode("\n", $downloads));

# Print connection status
$Api->debug($Api->amulecmd('status'));

# Print current downloads
$Api->printTable($Api->getDownloads(), array('file', 'percent', 'speed', 'status', 'sources'));

include (__DIR__.'/template-footer.php');
