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
        $this->checkAmule();

        if ($settings) {
            $this->setSettings($settings);
        }
    }

    private function checkAmule ()
    {
        $daemon = trim(shell_exec('ps -ef | grep amule | grep -v grep | grep -v $0'));

        if (empty($daemon)) {
            die('amule app or amuled daemon are not started');
        }
    }

    private function checkAmulecmd ()
    {
        $this->amulecmd = trim(shell_exec('which amulecmd'));

        if (empty($this->amulecmd)) {
            die('amulecmd command does not exists');
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
        if ($this->debug !== true) {
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

    public function search ($query, $net = 'global', $wait = 5)
    {
        $search = fixSearch($query);

        if (empty($search)) {
            $this->debug($query, 'No search defined', true);

            return array();
        }

        $content = $this->amulecmd('search '.$net.' '.$search);

        $this->debug($content);

        $repeat = 0;

        while (true) {
            if ($repeat === 10) {
                break;
            }

            sleep($wait);

            $progress = $this->amulecmd('progress');
            $progress = intval(preg_replace('#[^0-9]#', '', $progress));

            if (($progress === 0) || ($progress === 100)) {
                break;
            }

            $this->debug($progress, 'Search progress', true);

            ++$repeat;
        }

        $content = $this->amulecmd('results');

        preg_match_all('#\n([0-9]+)\.\s(.*)\s{4,}([0-9]+\.[0-9]+)\s+([0-9]+)#', $content, $files);

        if (empty($files[0])) {
            $this->debug($content, 'No Results');

            return array();
        }

        $results = array();

        for ($i = 0, $max = count($files[0]); $i < $max; $i++) {
            $name = trim($files[2][$i]);

            if (in_array(substr($name, 0, 1), array('!', '-', '_'))) {
                continue;
            }

            $results[] = array(
                'id' => (string)$files[1][$i],
                'name' => $name,
                'size' => floatval($files[3][$i]),
                'sources' => intval($files[4][$i])
            );
        }

        usort($results, function ($a, $b) {
            return $a['sources'] > $b['sources'] ? -1 : 1;
        });

        $this->printTable($results);

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

    public function getDownloads ()
    {
        $content = explode("\n", $this->amulecmd('show DL'));

        array_shift($content);

        $max = count($content);
        $downloads = array();

        for ($k = 0, $i = 0; $i < $max; $k++, $i += 2) {
            $expression = 
                '^([0-9a-z]+)\s*'.
                '([^\[]+)'.
                '\[([0-9\.]+)%\]\s*'.
                '([0-9]+/\s*[0-9]+\s*[\+0-9]*\s*[\(\)0-9]*)\s*'.
                '\-\s*([a-z0-9\.]+)\s*'.
                '\-\s*([a-z0-9\.]+)\s*'.
                '\-\s*([a-z]+\s*\[[a-z]+\])\s*'.
                '(\-\s*(.*))?'
            ;

            preg_match('#'.$expression.'#i', trim($content[$i]).' '.trim($content[$i + 1]), $row);

            if (empty($row)) {
                continue;
            }

            array_shift($row);

            $downloads[] = array(
                'hash' => $row[0],
                'file' => $row[1],
                'percent' => $row[2],
                'sources' => preg_replace('#\s+#', ' ', trim($row[3])),
                'status' => $row[4],
                'tmp' => $row[5],
                'priority' => $row[6],
                'speed' => (isset($row[8]) ? $row[8] : 0)
            );
        }

        usort($downloads, function ($a, $b) {
            if ($a['status'] === $b['status']) {
                return $a['percent'] > $b['percent'] ? -1 : 1;
            } else {
                return ($a['status'] === 'Downloading') ? -1 : 1;
            }
        });

        return $downloads;
    }

    public function printTable ($data, $fields = array(), $force = null)
    {
        if (($this->debug !== true) && ($force !== true)) {
            return '';
        }

        if (empty($data)) {
            return '';
        }

        if (empty($fields)) {
            $fields = array_keys($data[0]);
        } else if (is_string($fields)) {
            $fields = array($fields);
        }

        $valid_fields = array_keys($data[0]);

        foreach ($fields as $key => $field) {
            if (!in_array($field, $valid_fields)) {
                unset($fields[$key]);
            }
        }

        if (empty($fields)) {
            return '';
        }

        echo '<table>';
        echo '<thead>';
        echo '<tr>';

        foreach ($fields as $field) {
            echo '<th>'.ucfirst($field).'</th>';
        }

        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($data as $row) {
            echo '<tr>';

            foreach ($fields as $field) {
                echo '<td>'.$row[$field].'</td>';
            }

            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
    }
}
