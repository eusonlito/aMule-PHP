<?php
namespace Lito\Amule;

defined('BASE_PATH') or die();

class Amule {
    private $stream;

    private $amulecmd = '';
    private $cache = '';
    private $log = '';
    private $password = '';
    private $debug = true;

    public function __construct ($settings = array())
    {
        $this->checkAmulecmd();
        $this->checkAmuled();

        if ($settings) {
            $this->setSettings($settings);
        }
    }

    private function checkAmulecmd ()
    {
        $this->amulecmd = trim(shell_exec('which amulecmd'));

        if (empty($this->amulecmd)) {
            die('amulecmd command does not exists');
        }
    }

    private function checkAmuled ()
    {
        $daemon = trim(shell_exec('ps -ef | grep amuled | grep -v grep'));

        if (empty($daemon)) {
            die('amuled daemon is not started');
        }
    }

    public function setSettings ($settings)
    {
        if (empty($settings)) {
            return false;
        }

        foreach ($settings as $key => $value) {
            $key = 'set'.ucfirst($key);

            if (method_exists($this, $key)) {
                $this->$key($value);
            }
        }
    }

    public function setCache ($folder)
    {
        $this->cache = preg_replace('#/+#', '/', $folder.'/');

        if (!is_writable($folder)) {
            die($folder.' folder must be writable');
        }
        
        $this->log = $this->cache.'responses.log';

        if (is_file($this->log) && !is_writable($this->log)) {
            die($this->log.' file must be writable');
        }
    }

    public function setPassword ($password)
    {
        $this->password = $password;
    }

    private function setDebug ($debug)
    {
        $this->debug = $debug;
    }

    public function debug ($message, $title = null, $custom = null) {
        if (!$this->debug) {
            return;
        }

        debug($message, $title, $custom);
    }

    private function openStream ()
    {
        if (empty($this->log)) {
            die('Log file is not define. Please set cache folder using setCache method');
        }

        $this->stream = popen($this->amulecmd.' -P '.$this->password.' 2>&1 >'.$this->log, 'w');
    }

    private function writeStream ($text)
    {
        if (empty($this->stream)) {
            die('Stream was not open');
        }

        fwrite($this->stream, $text);
    }

    private function closeStream ()
    {
        if ($this->stream) {
            pclose($this->stream);
        }
    }

    private function getLog ()
    {
        if (empty($this->log)) {
            die('Log file is not define. Please set cache folder using setCache method');
        }

        $responses = explode('aMulecmd$', file_get_contents($this->log));

        array_shift($responses);
        array_pop($responses);

        foreach ($responses as &$response) {
            $response = trim(str_replace("\n > ", "\n", $response));
        }

        unset($response);

        return $responses;
    }

    public function amulecmd ($queries)
    {
        $string = is_string($queries);

        if ($string) {
            $queries = array($queries);
        }

        $this->openStream();

        foreach ($queries as $query) {
            $this->writeStream($query."\n");
        }

        $this->closeStream();

        $responses = $this->getLog();

        return $string ? $responses[0] : $responses;
    }

    public function search ($query, $net = 'global', $wait = 10)
    {
        $search = fixSearch($query);

        if (!$search) {
            $this->debug($query, 'No search defined', true);

            return array();
        }

        $content = $this->amulecmd('search '.$net.' '.$search);

        $this->debug($content);

        sleep($wait);

        $content = $this->amulecmd('results');

        preg_match_all('#\n([0-9]+)\.\s(.*)\s{4,}([0-9]+\.[0-9]+)\s+([0-9]+)#', $content, $files);

        if (empty($files[0])) {
            $this->debug($content, 'No Results');

            return array();
        }

        $this->debug($content);

        $results = array();

        for ($i = 0, $max = count($files[0]); $i < $max; $i++) {
            $results[] = array(
                'id' => (string)$files[1][$i],
                'name' => trim($files[2][$i]),
                'size' => floatval($files[3][$i]),
                'sources' => intval($files[4][$i])
            );
        }

        usort($results, function ($a, $b) {
            return $a['sources'] > $b['sources'] ? -1 : 1;
        });

        return $results;
    }

    public function download ($query)
    {
        if (strlen($query) === 0) {
            return false;
        }

        if (preg_match('#^[0-9]+$#', $query)) {
            $content = $this->amulecmd(array('results', 'download '.$query));

            $this->debug($content[1]);

            return true;
        }

        $results = $this->search($query);

        if (isset($results[0]) && array_key_exists('id', $results[0])) {
            return $this->download($results[0]['id']);
        }

        return false;
    }
}
