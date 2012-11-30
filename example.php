<?php
include (__DIR__.'/libs/Lito/Amule/loader.php');

echo '<pre>';

# URL with a top music list
$url = 'http://www.charly1300.com/euroairplay.htm';

# Print URL
$Api->debug($url, 'URL', true);

# Set aMule download folder
$incoming = '/home/user/.aMule/Incoming/';

# Create a HTML cache to avoid download for each request
$html = __DIR__.'/cache/'.md5($url).'.html';

# Check cache
if (!is_file($html) || (strtotime('5 days ago') > filemtime($html))) {
    file_put_contents($html, encode2utf(file_get_contents($url)));
}

# Disable XML errors
libxml_use_internal_errors(true);

# DOM parsers
$DOM = new DOMDocument();
$DOM->loadHTMLFile($html);

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
    $search = alphaNumeric($title);

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

    # Results are sorted by sources DESC (first result is the more shared)
    $top = $results[0];

    # If the file already was downloaded, continue
    if (is_file($incoming.$top['name'])) {
        $Api->debug($top['name'], 'File Already Exists', true);
        continue;
    }

    # Send the download request to amule daemon
    $Api->download($top['id']);
}

# Print status and current downloads
$Api->debug($Api->amulecmd(array('status', 'show DL')));
