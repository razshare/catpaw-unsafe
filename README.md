# Control flow is king

I am of the opinion that control flow is one of the most important things to deal with as a programmer, it affects my thinking and at times it actually guides my problem solving process.

Managing errors should not break the flow in which I control my program, I shouldn't have to jump up and down around my file (sometimes entire screens) to catch a new exception introduced by a new function I just invoked 20 lines above.


# Try/Catch

I found myself relying way too much on code like this

```php
try {
    // some code
} catch(SomeException1 $e){
    // manage error 1
} catch(SomeException2 $e) {
    // manage error 2
} catch(SomeException3 $e) {
    // manage error 3
}
```

or even worse

```php
try {
    // some code
} catch(SomeException1|SomeException2|SomeException3 $e){
    // manage all errors in one place
}
```

Some IDEs actually suggest you do this by default!

It might make sense in theory, but in practice those exceptions might each mean something different, a different cause for an error.

The reality is that very often I lump those exceptions in together because I forget to manage them or because for some reason at 4 AM I decide on the spot "yes, I should let my IDE dictate my error management".


Try/catch error handling has been (probably) the most popular way to manage errors in php, and I think it still is a valid way of dealing with errors in a global scope.

I can't argue there is something nice about having one centralized place to manage all errors, but I don't want to be forced to approach error management all the time in that manner.


If you're anything like me you might prefer managing your error inline, directly at the source, so that you deal with it when it pops up and then you don't have to think about it anymore.

# Unsafe<T>

I have a solution.

Do not throw exceptions in your code, instead return your errors as _Unsafe<T>_.

```php
namespace CatPaw\Unsafe;
/**
 * @template T
 */
readonly class Unsafe {
    /** @var T $value */
    public $value;
    public false|Error $error;
}
```

Use the _ok()_ and _error()_ functions to create _Unsafe<T>_ objects.

# ok()

```php
namespace CatPaw\Unsafe;
/**
 * @template T
 * @param T $value
 * @return Unsafe<T>
 */
function ok($value);
```
Return _ok($value)_ whenever there are no errors in your program.

This function will create a new _Unsafe<T>_ with a valid _$value_ and no error.

# error()

```php
namespace CatPaw\Core;
/**
 * @param string|Error $error
 * @return Unsafe<void>
 */
function error($error);
```
Return _error($error)_ whenever you encounter an error in your program and want to propagate it upstream.

This function will create a new _Unsafe<T>_ with a _null $value_ and the given _error_.

# Example

The following example tries to read the contents of a file while managing errors.


First I'm declaring all entities involved, classes and functions.

```php
<?php
use CatPaw\Unsafe\Unsafe;
use function CatPaw\Unsafe\anyError;
use function CatPaw\Unsafe\error;
use function CatPaw\Unsafe\ok;

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
 * @param string $fileName 
 * @return Unsafe<resource> 
 */
function openFile(string $fileName){
    if(!file_exists($fileName)){
        return error(new FileNotFoundError($fileName));
    }
    if(!$file = fopen('file.txt', 'r+')){
        return error("Something went wrong while trying to open file $fileName.");
    }
    return ok($file);
}

/**
 * Attempt to read 5 bytes from the file.
 * @param resource $stream 
 * @return Unsafe<string> 
 */
function readFile($stream){
    $content = fread($stream, 5);
    if(false === $content){
        return error("Couldn't read from stream.");
    }

    return ok($content);
}

/**
 * Attempt to close the file.
 * @param resource $stream 
 * @return Unsafe<void> 
 */
function closeFile($stream){
    if(!fclose($stream)){
        return error("Couldn't close file.");
    }
    return ok();
}
```

then 

1. open a file
2. read its contents
3. close the file

```php
<?php
// open file
$file = openFile('file.txt')->try($error);
if ($error) {
    echo $error.PHP_EOL;
    die();
}

// read contents
$contents = readFile($file)->try($error);
if ($error) {
    echo $error.PHP_EOL;
    die();
}

// close file
closeFile($file)->try($error);
if ($error) {
    echo $error.PHP_EOL;
    die();
}

echo $contents.PHP_EOL;
```
This code will print the contents of `file.txt` if all operations succeed.

Each time `->try($error)` is invoked the _Unsafe_ object tries to unwrap its value.\
If the _Unsafe_ object contains an error, the value returned by `->try($error)` resolves to `null` and the variable `$error` is assigned the contained error by reference.


# anyError()

You can use _anyError()_ to deal away with the repetitive snippet

```php
if($error){
    echo $error.PHP_EOL;
    // manage error here...
}
```

Here's the same example but written using _anyError()_

```php
<?php
$contents = anyError(function() {
    // open file
    $file = openFile('file.txt')->try($error)
    or yield $error;

    // read contents
    $contents = readFile($file)->try($error)
    or yield $error;


    // close file
    closeFile($file)->try($error)
    or yield $error;

    return $contents;
})->try($error);

if($error){
    echo $error.PHP_EOL;
    die();
}

echo $contents.PHP_EOL;
```

The _anyError()_ function takes a generator function and it consumes it step by step.

When the generator function `yield`s an _Error_ or an _Unsafe<T>_ containing an _Error_, the _anyError_ function will stop executing the generator immediately and return a new _Unsafe<T>_ containing the given error.

Effectively, `or yield $error` acts like 
```php
if($error){
    return error($error);
}
```
On the other hand, if the result of `->try()` is valid, the `or <expression>` is not executed and the generator keeps running until it reaches the next `yield error` statement, the next `return` statement or until the generator is consumed. 

> [!NOTE]
> When the _Unsafe_ object unwraps an error with `->try($error)`, the `or <expression>` is triggered in response to the `null` value returned by `->try($error)`.\
> \
> Technically, the `or <expression>` can trigger for any falsy value unwrapped, like `false` or `0`.\
> In those cases you will need to specifically check that the _$error_ is actually set.
>
> So this part
> ```php
> $contents = readFile($file)->try($error)
> or yield $error;
> ```
> becomes
> ```php
> $contents = readFile($file)->try($error)
> or $error // <=== this will ensure the error is set...
> and yield $error; // ...and only then it will yield the error and stop execution.
> ```