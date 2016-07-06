<?php
/**
 * PHP EPub Meta library
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Sébastien Lucas <sebastien@slucas.fr>
 */

class EPubDOMXPath extends DOMXPath
{
    public function __construct(DOMDocument $doc)
    {
        parent::__construct($doc);

        if (is_a($doc->documentElement, 'EPubDOMElement'))
        {
            foreach ($doc->documentElement->namespaces as $ns => $url) {
                $this->registerNamespace($ns, $url);
            }
        }
    }

    /**
     * Evaluates the given XPath expression
     * @link http://php.net/manual/en/domxpath.query.php
     * @param string $expression
     * @param DOMNode $contextnode
     * @return EpubDOMNodeList
     */
    public function query($expression, $contextnode = null) {
        return parent::query($expression, $contextnode);
    }

}
