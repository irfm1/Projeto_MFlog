<?php

namespace App\Services;

use App\DTOs\LogItemDTO;
use App\Models\CaixaLog;
use App\Models\CancelamentoLog;
use App\Models\SystemLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * LogEnricherService
 * 
 * Transforma logs brutos do Firebird em LogItemDTO enriquecidos com:
 * - Dados de relacionamentos (usuários, clientes, etc)
 * - Parsing de descrições livre
 * - Formatação humanizada
 * - Gravidade e categorização
 */
class LogEnricherService
{
    public function __construct(
        private LogParserService $parserService,
    ) {}

    /**
     * Enriquece um SystemLog em LogItemDTO
     * 
     * Fonte: USUARIOS_LOG (auditoria de ações do sistema)
     * Exemplo: "João alterou Ficha do Profissional"
     */
    public function enrichSystemLog(SystemLog $log): LogItemDTO
    {
        $user = $log->user;
        $parsedData = $this->parserService->parse($log->NOME_MOVIMENTADO ?? '');

        // Determina severidade baseado no tipo de operação
        $severity = match ($log->TIPO_OPERACAO) {
            'EXCLUIR' => 'danger',
            'ADICIONAR' => 'success',
            'SALVAR' => 'info',
            'VISUALIZAR' => 'info',
            default => 'warning',
        };

        $relatedName = $this->resolveSystemLogRelatedName($log);

        return new LogItemDTO(
            id: $log->CODIGO,
            type: 'system',
            actorName: $user?->NOME ?? 'Sistema',
            actorAvatar: $this->getUserAvatar($user),
            action: $this->formatAction($log->TIPO_OPERACAO, $log->MODULO),
            relatedRecordName: $relatedName,
            relatedRecordUrl: $this->buildSystemLogUrl($log),
            module: $log->MODULO,
            moduleIcon: $this->getModuleIcon($log->MODULO),
            operationType: $log->TIPO_OPERACAO,
            value: null,
            valueCurrency: null,
            tags: [
                $log->NOME_PC ?? 'N/A',
                $log->MODULO,
            ],
            timestamp: $this->combineDateTime($log->DATA, $log->HORA),
            humanReadableTime: $this->combineDateTime($log->DATA, $log->HORA)->diffForHumans(),
            severity: $severity,
        );
    }

    /**
     * Enriquece um CaixaLog em LogItemDTO
     * 
     * Fonte: LOG_CAIXA (transações financeiras)
     * Exemplo: "Maria realizou venda nº 89340 - R$ 500,00"
     */
    public function enrichCaixaLog(CaixaLog $log): LogItemDTO
    {
        $user = $log->user;
        $saleNumber = $this->parserService->extractSaleNumber($log->DESCRICAO ?? '');
        $operationType = $this->parserService->classifyCaixaOperationType($log->DESCRICAO ?? '');

        // Severidade: valores positivos = sucesso, negativos = aviso
        $severity = match (true) {
            $log->VALOR_MOVIMENTADO > 0 => 'success',
            $log->VALOR_MOVIMENTADO < 0 => 'warning',
            default => 'info',
        };

        return new LogItemDTO(
            id: $log->CODIGO,
            type: 'caixa',
            actorName: $user?->NOME ?? 'Sistema',
            actorAvatar: $this->getUserAvatar($user),
            action: 'realizou ' . Str::lower($operationType),
            relatedRecordName: $saleNumber ? "Venda #$saleNumber" : 'Transação',
            relatedRecordUrl: $saleNumber ? "/vendas/$saleNumber" : '',
            module: 'Caixa',
            moduleIcon: 'wallet-2',
            operationType: $operationType,
            value: abs($log->VALOR_MOVIMENTADO),
            valueCurrency: 'BRL',
            tags: [
                $log->NOME_PC ?? 'N/A',
                'Caixa ' . ($log->COD_CAIXA ?? 'N/A'),
            ],
            timestamp: $log->DATA_HORA,
            humanReadableTime: $log->DATA_HORA->diffForHumans(),
            severity: $severity,
        );
    }

    /**
     * Enriquece um CancelamentoLog em LogItemDTO
     * 
     * Fonte: LOG_CANCELAMENTO (cancelamentos críticos)
     * Exemplo: "João cancelou serviço - Dano no serviço - R$ 250,00"
     */
    public function enrichCancelamentoLog(CancelamentoLog $log): LogItemDTO
    {
        $user = $log->user;
        $client = $log->client;
        $tipoLabel = $log->TIPO_CANCELAMENTO === 'Serviço' ? 'Serviço' : 'Produto';

        return new LogItemDTO(
            id: $log->CODIGO,
            type: 'cancelamento',
            actorName: $user?->NOME ?? 'Sistema',
            actorAvatar: $this->getUserAvatar($user),
            action: "cancelou $tipoLabel",
            relatedRecordName: $client?->NOME ?? 'Cliente #' . $log->COD_CLIENTE,
            relatedRecordUrl: "/clientes/{$log->COD_CLIENTE}",
            module: 'Cancelamentos',
            moduleIcon: 'x-circle',
            operationType: $log->TIPO_CANCELAMENTO,
            value: $log->VALOR,
            valueCurrency: 'BRL',
            tags: [
                $tipoLabel,
                $log->MOTIVO ?? 'Sem motivo',
            ],
            timestamp: Carbon::parse($log->DATA_CANCELAMENTO),
            humanReadableTime: Carbon::parse($log->DATA_CANCELAMENTO)->diffForHumans(),
            severity: 'danger', // Sempre crítico
        );
    }

    /**
     * Resolve nome do registro relacionado para SystemLog
     */
    private function resolveSystemLogRelatedName(SystemLog $log): string
    {
        // Tenta carregar relacionamento dependendo do módulo
        if ($log->relationLoaded('professional') && $log->professional) {
            return $log->professional->NOME ?? $log->NOME_MOVIMENTADO;
        }

        if ($log->relationLoaded('client') && $log->client) {
            return $log->client->NOME ?? $log->NOME_MOVIMENTADO;
        }

        return $log->NOME_MOVIMENTADO ?? 'Registro alterado';
    }

    /**
     * Constrói URL para SystemLog
     */
    private function buildSystemLogUrl(SystemLog $log): string
    {
        // Mapeamento de módulos para rotas
        $routes = [
            'Ficha do Profissional' => '/profissionais',
            'Cadastro de Cliente' => '/clientes',
            'Caixa' => '/caixa',
            'Agendamento' => '/agendamentos',
        ];

        $route = $routes[$log->MODULO] ?? '/';

        if ($log->CODIGO_MOVIMENTADO && is_numeric($log->CODIGO_MOVIMENTADO)) {
            return "{$route}/{$log->CODIGO_MOVIMENTADO}";
        }

        return $route;
    }

    /**
     * Formata ação em português humanizado
     */
    private function formatAction(string $operation, string $module): string
    {
        $verb = match ($operation) {
            'ADICIONAR' => 'adicionou',
            'SALVAR' => 'salvou',
            'VISUALIZAR' => 'visualizou',
            'EXCLUIR' => 'excluiu',
            'ABRIR_MODULO' => 'abriu módulo',
            default => 'alterou',
        };

        // Extrai sigla do módulo
        $moduleName = strtolower(substr($module, 0, 20));

        return "$verb $moduleName";
    }

    /**
     * Obtém ícone baseado no módulo
     */
    private function getModuleIcon(string $module): string
    {
        return match ($module) {
            'Ficha do Profissional' => 'user-circle',
            'Cadastro de Cliente' => 'users',
            'Agendamento' => 'calendar',
            'Caixa' => 'wallet-2',
            'Configurações' => 'settings',
            'Produtos' => 'package',
            'Serviços' => 'briefcase',
            'Relatórios' => 'bar-chart',
            default => 'file-text',
        };
    }

    /**
     * Obtém URL do avatar do usuário
     */
    private function getUserAvatar($user): string
    {
        if ($user && isset($user->avatar_url)) {
            return $user->avatar_url;
        }

        // Avatar padrão baseado em inicial do nome
        if ($user && isset($user->NOME)) {
            $initial = strtoupper(substr($user->NOME, 0, 1));
            return "https://ui-avatars.com/api/?name={$initial}&background=random";
        }

        return 'https://ui-avatars.com/api/?name=S&background=999';
    }

    /**
     * Combina DATA e HORA em Carbon
     */
    private function combineDateTime(?string $date, ?string $time): Carbon
    {
        if (!$date && !$time) {
            return Carbon::now();
        }

        $dateStr = $date ?? Carbon::now()->format('Y-m-d');
        $timeStr = $time ?? '00:00:00';

        try {
            return Carbon::createFromFormat('Y-m-d H:i:s', "$dateStr $timeStr");
        } catch (\Exception $e) {
            return Carbon::now();
        }
    }
}
