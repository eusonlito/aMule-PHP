<?php
include (__DIR__.'/libs/Lito/Amule/loader.php');
include (__DIR__.'/template-head.php');

echo '<meta http-equiv="refresh" content="10" />';
echo '<pre>';

$Api->debug($Api->amulecmd('status'));

$Api->printTable($Api->getDownloads(), array('file', 'percent', 'speed', 'status', 'sources'));

include (__DIR__.'/template-footer.php');
