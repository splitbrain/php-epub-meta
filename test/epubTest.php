<?php

namespace splitbrain\epubmeta\test;

class EPubTest extends \PHPUnit_Framework_TestCase
{
    /** @var  EPub */
    protected $epub;

    protected function setUp()
    {
        // sometime I might have accidentally broken the test file
        if (filesize(realpath(__DIR__) . '/test.epub') != 768780) {
            die('test.epub has wrong size, make sure it\'s unmodified');
        }

        // we work on a copy to test saving
        if (!copy(realpath(__DIR__) . '/test.epub', realpath(__DIR__) . '/test.copy.epub')) {
            die('failed to create copy of the test book');
        }

        $this->epub = new EPub(realpath(__DIR__) . '/test.copy.epub');
    }

    public static function tearDownAfterClass()
    {
        unlink(realpath(dirname(__FILE__)) . '/test.copy.epub');
    }

    public function testManifest()
    {
        $manifest = $this->epub->readManifest();

        $this->assertEquals(41, count($manifest));
        $this->assertArrayHasKey('OPS/css/page.css', $manifest);
        $this->assertEquals(
            array(
                'id' => 'page-css',
                'mime' => 'text/css',
                'exists' => true,
                'path' => 'OPS/css/page.css'
            ),
            $manifest['OPS/css/page.css']
        );
    }

    public function testToc()
    {
        $toc = $this->epub->getToc();

        $this->assertEquals(34, count($toc));
        $this->assertEquals(
            array(
                'title' => 'Prologue',
                'src' => 'main0.xml#section_77304',
                'id' => 'main0',
                'mime' => 'application/xhtml+xml',
                'exists' => true,
                'path' => 'OPS/main0.xml'
            )
            , $toc[3]);
    }

    public function testFileReading()
    {
        $expect = '@import "page.css";

body {padding: 0;}
div.aboutauthor {text-align: left;}

div.also {
  text-align: left;
  padding-top: 5%;}

a {
  color: #000000;
  text-decoration: none;}

p {
  margin-top: 0.0em;
  margin-bottom: 0.0em;
  text-indent: 1.0em;
  text-align: justify;}';

        $data = $this->epub->getFile('OPS/css/about.css');

        $this->assertEquals($expect, $data);
    }

    /**
     * @expectedException \Exception
     */
    public function testFileFailed()
    {
        $this->epub->getFile('does/not/exist');
    }

    public function testAuthors()
    {
        // read curent value
        $this->assertEquals(
            array('Shakespeare, William' => 'William Shakespeare'),
            $this->epub->Authors()
        );

        // remove value with string
        $this->assertEquals(
            array(),
            $this->epub->Authors('')
        );

        // set single value by String

        $this->assertEquals(
            array('John Doe' => 'John Doe'),
            $this->epub->Authors('John Doe')
        );

        // set single value by indexed array
        $this->assertEquals(
            array('John Doe' => 'John Doe'),
            $this->epub->Authors(array('John Doe'))
        );

        // remove value with array
        $this->assertEquals(
            array(),
            $this->epub->Authors(array())
        );

        // set single value by associative array
        $this->assertEquals(
            array('Doe, John' => 'John Doe'),
            $this->epub->Authors(array('Doe, John' => 'John Doe'))
        );

        // set multi value by string
        $this->assertEquals(
            array('John Doe' => 'John Doe', 'Jane Smith' => 'Jane Smith'),
            $this->epub->Authors('John Doe, Jane Smith')
        );

        // set multi value by indexed array
        $this->assertEquals(
            array('John Doe' => 'John Doe', 'Jane Smith' => 'Jane Smith'),
            $this->epub->Authors(array('John Doe', 'Jane Smith'))
        );

        // set multi value by associative  array
        $this->assertEquals(
            array('Doe, John' => 'John Doe', 'Smith, Jane' => 'Jane Smith'),
            $this->epub->Authors(array('Doe, John' => 'John Doe', 'Smith, Jane' => 'Jane Smith'))
        );

        // check escaping
        $this->assertEquals(
            array('Doe, John&nbsp;' => 'John Doe&nbsp;'),
            $this->epub->Authors(array('Doe, John&nbsp;' => 'John Doe&nbsp;'))
        );
    }

    public function testTitle()
    {
        // get current value
        $this->assertEquals(
            'Romeo and Juliet',
            $this->epub->Title()
        );

        // delete current value
        $this->assertEquals(
            '',
            $this->epub->Title('')
        );

        // get current value
        $this->assertEquals(
            '',
            $this->epub->Title()
        );

        // set new value
        $this->assertEquals(
            'Foo Bar',
            $this->epub->Title('Foo Bar')
        );

        // check escaping
        $this->assertEquals(
            'Foo&nbsp;Bar',
            $this->epub->Title('Foo&nbsp;Bar')
        );
    }

    public function testSubject()
    {
        // get current values
        $this->assertEquals(
            array('Fiction', 'Drama', 'Romance'),
            $this->epub->Subjects()
        );

        // delete current values with String
        $this->assertEquals(
            array(),
            $this->epub->Subjects('')
        );

        // set new values with String
        $this->assertEquals(
            array('Fiction', 'Drama', 'Romance'),
            $this->epub->Subjects('Fiction, Drama, Romance')
        );

        // delete current values with Array
        $this->assertEquals(
            array(),
            $this->epub->Subjects(array())
        );

        // set new values with array
        $this->assertEquals(
            array('Fiction', 'Drama', 'Romance'),
            $this->epub->Subjects(array('Fiction', 'Drama', 'Romance'))
        );

        // check escaping
        $this->assertEquals(
            array('Fiction', 'Drama&nbsp;', 'Romance'),
            $this->epub->Subjects(array('Fiction', 'Drama&nbsp;', 'Romance'))
        );
    }

    public function testGetCoverFile()
    {
        $expect = array(
            'id' => 'book-cover',
            'mime' => 'image/png',
            'exists' => true,
            'path' => 'OPS/images/cover.png',
        );
        $cover = $this->epub->getCoverFile();
        $this->assertNotNull($cover);
        $this->assertEquals($expect, $cover);
    }

    public function testClearCover()
    {
        $this->epub->clearCover();
        $this->assertNull($this->epub->getCoverFile());
    }

    public function testSetCoverFile()
    {
        $this->epub->setCoverFile(__DIR__ . '/test.jpg', 'image/jpeg');
        $cover = $this->epub->getCoverFile();

        $this->assertNotNull($cover);

        $this->assertEquals(
            array(
                'id' => 'php-epub-meta-cover',
                'mime' => 'image/jpeg',
                'exists' => false,
                'path' => 'OPS/php-epub-meta-cover.img'

            ),
            $cover
        );
    }

    public function testCancel()
    {
        $this->epub->setCoverFile(__DIR__ . '/test.jpg', 'image/jpeg');
        $this->epub->Title('fooooooooooooooooooooooooooooooooooooo');
        $this->epub->close();

        clearstatcache($this->epub->getEPubLocation());
        $this->assertEquals(768780, filesize($this->epub->getEPubLocation()));
    }

    public function testSave()
    {
        $this->epub->setCoverFile(__DIR__ . '/test.jpg', 'image/jpeg');
        $this->epub->Title('fooooooooooooooooooooooooooooooooooooo');
        $this->epub->save();

        clearstatcache($this->epub->getEPubLocation());
        $this->assertNotEquals(768780, filesize($this->epub->getEPubLocation()));
    }


    /*public function testCover(){
        // read current cover
        $cover = $this->epub->Cover2();
        $this->assertEquals($cover['mime'],'image/png');
        $this->assertEquals($cover['found'],'OPS/images/cover.png');
        $this->assertEquals(strlen($cover['data']), 657911);

        // // delete cover // Don't work anymore
        // $cover = $this->epub->Cover('');
        // $this->assertEquals($cover['mime'],'image/gif');
        // $this->assertEquals($cover['found'],false);
        // $this->assertEquals(strlen($cover['data']), 42);

        // // set new cover (will return a not-found as it's not yet saved)
        $cover = $this->epub->Cover2(realpath( dirname( __FILE__ ) ) . '/test.jpg','image/jpeg');
        // $this->assertEquals($cover['mime'],'image/jpeg');
        // $this->assertEquals($cover['found'],'OPS/php-epub-meta-cover.img');
        // $this->assertEquals(strlen($cover['data']), 0);

        // save
        $this->epub->save();
        //$this->epub = new EPub(realpath( dirname( __FILE__ ) ) . '/test.copy.epub');

        // read now changed cover
        $cover = $this->epub->Cover2();
        $this->assertEquals($cover['mime'],'image/jpeg');
        $this->assertEquals($cover['found'],'OPS/images/cover.png');
        $this->assertEquals(strlen($cover['data']), filesize(realpath( dirname( __FILE__ ) ) . '/test.jpg'));
    }*/
}
