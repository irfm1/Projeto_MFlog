<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * LogParserService
 * 
 * Extrai dados estruturados de descrições de logs não-estruturadas.
 * Transforma texto livre em dados processáveis.
 */
class LogParserService
{
    /**
     * Extrai número de venda de string de descrição
     * 
     * Exemplos:
     * - "REALIZOU UMA VENDA Nº: 89340" → 89340
     * - "Venda 89340" → 89340
     * - "Nº 89340" → 89340
     */
    public function extractSaleNumber(string $description): ?int
    {
        $patterns = [
            '/venda\s*nº?\s*:?\s*(\d+)/i',
            '/venda\s+(\d+)/i',
            '/nº\s*:?\s*(\d+)/i',
            '/sale\s*#?(\d+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $description, $matches)) {
                return (int) $matches[1];
            }
        }

        return null;
    }

    /**
     * Extrai metadados de descrição
     */
    public function parse(string $description): array
    {
        return [
            'saleNumber' => $this->extractSaleNumber($description),
            'action' => $this->extractAction($description),
            'amount' => $this->extractAmount($description),
            'terminal' => $this->extractTerminal($description),
            'keywords' => $this->extractKeywords($description),
        ];
    }

    /**
     * Classifica ação baseada em palavras-chave
     */
    private function extractAction(string $description): string
    {
        $words = Str::upper($description);

        if (Str::contains($words, ['VENDA', 'VENDEU'])) return 'VENDA';
        if (Str::contains($words, ['CANCELAMENTO', 'CANCELOU'])) return 'CANCELAMENTO';
        if (Str::contains($words, ['ABERTURA', 'ABERTO'])) return 'ABERTURA';
        if (Str::contains($words, ['FECHAMENTO', 'FECHADO'])) return 'FECHAMENTO';
        if (Str::contains($words, ['DEVOLUÇÃO', 'DEVOLVEU'])) return 'DEVOLUCAO';
        if (Str::contains($words, ['ALTERAÇÃO', 'ALTEROU'])) return 'ALTERACAO';
        if (Str::contains($words, ['EXCLUSÃO', 'EXCLUIU'])) return 'EXCLUSAO';
        if (Str::contains($words, ['CRIAÇÃO', 'CRIOU'])) return 'CRIACAO';

        return 'OUTRO';
    }

    /**
     * Extrai valor monetário
     */
    private function extractAmount(string $description): ?float
    {
        // Padrões: R$ 100,50 ou $100.50 ou 100,50
        $patterns = [
            '/r?\$?\s*([\d.]+,[\d]{2})/i', // R$ 100,50
            '/r?\$?\s*([\d,]+\.[\d]{2})/i', // 100,50.00
            '/(?:valor|amount|r\$)\s*[:\s]*([\d.,]+)/i', // valor: 100,50
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $description, $matches)) {
                $amount = $matches[1];
                // Normaliza para formato PHP (ponto como separador decimal)
                $amount = str_replace(['.', ','], ['', '.'], $amount);
                // Se tiver formato brasileiro (virgula), converte
                if (strpos($matches[1], ',') !== false && strpos($matches[1], '.') === false) {
                    $amount = str_replace(',', '.', $matches[1]);
                }
                return (float) $amount;
            }
        }

        return null;
    }

    /**
     * Extrai referência de terminal
     */
    private function extractTerminal(string $description): ?string
    {
        if (preg_match('/DESKTOP-[\w]+/i', $description, $matches)) {
            return $matches[0];
        }

        if (preg_match('/(?:terminal|pc|computador|caixa)\s*[:#\-\s]*([\w\-]+)/i', $description, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Extrai palavras-chave para busca
     */
    private function extractKeywords(string $description): array
    {
        // Remove palavras comuns
        $stopwords = ['o', 'a', 'de', 'do', 'da', 'um', 'uma', 'e', 'ou', 'por', 'em', 'para'];

        $words = preg_split('/\s+/', Str::lower($description));
        $keywords = [];

        foreach ($words as $word) {
            $word = preg_replace('/[^\w\-]/', '', $word);
            if (strlen($word) > 3 && !in_array($word, $stopwords)) {
                $keywords[] = $word;
            }
        }

        return array_unique($keywords);
    }

    /**
     * Classifica tipo de cancelamento
     */
    public function classifyCancellationType(string $description): string
    {
        if (Str::contains(Str::upper($description), ['SERVIÇO', 'SERVIÇOS', 'SERVICE'])) {
            return 'SERVICO';
        }

        if (Str::contains(Str::upper($description), ['PRODUTO', 'PRODUTOS', 'PRODUCT'])) {
            return 'PRODUTO';
        }

        return 'OUTRO';
    }

    /**
     * Classifica tipo de operação de caixa
     */
    public function classifyCaixaOperationType(string $description): string
    {
        $upper = Str::upper($description);

        if (Str::contains($upper, 'ABERTURA')) return 'ABERTURA';
        if (Str::contains($upper, 'FECHAMENTO')) return 'FECHAMENTO';
        if (Str::contains($upper, ['VENDA', 'VENDEU'])) return 'VENDA';
        if (Str::contains($upper, ['DEVOLUÇÃO', 'DEVOLVEU'])) return 'DEVOLUCAO';
        if (Str::contains($upper, 'DESCONTO')) return 'DESCONTO';
        if (Str::contains($upper, 'TAXA')) return 'TAXA';

        return 'OUTRO';
    }
}
