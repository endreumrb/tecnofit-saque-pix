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

namespace App\Controller;

use App\Service\AccountService;
use App\Service\WithdrawMethod\PixWithdrawMethod;
use Hyperf\Di\Annotation\Inject;
use InvalidArgumentException;

class AccountController extends AbstractController
{
    #[Inject]
    protected AccountService $service;

    public function withdraw(string $accountId): array
    {
        $method = $this->request->input('method');
        $pix = $this->request->input('pix');
        $amount = $this->request->input('amount');
        $schedule = $this->request->input('schedule');

        $this->validate($method, $pix, $amount, $schedule);

        return $this->service->withdraw(
            accountId: $accountId,
            method: $method,
            pix: $pix,
            amount: (float) $amount,
            schedule: $schedule
        );
    }

    private function validate(
        mixed $method,
        mixed $pix,
        mixed $amount,
        mixed $schedule
    ): void {
        if (empty($method)) {
            throw new InvalidArgumentException('Campo "method" é obrigatório');
        }

        if (empty($pix) || ! is_array($pix)) {
            throw new InvalidArgumentException('Campo "pix" é obrigatório');
        }

        if (empty($amount) || ! is_numeric($amount)) {
            throw new InvalidArgumentException('Campo "amount" é obrigatório e deve ser numérico');
        }

        if ((float) $amount <= 0) {
            throw new InvalidArgumentException('Valor deve ser maior que zero');
        }

        if ($schedule !== null && ! strtotime($schedule)) {
            throw new InvalidArgumentException('Formato de agendamento inválido');
        }

        if ($schedule !== null && strtotime($schedule) < time()) {
            throw new InvalidArgumentException('Não é possível agendar saque no passado');
        }

        $withdrawMethod = match ($method) {
            'PIX' => new PixWithdrawMethod(),
            default => throw new InvalidArgumentException('Método de saque não suportado'),
        };

        $withdrawMethod->validate($pix);
    }
}
