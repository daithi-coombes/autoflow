<?php
/**
 * Compress ALL javascript files in the public_html/js/src directory using
 * packer.
 * 
 * Uses packer as a compression tool. Please make sure you always end all
 * curly brackets '}' and normal brackets ')' with a semicolon ';'.
 * 
 * Will compress all javascript files with the namesspace for this plugin and
 * place the compressed files in public_html/js folder with 'min' appened to
 * filename. ie:
 * <code>public_html/js/src/{$namespace}Module.js</code>
 * creates
 * <code>public_html/js/{$namespace}Module.min.js</code>
 * 
 * @author daithi
 * @uses packer
 * @link http://joliclic.free.fr/php/javascript-packer/en/
 * @package js-compressor
 */
error_reporting(E_ALL);
ini_set('display_errors','on');
/**
 * include the JavaScriptPacker class.
 */
require_once('../../../application/includes/class.JavaScriptPacker.php');

/**
 * An array of files to be compressed. Default is js/src/{$namespace}* 
 * @global array $files
 * @name files 
 */
$files = array();
/**
 * The packer object
 * @global object $packer
 * @name JavaScriptPacker
 */
$packer;

//get js files in current dir
$dir = opendir(".");
while(false !==($file=  readdir($dir)))
	if(preg_match("/\.js$/", $file)) $files[] = $file;
	
//compress files
print "<pre>";
foreach($files as $file){
	
	//vars
	$script = file_get_contents($file);
	$parts = explode(".", $file);
	$ext = array_pop($parts);
	$out = "../" . implode("", $parts) . ".min.{$ext}";
	
	//pack compressed file
	$packer = new JavaScriptPacker($script);
	$packed = $packer->pack();
	file_put_contents($out, $packed);
	
	//log
	print "{$file} compressed to:\n\t{$out}\n\tsuccessfully\n";
}
print "</pre>";
?>