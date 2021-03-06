<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\Util;

use Elastic\Apm\Impl\Util\StaticClassTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class RandomUtil
{
    use StaticClassTrait;

    public static function generateBool(): bool
    {
        return mt_rand(0, 1) !== 0;
    }
}
