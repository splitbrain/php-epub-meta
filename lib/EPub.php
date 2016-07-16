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
    #region Constants
    const DATE_MODIFICATION = 'modification';
    const DATE_CREATION = 'creation';
    const DATE_CONVERSION = 'conversion';
    const DATE_PUB = 'publication';
    const DATE_PUB_ORIG = 'original-publication';

    const IDENT_URI = 'URI';
    const IDENT_URN = 'URN';
    const IDENT_ISBN = 'ISBN';
    const IDENT_AMAZON = 'AMAZON';
    const IDENT_GOOGLE = 'GOOGLE';
    const IDENT_CALIBRE = 'CALIBRE';
    #endregion

    /** @var string Location of the meta package within the epub */
    protected $meta;
    /** @var EPubDOMDocument Parsed XML of the meta package */
    public $meta_xml;
    /** @var  EPubDOMXPath XPath access to the meta package */
    protected $meta_xpath;
    /** @var string The path to the epub file */
    protected $file;
    /** @var  \clsTbsZip handles the ZIP operations on the epub file */
    protected $zip;
    /** @var  null|array The manifest data, eg. which files are available */
    protected $manifest = null;

    /**
     * Constructor
     *
     * @param string $file path to epub file to work on
     * @throws \Exception if metadata could not be loaded
     */
    public function __construct($file)
    {
        // open file
        $this->file = $file;
        $this->zip = new \clsTbsZip();
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
                'path' => $file,
            );
        }

        return $manifest;
    }

    /**
     * Close the epub file
     */
    public function close()
    {
        $this->zip->Close();
    }

    /**
     * Writes back all changes to the epub
     */
    public function save()
    {
        $data = $this->download();
        $this->zip->Close();
        file_put_contents($this->getEPubLocation(), $data);
    }

    /**
     * Get the updated epub
     *
     * @param null|string $file
     * @return string|bool
     */
    public function download($file = null)
    {
        $this->zip->FileReplace($this->meta, $this->meta_xml->saveXML());

        if ($file) {
            return $this->zip->Flush(TBSZIP_DOWNLOAD, $file);
        } else {
            $this->zip->Flush(TBSZIP_STRING);
            return $this->zip->OutputSrc; // ugly but currently the only interface in the ZIP lib
        }
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
     * Makes sure the epub3 cover-image attribute is set
     */
    public function updateForKepub()
    {
        $cover = $this->getCoverFile();
        if ($cover === null) {
            return;
        }

        $id = $cover['id'];
        $nodes = $this->meta_xpath->query('//opf:manifest/opf:item[@id="' . $id . '"]');
        $node = $nodes->item(0);
        if (!$node) {
            return;
        }
        $node->attr('opf:properties', 'cover-image');
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
     * Read or set a date
     *
     * @param string $type The type to set/read - use the DATE_* constants for typical dates
     * @param string|null $date Date eg: 2012-05-19T12:54:25Z
     * @return string
     */
    public function Date($type, $date = null)
    {
        return $this->getset('dc:date', $date, 'opf:event', $type);
    }

    /**
     * Set or get the book's Identifier
     *
     * @param string $type Type of identifier, use TYPE_* constants for typical
     * @param string|null $ident Identifier
     * @return string
     */
    public function Identifier($type, $ident = null)
    {
        $res = $this->getset('dc:identifier', $ident, 'opf:scheme', $type);

        return $res;
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
        return array('id' => '', 'mime' => '', 'exists' => false, 'path' => $path);
    }

    /**
     * Get info on the cover image if any
     *
     * Returns the same info as getFileInfo() for the cover image if any. Returns null
     * if there's no cover image.
     *
     * @return array|null
     */
    public function getCoverFile()
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

        return $this->getFileInfo($this->getFullPath($nodes->item(0)->attr('href')));
    }

    /**
     * Removes the cover image if there's one
     *
     * If the actual image file was added by this library it will be removed. Otherwise only the
     * reference to it is removed from the metadata, since the same image might be referenced
     * by other parts of the epub file.
     */
    public function clearCover()
    {
        // do nothing if there's no cover currently
        $cover = $this->getCoverFile();
        if ($cover === null) {
            return;
        }

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
        // remove the actual file if it was added by us
        if ($cover['id'] == 'php-epub-meta-cover') {
            $this->zip->FileReplace($cover['path'], false);
            $this->manifest = null;
        }
    }

    /**
     * Set a new cover image
     *
     * @param string $path path to the image to set on the local file system
     * @param string $mime mime type of that image (like 'image/jpeg')
     * @throws \Exception when the given image can't be read
     */
    public function setCoverFile($path, $mime)
    {
        $this->clearCover();
        $data = file_get_contents($path);
        if ($data === false) {
            throw new \Exception("Couldn't load data from $path");
        }

        /** @var EPubDOMElement $node */

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
        $node->attr('opf:properties', 'cover-image');

        $full = $this->getFullPath('php-epub-meta-cover.img');

        // remember path for save action
        if ($this->zip->FileExists($full)) {
            $this->zip->FileReplace($full, $data);
        } else {
            $this->zip->FileAdd($full, $data);
        }

        $this->reparse();
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
     * Resolves paths relative to the meta container location
     *
     * @param string $file relative path
     * @return string full path within the zip
     */
    private function getFullPath($file)
    {
        list($file) = explode('#', $file); // strip anchors
        $path = dirname('/' . $this->meta) . '/' . $file;
        $path = ltrim($path, '\\');
        $path = ltrim($path, '/');

        return $path;
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
     * This is needed when new nodes are added to the DOM, otherwise XPath won't find those new
     * nodes.
     */
    protected function reparse()
    {
        $this->meta_xml->loadXML($this->meta_xml->saveXML());
        $this->meta_xpath = new EPubDOMXPath($this->meta_xml);
        $this->manifest = null;
    }
}
