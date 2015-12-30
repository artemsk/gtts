<?php

namespace Artemsk\GTTS;

use Symfony\Component\HttpFoundation\Response as Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

        if($send === true) {
            return $response->send();
        }

        return $response;
    }

    public function download($send = true)
    {
        $this->isAllowedResponse();

        $response = new BinaryFileResponse($this->combined_filename, 200, ['content-type' => 'audio/mpeg'], true, 'attachment');

        if($send === true) {
            return $response->send();
        }

        return $response;
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