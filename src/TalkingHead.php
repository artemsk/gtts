<?php

namespace Artemsk\GTTS;

use Symfony\Component\HttpFoundation\Response as Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 *  Ex.: TalkingHead::make('Hello World!', 'en')->speak(true);
 *
 *  or
 *
 *  $talking = new TalkingHead;
 *  $talking->text('Hello World!')->language('en')->speak();
 *
 *  $talking->download();          // get download response
 *  $talking->getMp3();            // get mp3 filename, also TalkingHead::mp3('Hello World', 'en');
 *  $talking->flush();             // clear 'storage' folder, also TalkingHead::clean();
 *
 *  configuration:
 *
 *  $talking->enc($encoding);      // encoding, default: UTF-8
 *  $talking->language($language); // language, default: en
 *  $talking->path($path);         // storage path
 *  $talking->sleep($seconds);     // pause in seconds before requesting next url.
 *  $talking->split($length);      // split text by chunks, default: 100
 *  $talking->text($text);         // text
 *
 *  helpers:
 * 
 *  $talking->raw($raw);           // additional url parameter
 *  $talking->writeToMp3();        // just create mp3
 *  $talking->getProcessedText();
 *  $talking->getTk($text);        // get 'tk' parameter for text
 *  $talking->getUrls();           // list of urls for tts
 *  $talking->getSpentTime();      // Symfony StopwatchEvent to measure spent time for downloading
 */

class TalkingHead extends Handler {

    public static function make($text = null, $language = null)
    {
        $handler = new self;

        if(!empty($text)) {
            $handler->text($text);
        }

        if(!empty($language)) {
            $handler->language($language);
        }
        
        return $handler;
    }

    public function speak($send = false)
    {
        $this->isAllowedResponse();

        $response = Response::create(
            file_get_contents($this->combined_filename),
            Response::HTTP_OK,
            ['content-type' => 'audio/mpeg']
        );

        return $send === true ? $response->send() : $response;
    }

    public function download($send = true)
    {
        $this->isAllowedResponse();

        $response = new BinaryFileResponse(
                $this->combined_filename,
                Response::HTTP_OK,
                ['content-type' => 'audio/mpeg'], true, 'attachment'
        );

        return $send === true ? $response->send() : $response;
    }

    public static function mp3($text = null, $language = null)
    {
        $handler = self::make($text, $language);

        return $handler->getMp3();
    }

    public static function clean()
    {
        return (new static)->flush();
    }
}
