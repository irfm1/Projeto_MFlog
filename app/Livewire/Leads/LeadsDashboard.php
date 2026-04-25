<?php

namespace App\Livewire\Leads;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class LeadsDashboard extends Component
{
    public ?int $selectedClienteId = null;
    public ?array $selectedClienteDetails = null;
    public ?string $firebirdWarning = null;

    public function mount(): void
    {
        $candidates = $this->getLeadCandidates();
        if (!empty($candidates)) {
            $this->selectedClienteId = (int) $candidates[0]['cliente_id'];
            $this->selectCliente();
        }
    }

    public function selectCliente(): void
    {
        if (!$this->selectedClienteId) {
            $this->selectedClienteDetails = null;
            return;
        }

        $cliente = DB::table('dim_cliente')
            ->where('cliente_id', $this->selectedClienteId)
            ->first([
                'cliente_id',
                'codigo_cliente',
                'nome',
                'telefone',
                'email',
                'cidade',
            ]);

        if (!$cliente) {
            $this->selectedClienteDetails = null;
            return;
        }

        $cutoffKey = (int) Carbon::today()->subMonths(3)->format('Ymd');
        $stats3m = DB::table('fato_logs_financeiro')
            ->where('tipo_movimento', 'CAIXA')
            ->where('cliente_id', (int) $cliente->cliente_id)
            ->where('data_key', '>=', $cutoffKey)
            ->selectRaw('COUNT(*) as visitas, COALESCE(SUM(valor_movimento), 0) as total_gasto, MAX(data_key) as ultima_data')
            ->first();

        $phone = $this->normalizeText($cliente->telefone ?? null);
        if ($phone === '' && !empty($cliente->codigo_cliente)) {
            $phone = $this->fetchPhoneFromFirebird((int) $cliente->codigo_cliente) ?? '';

            if ($phone !== '') {
                DB::table('dim_cliente')
                    ->where('cliente_id', (int) $cliente->cliente_id)
                    ->update([
                        'telefone' => mb_substr($phone, 0, 20),
                        'updated_at' => now(),
                    ]);
            }
        }

        $this->selectedClienteDetails = [
            'cliente_id' => (int) $cliente->cliente_id,
            'codigo_cliente' => (int) ($cliente->codigo_cliente ?? 0),
            'nome' => $this->normalizeText($cliente->nome ?? 'SEM INFORMAÇÃO'),
            'telefone' => $phone,
            'email' => $this->normalizeText($cliente->email ?? ''),
            'cidade' => $this->normalizeText($cliente->cidade ?? ''),
            'visitas_3m' => (int) ($stats3m->visitas ?? 0),
            'gasto_3m' => (float) ($stats3m->total_gasto ?? 0),
            'ultima_data' => (int) ($stats3m->ultima_data ?? 0),
            'phone_missing' => $phone === '',
        ];
    }

    private function getTopAssiduas(int $limit = 10): array
    {
        $cutoffKey = (int) Carbon::today()->subMonths(3)->format('Ymd');

        return DB::table('fato_logs_financeiro as f')
            ->join('dim_cliente as c', 'c.cliente_id', '=', 'f.cliente_id')
            ->where('f.tipo_movimento', 'CAIXA')
            ->whereNotNull('f.cliente_id')
            ->where('f.data_key', '>=', $cutoffKey)
            ->groupBy('c.cliente_id', 'c.codigo_cliente', 'c.nome', 'c.telefone', 'c.email')
            ->orderByRaw('COUNT(*) DESC')
            ->orderByRaw('COALESCE(SUM(f.valor_movimento), 0) DESC')
            ->limit($limit)
            ->get([
                'c.cliente_id',
                'c.codigo_cliente',
                'c.nome',
                'c.telefone',
                'c.email',
                DB::raw('COUNT(*) as visitas'),
                DB::raw('COALESCE(SUM(f.valor_movimento), 0) as total_gasto'),
            ])
            ->map(fn (object $row) => [
                'cliente_id' => (int) $row->cliente_id,
                'codigo_cliente' => (int) ($row->codigo_cliente ?? 0),
                'nome' => $this->normalizeText($row->nome ?? 'SEM INFORMAÇÃO'),
                'telefone' => $this->normalizeText($row->telefone ?? ''),
                'email' => $this->normalizeText($row->email ?? ''),
                'visitas' => (int) ($row->visitas ?? 0),
                'total_gasto' => (float) ($row->total_gasto ?? 0),
                'phone_missing' => $this->normalizeText($row->telefone ?? '') === '',
            ])
            ->all();
    }

    private function getTopGasto(int $limit = 10): array
    {
        $cutoffKey = (int) Carbon::today()->subMonths(3)->format('Ymd');

        return DB::table('fato_logs_financeiro as f')
            ->join('dim_cliente as c', 'c.cliente_id', '=', 'f.cliente_id')
            ->where('f.tipo_movimento', 'CAIXA')
            ->whereNotNull('f.cliente_id')
            ->where('f.data_key', '>=', $cutoffKey)
            ->groupBy('c.cliente_id', 'c.codigo_cliente', 'c.nome', 'c.telefone', 'c.email')
            ->orderByRaw('COALESCE(SUM(f.valor_movimento), 0) DESC')
            ->orderByRaw('COUNT(*) DESC')
            ->limit($limit)
            ->get([
                'c.cliente_id',
                'c.codigo_cliente',
                'c.nome',
                'c.telefone',
                'c.email',
                DB::raw('COUNT(*) as visitas'),
                DB::raw('COALESCE(SUM(f.valor_movimento), 0) as total_gasto'),
            ])
            ->map(fn (object $row) => [
                'cliente_id' => (int) $row->cliente_id,
                'codigo_cliente' => (int) ($row->codigo_cliente ?? 0),
                'nome' => $this->normalizeText($row->nome ?? 'SEM INFORMAÇÃO'),
                'telefone' => $this->normalizeText($row->telefone ?? ''),
                'email' => $this->normalizeText($row->email ?? ''),
                'visitas' => (int) ($row->visitas ?? 0),
                'total_gasto' => (float) ($row->total_gasto ?? 0),
                'phone_missing' => $this->normalizeText($row->telefone ?? '') === '',
            ])
            ->all();
    }

    private function getTopAssiduasAllTime(int $limit = 10): array
    {
        return DB::table('fato_logs_financeiro as f')
            ->join('dim_cliente as c', 'c.cliente_id', '=', 'f.cliente_id')
            ->where('f.tipo_movimento', 'CAIXA')
            ->whereNotNull('f.cliente_id')
            ->groupBy('c.cliente_id', 'c.codigo_cliente', 'c.nome', 'c.telefone', 'c.email')
            ->orderByRaw('COUNT(*) DESC')
            ->orderByRaw('COALESCE(SUM(f.valor_movimento), 0) DESC')
            ->limit($limit)
            ->get([
                'c.cliente_id',
                'c.codigo_cliente',
                'c.nome',
                'c.telefone',
                'c.email',
                DB::raw('COUNT(*) as visitas'),
                DB::raw('COALESCE(SUM(f.valor_movimento), 0) as total_gasto'),
            ])
            ->map(fn (object $row) => [
                'cliente_id' => (int) $row->cliente_id,
                'codigo_cliente' => (int) ($row->codigo_cliente ?? 0),
                'nome' => $this->normalizeText($row->nome ?? 'SEM INFORMAÇÃO'),
                'telefone' => $this->normalizeText($row->telefone ?? ''),
                'email' => $this->normalizeText($row->email ?? ''),
                'visitas' => (int) ($row->visitas ?? 0),
                'total_gasto' => (float) ($row->total_gasto ?? 0),
                'phone_missing' => $this->normalizeText($row->telefone ?? '') === '',
            ])
            ->all();
    }

    private function getTopGastoAllTime(int $limit = 10): array
    {
        return DB::table('fato_logs_financeiro as f')
            ->join('dim_cliente as c', 'c.cliente_id', '=', 'f.cliente_id')
            ->where('f.tipo_movimento', 'CAIXA')
            ->whereNotNull('f.cliente_id')
            ->groupBy('c.cliente_id', 'c.codigo_cliente', 'c.nome', 'c.telefone', 'c.email')
            ->orderByRaw('COALESCE(SUM(f.valor_movimento), 0) DESC')
            ->orderByRaw('COUNT(*) DESC')
            ->limit($limit)
            ->get([
                'c.cliente_id',
                'c.codigo_cliente',
                'c.nome',
                'c.telefone',
                'c.email',
                DB::raw('COUNT(*) as visitas'),
                DB::raw('COALESCE(SUM(f.valor_movimento), 0) as total_gasto'),
            ])
            ->map(fn (object $row) => [
                'cliente_id' => (int) $row->cliente_id,
                'codigo_cliente' => (int) ($row->codigo_cliente ?? 0),
                'nome' => $this->normalizeText($row->nome ?? 'SEM INFORMAÇÃO'),
                'telefone' => $this->normalizeText($row->telefone ?? ''),
                'email' => $this->normalizeText($row->email ?? ''),
                'visitas' => (int) ($row->visitas ?? 0),
                'total_gasto' => (float) ($row->total_gasto ?? 0),
                'phone_missing' => $this->normalizeText($row->telefone ?? '') === '',
            ])
            ->all();
    }

    private function getAniversariantesDoMes(int $limit = 20): array
    {
        $this->firebirdWarning = null;

        try {
            $mesAtual = (int) Carbon::today()->month;

            return DB::connection('firebird_prod')->select(
                'SELECT FIRST ' . $limit . '
                    C.CODIGO,
                    C.NOME,
                    C.NASCIMENTO,
                    C.EMAIL,
                                        (SELECT FIRST 1 COALESCE(NULLIF(CT.TELEFONE_FORMATADO, \'\'), CT.TELEFONE)
                     FROM CLIENTES_TEL CT
                     WHERE CT.CODIGO = C.CODIGO
                     ORDER BY CT.PADRAO DESC, CT.ID DESC) AS TELEFONE
                 FROM CLIENTES C
                                 WHERE C.LIXO = \'F\'
                   AND C.NASCIMENTO IS NOT NULL
                   AND EXTRACT(MONTH FROM C.NASCIMENTO) = ?
                 ORDER BY EXTRACT(DAY FROM C.NASCIMENTO), C.NOME',
                [$mesAtual]
            );
        } catch (\Throwable $e) {
            $this->firebirdWarning = 'Não foi possível consultar aniversariantes na origem Firebird.';
            return [];
        }
    }

    private function fetchPhoneFromFirebird(int $codigoCliente): ?string
    {
        try {
            $row = DB::connection('firebird_prod')->selectOne(
                'SELECT FIRST 1 COALESCE(NULLIF(TELEFONE_FORMATADO, \'\'), TELEFONE) AS TELEFONE
                 FROM CLIENTES_TEL
                 WHERE CODIGO = ?
                 ORDER BY PADRAO DESC, ID DESC',
                [$codigoCliente]
            );

            $phone = $this->normalizeText($row->TELEFONE ?? null);
            return $phone !== '' ? $phone : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function getLeadCandidates(): array
    {
        $merged = array_merge($this->getTopAssiduas(20), $this->getTopGasto(20));
        $unique = [];

        foreach ($merged as $lead) {
            $id = (int) ($lead['cliente_id'] ?? 0);
            if ($id <= 0 || isset($unique[$id])) {
                continue;
            }
            $unique[$id] = $lead;
        }

        return array_values($unique);
    }

    private function normalizeText(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (mb_check_encoding($value, 'UTF-8')) {
            return trim($value);
        }

        return trim(mb_convert_encoding($value, 'UTF-8', 'Windows-1252,ISO-8859-1,UTF-8'));
    }

    private function getFinanceLoadedRange(): array
    {
        $range = DB::table('fato_logs_financeiro')
            ->where('tipo_movimento', 'CAIXA')
            ->selectRaw('MIN(data_key) as min_data, MAX(data_key) as max_data, COUNT(*) as total')
            ->first();

        $minKey = (int) ($range->min_data ?? 0);
        $maxKey = (int) ($range->max_data ?? 0);
        $total = (int) ($range->total ?? 0);

        $minDate = $minKey > 0 ? Carbon::createFromFormat('Ymd', (string) $minKey) : null;
        $maxDate = $maxKey > 0 ? Carbon::createFromFormat('Ymd', (string) $maxKey) : null;
        $daysCovered = ($minDate && $maxDate) ? $minDate->diffInDays($maxDate) + 1 : 0;

        return [
            'min_key' => $minKey,
            'max_key' => $maxKey,
            'min_date' => $minDate ? $minDate->format('d/m/Y') : null,
            'max_date' => $maxDate ? $maxDate->format('d/m/Y') : null,
            'days_covered' => $daysCovered,
            'total' => $total,
            'is_short_history' => $daysCovered > 0 && $daysCovered < 95,
        ];
    }

    public function render()
    {
        $assiduas = $this->getTopAssiduas();
        $gastadoras = $this->getTopGasto();
        $assiduasAllTime = $this->getTopAssiduasAllTime();
        $gastadorasAllTime = $this->getTopGastoAllTime();
        $financeRange = $this->getFinanceLoadedRange();
        $aniversariantesRaw = $this->getAniversariantesDoMes();
        $aniversariantes = array_map(function (object $row) {
            $nascimento = null;
            try {
                $nascimento = $row->NASCIMENTO ? Carbon::parse((string) $row->NASCIMENTO) : null;
            } catch (\Throwable) {
                $nascimento = null;
            }

            $telefone = $this->normalizeText($row->TELEFONE ?? null);

            return [
                'codigo_cliente' => (int) ($row->CODIGO ?? 0),
                'nome' => $this->normalizeText($row->NOME ?? 'SEM INFORMAÇÃO'),
                'telefone' => $telefone,
                'email' => $this->normalizeText($row->EMAIL ?? ''),
                'dia' => $nascimento ? $nascimento->format('d') : '--',
                'phone_missing' => $telefone === '',
            ];
        }, $aniversariantesRaw);

        return view('livewire.leads.leads-dashboard', [
            'assiduas' => $assiduas,
            'gastadoras' => $gastadoras,
            'assiduasAllTime' => $assiduasAllTime,
            'gastadorasAllTime' => $gastadorasAllTime,
            'financeRange' => $financeRange,
            'aniversariantes' => $aniversariantes,
            'leadCandidates' => $this->getLeadCandidates(),
            'firebirdWarning' => $this->firebirdWarning,
            'selectedClienteDetails' => $this->selectedClienteDetails,
        ]);
    }
}
