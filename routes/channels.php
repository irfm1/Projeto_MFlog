<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The required channels may be returned from here
| by listening to the BroadcastChannelCreated event that is fired when
| a user authenticates their connection to a private or presence channel.
|
*/

/**
 * Canais públicos - Sem autenticação
 */

// Todos podem se conectar a logs gerais
Broadcast::channel('logs', function () {
    return true;
});

// Todos podem monitorar auditoria do sistema
Broadcast::channel('logs.system', function () {
    return true;
});

// Todos podem monitorar transações de caixa
Broadcast::channel('logs.caixa', function () {
    return true;
});

// Todos podem monitorar caixa por tipo de operação
Broadcast::channel('logs.caixa.{type}', function () {
    return true;
});

// Todos podem monitorar cancelamentos (críticos)
Broadcast::channel('logs.cancelamento', function () {
    return true;
});

// Todos podem monitorar logs críticos
Broadcast::channel('logs.critico', function () {
    return true;
});

// Canais financeiros - Monitoramento geral de transações
Broadcast::channel('logs.financeiro', function () {
    return true;
});

/**
 * Canais privados - Requer autenticação
 */

// Usuário específico monitora seus próprios logs
Broadcast::channel('user.{userId}.logs', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Gestor monitora logs de um módulo específico
Broadcast::channel('module.{module}.logs', function ($user, $module) {
    // Pode ser restrito por permissões futuras
    return true;
});

// Canais para operações críticas requerem permissão elevada
Broadcast::channel('logs.critico.private', function ($user) {
    // Apenas users com permissão 'view-critical-logs'
    return $user->can('view-critical-logs') ?? true; // TODO: Implementar permissões
});

// Dashboard executivo - Resumo de tudo
Broadcast::channel('dashboard.executive', function ($user) {
    // Apenas executivos
    return true; // TODO: Validar role
});
