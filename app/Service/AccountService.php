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

use App\Exception\AccountNotFoundException;
use App\Exception\InsufficientBalanceException;
use App\Model\Account;
use App\Model\AccountWithdraw;
use App\Model\AccountWithdrawPix;
use Carbon\Carbon;
use Hyperf\DbConnection\Db;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class AccountService
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function withdraw(
        string $accountId,
        string $method,
        array $pix,
        float $amount,
        ?string $schedule
    ): array {
        return $this->withdrawNow($accountId, $method, $pix, $amount);
    }

    private function withdrawNow(
        string $accountId,
        string $method,
        array $pix,
        float $amount
    ): array {
        $this->logger->info('Processando saque imediato', [
            'account_id' => $accountId,
            'amount' => $amount,
            'method' => $method,
            'pix_type' => $pix['type'],
        ]);

        return Db::transaction(function () use ($accountId, $method, $pix, $amount) {
            $account = Account::query()
                ->where('id', $accountId)
                ->lockForUpdate()
                ->first();

            if (! $account) {
                $this->logger->warning('Conta não encontrada', [
                    'account_id' => $accountId,
                ]);
                throw new AccountNotFoundException('Conta não encontrada');
            }

            if ($account->balance < $amount) {
                $this->logger->warning('Saldo insuficiente', [
                    'account_id' => $accountId,
                    'balance' => $account->balance,
                    'requested_amount' => $amount,
                ]);
                throw new InsufficientBalanceException('Saldo insuficiente');
            }

            $withdrawId = Uuid::uuid4()->toString();
            $withdraw = $this->createWithdraw(
                id: $withdrawId,
                accountId: $account->id,
                method: $method,
                amount: $amount,
                scheduled: false,
                scheduledFor: null,
                done: true
            );

            $this->createWithdrawPix(
                withdrawId: $withdrawId,
                type: $pix['type'],
                key: $pix['key']
            );

            $oldBalance = $account->balance;
            $account->balance -= $amount;
            $account->save();

            $processedAt = Carbon::now()->toDateTimeString();

            $this->logger->info('Saque concluído com sucesso', [
                'withdraw_id' => $withdraw->id,
                'account_id' => $accountId,
                'amount' => $amount,
                'old_balance' => $oldBalance,
                'new_balance' => $account->balance,
                'processed_at' => $processedAt,
            ]);

            return [
                'status' => 'processado',
                'id_saque' => $withdraw->id,
                'id_conta' => $account->id,
                'saldo' => $account->balance,
                'valor' => $amount,
                'processado_em' => $processedAt,
            ];
        });
    }

    private function createWithdraw(
        string $id,
        string $accountId,
        string $method,
        float $amount,
        bool $scheduled,
        ?string $scheduledFor,
        bool $done
    ): AccountWithdraw {
        return AccountWithdraw::create([
            'id' => $id,
            'account_id' => $accountId,
            'method' => $method,
            'amount' => $amount,
            'scheduled' => $scheduled,
            'scheduled_for' => $scheduledFor,
            'done' => $done,
            'error' => false,
            'error_reason' => null,
        ]);
    }

    private function createWithdrawPix(
        string $withdrawId,
        string $type,
        string $key
    ): AccountWithdrawPix {
        return AccountWithdrawPix::create([
            'account_withdraw_id' => $withdrawId,
            'type' => $type,
            'key' => $key,
        ]);
    }
}
