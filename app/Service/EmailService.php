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

namespace App\Service;

use FriendsOfHyperf\Mail\Facade\Mail;

class EmailService
{
    public function sendWithdrawNotification(
        string $email,
        string $withdrawId,
        float $amount,
        string $pixType,
        string $pixKey,
        string $processedAt
    ): void {
        $body = $this->buildEmailBody($withdrawId, $amount, $pixType, $pixKey, $processedAt);

        Mail::raw($body, function ($message) use ($email) {
            $message->to($email)
                ->subject('Saque PIX Realizado - Tecnofit');
        });
    }

    private function buildEmailBody(
        string $withdrawId,
        float $amount,
        string $pixType,
        string $pixKey,
        string $processedAt
    ): string {
        return "
Olá,

Seu saque PIX foi processado com sucesso!

Detalhes da transação:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
ID da Transação: {$withdrawId}
Valor: R$ " . number_format($amount, 2, ',', '.') . "
Data/Hora: {$processedAt}

Dados PIX:
Tipo: {$pixType}
Chave: {$pixKey}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Atenciosamente,
Equipe Tecnofit
        ";
    }
}
