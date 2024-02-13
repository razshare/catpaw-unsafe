<?php
namespace Tests;

use function CatPaw\Unsafe\error;
use function CatPaw\Unsafe\ok;
use CatPaw\Unsafe\Unsafe;
use Error;

// This is not required, but you can return custom errors
class FileNotFoundError extends Error {
    public function __construct(private string $fileName) {
        parent::__construct('', 0, null);
    }

    public function __toString() {
        return "I'm looking for $this->fileName, where's the file Lebowski????";
    }
}

/**
 * Attempt to open a file.
 * @param  string           $fileName
 * @return Unsafe<resource>
 */
function openFile(string $fileName) {
    if (!file_exists($fileName)) {
        return error(new FileNotFoundError($fileName));
    }
    if (!$file = fopen('file.txt', 'r+')) {
        return error("Something went wrong while trying to open file $fileName.");
    }
    return ok($file);
}

/**
 * Attempt to read 5 bytes from the file.
 * @param  resource       $stream
 * @return Unsafe<string>
 */
function readFile($stream) {
    $content = fread($stream, 5);
    if (false === $content) {
        return error("Couldn't read from stream.");
    }

    return ok($content);
}

/**
 * Attempt to close the file.
 * @param  resource     $stream
 * @return Unsafe<void>
 */
function closeFile($stream) {
    if (!fclose($stream)) {
        return error("Couldn't close file.");
    }
    return ok();
}