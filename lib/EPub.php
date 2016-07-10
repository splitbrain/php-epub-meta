<?php
/**
 * PHP EPub Meta library
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Sébastien Lucas <sebastien@slucas.fr>
 */

namespace splitbrain\epubmeta;

define('METADATA_FILE', 'META-INF/container.xml');

class EPub
{

    /** @var string Location of the meta package within the epub */
    protected $meta;
    /** @var EPubDOMDocument Parsed XML of the meta package */
    public $meta_xml;
    /** @var  EPubDOMXPath XPath access to the meta package */
    protected $meta_xpath;

    /** @var string The path to the epub file */
    protected $file;

    /** @var  \clsTbsZip */
    protected $zip;
    protected $coverpath = '';
    protected $namespaces;
    protected $imagetoadd = '';

    /** @var  null|array The manifest data, eg. which files are available */
    protected $manifest = null;

    /**
     * Constructor
     *
     * @param string $file path to epub file to work on
     * @param string $zipClass class to handle zip
     * @throws \Exception if metadata could not be loaded
     */
    public function __construct($file, $zipClass = 'clsTbsZip')
    {
        // open file
        $this->file = $file;
        $this->zip = new $zipClass();
        if (!$this->zip->Open($this->file)) {
            throw new \Exception('Failed to read epub file');
        }

        // read container data
        if (!$this->zip->FileExists(METADATA_FILE)) {
            throw new \Exception('Unable to find metadata.xml');
        }

        $data = $this->zip->FileRead(METADATA_FILE);
        if ($data == false) {
            throw new \Exception('Failed to access epub container data');
        }
        $xml = new EPubDOMDocument();
        $xml->loadXML($data);
        $xpath = new EPubDOMXPath($xml);
        $nodes = $xpath->query('//n:rootfiles/n:rootfile[@media-type="application/oebps-package+xml"]');
        $this->meta = $nodes->item(0)->attr('full-path');

        // load metadata
        if (!$this->zip->FileExists($this->meta)) {
            throw new \Exception('Unable to find ' . $this->meta);
        }

        $data = $this->zip->FileRead($this->meta);
        if (!$data) {
            throw new \Exception('Failed to access epub metadata');
        }
        $this->meta_xml = new EpubDOMDocument();
        $this->meta_xml->loadXML($data);
        $this->meta_xml->formatOutput = true;
        $this->meta_xpath = new EPubDOMXPath($this->meta_xml);
    }

    /**
     * Lists all files from the manifest
     *
     * @return array
     */
    protected function readManifest()
    {
        $manifest = array();
        $nodes = $this->meta_xpath->query('//opf:manifest/opf:item');

        foreach ($nodes as $node) {
            /** @var EPubDOMElement $node */
            $file = $node->attr('opf:href');
            if ($file === '') {
                continue;
            }
            $file = $this->getFullPath($file);

            $manifest[$file] = array(
                'id' => $node->attr('id'),
                'mime' => $node->attr('opf:media-type'),
                'exists' => (bool)$this->zip->FileExists($file),
                'path' => $file
            );
        }

        return $manifest;
    }



    /**
     * Close the epub file
     */
    public function close()
    {
        $this->zip->FileCancelModif($this->meta);
        // TODO: Add cancelation of cover image
        $this->zip->Close();
    }

    /**
     * Remove iTunes files
     */
    public function cleanITunesCrap()
    {
        if ($this->zip->FileExists('iTunesMetadata.plist')) {
            $this->zip->FileReplace('iTunesMetadata.plist', false);
        }
        if ($this->zip->FileExists('iTunesArtwork')) {
            $this->zip->FileReplace('iTunesArtwork', false);
        }
    }

    /**
     * Writes back all meta data changes
     */
    public function save()
    {
        $this->download();
        $this->zip->Close();
    }

    /**
     * Get the updated epub
     */
    public function download($file = false)
    {
        $this->zip->FileReplace($this->meta, $this->meta_xml->saveXML());
        // add the cover image
        if ($this->imagetoadd) {
            $this->zip->FileReplace($this->coverpath, file_get_contents($this->imagetoadd));
            $this->imagetoadd = '';
        }
        if ($file) {
            $this->zip->Flush(TBSZIP_DOWNLOAD, $file);
        }
    }



    #region Book Attribute Getter/Setters

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
     *      'Simpson, Jacqueline' => 'Jacqueline Simpson',
     * )
     *
     * When a string is given, it assumed to be a comma separted list of Author names
     *
     * @param array|string|null $authors
     * @return array
     */
    public function Authors($authors = null)
    {
        // set new data
        if ($authors !== null) {
            // Author where given as a comma separated list
            if (is_string($authors)) {
                if ($authors == '') {
                    $authors = array();
                } else {
                    $authors = explode(',', $authors);
                    $authors = array_map('trim', $authors);
                }
            }

            // delete existing nodes
            $nodes = $this->meta_xpath->query('//opf:metadata/dc:creator[@opf:role="aut"]');
            foreach ($nodes as $node) {
                /** @var EPubDOMElement $node */
                $node->delete();
            }

            // add new nodes
            $parent = $this->meta_xpath->query('//opf:metadata')->item(0);
            foreach ($authors as $as => $name) {
                if (is_int($as)) {
                    $as = $name; //numeric array given
                }
                $node = $parent->newChild('dc:creator', $name);
                $node->attr('opf:role', 'aut');
                $node->attr('opf:file-as', $as);
            }

            $this->reparse();
        }

        // read current data
        $rolefix = false;
        $authors = array();
        $nodes = $this->meta_xpath->query('//opf:metadata/dc:creator[@opf:role="aut"]');
        if ($nodes->length == 0) {
            // no nodes where found, let's try again without role
            $nodes = $this->meta_xpath->query('//opf:metadata/dc:creator');
            $rolefix = true;
        }
        foreach ($nodes as $node) {
            $name = $node->nodeValue;
            $as = $node->attr('opf:file-as');
            if (!$as) {
                $as = $name;
                $node->attr('opf:file-as', $as);
            }
            if ($rolefix) {
                $node->attr('opf:role', 'aut');
            }
            $authors[$as] = $name;
        }
        return $authors;
    }

    /**
     * Set or get the book title
     *
     * @param string|null $title
     * @return string
     */
    public function Title($title = null)
    {
        return $this->getset('dc:title', $title);
    }

    /**
     * Set or get the book's language
     *
     * @param string|null $lang
     * @return string
     */
    public function Language($lang = null)
    {
        return $this->getset('dc:language', $lang);
    }

    /**
     * Set or get the book' publisher info
     *
     * @param string|null $publisher
     * @return string
     */
    public function Publisher($publisher = null)
    {
        return $this->getset('dc:publisher', $publisher);
    }

    /**
     * Set or get the book's copyright info
     *
     * @param string|null $rights
     * @return string
     */
    public function Copyright($rights = null)
    {
        return $this->getset('dc:rights', $rights);
    }

    /**
     * Set or get the book's description
     *
     * @param string|null $description
     * @return string
     */
    public function Description($description = null)
    {
        return $this->getset('dc:description', $description);
    }

    /**
     * Set or get the book's Unique Identifier
     *
     * @param string|null $uuid Unique identifier
     * @return string
     * @throws \Exception
     * @todo auto add unique identifer if needed
     */
    public function Uuid($uuid = null)
    {
        $nodes = $this->meta_xpath->query('/opf:package');
        if ($nodes->length !== 1) {
            throw new \Exception('Cannot find ebook identifier');
        }
        $identifier = $nodes->item(0)->attr('unique-identifier');

        $res = $this->getset('dc:identifier', $uuid, 'id', $identifier);

        return $res;
    }

    /**
     * Set or get the book's creation date
     *
     * @param string|null $date Date eg: 2012-05-19T12:54:25Z
     * @todo use DateTime class instead of string
     * @return string
     */
    public function CreationDate($date = null)
    {
        $res = $this->getset('dc:date', $date, 'opf:event', 'creation');

        return $res;
    }

    /**
     * Set or get the book's modification date
     *
     * @param string|null $date Date eg: 2012-05-19T12:54:25Z
     * @todo use DateTime class instead of string
     * @return string
     */
    public function ModificationDate($date = null)
    {
        $res = $this->getset('dc:date', $date, 'opf:event', 'modification');

        return $res;
    }

    /**
     * Set or get the book's URI
     *
     * @param string|null $uri URI
     * @return string
     */
    public function Uri($uri = null)
    {
        $res = $this->getset('dc:identifier', $uri, 'opf:scheme', 'URI');

        return $res;
    }

    /**
     * Set or get the book's ISBN number
     *
     * @param string|null $isbn
     * @return string
     */
    public function ISBN($isbn = null)
    {
        return $this->getset('dc:identifier', $isbn, 'opf:scheme', 'ISBN');
    }

    /**
     * Set or get the Google Books ID
     *
     * @param string|null $google
     * @return string
     */
    public function Google($google = null)
    {
        return $this->getset('dc:identifier', $google, 'opf:scheme', 'GOOGLE');
    }

    /**
     * Set or get the Amazon ID of the book
     *
     * @param string|null $amazon
     * @return string
     */
    public function Amazon($amazon = null)
    {
        return $this->getset('dc:identifier', $amazon, 'opf:scheme', 'AMAZON');
    }

    /**
     * Set or get the Calibre UUID of the book
     *
     * @param null|string $uuid
     * @return string
     */
    public function Calibre($uuid = null)
    {
        return $this->getset('dc:identifier', $uuid, 'opf:scheme', 'calibre');
    }

    /**
     * Set or get the Series of the book
     *
     * @param string|null $series
     * @return string
     */
    public function Series($series = null)
    {
        return $this->getset('opf:meta', $series, 'name', 'calibre:series', 'content');
    }

    /**
     * Set or get the Series Index of the book
     *
     * @param string|null $seriesIndex
     * @return string
     */
    public function SeriesIndex($seriesIndex = null)
    {
        return $this->getset('opf:meta', $seriesIndex, 'name', 'calibre:series_index', 'content');
    }

    /**
     * Set or get the book's subjects (aka. tags)
     *
     * Subject should be given as array, but a comma separated string will also
     * be accepted.
     *
     * @param array|string|null $subjects
     * @return string[]
     */
    public function Subjects($subjects = null)
    {
        // setter
        if ($subjects !== null) {
            if (is_string($subjects)) {
                if ($subjects === '') {
                    $subjects = array();
                } else {
                    $subjects = explode(',', $subjects);
                    $subjects = array_map('trim', $subjects);
                }
            }

            // delete previous
            $nodes = $this->meta_xpath->query('//opf:metadata/dc:subject');
            foreach ($nodes as $node) {
                /** @var EPubDOMElement $node */
                $node->delete();
            }
            // add new ones
            $parent = $this->meta_xpath->query('//opf:metadata')->item(0);
            foreach ($subjects as $subj) {
                $node = $this->meta_xml->createElement('dc:subject', htmlspecialchars($subj));
                $parent->appendChild($node);
            }

            $this->reparse();
        }

        //getter
        $subjects = array();
        $nodes = $this->meta_xpath->query('//opf:metadata/dc:subject');
        foreach ($nodes as $node) {
            $subjects[] = $node->nodeValue;
        }
        return $subjects;
    }

    #endregion

    #region Book Attribute Getters

    /**
     * The path to the currently loaded EPub
     */
    public function getEPubLocation()
    {
        return $this->file;
    }


    /**
     * Returns the Table of Contents
     *
     * @return array
     * @throws \Exception
     */
    public function getToc()
    {
        $contents = array();

        // find TOC file
        $tocid = $this->meta_xpath->query('//opf:spine')->item(0)->attr('toc');
        $tochref = $this->meta_xpath->query('//opf:manifest/opf:item[@id="' . $tocid . '"]')->item(0)->attr('href');
        $tocpath = $this->getFullPath($tochref);
        // read TOC file
        if (!$this->zip->FileExists($tocpath)) {
            throw new \Exception('Unable to find ' . $tocpath);
        }
        $data = $this->zip->FileRead($tocpath);
        // parse TOC file
        $toc_xml = new EPubDOMDocument();
        $toc_xml->loadXML($data);
        $toc_xpath = new EPubDOMXPath($toc_xml);

        // read nav point nodes
        $nodes = $toc_xpath->query('//ncx:ncx/ncx:navMap/ncx:navPoint');
        foreach ($nodes as $node) {
            $contents[] = $this->mkTocEntry($node, $toc_xpath);

            $insidenodes = $toc_xpath->query('ncx:navPoint', $node);
            foreach ($insidenodes as $insidenode) {
                $contents[] = $this->mkTocEntry($insidenode, $toc_xpath);
            }
        }

        return $contents;
    }

    /**
     * Returns info about the given file from the manifest
     *
     * @param $path
     * @return array
     */
    public function getFileInfo($path)
    {
        if ($this->manifest === null) {
            $this->manifest = $this->readManifest();
        }

        if (isset($this->manifest[$path])) {
            return $this->manifest[$path];
        }
        return array('id' => '', 'mime' => '', 'exists' => false, 'file' => $path);
    }

    /**
     * Read the contents of a file witin the epub
     *
     * You probably want to use getFileInfo() first to check if the file exists and get
     * additional file info like the mime type
     *
     * @param string $path the path within the epub file
     * @return string the raw file contents
     * @throws \Exception when the file doesn't exists
     */
    public function getFile($path)
    {
        if (!$this->zip->FileExists($path)) {
            throw new \Exception('No such file');
        }

        return $this->zip->FileRead($path);
    }

    #endregion


    #region Internal Functions

    /**
     * Enhances the a single TOC entry with data from the manifest
     *
     * @param EPubDOMElement $node an ncx:navPoint entry
     * @param EPubDOMXPath $toc_xpath
     * @return array
     */
    protected function mkTocEntry(EPubDOMElement $node, EPubDOMXPath $toc_xpath)
    {
        $title = $toc_xpath->query('ncx:navLabel/ncx:text', $node)->item(0)->nodeValue;
        $src = $toc_xpath->query('ncx:content', $node)->item(0)->attr('src');

        $file = $this->getFullPath($src);

        return array_merge(array('title' => $title, 'src' => $src), $this->getFileInfo($file));
    }

    #endregion

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
     * When adding a new image this function return no or old data because the
     * image contents are not in the epub file, yet. The image will be added when
     * the save() method is called.
     *
     * @param  string $path local filesystem path to a new cover image
     * @param  string $mime mime type of the given file
     * @return array
     */
    public function Cover($path = false, $mime = false)
    {
        // set cover
        if ($path !== false) {
            // remove current pointer
            $nodes = $this->meta_xpath->query('//opf:metadata/opf:meta[@name="cover"]');
            foreach ($nodes as $node) {
                /** @var EPubDOMElement $node */
                $node->delete();
            }
            // remove previous manifest entries if they where made by us
            $nodes = $this->meta_xpath->query('//opf:manifest/opf:item[@id="php-epub-meta-cover"]');
            foreach ($nodes as $node) {
                /** @var EPubDOMElement $node */
                $node->delete();
            }

            if ($path) {
                // add pointer
                $parent = $this->meta_xpath->query('//opf:metadata')->item(0);
                $node = $parent->newChild('opf:meta');
                $node->attr('opf:name', 'cover');
                $node->attr('opf:content', 'php-epub-meta-cover');

                // add manifest
                $parent = $this->meta_xpath->query('//opf:manifest')->item(0);
                $node = $parent->newChild('opf:item');
                $node->attr('id', 'php-epub-meta-cover');
                $node->attr('opf:href', 'php-epub-meta-cover.img');
                $node->attr('opf:media-type', $mime);

                // remember path for save action
                $this->imagetoadd = $path;
            }

            $this->reparse();
        }

        // load cover
        $nodes = $this->meta_xpath->query('//opf:metadata/opf:meta[@name="cover"]');
        if (!$nodes->length) {
            return $this->no_cover();
        }
        $coverid = (String)$nodes->item(0)->attr('opf:content');
        if (!$coverid) {
            return $this->no_cover();
        }

        $nodes = $this->meta_xpath->query('//opf:manifest/opf:item[@id="' . $coverid . '"]');
        if (!$nodes->length) {
            return $this->no_cover();
        }
        $mime = $nodes->item(0)->attr('opf:media-type');
        $path = $nodes->item(0)->attr('opf:href');
        $path = dirname('/' . $this->meta) . '/' . $path; // image path is relative to meta file
        $path = ltrim($path, '/');

        $zip = new \ZipArchive();
        if (!@$zip->open($this->file)) {
            throw new \Exception('Failed to read epub file');
        }
        $data = $zip->getFromName($path);

        return array(
            'mime' => $mime,
            'data' => $data,
            'found' => $path
        );
    }

    public function getCoverItem()
    {
        $nodes = $this->meta_xpath->query('//opf:metadata/opf:meta[@name="cover"]');
        if (!$nodes->length) {
            return null;
        }

        $coverid = (String)$nodes->item(0)->attr('opf:content');
        if (!$coverid) {
            return null;
        }

        $nodes = $this->meta_xpath->query('//opf:manifest/opf:item[@id="' . $coverid . '"]');
        if (!$nodes->length) {
            return null;
        }

        return $nodes->item(0);
    }

    public function combine($a, $b)
    {
        $isAbsolute = false;
        if ($a[0] == '/') {
            $isAbsolute = true;
        }

        if ($b[0] == '/') {
            throw new \InvalidArgumentException('Second path part must not start with /');
        }

        $splittedA = preg_split('#/#', $a);
        $splittedB = preg_split('#/#', $b);

        $pathParts = array();
        $mergedPath = array_merge($splittedA, $splittedB);

        foreach ($mergedPath as $item) {
            if ($item == null || $item == '' || $item == '.') {
                continue;
            }

            if ($item == '..') {
                array_pop($pathParts);
                continue;
            }

            array_push($pathParts, $item);
        }

        $path = implode('/', $pathParts);
        if ($isAbsolute) {
            return ('/' . $path);
        } else {
            return ($path);
        }
    }

    private function getFullPath($file, $context = null)
    {
        list($file) = explode('#', $file); // strip anchors

        $path = dirname('/' . $this->meta) . '/' . $file;
        $path = ltrim($path, '\\');
        $path = ltrim($path, '/');
        if (!empty($context)) {
            $path = $this->combine(dirname($path), $context);
        }
        //error_log ("FullPath : $path ($file / $context)");
        return $path;
    }

    public function updateForKepub()
    {
        $item = $this->getCoverItem();
        if (!is_null($item)) {
            $item->attr('opf:properties', 'cover-image');
        }
    }

    public function Cover2($path = false, $mime = false)
    {
        $hascover = true;
        $item = $this->getCoverItem();
        if (is_null($item)) {
            $hascover = false;
        } else {
            $mime = $item->attr('opf:media-type');
            $this->coverpath = $item->attr('opf:href');
            $this->coverpath = dirname('/' . $this->meta) . '/' . $this->coverpath; // image path is relative to meta file
            $this->coverpath = ltrim($this->coverpath, '\\');
            $this->coverpath = ltrim($this->coverpath, '/');
        }

        // set cover
        if ($path !== false) {
            if (!$hascover) {
                return; // TODO For now only update
            }

            if ($path) {
                $item->attr('opf:media-type', $mime);

                // remember path for save action
                $this->imagetoadd = $path;
            }

            $this->reparse();
        }

        if (!$hascover) {
            return $this->no_cover();
        }
    }

    /**
     * A simple getter/setter for simple meta attributes
     *
     * It should only be used for attributes that are expected to be unique
     *
     * @param string $item XML node to set/get
     * @param string|null $value New value to set, null to get, passing an empty string deletes the node
     * @param string|null $att Attribute name the node needs to have for a match
     * @param string|null $aval Attribute value the node needs to have for a match
     * @param string|null $datt Destination attribute to set instead of the node value
     * @return string
     */
    protected function getset($item, $value = null, $att = null, $aval = null, $datt = null)
    {
        // construct xpath
        $xpath = '//opf:metadata/' . $item;
        if ($att) {
            if ($aval) {
                $xpath .= '[@' . $att . '="' . $aval . '"]';
            } else {
                $xpath .= '[@' . $att . ']';
            }
        }

        // set value
        if ($value !== null) {
            $value = htmlspecialchars($value);
            $nodes = $this->meta_xpath->query($xpath);
            if ($nodes->length == 1) {
                if ($value === '') {
                    // the user want's to empty this value -> delete the node
                    $nodes->item(0)->delete();
                } else {
                    // replace value
                    if ($datt) {
                        $nodes->item(0)->attr($datt, $value);
                    } else {
                        $nodes->item(0)->nodeValue = $value;
                    }
                }
            } else {
                // if there are multiple matching nodes for some reason delete
                // them. we'll replace them all with our own single one
                foreach ($nodes as $n) {
                    /** @var EPubDOMElement $n */
                    $n->delete();
                }
                // readd them
                if ($value) {
                    $parent = $this->meta_xpath->query('//opf:metadata')->item(0);

                    $node = $parent->newChild($item);
                    /** @var EPubDOMElement $node */
                    if ($att) {
                        $node->attr($att, $aval);
                    }
                    if ($datt) {
                        $node->attr($datt, $value);
                    } else {
                        $node->nodeValue = $value;
                    }
                }
            }

            $this->reparse();
        }

        // get value
        $nodes = $this->meta_xpath->query($xpath);
        if ($nodes->length) {
            if ($datt) {
                return $nodes->item(0)->attr($datt);
            } else {
                return $nodes->item(0)->nodeValue;
            }
        } else {
            return '';
        }
    }

    /**
     * Return a not found response for Cover()
     */
    protected function no_cover()
    {
        return array(
            'data' => base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAEALAAAAAABAAEAAAIBTAA7'),
            'mime' => 'image/gif',
            'found' => false
        );
    }

    /**
     * Reparse the DOM tree
     *
     * I had to rely on this because otherwise xpath failed to find the newly
     * added nodes
     */
    protected function reparse()
    {
        $this->meta_xml->loadXML($this->meta_xml->saveXML());
        $this->meta_xpath = new EPubDOMXPath($this->meta_xml);
    }
}
