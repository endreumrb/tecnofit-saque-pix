<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Contract;

use InvalidArgumentException;

interface WithdrawMethodInterface
{
    /**
     * @throws InvalidArgumentException
     */
    public function validate(array $data): void;

    public function getMethodName(): string;
}
