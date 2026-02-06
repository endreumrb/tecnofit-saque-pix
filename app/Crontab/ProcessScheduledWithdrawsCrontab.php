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
use Hyperf\Redis\Redis;
use Psr\Log\LoggerInterface;
use Throwable;

#[Crontab(
    name: 'ProcessScheduledWithdraws',
    rule: '* * * * *',
    callback: 'execute',
    memo: 'Processa saques agendados a cada minuto'
)]
class ProcessScheduledWithdrawsCrontab
{
    private const LOCK_TTL = 60;
    
    public function __construct(
        private readonly EmailService $emailService,
        private readonly LoggerInterface $logger,
        private readonly Redis $redis
    ) {}

    public function execute(): void
    {
        $globalLock = 'cron:scheduled_withdraws:lock';
        
        $acquired = $this->redis->set(
            $globalLock, 
            getmypid(),
            ['NX', 'EX' => self::LOCK_TTL]
        );
        
        if (!$acquired) {
            $this->logger->info('Processamento de saques agendados já em execução em outra instância', [
                'lock_holder' => $this->redis->get($globalLock),
                'current_pid' => getmypid(),
            ]);
            return;
        }

        try {
            $this->processScheduledWithdraws();
        } finally {
            $this->redis->del($globalLock);
        }
    }

    private function processScheduledWithdraws(): void
    {
        $this->logger->info('Iniciando processamento de saques agendados', [
            'pid' => getmypid(),
        ]);

        $startTime = microtime(true);

        $withdraws = AccountWithdraw::query()
            ->where('scheduled', true)
            ->where('done', false)
            ->where('scheduled_for', '<=', Carbon::now())
            ->get();

        if ($withdraws->isEmpty()) {
            $this->logger->info('Nenhum saque agendado para processar');
            return;
        }

        $pendingCount = $withdraws->count();
        $processed = 0;
        $errors = 0;

        $this->logger->info('Saques agendados encontrados', [
            'total' => $pendingCount,
        ]);

        foreach ($withdraws as $withdraw) {
            try {
                $this->processWithdraw($withdraw);
                ++$processed;

                $this->logger->info('Saque agendado processado com sucesso', [
                    'withdraw_id' => $withdraw->id,
                    'account_id' => $withdraw->account_id,
                    'amount' => $withdraw->amount,
                ]);
            } catch (Throwable $e) {
                ++$errors;

                $this->logger->error('Erro ao processar saque agendado', [
                    'withdraw_id' => $withdraw->id,
                    'account_id' => $withdraw->account_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $duration = round(microtime(true) - $startTime, 2);

        $this->logger->info('Processamento de saques agendados concluído', [
            'total' => $pendingCount,
            'processados' => $processed,
            'erros' => $errors,
            'taxa_sucesso' => $pendingCount > 0 ? round(($processed / $pendingCount) * 100, 2) : 0,
            'duracao_segundos' => $duration,
        ]);
    }

    private function processWithdraw(AccountWithdraw $withdraw): void
    {
        Db::transaction(function () use ($withdraw) {
            $account = Account::query()
                ->where('id', $withdraw->account_id)
                ->lockForUpdate()
                ->first();

            if (! $account) {
                $withdraw->done = true;
                $withdraw->error = true;
                $withdraw->error_reason = 'Conta não encontrada';
                $withdraw->save();

                $this->logger->warning('Conta não encontrada durante processamento', [
                    'withdraw_id' => $withdraw->id,
                    'account_id' => $withdraw->account_id,
                ]);

                return;
            }

            if ($account->balance < $withdraw->amount) {
                $withdraw->done = true;
                $withdraw->error = true;
                $withdraw->error_reason = 'Saldo insuficiente';
                $withdraw->save();

                $this->logger->warning('Saldo insuficiente para saque agendado', [
                    'withdraw_id' => $withdraw->id,
                    'account_id' => $withdraw->account_id,
                    'balance' => $account->balance,
                    'requested_amount' => $withdraw->amount,
                ]);

                return;
            }

            $oldBalance = $account->balance;
            $account->balance -= $withdraw->amount;
            $account->save();

            $withdraw->done = true;
            $withdraw->error = false;
            $withdraw->save();

            $this->logger->debug('Saldo atualizado para saque agendado', [
                'withdraw_id' => $withdraw->id,
                'account_id' => $withdraw->account_id,
                'old_balance' => $oldBalance,
                'new_balance' => $account->balance,
                'amount' => $withdraw->amount,
            ]);

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

                $this->logger->debug('Email de notificação enviado', [
                    'withdraw_id' => $withdraw->id,
                    'recipient' => $pixData->key,
                ]);
            }
        });
    }
}
