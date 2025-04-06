<?php

namespace App\Traits;

use Throwable;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Str;
use App\Exceptions\BaseException;
use Illuminate\Support\Facades\Log;

trait Logger
{
    /**
     * Formata a exceção para exibição mais legível
     */
    protected static function beautifyException(Throwable $e): array
    {
        return [
            'msg'  => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile(),
        ];
    }

    /**
     * Cria um log padrão formatado
     */
    public function createLog(
        string $description,
        string $action,
        $value,
        Throwable $error = null,
        string $idUser = null,
        string $idCompany = null,
        string $entityId = null,
        string $entity = null,
        string $logLevel = 'debug',
        string $logType = 'server',
        Carbon $requestDatetime = null,
        Carbon $responseDatetime = null
    ): array {
        try {
            $uuid     = Uuid::uuid4()->toString();
            $logLevel = strtolower($logLevel);

            // Garantia de consistência de tipo
            $value = is_array($value) ? $value : [$value];

            // Adiciona detalhes da exception, se houver
            if ($error) {
                $value['error'] = self::beautifyException($error);
            }

            $requestDuration = null;
            if ($requestDatetime && $responseDatetime) {
                $requestDuration = $requestDatetime->diffInMilliseconds($responseDatetime);
            }

            $user = $this->getUserFromJwt();

            $context = [
                'uuid'                          => $uuid,
                'description'                   => $description,
                'action'                        => $action,
                'log_type'                      => strtoupper($logType),
                'log_level'                     => strtoupper($logLevel),
                'entity'                        => strtoupper($entity),
                'entity_id'                     => $entityId,
                'user_id'                       => $idUser ?? data_get($user, 'user_id'),
                'company_id'                    => $idCompany ?? data_get($user, 'company_id'),
                'admin_user_id'                 => data_get($user, 'admin_user_id'),
                'origin'                        => request()->headers->get('origin'),
                'referer'                       => request()->headers->get('referer'),
                'url'                           => request()->url(),
                'ip'                            => request()->ip(),
                'endpoint'                      => [
                    'url'    => request()->url(),
                    'method' => request()->getMethod(),
                ],
                'date'                          => now()->toISOString(),
                'request_datetime'              => $requestDatetime?->toISOString(),
                'response_datetime'             => $responseDatetime?->toISOString(),
                'request_duration_milliseconds' => $requestDuration,
                'value'                         => $value,
            ];

            Log::channel('log_service')->{$logLevel}($description, $context);

            return [
                'success' => true,
                'message' => 'Log feito com sucesso',
                'uuid'    => $uuid,
                'data'    => $context,
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => 'Falha ao enviar Log',
                'data'    => self::beautifyException($e),
            ];
        }
    }

    /**
     * Tratamento padrão de erros internos
     */
    public function defaultErrorHandling(
        Throwable $exception,
        $data = null,
        string $idEntity = null,
        string $entity = null,
        string $level = 'error'
    ): void {
        if ($exception instanceof BaseException) {
            throw $exception;
        }

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $trace[1]['function'] ?? 'unknown_method';

        $this->createLog(
            description: get_called_class(),
            action: strtoupper(Str::snake($caller)) . '_ERROR',
            value: $data,
            error: $exception,
            entityId: $idEntity,
            entity: $entity,
            logLevel: $level
        );
        dump(get_class($exception), $exception->getMessage());
        // Lançando erro genérico controlado
        throw new BaseException(
            'UNKNOW_ERROR_TRY_AGAIN',
            0
        );
    }
}
