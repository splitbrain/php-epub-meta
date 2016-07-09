<?php
/**
 * PHP EPub Meta library
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Sébastien Lucas <sebastien@slucas.fr>
 */

namespace splitbrain\epubmeta;

class EPubDOMElement extends \DOMElement
{
    static public $namespaces = array(
        'n' => 'urn:oasis:names:tc:opendocument:xmlns:container',
        'opf' => 'http://www.idpf.org/2007/opf',
        'dc' => 'http://purl.org/dc/elements/1.1/',
        'ncx' => 'http://www.daisy.org/z3986/2005/ncx/'
    );

    public function __construct($name, $value = '', $namespaceURI = '')
    {
        list($ns, $name) = $this->splitns($name);
        $value = htmlspecialchars($value);
        if (!$namespaceURI && $ns) {
            $namespaceURI = self::$namespaces[$ns];
        }
        parent::__construct($name, $value, $namespaceURI);
    }

    /**
     * Create and append a new child
     *
     * Works with our epub namespaces and omits default namespaces
     * @param string $name
     * @param string $value
     * @return \DOMNode
     */
    public function newChild($name, $value = '')
    {
        $nsuri = '';
        list($ns, $local) = $this->splitns($name);
        if ($ns) {
            $nsuri = self::$namespaces[$ns];
            if ($this->isDefaultNamespace($nsuri)) {
                $name = $local;
                $nsuri = '';
            }
        }

        // this doesn't call the construcor: $node = $this->ownerDocument->createElement($name,$value);
        $node = new EPubDOMElement($name, $value, $nsuri);
        return $this->appendChild($node);
    }

    /**
     * Split given name in namespace prefix and local part
     *
     * @param  string $name
     * @return array  (namespace, name)
     */
    public function splitns($name)
    {
        $list = explode(':', $name, 2);
        if (count($list) < 2) {
            array_unshift($list, '');
        }
        return $list;
    }

    /**
     * Simple EPub namespace aware attribute accessor
     * @param string $attr Attribute to access
     * @param null $value Value to set. False to delete
     * @return string
     */
    public function attr($attr, $value = null)
    {
        list($ns, $attr) = $this->splitns($attr);

        $nsuri = '';
        if ($ns) {
            $nsuri = self::$namespaces[$ns];
            if (!$this->namespaceURI) {
                if ($this->isDefaultNamespace($nsuri)) {
                    $nsuri = '';
                }
            } elseif ($this->namespaceURI == $nsuri) {
                $nsuri = '';
            }
        }

        if (!is_null($value)) {
            if ($value === false) {
                // delete if false was given
                if ($nsuri) {
                    $this->removeAttributeNS($nsuri, $attr);
                } else {
                    $this->removeAttribute($attr);
                }
            } else {
                // modify if value was given
                if ($nsuri) {
                    $this->setAttributeNS($nsuri, $attr, $value);
                } else {
                    $this->setAttribute($attr, $value);
                }
            }
        } else {
            // return value if none was given
            if ($nsuri) {
                return $this->getAttributeNS($nsuri, $attr);
            } else {
                return $this->getAttribute($attr);
            }
        }
    }

    /**
     * Remove this node from the DOM
     */
    public function delete()
    {
        $this->parentNode->removeChild($this);
    }
}
