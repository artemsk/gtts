<?php

namespace Artemsk\GTTS;

use Symfony\Component\Stopwatch\Stopwatch;

class Handler {

    protected $text;
    protected $processed_text;
    protected $urls = [];
    protected $combined_filename;

    protected $max = 100;
    protected $lang = 'en';
    protected $enc = 'UTF-8';
    protected $raw = 'client=t';
    protected $tk;
    protected $tkk;

    protected $flag_split_done = false;
    protected $spent_time;

    protected $url = 'http://translate.google.com/translate_tts';
    protected $sleep = 5;
    protected $storage_path;

    public function __construct()
    {
        $this->storage_path = realpath(__DIR__ . '/../storage');
    }

    public function enc($enc)
    {
        $this->removeValues();
        $this->enc = $enc;
        return $this;
    }

    public function flush()
    {
        $glob = glob($this->storage_path . '/gtts_*');

        $files = ($glob === false) ? [] : array_filter($glob, function ($file) {
            return filetype($file) == 'file';
        });

        foreach($files as $file) {
            unlink($file);
        }

        return $this;
    }

    public function getMp3()
    {
        if(empty($this->combined_filename)) {
            $this->writeToMp3();
        }

        return $this->combined_filename;
    }

    public function getProcessedText()
    {
        if(!$this->flag_split_done) {
            $this->splitAction();
        }

        return $this->processed_text;
    }

    public function getTk($text)
    {
        return $this->_generateTk($text);
    }

    public function getTkk()
    {
        return $this->tkk;
    }

    public function getSpentTime()
    {
        return $this->spent_time;
    }

    public function getUrls()
    {
        if(!$this->flag_split_done) {
            $this->splitAction();
        }

        $this->isAllowedToProcess();

        $baseUrl = $this->makeBaseUrl();
        foreach($this->processed_text as $part) {
            $this->urls[] = $baseUrl . '&q=' . urlencode($part) . '&tk=' . $this->getTk($part);
        }

        return $this->urls;
    }

    protected function isAllowedToProcess()
    {
        if(empty($this->lang) || empty($this->enc) ||
            empty($this->processed_text)) {

            throw new TalkingHeadException('Not enough data.');
        }
    }

    protected function isAllowedResponse()
    {
        if(empty($this->combined_filename)) {
            $this->writeToMp3();
        }

        if(empty($this->combined_filename)) {
            throw new TalkingHeadException('File does not exist.');
        }
    }

    public function language($lang)
    {
        $this->removeValues();
        $this->lang = $lang;
        return $this;
    }

    protected function makeBaseUrl()
    {
        $url = $this->url . '?ie=' . $this->enc . '&tl=' . $this->lang;

        if(!empty($this->raw)) {
            $url .= '&' . $this->raw;
        }

        return $url;
    }

    public function path($path)
    {
        $this->storage_path = $path;
        return $this;
    }

    public function raw($raw)
    {
        $this->removeValues();
        $this->raw = $raw;
        return $this;
    }

    protected function removeValues()
    {
        $this->urls = [];
        $this->combined_filename = null;
        $this->flag_split_done = false;
        $this->spent_time = null;
    }

    public function sleep($sleep)
    {
        $this->sleep = $sleep;
        return $this;
    }

    public function split($max = 100)
    {
        $this->max = $max;

        $this->splitAction();
        return $this;
    }

    protected function splitAction()
    {
        if(empty($this->text)) {
            throw new TalkingHeadException('Text string is empty.');
        }

        $parts = preg_split("/[,;:]/", $this->text); // .
        $processedParts = [];

        while(list($key, $part) = each($parts)) {

            if(strlen($part) > $this->max) {
                $cutAt = strrpos(substr($part, 0, $this->max), ' ');
                $cut = $cutAt === false ? $part : substr($part, 0, $cutAt);
                $leftovers = substr($part, $cutAt);

                if(strlen($leftovers) > 0) {
                    $parts[$key + 1] = $leftovers . (isset($parts[$key + 1]) ? $parts[$key + 1] : null);
                }

            } else {
                $cut = $part;
            }

            if(!empty(trim($cut))) {
                $processedParts[] = trim($cut);
            }
        }

        $this->processed_text = $processedParts;
        $this->flag_split_done = true;
    }

    public function text($text)
    {
        $this->removeValues();
        $this->text = $text;
        return $this;
    }

    public function writeToMp3()
    {
        $combined_data = $this->_mp3Data();

        // merge
        if(!empty($combined_data)) {
            $this->combined_filename = $this->storage_path . '/gtts_combined_' . date('YmdHis') . '.mp3';
            file_put_contents($this->combined_filename, $combined_data);
        }
        return $this;
    }

    protected function _mp3Data()
    {
        if(empty($this->urls)) {
            $this->getUrls();
        }

        $combined_data = [];
        $stopwatch = new Stopwatch();
        $stopwatch->start('download');
        foreach($this->urls as $url) {

            set_time_limit(90);
            list($filename, $data) = $this->_download($url);
            if(!$filename) {
                continue;
            }

            if(!empty($data)) {
                sleep($this->sleep);
            } else {
                $data = file_get_contents($filename);
            }

            $combined_data[] = $data;
            $stopwatch->lap('download');
        }

        $this->spent_time = $stopwatch->stop('download');
        return $combined_data;
    }

    protected function _download($url)
    {
        $filename = $this->storage_path . '/gtts_' . md5($url) . '.mp3';
        if(file_exists($filename)) {
            return [$filename, null];
        }

        $client = new \GuzzleHttp\Client();
        $res = $client->request('GET', $url);

        if($res->getStatusCode() != '200' || $res->getHeaderLine('content-type') != 'audio/mpeg') {
            return [false, null];
        }

        file_put_contents($filename, $res->getBody());
        return [$filename, $res->getBody()];
    }

    /**
     *  This function is copied from Google Translate Javascript. It generates 'tk' hashed value for url.
     *  The logic of it can be changed by Google anytime.
     */

    protected function _generateTk($text)
    {
        $a = htmlentities($text);
        $a = preg_split('//u', $a, -1, PREG_SPLIT_NO_EMPTY);

        for ($d = [], $e = 0, $f = 0; $f < count($a); $f++) {
            $g = $this->_charAtCode($a[$f]);
            if (128 > $g) {
              $d[$e++] = $g;
            } else {
              if (2048 > $g) {
                $d[$e++] = $g >> 6 | 192;
              } else {
                if ( 55296 == ($g & 64512) && ($f + 1) < count($a) && 56320 == ($this->_charAtCode($a[$f + 1]) & 64512) ) {
                  $g = 65536 + (($g & 1023) << 10) + ($this->_charAtCode($a[++$f]) & 1023);
                  $d[$e++] = $g >> 18 | 240;
                  $d[$e++] = $g >> 12 & 63 | 128;
                } else {
                  $d[$e++] = $g >> 12 | 224;
                }

                $d[$e++] = $g >> 6 & 63 | 128;
              }

              $d[$e++] = $g & 63 | 128;
            }
        }

        $zerofill = function($int, $shft) {
            return $int >= 0 ? ($int >> $shft) : (($int + 0x100000000) >> $shft);
            //return ($int >> $shft) & (PHP_INT_MAX >> ($shft - 1));
        };

        $RL = function($a, $b) use($zerofill) {
            for ($c = 0; $c < strlen($b) - 2; $c += 3) {
                $d = $b{$c + 2};
                $d = $d >= "a" ? $this->_charAtCode($d[0]) - 87 : $d;
                $d = $b{$c + 1} == "+" ? $zerofill($a, $d) : $a << $d;
                $a = $b{$c} == "+" ? $a + $d & 4294967295 : $a ^ $d;
            }
            return $a;
        };

        list($first_seed, $second_seed) = array_pad(explode('.', $this->_getTokenKey()), 2, null);

        $Vb = "+-a^+6";
        $Ub = "+-3^+b+-f";

        $a = (int)$first_seed;
        for ($e = 0; $e < count($d); $e++) {
            $a += $d[$e];
            $a = $RL($a, $Vb);
        }
        
        $a = $RL($a, $Ub);
        $a ^= (int)$second_seed;
        0 > $a && ($a = ($a & 2147483647) + 2147483648);
        $a = fmod($a, 1E6);

        return (string)$a . '.' . (string)($a ^ (int)$first_seed);
    }

    protected function _charAtCode($utf8Character)
    {
        $utf16Char = mb_convert_encoding($utf8Character, 'UTF-16', $this->enc);
        $ord = hexdec(bin2hex($utf16Char));
        //list(, $ord) = unpack('N', mb_convert_encoding($utf8Character, 'UCS-4BE', 'UTF-8'));
        return $ord;
    }

    protected function _getTokenKey()
    {
        if(!empty($this->tkk)) {
            return $this->tkk;
        }
        
        $hours = (int)(time() / 3600);

        $client = new \GuzzleHttp\Client;
        $res = $client->get('https://translate.google.com/', ['verify' => false]);

        if($res->getStatusCode() != '200') {
            throw new TalkingHeadException('Access denied.');
        }

        $matches = $tkk_expr = null;
        preg_match_all("/.*?(TKK=.*?;)W.*?/", (string)$res->getBody(), $matches);

        if(isset($matches[1][0])) {

            $tkk_expr = $matches[1][0];
            $a = $b = null;
            preg_match("/a\\\\x3d(-?\d+);/", $tkk_expr, $a);
            preg_match("/b\\\\x3d(-?\d+);/", $tkk_expr, $b);

            if(isset($a[1]) && isset($b[1])) {
                $this->tkk = (string)$hours . '.' . (string)($a[1] + $b[1]);
                return $this->tkk;
            }
        }
        
        throw new TalkingHeadException('TKK not found.');
    }
}
