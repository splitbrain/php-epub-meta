<?php

namespace splitbrain\epubmeta;

class EpubDOMDocument extends \DOMDocument
{

    /**  @var EPubDOMElement documentElement */
    public $documentElement;

    /**
     * Creates a new DOMDocument object
     * @link http://php.net/manual/domdocument.construct.php
     * @param $version [optional] The version number of the document as part of the XML declaration.
     * @param $encoding [optional] The encoding of the document as part of the XML declaration.
     */
    public function __construct($version = '', $encoding = '')
    {
        parent::__construct($version, $encoding);
        $this->registerNodeClass('DOMElement', '\\splitbrain\\epubmeta\\EPubDOMElement');
    }

}