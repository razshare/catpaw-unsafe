<?php
namespace Tests;

use function CatPaw\Unsafe\anyError;

use PHPUnit\Framework\TestCase;

class MainTest extends TestCase {
    public function testExample() {
        // open file
        $file = openFile('file.txt')->try($error);
        $this->assertFalse($error);

        // read contents
        $contents = readfile($file)->try($error);
        $this->assertFalse($error);


        $this->assertEquals('hello', $contents);

        // close file
        closeFile($file)->try($error);
        $this->assertFalse($error);


        echo $contents.PHP_EOL;
    }

    public function testExampleWithAnyError() {
        $contents = anyError(function() {
            // open file
            $file = openFile('file.txt')->try($error)
            or yield $error;

            // read contents
            $contents = readfile($file)->try($error)
            or yield $error;


            // close file
            closeFile($file)->try($error)
            or yield $error;

            return $contents;
        })->try($error);
        
        $this->assertFalse($error);

        $this->assertEquals('hello', $contents);

        echo $contents.PHP_EOL;
    }
}
