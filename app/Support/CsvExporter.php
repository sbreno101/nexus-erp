<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gera arquivos CSV em streaming (linha a linha), sem carregar todos os
 * registros na memória — importante para exportações grandes.
 *
 * Formato compatível com o Excel em português: separador ";" e BOM UTF-8
 * (para acentos aparecerem corretamente).
 */
class CsvExporter
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<array<int, scalar|null>>  $rows
     */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers, ';', '"', '');

            foreach ($rows as $row) {
                fputcsv($handle, array_map(self::sanitizeCell(...), $row), ';', '"', '');
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Evita "CSV injection": células iniciadas por = + - @ seriam
     * interpretadas como fórmulas pelo Excel.
     */
    private static function sanitizeCell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        // Números negativos no formato brasileiro ("-1234,56") são valores legítimos, não fórmulas.
        $isNumber = is_numeric($value) || preg_match('/^-?\d+(,\d+)?$/', $value) === 1;

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 && ! $isNumber ? "'".$value : $value;
    }
}
