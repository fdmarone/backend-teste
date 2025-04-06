<?php

namespace App\Exceptions;

use Throwable;
use Carbon\Carbon;
use App\Traits\Logger;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Responses\InternalError;
use App\Http\Responses\DefaultResponse;
use Illuminate\Validation\ValidationException;
use App\Exceptions\BaseException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class Handler extends ExceptionHandler
{
    use Logger;

    /**
     * Lista de exceções que não devem ser reportadas.
     */
    protected $dontReport = [];

    /**
     * Campos que nunca devem ser incluídos nos erros de validação.
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Reporta ou registra uma exceção.
     */
    public function report(Throwable $exception)
    {
        // Evita logar exceções de política
        if ($exception instanceof PolicyException) {
            return;
        }

        parent::report($exception);
    }

    /**
     * Renderiza uma exceção em uma resposta HTTP JSON padronizada.
     */
    public function render($request, Throwable $exception)
    {
        // Exceção de rota não encontrada (404)
        if ($exception instanceof NotFoundHttpException) {
            return $this->jsonError(404, []);
        }

        // Exceções de domínio conhecidas (BaseException e suas filhas)
        if (
            $exception instanceof BaseException &&
            get_class($exception) !== BaseException::class
        ) {
            return parent::render($request, $exception);
        }

        // Exceção de validação (422), resposta padrão do Laravel
        if ($exception instanceof ValidationException) {
            return parent::render($request, $exception);
        }

        // Exceção de autenticação (401)
        if ($exception instanceof UnauthorizedHttpException) {
            return $this->jsonError(401, [
                new InternalError('UNAUTHORIZED')
            ]);
        }

        // Loga contexto completo da requisição para rastreabilidade
        $this->logWithContext($request, $exception);

        // Em modo debug, exibe o erro diretamente
        if (config('app.debug')) {
            dd($exception);
        }

        // Retorno padronizado para erros inesperados (500)
        return $this->jsonError(500, [
            new InternalError('INTERNAL_SERVER_ERROR')
        ]);
    }

    /**
     * Converte um erro de validação em resposta JSON padronizada.
     */
    protected function invalidJson($request, ValidationException $exception): JsonResponse
    {
        $errors = [];

        foreach ($exception->errors() as $fieldErrors) {
            foreach ($fieldErrors as $error) {
                $errors[] = new InternalError($error);
            }
        }

        return $this->jsonError(422, $errors);
    }

    /**
     * Gera uma resposta JSON com o modelo de erro padronizado.
     */
    private function jsonError(int $code, array $errors): JsonResponse
    {
        $response = new DefaultResponse(
            null,
            false,
            $errors,
            $code
        );

        return response()->json($response->toArray(), $code);
    }

    /**
     * Cria log detalhado com contexto da requisição.
     */
    private function logWithContext(Request $request, Throwable $exception): void
    {
        $route = $request->route();

        // Ignora se a rota não estiver disponível
        if (is_null($route)) return;

        $context = [
            'endpoint' => [
                'method' => $route->methods[0] ?? 'UNKNOWN',
                'url'    => $request->url(),
            ],
            'request' => [
                'query' => $request->query(),
                'body'  => $request->post(),
            ],
            'request_time' => Carbon::now()->setTimezone(config('app.timezone')),
        ];

        $this->createLog(
            'Erro na execução da API ' . ($route->methods[0] ?? '-') . ' ' . $route->uri,
            'REQUEST_' . strtoupper(($route->methods[0] ?? '-') . '_' . $this->applyUuidRegexPattern($route->uri)) . '_ERROR',
            $context,
            $exception
        );
    }

    /**
     * Substitui UUIDs por identificador genérico para padronização dos logs.
     */
    protected function applyUuidRegexPattern(string $path): string
    {
        $pattern = '/[A-Fa-f0-9]{8}-?[A-Fa-f0-9]{4}-?[A-Fa-f0-9]{4}-?[A-Fa-f0-9]{4}-?[A-Fa-f0-9]{12}/';
        return str_replace(['/', '-'], '_', preg_replace($pattern, 'UUID', $path));
    }
}
