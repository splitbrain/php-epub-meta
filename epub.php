<?php
/**
 * PHP EPub Meta library
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */
class EPub {
    public $xml; //FIXME change to protected, later
    protected $xpath;
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
        // open file
        $this->file = $file;
        $zip = new ZipArchive();
        if(!@$zip->open($this->file)){
            throw new Exception('Failed to read epub file');
        }

        // read container data
        $data = $zip->getFromName('META-INF/container.xml');
        if($data == false){
            throw new Exception('Failed to access epub container data');
        }
        $xml = new DOMDocument();
        $xml->registerNodeClass('DOMElement','EPubDOMElement');
        $xml->loadXML($data);
        $xpath = new EPubDOMXPath($xml);
        $nodes = $xpath->query('//n:rootfiles/n:rootfile[@media-type="application/oebps-package+xml"]');
        $this->meta = $nodes->item(0)->attr('full-path');

        // load metadata
        $data = $zip->getFromName($this->meta);
        if(!$data){
            throw new Exception('Failed to access epub metadata');
        }
        $this->xml =  new DOMDocument();
        $this->xml->registerNodeClass('DOMElement','EPubDOMElement');
        $this->xml->loadXML($data);
        $this->xpath = new EPubDOMXPath($this->xml);

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
        if($authors !== false){
            // Author where given as a comma separated list
            if(is_string($authors)){
                if($authors == ''){
                    $authors = array();
                }else{
                    $authors = explode(',',$authors);
                    $authors = array_map('trim',$authors);
                }
            }

            // delete existing nodes
            $nodes = $this->xpath->query('//opf:metadata/dc:creator[@opf:role="aut"]');
            foreach($nodes as $node) $node->delete();

            // add new nodes
            $parent = $this->xpath->query('//opf:metadata')->item(0);
            foreach($authors as $as => $name){
                if(is_int($as)) $as = $name; //numeric array given

                $node = new EPubDomElement('dc:creator',$name);
                $node = $parent->appendChild($node);
                $node->attr('opf:role', 'aut');
                $node->attr('opf:file-as', $as);
            }
        }

        // read current data
        $rolefix = false;
        $authors = array();
        $nodes = $this->xpath->query('//opf:metadata/dc:creator[@opf:role="aut"]');
        if($nodes->length == 0){
            // no nodes where found, let's try again without role
            $nodes = $this->xpath('//opf:metadata/dc:creator');
            $rolefix = true;
        }
        foreach($nodes as $node){
            $name = $node->nodeValue;
            $as   = $node->attr('opf:file-as');
            if(!$as){
                $as = $name;
                $node->attr('opf:file-as',$as);
            }
            if($rolefix){
                $node->attr('opf:role','aut');
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
     * Subject should be given as array, but a comma separated string will also
     * be accepted.
     *
     * @param array $subjects
     */
    public function Subjects($subjects=false){
        // setter
        if($subjects !== false){
            if(is_string($subjects)){
                if($subjects === ''){
                    $subjects = array();
                }else{
                    $subjects = explode(',',$subjects);
                    $subjects = array_map('trim',$subjects);
                }
            }

            // delete previous
            $nodes = $this->xpath->query('//opf:metadata/dc:subject');
            foreach($nodes as $node){
                $node->delete();
            }
            // add new ones
            $parent = $this->xpath->query('//opf:metadata')->item(0);
            foreach($subjects as $subj){
                $node = new EPubDomElement('dc:subject',$subj);
                $node = $parent->appendChild($node);
            }
        }

        //getter
        $subjects = array();
        $nodes = $this->xpath->query('//opf:metadata/dc:subject');
        foreach($nodes as $node){
            $subjects[] =  $node->nodeValue;
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
            $nodes = $this->xpath->query($xpath);
            if($nodes->length == 1 ){
                if($value === ''){
                    // the user want's to empty this value -> delete the node
                    $nodes->item(0)->delete();
                }else{
                    // replace value
                    $nodes->item(0)->nodeValue = $value;
                }
            }else{
                // if there are multiple matching nodes for some reason delete
                // them. we'll replace them all with our own single one
                foreach($nodes as $n) $n->delete();
                // readd them
                if($value){
                    $parent = $this->xpath->query('//opf:metadata')->item(0);
                    $node   = new EPubDomElement($item,$value);
                    $node   = $parent->appendChild($node);
                    if($att) $node->attr($att,$aval);
                }
            }
        }

        // get value
        $nodes = $this->xpath->query($xpath);
        if($nodes->length){
            return $nodes->item(0)->nodeValue;
        }else{
            return '';
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

class EPubDOMXPath extends DOMXPath {
    public function __construct(DOMDocument $doc){
        parent::__construct($doc);

        if(is_a($doc->documentElement, 'EPubDOMElement')){
            foreach($doc->documentElement->namespaces as $ns => $url){
                $this->registerNamespace($ns,$url);
            }
        }
    }
}

class EPubDOMElement extends DOMElement {
    public $namespaces = array(
        ''    => '',
        'n'   => 'urn:oasis:names:tc:opendocument:xmlns:container',
        'opf' => 'http://www.idpf.org/2007/opf',
        'dc'  => 'http://purl.org/dc/elements/1.1/'
    );


    public function __construct($name, $value='', $namespaceURI=''){
        list($ns,$name) = $this->splitns($name);
        if(!$namespaceURI && $ns){
            $namespaceURI = $this->namespaces[$ns];
        }

        parent::__construct($name, $value, $namespaceURI);
    }

    /**
     * Split given name in namespace prefix and local part
     *
     * @param  string $name
     * @return array  (namespace, name)
     */
    public function splitns($name){
        $list = explode(':',$name,2);
        if(count($list) < 2) array_unshift($list,'');
        return $list;
    }

    /**
     * Simple EPub namespace aware attribute accessor
     */
    public function attr($attr,$value=null){
        list($ns,$attr) = $this->splitns($attr);

        if(!is_null($value)){
            if($value === false){
                // delete if false was given
                if($ns){
                    $this->removeAttributeNS($this->namespaces[$ns],$attr);
                }else{
                    $this->removeAttribute($attr);
                }
            }else{
                // modify if value was given
                if($ns){
                    $this->setAttributeNS($this->namespaces[$ns],$attr,$value);
                }else{
                    $this->setAttribute($attr,$value);
                }
            }
        }else{
            // return value if none was given
            if($ns){
                return $this->getAttributeNS($this->namespaces[$ns],$attr);
            }else{
                return $this->getAttribute($attr);
            }
        }
    }

    /**
     * Remove this node from the DOM
     */
    public function delete(){
        $this->parentNode->removeChild($this);
    }

}


