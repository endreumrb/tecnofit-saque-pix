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
use Hyperf\Swagger\Annotation as SA;
use InvalidArgumentException;

#[SA\HyperfServer('http')]
class AccountController extends AbstractController
{
    #[Inject]
    protected AccountService $service;

    #[SA\Post(
        path: '/account/{accountId}/balance/withdraw',
        summary: 'Realizar saque PIX',
        tags: ['Saques']
    )]
    #[SA\Parameter(
        name: 'accountId',
        in: 'path',
        required: true,
        description: 'ID da conta',
        schema: new SA\Schema(
            type: 'string',
            format: 'uuid',
            example: '123e4567-e89b-12d3-a456-426614174000'
        )
    )]
    #[SA\RequestBody(
        required: true,
        content: new SA\JsonContent(
            required: ['method', 'pix', 'amount'],
            properties: [
                new SA\Property(
                    property: 'method',
                    type: 'string',
                    enum: ['PIX'],
                    example: 'PIX'
                ),
                new SA\Property(
                    property: 'pix',
                    type: 'object',
                    required: ['type', 'key'],
                    properties: [
                        new SA\Property(
                            property: 'type',
                            type: 'string',
                            enum: ['email'],
                            example: 'email'
                        ),
                        new SA\Property(
                            property: 'key',
                            type: 'string',
                            format: 'email',
                            example: 'usuario@email.com'
                        ),
                    ]
                ),
                new SA\Property(
                    property: 'amount',
                    type: 'number',
                    format: 'double',
                    minimum: 0.01,
                    example: 150.75
                ),
                new SA\Property(
                    property: 'schedule',
                    type: 'string',
                    format: 'date-time',
                    nullable: true,
                    example: '2026-02-10 15:00:00'
                ),
            ]
        )
    )]
    #[SA\Response(
        response: 200,
        description: 'Saque processado com sucesso'
    )]
    #[SA\Response(
        response: 404,
        description: 'Conta não encontrada'
    )]
    #[SA\Response(
        response: 422,
        description: 'Saldo insuficiente ou dados inválidos'
    )]
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