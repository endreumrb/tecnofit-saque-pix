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

namespace App\Crontab;

use App\Model\Account;
use App\Model\AccountWithdraw;
use App\Model\AccountWithdrawPix;
use App\Service\EmailService;
use Carbon\Carbon;
use Hyperf\Crontab\Annotation\Crontab;
use Hyperf\DbConnection\Db;
use Psr\Log\LoggerInterface;
use Throwable;

#[Crontab(
    name: 'ProcessScheduledWithdraws',
    rule: '* * * * *',
    callback: 'execute',
    memo: 'Process scheduled withdraws every minute'
)]
class ProcessScheduledWithdrawsCrontab
{
    /**
     * Injeção de dependências via Constructor Property Promotion.
     *
     * O Hyperf resolve automaticamente as dependências.
     * Usa PSR-3 LoggerInterface (padrão) ao invés de StdoutLoggerInterface.
     */
    public function __construct(
        private readonly EmailService $emailService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        $this->logger->info('Starting scheduled withdrawals processing');

        $startTime = microtime(true);

        // Busca saques agendados pendentes
        $withdraws = AccountWithdraw::query()
            ->where('scheduled', true)
            ->where('done', false)
            ->where('scheduled_for', '<=', Carbon::now())
            ->get();

        if ($withdraws->isEmpty()) {
            $this->logger->info('No scheduled withdrawals to process');
            return;
        }

        $pendingCount = $withdraws->count();
        $processed = 0;
        $errors = 0;

        $this->logger->info('Found scheduled withdrawals to process', [
            'total' => $pendingCount,
        ]);

        foreach ($withdraws as $withdraw) {
            try {
                $this->processWithdraw($withdraw);
                ++$processed;

                $this->logger->info('Scheduled withdrawal processed successfully', [
                    'withdraw_id' => $withdraw->id,
                    'account_id' => $withdraw->account_id,
                    'amount' => $withdraw->amount,
                ]);
            } catch (Throwable $e) {
                ++$errors;

                $this->logger->error('Error processing scheduled withdrawal', [
                    'withdraw_id' => $withdraw->id,
                    'account_id' => $withdraw->account_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $duration = round(microtime(true) - $startTime, 2);

        $this->logger->info('Scheduled withdrawals processing completed', [
            'total' => $pendingCount,
            'processed' => $processed,
            'errors' => $errors,
            'success_rate' => $pendingCount > 0 ? round(($processed / $pendingCount) * 100, 2) : 0,
            'duration_seconds' => $duration,
        ]);
    }

    /**
     * Processa um saque agendado individual.
     *
     * Usa lock pessimista para garantir consistência mesmo
     * em ambiente com múltiplas instâncias do cron.
     */
    private function processWithdraw(AccountWithdraw $withdraw): void
    {
        Db::transaction(function () use ($withdraw) {
            // 1. Busca conta com lock
            $account = Account::query()
                ->where('id', $withdraw->account_id)
                ->lockForUpdate()
                ->first();

            if (! $account) {
                $withdraw->done = true;
                $withdraw->error = true;
                $withdraw->error_reason = 'Account not found';
                $withdraw->save();

                $this->logger->warning('Account not found during scheduled withdrawal', [
                    'withdraw_id' => $withdraw->id,
                    'account_id' => $withdraw->account_id,
                ]);

                return;
            }

            // 2. Valida saldo
            if ($account->balance < $withdraw->amount) {
                $withdraw->done = true;
                $withdraw->error = true;
                $withdraw->error_reason = 'Insufficient balance';
                $withdraw->save();

                $this->logger->warning('Insufficient balance for scheduled withdrawal', [
                    'withdraw_id' => $withdraw->id,
                    'account_id' => $withdraw->account_id,
                    'balance' => $account->balance,
                    'requested_amount' => $withdraw->amount,
                ]);

                return;
            }

            // 3. Deduz saldo
            $oldBalance = $account->balance;
            $account->balance -= $withdraw->amount;
            $account->save();

            // 4. Marca como processado
            $withdraw->done = true;
            $withdraw->error = false;
            $withdraw->save();

            $this->logger->debug('Balance updated for scheduled withdrawal', [
                'withdraw_id' => $withdraw->id,
                'account_id' => $withdraw->account_id,
                'old_balance' => $oldBalance,
                'new_balance' => $account->balance,
                'amount' => $withdraw->amount,
            ]);

            // 5. Busca dados PIX e envia email
            $pixData = AccountWithdrawPix::query()
                ->where('account_withdraw_id', $withdraw->id)
                ->first();

            if ($pixData && $pixData->type === 'email') {
                $this->emailService->sendWithdrawNotification(
                    email: $pixData->key,
                    withdrawId: $withdraw->id,
                    amount: $withdraw->amount,
                    pixType: $pixData->type,
                    pixKey: $pixData->key,
                    processedAt: Carbon::now()->toDateTimeString()
                );

                $this->logger->debug('Email notification sent for scheduled withdrawal', [
                    'withdraw_id' => $withdraw->id,
                    'recipient' => $pixData->key,
                ]);
            }
        });
    }
}
