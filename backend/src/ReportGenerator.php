<?php
declare(strict_types=1);

namespace App;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Converter;
use PDO;
use Exception;

class ReportGenerator
{
    public function __construct(
        private readonly Database $db
    ) {}

    public function generate(): ?string
    {
        $connection = $this->db->getConnection();

        $stmtOrg = $connection->query('SELECT * FROM organic_campaign');
        $organicRecords = $stmtOrg->fetchAll();

        $stmtPaid = $connection->query('SELECT * FROM paid_campaign');
        $paidRecords = $stmtPaid->fetchAll();

        if (count($organicRecords) === 0 && count($paidRecords) === 0) {
            return null;
        }

        // Determine months from data
        $monthsSet = [];
        foreach ($organicRecords as $r) {
            if (!empty($r['mes'])) $monthsSet[$r['mes']] = true;
        }
        foreach ($paidRecords as $r) {
            if (!empty($r['mes'])) $monthsSet[$r['mes']] = true;
        }
        $months = array_keys($monthsSet);
        $monthStr = !empty($months) ? implode(', ', $months) : 'Varios Meses';
        $year = date('Y'); // Assume current year or could be parsed too, sticking to current year for V2

        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(10.5);

        $corporateBlue = '2E5B82';
        $darkBlue = '1E3A8A';

        $section = $phpWord->addSection([
            'marginTop' => Converter::cmToTwip(2.5),
            'marginBottom' => Converter::cmToTwip(2.5),
            'marginLeft' => Converter::cmToTwip(2.5),
            'marginRight' => Converter::cmToTwip(2.5),
        ]);

        $header = $section->addHeader();
        $headerTable = $header->addTable([
            'align' => 'center',
            'cellMarginTop' => 0, 'cellMarginBottom' => 0, 'cellMarginLeft' => 0, 'cellMarginRight' => 0,
            'borderSize' => 0, 'borderColor' => 'FFFFFF'
        ]);
        $row = $headerTable->addRow(Converter::cmToTwip(1.5));
        $cell1 = $row->addCell(Converter::cmToTwip(8));
        $cell2 = $row->addCell(Converter::cmToTwip(8));

        $logoPath = __DIR__ . '/../logo.png';
        $agencyLogoPath = __DIR__ . '/../dipaolalatina_logo.png';

        if (file_exists($logoPath)) {
            $cell1->addImage($logoPath, [
                'width' => 88.5,
                'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::START
            ]);
        }
        if (file_exists($agencyLogoPath)) {
            $cell2->addImage($agencyLogoPath, [
                'width' => 119.5,
                'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END
            ]);
        }

        for ($i = 0; $i < 5; $i++) {
            $section->addTextBreak(1);
        }

        $section->addText("Reporte de Campaña Mogul 360º", [
            'name' => 'Arial', 'size' => 22, 'bold' => true, 'color' => $darkBlue
        ], ['align' => 'center']);

        $section->addText("Reporte de Métricas Consolidadas – {$monthStr} {$year}", [
            'name' => 'Arial', 'size' => 14, 'italic' => true, 'color' => '4B5563'
        ], ['align' => 'center']);

        $section->addTextBreak(2);

        $section->addText("1. Resumen Ejecutivo (Métricas Clave)", [
            'name' => 'Arial', 'size' => 14, 'bold' => true, 'color' => $corporateBlue
        ]);
        $section->addTextBreak(1);

        $totalOrganic = count($organicRecords);
        $totalPaid = count($paidRecords);
        $totalMentions = $totalOrganic + $totalPaid;

        $likes = array_sum(array_column($organicRecords, 'likes')) + array_sum(array_column($paidRecords, 'likes'));
        $shares = array_sum(array_column($organicRecords, 'compartidos')) + array_sum(array_column($paidRecords, 'compartidos'));
        $saves = array_sum(array_column($organicRecords, 'guardados')) + array_sum(array_column($paidRecords, 'guardados'));
        $comments = array_sum(array_column($organicRecords, 'comentarios')) + array_sum(array_column($paidRecords, 'comentarios'));
        $views = array_sum(array_column($organicRecords, 'views_semana')) + array_sum(array_column($paidRecords, 'views_semana'));

        $totalEngagement = $likes + $shares + $saves + $comments;

        $summaryData = [
            ["Menciones Totales", $totalMentions],
            ["Menciones Orgánicas", $totalOrganic],
            ["Menciones Pagas", $totalPaid],
            ["Reproducciones Totales (Views)", $views],
            ["Me Gusta (Likes)", $likes],
            ["Compartidos (Shares)", $shares],
            ["Guardados (Saves)", $saves],
            ["Comentarios", $comments],
            ["Interacciones Totales (Engagement)", $totalEngagement]
        ];

        $table = $section->addTable([
            'borderSize' => 6, 'borderColor' => 'D3D3D3', 'cellMargin' => 80, 'align' => 'center'
        ]);

        $row = $table->addRow();
        $cell1 = $row->addCell(Converter::cmToTwip(10), ['bgColor' => $corporateBlue]);
        $cell2 = $row->addCell(Converter::cmToTwip(6), ['bgColor' => $corporateBlue]);
        $cell1->addText("Métrica de Campaña", ['bold' => true, 'color' => 'FFFFFF']);
        $cell2->addText("Valor Acumulado", ['bold' => true, 'color' => 'FFFFFF'], ['align' => 'right']);

        foreach ($summaryData as $idx => $item) {
            $bgColor = $idx % 2 === 1 ? 'F0F4F8' : 'FFFFFF';
            $row = $table->addRow();
            $c1 = $row->addCell(Converter::cmToTwip(10), ['bgColor' => $bgColor]);
            $c2 = $row->addCell(Converter::cmToTwip(6), ['bgColor' => $bgColor]);
            
            $c1->addText($item[0]);
            $c2->addText($this->formatNumber((int)$item[1]), ['bold' => true], ['align' => 'right']);
        }

        $section->addPageBreak();

        $section->addText("2. Desglose por Plataforma", [
            'name' => 'Arial', 'size' => 14, 'bold' => true, 'color' => $corporateBlue
        ]);
        $section->addTextBreak(1);

        $platformData = [];
        $processPlatform = function($records) use (&$platformData) {
            foreach ($records as $r) {
                $plat = strtoupper(trim((string)$r['plataforma']));
                if ($plat === 'IG' || $plat === 'INSTAGRAM') {
                    $platName = 'Instagram';
                } elseif ($plat === 'TIKTOK' || $plat === 'TT') {
                    $platName = 'Tik tok';
                } elseif ($plat === 'X' || $plat === 'TWITTER') {
                    $platName = 'X (Twitter)';
                } elseif ($plat === 'YT' || $plat === 'YOUTUBE') {
                    $platName = 'YouTube';
                } else {
                    $platName = ucfirst(strtolower($plat));
                }

                if (!isset($platformData[$platName])) {
                    $platformData[$platName] = [
                        'menciones' => 0,
                        'views' => 0,
                        'likes' => 0,
                        'engagement' => 0
                    ];
                }

                $platformData[$platName]['menciones'] += 1;
                $platformData[$platName]['views'] += (int)($r['views_semana'] ?? 0);
                $platformData[$platName]['likes'] += (int)($r['likes'] ?? 0);
                $platformData[$platName]['engagement'] += (int)($r['likes'] ?? 0) + (int)($r['compartidos'] ?? 0) + (int)($r['comentarios'] ?? 0) + (int)($r['guardados'] ?? 0);
            }
        };

        $processPlatform($organicRecords);
        $processPlatform($paidRecords);

        uasort($platformData, fn($a, $b) => $b['menciones'] <=> $a['menciones']);

        $table2 = $section->addTable([
            'borderSize' => 6, 'borderColor' => 'D3D3D3', 'cellMargin' => 80, 'align' => 'center'
        ]);
        
        $row = $table2->addRow();
        $row->addCell(Converter::cmToTwip(5), ['bgColor' => $corporateBlue])->addText("Plataforma", ['bold' => true, 'color' => 'FFFFFF']);
        $row->addCell(Converter::cmToTwip(3.6), ['bgColor' => $corporateBlue])->addText("Menciones", ['bold' => true, 'color' => 'FFFFFF'], ['align' => 'right']);
        $row->addCell(Converter::cmToTwip(3.7), ['bgColor' => $corporateBlue])->addText("Reproducciones", ['bold' => true, 'color' => 'FFFFFF'], ['align' => 'right']);
        $row->addCell(Converter::cmToTwip(3.7), ['bgColor' => $corporateBlue])->addText("Interacciones", ['bold' => true, 'color' => 'FFFFFF'], ['align' => 'right']);

        $idx = 0;
        foreach ($platformData as $plat => $metrics) {
            $bgColor = $idx % 2 === 1 ? 'F0F4F8' : 'FFFFFF';
            $row = $table2->addRow();
            $row->addCell(Converter::cmToTwip(5), ['bgColor' => $bgColor])->addText($plat);
            $row->addCell(Converter::cmToTwip(3.6), ['bgColor' => $bgColor])->addText($this->formatNumber($metrics['menciones']), [], ['align' => 'right']);
            $row->addCell(Converter::cmToTwip(3.7), ['bgColor' => $bgColor])->addText($this->formatNumber($metrics['views']), [], ['align' => 'right']);
            $row->addCell(Converter::cmToTwip(3.7), ['bgColor' => $bgColor])->addText($this->formatNumber($metrics['engagement']), [], ['align' => 'right']);
            $idx++;
        }

        $section->addTextBreak(2);

        $section->addText("3. Análisis de Sentimiento y Pilares de Contenido", [
            'name' => 'Arial', 'size' => 14, 'bold' => true, 'color' => $corporateBlue
        ]);
        $section->addTextBreak(1);

        $sentitudes = ['POSITIVO' => 0, 'NEGATIVO' => 0, 'NEUTRO' => 0];
        foreach ($organicRecords as $r) {
            $s = strtoupper(trim((string)$r['sentiment']));
            if (isset($sentitudes[$s])) $sentitudes[$s]++;
        }
        foreach ($paidRecords as $r) {
            $s = strtoupper(trim((string)$r['sentiment']));
            if (isset($sentitudes[$s])) $sentitudes[$s]++;
        }

        $kioscoCount = array_sum(array_map(fn($r) => $r['categoria_perfil'] === 'Kiosco' ? 1 : 0, $organicRecords));
        $ugcCount = array_sum(array_map(fn($r) => $r['categoria_perfil'] !== 'Kiosco' ? 1 : 0, $organicRecords)) + array_sum(array_map(fn($r) => $r['categoria'] === 'CANJE' ? 1 : 0, $paidRecords));
        $influencersCount = array_sum(array_map(fn($r) => $r['categoria'] === 'PRESUPUESTO' ? 1 : 0, $paidRecords));

        $section->addText("Distribución de Sentimiento:", ['bold' => true]);
        $section->addTextBreak(1);

        $tableSent = $section->addTable([
            'borderSize' => 6, 'borderColor' => 'D3D3D3', 'cellMargin' => 80, 'align' => 'center'
        ]);
        $row = $tableSent->addRow();
        $row->addCell(Converter::cmToTwip(6), ['bgColor' => $corporateBlue])->addText("Sentimiento", ['bold' => true, 'color' => 'FFFFFF']);
        $row->addCell(Converter::cmToTwip(5), ['bgColor' => $corporateBlue])->addText("Menciones", ['bold' => true, 'color' => 'FFFFFF'], ['align' => 'right']);
        $row->addCell(Converter::cmToTwip(5), ['bgColor' => $corporateBlue])->addText("Porcentaje", ['bold' => true, 'color' => 'FFFFFF'], ['align' => 'right']);

        $sentList = [["Positivo", $sentitudes['POSITIVO']], ["Neutro", $sentitudes['NEUTRO']], ["Negativo", $sentitudes['NEGATIVO']]];
        foreach ($sentList as $i => $item) {
            $bgColor = $i % 2 === 1 ? 'F0F4F8' : 'FFFFFF';
            $row = $tableSent->addRow();
            $row->addCell(Converter::cmToTwip(6), ['bgColor' => $bgColor])->addText($item[0]);
            $row->addCell(Converter::cmToTwip(5), ['bgColor' => $bgColor])->addText($this->formatNumber($item[1]), [], ['align' => 'right']);
            $pct = $totalMentions > 0 ? ($item[1] / $totalMentions * 100) : 0;
            $row->addCell(Converter::cmToTwip(5), ['bgColor' => $bgColor])->addText(sprintf("%.1f%%", $pct), [], ['align' => 'right']);
        }

        $section->addTextBreak(2);

        $section->addText("Distribución por Pilares de Contenido:", ['bold' => true]);
        $section->addTextBreak(1);

        $tablePil = $section->addTable([
            'borderSize' => 6, 'borderColor' => 'D3D3D3', 'cellMargin' => 80, 'align' => 'center'
        ]);
        $row = $tablePil->addRow();
        $row->addCell(Converter::cmToTwip(6), ['bgColor' => $corporateBlue])->addText("Pilar de Contenido", ['bold' => true, 'color' => 'FFFFFF']);
        $row->addCell(Converter::cmToTwip(5), ['bgColor' => $corporateBlue])->addText("Menciones", ['bold' => true, 'color' => 'FFFFFF'], ['align' => 'right']);
        $row->addCell(Converter::cmToTwip(5), ['bgColor' => $corporateBlue])->addText("Porcentaje", ['bold' => true, 'color' => 'FFFFFF'], ['align' => 'right']);

        $pilList = [["Kiosco", $kioscoCount], ["UGC", $ugcCount], ["Influencers", $influencersCount]];
        foreach ($pilList as $i => $item) {
            $bgColor = $i % 2 === 1 ? 'F0F4F8' : 'FFFFFF';
            $row = $tablePil->addRow();
            $row->addCell(Converter::cmToTwip(6), ['bgColor' => $bgColor])->addText($item[0]);
            $row->addCell(Converter::cmToTwip(5), ['bgColor' => $bgColor])->addText($this->formatNumber($item[1]), [], ['align' => 'right']);
            $pct = $totalMentions > 0 ? ($item[1] / $totalMentions * 100) : 0;
            $row->addCell(Converter::cmToTwip(5), ['bgColor' => $bgColor])->addText(sprintf("%.1f%%", $pct), [], ['align' => 'right']);
        }

        $section->addPageBreak();

        $section->addText("4. Top 5 Contenidos Destacados", [
            'name' => 'Arial', 'size' => 14, 'bold' => true, 'color' => $corporateBlue
        ]);
        $section->addTextBreak(1);

        $allContent = [];
        $addContent = function($records) use (&$allContent) {
            foreach ($records as $r) {
                $likes = (int)($r['likes'] ?? 0);
                $shares = (int)($r['compartidos'] ?? 0);
                $comments = (int)($r['comentarios'] ?? 0);
                $saves = (int)($r['guardados'] ?? 0);
                $allContent[] = [
                    'usuario' => (string)$r['usuario'],
                    'plataforma' => (string)$r['plataforma'],
                    'link' => (string)$r['link_publicacion'],
                    'likes' => $likes,
                    'views' => (int)($r['views_semana'] ?? 0),
                    'engagement' => $likes + $shares + $comments + $saves
                ];
            }
        };

        $addContent($organicRecords);
        $addContent($paidRecords);

        usort($allContent, fn($a, $b) => $b['engagement'] <=> $a['engagement']);
        $top5 = array_slice($allContent, 0, 5);

        foreach ($top5 as $idx => $item) {
            $plat = strtoupper(trim($item['plataforma']));
            if ($plat === 'IG' || $plat === 'INSTAGRAM') {
                $platName = 'Instagram';
            } elseif ($plat === 'TIKTOK' || $plat === 'TT') {
                $platName = 'Tik tok';
            } elseif ($plat === 'X' || $plat === 'TWITTER') {
                $platName = 'X (Twitter)';
            } else {
                $platName = ucfirst(strtolower($plat));
            }

            $p = $section->addTextRun();
            $p->addText(sprintf("%d. ", $idx + 1), ['bold' => true, 'color' => $corporateBlue]);
            $p->addText(htmlspecialchars($item['usuario']), ['bold' => true]);
            $p->addText(" ({$platName})");

            $section->addListItem(
                sprintf("Me gusta: %s  |  Reproducciones: %s", $this->formatNumber($item['likes']), $this->formatNumber($item['views'])),
                0, null, null, ['leftIndent' => 400]
            );

            if ($item['link']) {
                $pLink = $section->addListItemRun(0, null, ['leftIndent' => 400]);
                $pLink->addText("Enlace: ", ['bold' => true]);
                $pLink->addLink(htmlspecialchars($item['link']), htmlspecialchars($item['link']), ['color' => $corporateBlue, 'underline' => true, 'size' => 9]);
            }
            $section->addTextBreak(1);
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'word_');
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempFile);

        $data = file_get_contents($tempFile);
        unlink($tempFile);

        return $data;
    }

    private function formatNumber(int $val): string
    {
        return number_format($val, 0, ',', '.');
    }
}
