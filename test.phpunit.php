<?php

require 'epub.php';


class EPubTest extends PHPUnit_Framework_TestCase {

    protected $epub;

    protected function setUp(){
        $this->epub = new EPub('test.epub');
    }

    public function testAuthors(){
        // read curent value
        $this->assertEquals(
            $this->epub->Authors(),
            array('Shakespeare, William' => 'William Shakespeare')
        );

        // remove value with string
        $this->assertEquals(
            $this->epub->Authors(''),
            array()
        );

        // set single value by String

        $this->assertEquals(
            $this->epub->Authors('John Doe'),
            array('John Doe' => 'John Doe')
        );

        // set single value by indexed array
        $this->assertEquals(
            $this->epub->Authors(array('John Doe')),
            array('John Doe' => 'John Doe')
        );

        // remove value with array
        $this->assertEquals(
            $this->epub->Authors(array()),
            array()
        );

        // set single value by associative array
        $this->assertEquals(
            $this->epub->Authors(array('Doe, John' => 'John Doe')),
            array('Doe, John' => 'John Doe')
        );

        // set multi value by string
        $this->assertEquals(
            $this->epub->Authors('John Doe, Jane Smith'),
            array('John Doe' => 'John Doe', 'Jane Smith' => 'Jane Smith')
        );

        // set multi value by indexed array
        $this->assertEquals(
            $this->epub->Authors(array('John Doe', 'Jane Smith')),
            array('John Doe' => 'John Doe', 'Jane Smith' => 'Jane Smith')
        );

        // set multi value by associative  array
        $this->assertEquals(
            $this->epub->Authors(array('Doe, John' => 'John Doe', 'Smith, Jane' => 'Jane Smith')),
            array('Doe, John' => 'John Doe', 'Smith, Jane' => 'Jane Smith')
        );
    }

    public function testTitle(){
        // get current value
        $this->assertEquals(
            $this->epub->Title(),
            'Romeo and Juliet'
        );

        // delete current value
        $this->assertEquals(
            $this->epub->Title(''),
            ''
        );

        // get current value
        $this->assertEquals(
            $this->epub->Title(),
            ''
        );

        // set new value
        $this->assertEquals(
            $this->epub->Title('Foo Bar'),
            'Foo Bar'
        );
    }

    public function testSubject(){
        // get current values
        $this->assertEquals(
            $this->epub->Subjects(),
            array('Fiction','Drama','Romance')
        );

        // delete current values with String
        $this->assertEquals(
            $this->epub->Subjects(''),
            array()
        );

        // set new values with String
        $this->assertEquals(
            $this->epub->Subjects('Fiction, Drama, Romance'),
            array('Fiction','Drama','Romance')
        );

        // delete current values with Array
        $this->assertEquals(
            $this->epub->Subjects(array()),
            array()
        );

        // set new values with array
        $this->assertEquals(
            $this->epub->Subjects(array('Fiction','Drama','Romance')),
            array('Fiction','Drama','Romance')
        );
    }

}
