- 32-bit systems are not supported due to integer size limitations.

Example: 

```
TalkingHead::make('Hello World!', 'en')->speak(true);
```

or

```
$talking = new TalkingHead;
$talking->text('Hello World!')->language('en')->speak();
```

```
$talking->download(); // get download response
$talking->getMp3();   // get mp3 filename, also TalkingHead::mp3('Hello World', 'en');
$talking->flush();    // clear 'storage' folder, also TalkingHead::clean();
```

Configuration:

```
$talking->enc($encoding); // encoding, default: UTF-8
$talking->language($language); // language, default: en
$talking->path($path); // storage path
$talking->sleep($seconds); // pause in seconds before requesting next url
$talking->split($length); // split text by chunks, default: 100
$talking->text($text); // text
```

Helpers:

```
$talking->raw($raw); // additional url parameter
$talking->writeToMp3(); // just create mp3
$talking->getProcessedText();
$talking->getTk($text); // get 'tk' parameter for text
$talking->getTkk(); // get token
$talking->getUrls();  // list of urls for tts
$talking->getSpentTime(); // Symfony StopwatchEvent to measure spent time for downloading
```
