<?php
include (__DIR__.'/libs/Lito/Amule/loader.php');

echo '<pre>';

$Api->debug($Api->amulecmd(array('status', 'show DL')));
