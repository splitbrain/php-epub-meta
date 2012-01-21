<?php
/**
 * PHP EPub Meta library
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */
class EPub {
    public $xml; //FIXME change to protected, later
    protected $file;
    protected $meta;
    protected $namespaces;
    protected $imagetoadd='';

    /**
     * Constructor
     *
     * @param string $file path to epub file to work on
     * @throws Exception if metadata could not be loaded
     */
    public function __construct($file){
        // prepare namespaces
        $this->ns = array(
            'n'   => 'urn:oasis:names:tc:opendocument:xmlns:container',
            'opf' => 'http://www.idpf.org/2007/opf',
            'dc'  => 'http://purl.org/dc/elements/1.1/'
        );

        // open file
        $this->file = $file;
        $zip = new ZipArchive();
        if(!@$zip->open($this->file)){
            throw new Exception('Failed to read epub file');
        }

        // read container data
        $container = $zip->getFromName('META-INF/container.xml');
        if($container == false){
            throw new Exception('Failed to access epub container data');
        }
        $xml = new DOMDocument();
        $xml->loadXML($container);

        $container->registerXPathNamespace('n','urn:oasis:names:tc:opendocument:xmlns:container');
        $nodes = $container->xpath('//n:rootfiles/n:rootfile[@media-type="application/oebps-package+xml"]');
        $this->meta = (String) $nodes[0]['full-path'];

        // load metadata
        $xml = $zip->getFromName($this->meta);
        if(!$xml){
            throw new Exception('Failed to access epub metadata');
        }
        $this->xml = new SimpleXMLElement($xml);

        $this->namespaces = array_merge($ns,$this->xml->getDocNamespaces(true));


        foreach($this->namespaces as $ns => $url){
            $this->xml->registerXPathNamespace($ns,$url);
        }

        $zip->close();
    }

    /**
     * Writes back all meta data changes
     */
    public function save(){
        $zip = new ZipArchive();
        $res = @$zip->open($this->file, ZipArchive::CREATE);
        if($res === false){
            throw new Exception('Failed to write back metadata');
        }
        $zip->addFromString($this->meta,$this->xml->asXML());
        // add the cover image
        if($this->imagetoadd){
            $path = dirname('/'.$this->meta).'/php-epub-meta.img'; // image path is relative to meta file
            $path = ltrim($path,'/');

            $zip->addFromString($path,file_get_contents($this->imagetoadd));
            $this->imagetoadd='';
        }
        $zip->close();
    }

    /**
     * Get or set the book author(s)
     *
     * Authors should be given with a "file-as" and a real name. The file as
     * is used for sorting in e-readers.
     *
     * Example:
     *
     * array(
     *      'Pratchett, Terry'   => 'Terry Pratchett',
     *      'Simpson, Jacqeline' => 'Jacqueline Simpson',
     * )
     *
     * @params array $authors
     */
    public function Authors($authors=false){
        // set new data
        if($authors){
            // Author where given as a comma separated list
            if(is_string($authors)){
                $authors = explode(',',$authors);
                $authors = array_map('trim',$authors);
            }

            if(is_array($authors)){
                // delete existing nodes
                $res = $this->xpath('//opf:metadata/dc:creator[@opf:role="aut"]',false);
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
        $rolefix = false;
        $authors = array();
        $res = $this->xpath('//opf:metadata/dc:creator[@opf:role="aut"]',false);
        if(count($res) == 0){
            // no nodes where found, let's try again without role
            $res = $this->xpath('//opf:metadata/dc:creator',false);
            $rolefix = true;
        }
        foreach($res as $r){
            $name = (String) $r;
            $as   = (String) $this->readAttribute($r,'opf','file-as');
            if(!$as){
                $as = $name;
                $r->addAttribute('opf:file-as',$as,$this->namespaces['opf']);
            }
            if($rolefix){
                $r->addAttribute('opf:role','aut',$this->namespaces['opf']);
            }
            $authors[$as] = $name;
        }
        return $authors;
    }

    /**
     * Set or get the book title
     *
     * @param string $title
     */
    public function Title($title=false){
        return $this->getset('dc:title',$title);
    }

    /**
     * Set or get the book's language
     *
     * @param string $lang
     */
    public function Language($lang=false){
        return $this->getset('dc:language',$lang);
    }

    /**
     * Set or get the book' publisher info
     *
     * @param string $publisher
     */
    public function Publisher($publisher=false){
        return $this->getset('dc:publisher',$publisher);
    }

    /**
     * Set or get the book's copyright info
     *
     * @param string $rights
     */
    public function Copyright($rights=false){
        return $this->getset('dc:rights',$rights);
    }

    /**
     * Set or get the book's description
     *
     * @param string $description
     */
    public function Description($description=false){
        return $this->getset('dc:description',$description);
    }

    /**
     * Set or get the book's ISBN number
     *
     * @param string $isbn
     */
    public function ISBN($isbn=false){
        return $this->getset('dc:identifier',$isbn,'opf:scheme','ISBN');
    }

    /**
     * Set or get the Google Books ID
     *
     * @param string $google
     */
    public function Google($google=false){
        return $this->getset('dc:identifier',$google,'opf:scheme','GOOGLE');
    }

    /**
     * Set or get the Amazon ID of the book
     *
     * @param string $amazon
     */
    public function Amazon($amazon=false){
        return $this->getset('dc:identifier',$amazon,'opf:scheme','AMAZON');
    }

    /**
     * Set or get the book's subjects (aka. tags)
     *
     * @param array $subjects
     */
    public function Subjects($subjects=false){
        // setter
        if($subjects){
            if(is_string($subjects)){
                $subjects = explode(',',$subjects);
                $subjects = array_map('trim',$subjects);
            }

            $nodes = $this->xpath('//opf:metadata/dc:subject');
            foreach($nodes as $node){
                $this->deleteNode($node);
            }
            foreach($subjects as $subj){
                $this->addMeta('dc:subject',$subj);
            }
        }

        //getter
        $subjects = array();
        $nodes = $this->xpath('//opf:metadata/dc:subject',false);
        foreach($nodes as $node){
            $subjects[] = (String) $node;
        }
        return $subjects;
    }

    /**
     * Read the cover data
     *
     * Returns an associative array with the following keys:
     *
     *   mime  - filetype (usually image/jpeg)
     *   data  - the binary image data
     *   found - the internal path, or false if no image is set in epub
     *
     * When no image is set in the epub file, the binary data for a transparent
     * GIF pixel is returned.
     *
     * @return array
     */
    public function Cover($path=false, $mime=false){
        /*
        // set cover
        if($path !== false){
            // remove current pointer
            $nodes = $this->xpath('//opf:metadata/opf:meta[@name="cover"]',false);
            foreach($nodes as $node) $this->deleteNode($node);
            // remove previous manifest entries if they where made by us
            $nodes = $this->xpath('//opf:manifest/opf:item[@id="php-epub-meta-cover"]',false);
            foreach($nodes as $node) $this->deleteNode($node);

            if($path){
                // add pointer
                $this->addMeta('opf:meta','',array(
                                    'opf:name'    => 'cover',
                                    'opf:content' => 'php-epub-meta-cover'
                              ));

                // add manifest
                $parent = $this->xpath('//opf:manifest');
                $node = $parent->addChild('opf:item','',$this->namespaces['opf']);
                $node->addAttribute('opf:id', 'php-epub-meta-cover', $this->namespaces['opf']);
                $node->addAttribute('opf:href', 'php-epub-meta-cover.img', $this->namespaces['opf']);
                $node->addAttribute('opf:media-type', $mime, $this->namespaces['opf']);
                // remember path for save action
                $this->imagetoadd = $path;
            }
        }
        */

        // load cover
        $node = $this->xpath('//opf:metadata/opf:meta[@name="cover"]');
        if(!$node) return $this->no_cover();
        $coverid = (String) $node['content'];
        if(!$coverid) return $this->no_cover();

        $node = $this->xpath('//opf:manifest/opf:item[@id="'.$coverid.'"]');
        $mime = (String) $node['media-type'];
        $path = (String) $node['href'];
        $path = dirname('/'.$this->meta).'/'.$path; // image path is relative to meta file
        $path = ltrim($path,'/');

        $zip = new ZipArchive();
        if(!@$zip->open($this->file)){
            throw new Exception('Failed to read epub file');
        }
        $data = $zip->getFromName($path);

        return array(
            'mime'  => $mime,
            'data'  => $data,
            'found' => $path
        );
    }

    /**
     * A simple getter/setter for simple meta attributes
     *
     * It should only be used for attributes that are expected to be unique
     *
     * @param string $item   XML node to set/get
     * @param string $value  New node value
     * @param string $att    Attribute name
     * @param string $aval   Attribute value
     */
    protected function getset($item,$value=false,$att=false,$aval=false){
        // construct xpath
        $xpath = '//opf:metadata/'.$item;
        if($att){
            $xpath .= "[@$att=\"$aval\"]";
        }

        // set value
        if($value !== false){
            $node = $this->xpath($xpath);

            if(is_array($node)){
                // there are multiple matching nodes for some reason
                // we'll replace them all with our own single one
                // this will also be run if xcode returns an empty array
                foreach($node as $n) $this->deleteNode($n);
                if($att){
                    $this->addMeta($item,$value,array($att=>$aval));
                }else{
                    $this->addMeta($item,$value);
                }
            }elseif($value === ''){
                // the user want's to empty this value -> delete the node
                $this->deleteNode($node);
            }else{
                // replace value
                $node->{0} = $value;
            }
        }

        // get value
        $node = $this->xpath($xpath);
        if($node){
            return (String) $node;
        }else{
            return '';
        }
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
     * Namespaces are already correctly registered.
     *
     * @param string $xpath  the xpath expression
     * @param bool   $simple return a SimpleXMLElement instead of Array if only one hit
     */
    protected function xpath($xpath, $simple=true){
        $result = $this->xml->xpath($xpath);
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
        $list = explode(':',$item);
        $ns   = (count($list) ==2 ) ? $list[0] : '';

        $parent = $this->xpath('//opf:metadata');
        $node = $parent->addChild($item,$value,$this->namespaces[$ns]);
        foreach($attributes as $attr => $value){
            $list = explode(':', $attr);
            $ns   = (count($list) ==2 ) ? $list[0] : '';
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
        $parent = $this->xpath('//opf:metadata');
        $childs = $parent->children($this->namespaces[$ns]);
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

    /**
     * Return a not found response for Cover()
     */
    protected function no_cover(){
        return array(
            'data'  => base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAEALAAAAAABAAEAAAIBTAA7'),
            'mime'  => 'image/gif',
            'found' => false
        );
    }
}

