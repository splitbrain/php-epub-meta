<?php
/**
 * PHP EPub Meta library
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */
class EPub {
    public $xml; //FIXME change to protected, later
    protected $file;
    protected $namespaces;

    /**
     * Constructor
     *
     * @param string $file path to epub file to work on
     * @throws Exception if metadata could not be loaded
     */
    public function __construct($file){
        $this->xml = simplexml_load_file('zip://'.$file.'#OEBPS/content.opf');
        if($this->xml === false){
            throw new Exception('Failed to access epub metadata');
        }
        $this->file = $file;

        $this->namespaces = $this->xml->getDocNamespaces(true);
        foreach($this->namespaces as $ns => $url){
            if($ns) $this->xml->registerXPathNamespace($ns,$url);
        }
    }

    /**
     * Get or set the book author(s)
     *
     */
    public function Authors($authors=false){
        // set new data
        if($authors){
            // A simple author name was given:
            if(is_string($authors)){
                $authors = array($authors => $authors);
            }

            if(is_array($authors)){
                // delete existing nodes
                $res = $this->xpath('//dc:creator[@opf:role="aut"]',false);
                foreach($res as $r) $this->deleteNode($r);

                // add new nodes
                foreach($authors as $as => $name){
                    if(is_int($as)) $as = $name; //numeric array given
                    $this->addMeta('dc:creator',$name,array(
                                        'opf:role'    => 'aut',
                                        'opf:file-as' => $as
                                  ));
                }
            }
        }

        // read current data
        $authors = array();
        $res = $this->xpath('//dc:creator[@opf:role="aut"]',false);
        foreach($res as $r){
            $name = (String) $r;
            $as   = (String) $this->readAttribute($r,'opf','file-as');
            if(!$as) $as = $name;
            $authors[$as] = $name;
        }
        return $authors;
    }


    /**
     * Remove a node form the XML
     *
     * @link http://www.kavoir.com/2008/12/how-to-delete-remove-nodes-in-simplexml.html#comment-2694
     */
    protected function deleteNode(SimpleXMLElement $node){
        $oNode = dom_import_simplexml($node);
        $oNode->parentNode->removeChild($oNode);
    }

    /**
     * Fetch Item(s) via xpath expression
     *
     * The xpath is applied on the metadata xml node, not at the root.
     * Namespaces are already correctly registered.
     *
     * @param string $xpath  the xpath expression
     * @param bool   $simple return a SimpleXMLElement instead of Array if only one hit
     */
    protected function xpath($xpath, $simple=true){
        $result = $this->xml->metadata->xpath($xpath);
        if($simple && is_array($result) && count($result) === 1){
            return $result[0];
        }else{
            return $result;
        }
    }

    /**
     * Add a new metadata node
     *
     * @param string $item        name of the new node (can be ns prefixed)
     * @param string $value       value of the new node
     * @param array  $attributes  name/value pairs of attributes (ns prefix okay)
     */
    protected function addMeta($item, $value, $attributes=array()){
        list($ns,$item) = explode(':',$item);
        if(!$item){
            $item = $ns;
            $ns   = '';
        }

        $node = $this->xml->metadata->addChild($item,$value,$this->namespaces[$ns]);
        foreach($attributes as $attr => $value){
            list($ns, $item) = explode(':', $attr);
            if(!$item) $ns   = '';
            $node->addAttribute($attr,$value,$this->namespaces[$ns]);
        }
    }

    /**
     * Fetch Item
     *
     * If no item name is given all items from the given namespace are returned
     *
     * @param string $ns   namespace prefix
     * @param string $item the name of the item to retrieve
     * @return SimpleXMLElement
     */
    protected function getMeta($ns, $item=''){
        $childs = $this->xml->metadata->children($this->namespaces[$ns]);
        if($item){
            return $childs->$item;
        }else{
            return $childs;
        }
    }

    /*
     * Read attributes from a given item
     *
     * If no attribute name is given all all attributes from the given namespace
     * are returned
     *
     * @param SimpleXMLElement $node
     * @param string $ns namespace prefix
     * @param string $attribute the name of the attribute to retrieve
     * @return SimpleXMLElement
     */
    protected function readAttribute(SimpleXMLElement $node, $ns, $attribute=''){
        $attrs = $node->attributes($this->namespaces[$ns]);
        if($attribute){
            return $attrs->$attribute;
        }else{
            return $attrs;
        }
    }

}

