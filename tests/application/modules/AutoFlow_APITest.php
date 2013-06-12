<?php

class AutoFlow_APITest extends PHPUnit_Framework_TestCase{

	function setUp(){
		parent::setUp();

		$this->obj = new AutoFlow_API();
	}

	function test_foo(){
		$foo = true;
		$this->assertEquals(true, $foo);
	}
}
