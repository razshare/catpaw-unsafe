<?php
namespace CatPaw\Unsafe;

use Error;
use Generator;
use Throwable;


/**
 * @template T
 * @param  T         $value
 * @return Unsafe<T>
 */
function ok(mixed $value = true): Unsafe {
    return new Unsafe($value, false);
}

/**
 * @param  string|Error $message
 * @return Unsafe<void>
 */
function error(string|Error $message): Unsafe {
    if (is_string($message)) {
        return new Unsafe(null, new Error($message));
    }

    return new Unsafe(null, $message);
}

/**
 * Invoke a generator function and immediately return any `Error`
 * or `Unsafe` that contains an error.\
 * In both cases the result is always an `Unsafe<T>` object.
 *
 * - If you generate an `Unsafe<T>` the error within the object is transferred to a new `Unsafe<T>` for the sake of consistency.
 * - If you generate an `Error` instead, then the `Error` is wrapped in `Unsafe<T>`.
 *
 * The generator is consumed and if no error is detected then the function produces the returned value of the generator.
 *
 * ## Example
 * ```php
 * $content = anyError(function(){
 *  $file = File::open('file.txt')->try($error)
 *  or yield $error;
 *
 *  $content = $file->readAll()->await()->try($error)
 *  or yield $error;
 *
 *  return $content;
 * });
 * ```
 * @template T
 * @param  callable():Generator<Unsafe|Error|T> $function
 * @return Unsafe<T>
 */
function anyError(callable $function): Unsafe {
    /** @var Generator<Unsafe<mixed>> $result */
    $result = $function();

    if (!($result instanceof Generator)) {
        if ($result instanceof Unsafe) {
            return $result;
        }
        return ok($result);
    }

    for ($result->rewind(); $result->valid(); $result->next()) {
        /** @var Unsafe<Error|Unsafe> $value */
        $value = $result->current();
        if ($value instanceof Error) {
            return error($value);
        } elseif ($value instanceof Unsafe && $value->error) {
            return error($value->error);
        }
    }

    try {
        $return = $result->getReturn() ?? true;

        if ($return instanceof Unsafe) {
            return $result;
        }

        return ok($return);
    } catch (Throwable $error) {
        return error($error);
    }
}