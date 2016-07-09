<?php

namespace splitbrain\epubmeta\test;

/**
 * Class EPub
 *
 * Gives access to protected methods for testing
 *
 * @package splitbrain\epubmeta\test
 */
class EPub extends \splitbrain\epubmeta\EPub
{

    public function readManifest()
    {
        return parent::readManifest();
    }

}