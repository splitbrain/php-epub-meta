<?php

namespace splitbrain\epubmeta;

class EPubDOMNodeList extends \DOMNodeList {
    /**
     * @link http://php.net/manual/en/domnodelist.item.php
     * @param int $index
     * @return EPubDOMElement
     */
    public function item($index) {
        return parent::item($index);
    }

}