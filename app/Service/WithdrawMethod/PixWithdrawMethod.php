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

namespace App\Service\WithdrawMethod;

use App\Contract\WithdrawMethodInterface;
use InvalidArgumentException;

class PixWithdrawMethod implements WithdrawMethodInterface
{
    private const VALID_TYPES = ['email'];

    public function validate(array $data): void
    {
        if (! isset($data['type'], $data['key'])) {
            throw new InvalidArgumentException('PIX requer campos "type" e "key"');
        }

        if (! in_array($data['type'], self::VALID_TYPES, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Tipo de PIX inválido. Tipos suportados: %s',
                    implode(', ', self::VALID_TYPES)
                )
            );
        }

        $this->validateByType($data['type'], $data['key']);
    }

    public function getMethodName(): string
    {
        return 'PIX';
    }

    private function validateByType(string $type, string $key): void
    {
        if ($type === 'email' && ! filter_var($key, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Formato de email inválido para chave PIX');
        }
    }
}
