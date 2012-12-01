<?php
defined('BASE_PATH') or die();

function debug ($texts, $title = null, $custom = null)
{
    flush();
    ob_flush();

    if (!is_array($texts)) {
        $texts = array($texts);
    }

    foreach ($texts as $text) {
        if (is_string($text) && ($custom === null)) {
            $text = preg_replace('#^([^\n]+)#', "<strong>$> \\1</strong>\n", $text);
        }

        echo "\n";

        if ($title) {
            echo '<code><strong>['.$title.'] </strong></code>';
        }

        print_r($text);

        echo "\n";
    }
}

function encode2utf ($string)
{
    if ((mb_detect_encoding($string) == 'UTF-8') && mb_check_encoding($string, 'UTF-8')) {
        return $string;
    } else {
        return utf8_encode($string);
    }
}

function fixSearch ($text, $limit = 52)
{
    $text = htmlentities(trim(strip_tags($text)), ENT_NOQUOTES, 'UTF-8');
    $text = preg_replace('#&(\w)\w+;#', '$1', $text);
    $text = preg_replace('#\W#', ' ', $text);
    $text = trim(preg_replace('#\s+#', ' ', $text));

    if (strlen($text) <= $limit) {
        return $text;
    }

    $text = preg_replace('# (\w{1,2}([^\w]|$))+#', ' ', $text);
    $text = trim(preg_replace('#\s+#', ' ', $text));

    return substr($text, 0, strrpos(substr($text, 0, $limit), ' '));
}
