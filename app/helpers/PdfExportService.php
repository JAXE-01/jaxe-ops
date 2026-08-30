<?php
class PdfExportService {
    public static function outputCalendarPdf(string $title, array $rows, string $fileName = 'calendrier-editorial.pdf'): void {
        if (class_exists('Dompdf\\Dompdf')) {
            self::renderWithDompdf(self::buildCalendarHtml($title, $rows), $fileName, 'landscape');
            return;
        }
        self::outputTablePdf($title, $rows, ['date_prevue','client','projet','titre','type_livrable','reseau','sujet'], $fileName);
    }

    public static function outputBriefsPdf(string $title, array $rows, string $fileName = 'briefs-et-scripts.pdf'): void {
        if (class_exists('Dompdf\\Dompdf')) {
            self::renderWithDompdf(self::buildBriefsHtml($title, $rows), $fileName, 'portrait');
            return;
        }
        self::outputTablePdf($title, $rows, ['client','projet','periode_mois','titre','script_contenu'], $fileName);
    }
    public static function outputTablePdf($title, array $rows, array $columns, $fileName = 'export.pdf') {
        if (class_exists('Dompdf\\Dompdf')) {
            $html = self::buildTableHtml($title, $rows, $columns);
            self::renderWithDompdf($html, $fileName);
            return;
        }

        $lines = [];
        $lines[] = $title;
        $lines[] = str_repeat('=', max(12, strlen($title)));
        $lines[] = '';
        $lines[] = 'Colonnes: ' . implode(', ', $columns);
        $lines[] = '';

        foreach ($rows as $index => $row) {
            $lines[] = 'Ligne ' . ($index + 1);
            foreach ($columns as $column) {
                $lines[] = ' - ' . $column . ': ' . (string) ($row[$column] ?? '');
            }
            $lines[] = '';
        }

        if (empty($rows)) {
            $lines[] = 'Aucune donnee.';
        }

        self::renderSimplePdfLines($lines, $fileName);
    }

    public static function outputReportPdf($title, array $data, array $fields, $fileName = 'rapport.pdf', array $sections = []) {
        $global = (array) ($data['global'] ?? []);
        $items = (array) ($data['items'] ?? []);
        $byPublication = (array) ($data['by_publication'] ?? []);
        $byNetwork = (array) ($data['by_network'] ?? []);
        $recommendations = (array) ($data['recommendations'] ?? []);
        $sections = !empty($sections) ? $sections : ['global', 'publication', 'network', 'recommendations'];

        if (class_exists('Dompdf\\Dompdf')) {
            $html = self::buildReportHtml($title, $global, $items, $fields, $sections, $byPublication, $byNetwork, $recommendations);
            self::renderWithDompdf($html, $fileName);
            return;
        }

        $lines = [];
        $lines[] = $title;
        $lines[] = str_repeat('=', max(12, strlen($title)));
        $lines[] = '';
        if (in_array('global', $sections, true)) {
            $lines[] = 'Impact global';
            $lines[] = ' - Total contenus: ' . (int) ($global['total_contenus'] ?? 0);
            $lines[] = ' - Videos: ' . (int) ($global['videos'] ?? 0);
            $lines[] = ' - Visuels: ' . (int) ($global['visuels'] ?? 0);
            $lines[] = ' - Types non qualifies: ' . (int) ($global['unknown_type'] ?? 0);
            $lines[] = ' - Avec message: ' . (int) ($global['with_message'] ?? 0);
            $lines[] = ' - Avec script: ' . (int) ($global['with_script'] ?? 0);
            $lines[] = ' - Date planifiee renseignee: ' . (int) ($global['scheduled'] ?? 0);
            $lines[] = ' - Reseaux uniques: ' . (int) ($global['network_count'] ?? 0);
            $lines[] = ' - Taux contenus avec message: ' . (float) ($global['message_rate'] ?? 0) . '%';
            $lines[] = ' - Taux videos avec script: ' . (float) ($global['script_rate'] ?? 0) . '%';
            $lines[] = '';
        }

        if (in_array('publication', $sections, true)) {
            foreach ($byPublication as $index => $item) {
                $lines[] = 'Publication ' . ($index + 1);
                foreach ($fields as $field) {
                    $lines[] = ' - ' . $field . ': ' . (string) ($item[$field] ?? '');
                }
                $lines[] = '';
            }
        }

        if (in_array('network', $sections, true)) {
            $lines[] = 'Impact par reseau';
            foreach ($byNetwork as $networkStats) {
                $lines[] = ' - ' . (string) ($networkStats['reseau'] ?? 'Non defini')
                    . ': total=' . (int) ($networkStats['total'] ?? 0)
                    . ', videos=' . (int) ($networkStats['videos'] ?? 0)
                    . ', visuels=' . (int) ($networkStats['visuels'] ?? 0)
                    . ', avec message=' . (int) ($networkStats['with_message'] ?? 0);
            }
            $lines[] = '';
        }

        if (in_array('recommendations', $sections, true)) {
            $lines[] = 'Recommandations';
            foreach ($recommendations as $recommendation) {
                $lines[] = ' - ' . (string) $recommendation;
            }
            $lines[] = '';
        }

        if (empty($items) && empty($byNetwork) && empty($recommendations)) {
            $lines[] = 'Aucune donnee.';
        }

        self::renderSimplePdfLines($lines, $fileName);
    }

    public static function outputKpiClientPdf($title, array $analysis, $fileName = 'rapport-client-kpi.pdf') {
        $cards = is_array($analysis['cards'] ?? null) ? $analysis['cards'] : [];
        $top = is_array($analysis['top_publications'] ?? null) ? $analysis['top_publications'] : [];
        $weak = is_array($analysis['weak_publications'] ?? null) ? $analysis['weak_publications'] : [];
        $network = is_array($analysis['network_comparison'] ?? null) ? $analysis['network_comparison'] : [];
        $insights = is_array($analysis['global_insights'] ?? null) ? $analysis['global_insights'] : [];

        if (class_exists('Dompdf\\Dompdf')) {
            $html = self::buildKpiClientReportHtml($title, $cards, $top, $weak, $network, $insights);
            self::renderWithDompdf($html, $fileName);
            return;
        }

        $lines = [];
        $lines[] = $title;
        $lines[] = str_repeat('=', max(12, strlen($title)));
        $lines[] = '';
        $lines[] = '1) Resume executif';
        $lines[] = ' - Collectes: ' . (int) ($cards['collectes'] ?? 0);
        $lines[] = ' - Score moyen: ' . round((float) ($cards['score_moyen'] ?? 0), 2);
        $lines[] = ' - Croissance moyenne: ' . round((float) ($cards['growth_moyen'] ?? 0), 2) . '%';
        $lines[] = '';

        $lines[] = '2) Top publications';
        foreach (array_slice($top, 0, 5) as $item) {
            $lines[] = ' - ' . (string) ($item['publication_titre'] ?? 'Publication') . ' (score ' . round((float) ($item['score_moyen'] ?? 0), 2) . ')';
        }
        $lines[] = '';

        $lines[] = '3) Points faibles';
        foreach (array_slice($weak, 0, 5) as $item) {
            $lines[] = ' - ' . (string) ($item['publication_titre'] ?? 'Publication') . ' (score ' . round((float) ($item['score_moyen'] ?? 0), 2) . ')';
        }
        $lines[] = '';

        $lines[] = '4) Comparaison reseaux';
        foreach ($network as $row) {
            $lines[] = ' - ' . (string) ($row['reseau_label'] ?? $row['reseau'] ?? 'Reseau') . ': score ' . round((float) ($row['performance_globale'] ?? 0), 2);
        }
        $lines[] = '';

        $lines[] = '5) Recommandations';
        foreach ($insights as $insight) {
            $lines[] = ' - ' . (string) $insight;
        }

        self::renderSimplePdfLines($lines, $fileName);
    }

    private static function renderWithDompdf($html, $fileName, string $orientation = 'landscape') {
        $dompdf = new Dompdf\Dompdf();
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo $dompdf->output();
        exit;
    }

    private static function documentCss(): string {
        return '@page{margin:16mm 13mm 15mm}body{font-family:DejaVu Sans,Arial,sans-serif;color:#13233d;font-size:9.5px;margin:0}.head{background:#102743;color:#fff;padding:15px 18px;border-radius:10px;margin-bottom:12px}.head h1{font-size:19px;margin:0 0 4px}.head p{margin:0;color:#d7e6f5}.meta{color:#61738b;font-size:8.5px}.section-title{font-size:12px;margin:12px 0 6px}.chip{display:inline-block;padding:3px 7px;border-radius:10px;background:#eaf2fb;color:#244d78;margin-right:4px}.empty{padding:18px;border:1px dashed #b8c7d8;text-align:center;color:#61738b}.footer{position:fixed;bottom:-9mm;left:0;right:0;color:#718198;font-size:8px;text-align:right}';
    }

    private static function buildCalendarHtml(string $title, array $rows): string {
        $groups = [];
        foreach ($rows as $row) {
            $key = trim((string)($row['periode_mois'] ?? '')) ?: 'Période non définie';
            $groups[$key][] = $row;
        }
        ob_start(); ?>
        <!doctype html><html lang="fr"><head><meta charset="UTF-8"><style>
        <?= self::documentCss() ?>
        table{width:100%;border-collapse:collapse;table-layout:fixed;margin-bottom:10px}th{background:#e9f0f7;color:#38516f;text-transform:uppercase;font-size:8px;letter-spacing:.03em}th,td{padding:6px;border-bottom:1px solid #dce5ef;vertical-align:top}tr:nth-child(even) td{background:#f7f9fc}.date{width:11%}.type{width:9%}.network{width:13%}.content{width:25%}.project{width:20%}
        </style></head><body><div class="head"><h1><?= htmlspecialchars($title) ?></h1><p>Calendrier éditorial · <?= date('d/m/Y H:i') ?> · <?= count($rows) ?> contenu(s)</p></div>
        <?php foreach ($groups as $period => $items): ?><h2 class="section-title"><?= htmlspecialchars($period) ?></h2><table><thead><tr><th class="date">Date</th><th>Client / projet</th><th class="content">Contenu</th><th class="type">Format</th><th class="network">Réseau</th><th>Message clé</th></tr></thead><tbody>
        <?php foreach ($items as $row): ?><tr><td><?= htmlspecialchars((string)($row['date_prevue'] ?? '—')) ?></td><td><strong><?= htmlspecialchars((string)($row['client'] ?? '')) ?></strong><br><span class="meta"><?= htmlspecialchars((string)($row['projet'] ?? '')) ?></span></td><td><strong><?= htmlspecialchars((string)($row['titre'] ?? '')) ?></strong><br><span class="meta"><?= htmlspecialchars((string)($row['sujet'] ?? '')) ?></span></td><td><?= htmlspecialchars((string)($row['type_livrable'] ?? '')) ?></td><td><?= htmlspecialchars((string)($row['reseau'] ?? '')) ?></td><td><?= nl2br(htmlspecialchars(mb_strimwidth((string)($row['message'] ?? ''),0,240,'…'))) ?></td></tr><?php endforeach ?>
        </tbody></table><?php endforeach ?><?php if (!$rows): ?><div class="empty">Aucun contenu dans la sélection.</div><?php endif ?><div class="footer">Strax · Calendrier éditorial</div></body></html>
        <?php return (string)ob_get_clean();
    }

    private static function buildBriefsHtml(string $title, array $rows): string {
        ob_start(); ?>
        <!doctype html><html lang="fr"><head><meta charset="UTF-8"><style>
        <?= self::documentCss() ?>
        .brief{page-break-after:always}.brief:last-child{page-break-after:auto}.brief-head{border-left:4px solid #4c8ac3;background:#eef4fa;padding:10px 12px;margin-bottom:10px}.brief-head h2{margin:0 0 4px;font-size:15px}.grid{width:100%;border-collapse:separate;border-spacing:6px}.grid td{border:1px solid #d9e3ed;border-radius:7px;padding:8px;vertical-align:top;width:50%}.label{display:block;text-transform:uppercase;letter-spacing:.04em;color:#60738b;font-size:7.5px;margin-bottom:4px}.block{border:1px solid #d9e3ed;border-radius:7px;padding:10px;margin:7px 0;line-height:1.5;white-space:pre-wrap}.block strong{display:block;color:#38516f;margin-bottom:4px}
        </style></head><body><div class="head"><h1><?= htmlspecialchars($title) ?></h1><p>Briefs et scripts de production · <?= date('d/m/Y H:i') ?></p></div>
        <?php foreach ($rows as $row): ?><section class="brief"><div class="brief-head"><h2><?= htmlspecialchars((string)($row['titre'] ?? 'Contenu')) ?></h2><span class="meta"><?= htmlspecialchars(trim((string)($row['client'] ?? '')).' · '.trim((string)($row['projet'] ?? '')).' · '.trim((string)($row['periode_mois'] ?? ''))) ?></span></div><table class="grid"><tr><td><span class="label">Format</span><?= htmlspecialchars((string)($row['type_livrable'] ?? '')) ?></td><td><span class="label">Réseau</span><?= htmlspecialchars((string)($row['reseau'] ?? '')) ?></td></tr><tr><td><span class="label">Sujet / angle</span><?= nl2br(htmlspecialchars((string)($row['sujet'] ?? ''))) ?></td><td><span class="label">Message</span><?= nl2br(htmlspecialchars((string)($row['message'] ?? ''))) ?></td></tr></table>
        <?php foreach ([['plan_script','Plan / structure'],['texte_script','Texte du script'],['script_contenu','Brief / consigne']] as [$key,$label]): if(trim((string)($row[$key]??''))!==''): ?><div class="block"><strong><?= $label ?></strong><?= nl2br(htmlspecialchars((string)$row[$key])) ?></div><?php endif; endforeach ?></section><?php endforeach ?><?php if (!$rows): ?><div class="empty">Aucun brief ou script dans la sélection.</div><?php endif ?><div class="footer">Strax · Document de production</div></body></html>
        <?php return (string)ob_get_clean();
    }

    private static function buildTableHtml($title, array $rows, array $columns) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #1e293b; margin: 20px; }
                .hero { background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%); color: #ffffff; border-radius: 12px; padding: 16px 18px; margin-bottom: 14px; }
                .hero h1 { margin: 0 0 4px; font-size: 18px; }
                .hero p { margin: 0; font-size: 10px; opacity: 0.96; }
                .meta { margin-bottom: 8px; font-size: 10px; color: #334155; }
                .table-wrap { border: 1px solid #cbd5e1; border-radius: 10px; overflow: hidden; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border-bottom: 1px solid #e2e8f0; padding: 7px 8px; vertical-align: top; }
                th { background: #e2e8f0; text-transform: uppercase; font-size: 9px; letter-spacing: 0.02em; color: #334155; }
                tbody tr:nth-child(even) td { background: #f8fafc; }
                tbody tr:last-child td { border-bottom: none; }
                td { line-height: 1.35; }
            </style>
        </head>
        <body>
            <div class="hero">
                <h1><?= htmlspecialchars((string) $title) ?></h1>
                <p>Export PDF professionnel · <?= date('d/m/Y H:i') ?></p>
            </div>
            <div class="meta">Colonnes selectionnees: <?= htmlspecialchars(implode(', ', $columns)) ?></div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <?php foreach ($columns as $column): ?>
                                <th><?= htmlspecialchars((string) $column) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <?php foreach ($columns as $column): ?>
                                    <td><?= nl2br(htmlspecialchars((string) ($row[$column] ?? ''))) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="<?= count($columns) ?>">Aucune donnee.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </body>
        </html>
        <?php
        return (string) ob_get_clean();
    }

    private static function buildKpiClientReportHtml($title, array $cards, array $top, array $weak, array $network, array $insights) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #1e293b; margin: 20px; }
                .hero { background: linear-gradient(135deg, #0ea5e9 0%, #1d4ed8 100%); color: #fff; border-radius: 12px; padding: 16px 18px; margin-bottom: 12px; }
                .hero h1 { margin: 0 0 4px; font-size: 18px; }
                .hero p { margin: 0; font-size: 10px; opacity: 0.96; }
                .cards { margin: 10px 0 12px; }
                .cards table { width: 100%; border-collapse: separate; border-spacing: 8px; }
                .cards td { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 8px; }
                .section { border: 1px solid #cbd5e1; border-radius: 10px; padding: 10px; margin-bottom: 10px; background: #f8fafc; }
                .section h3 { margin: 0 0 6px; font-size: 12px; color: #0f172a; }
                .row { margin: 3px 0; }
            </style>
        </head>
        <body>
            <div class="hero">
                <h1><?= htmlspecialchars((string) $title) ?></h1>
                <p>Rapport client synthese KPI · <?= date('d/m/Y H:i') ?></p>
            </div>

            <div class="cards">
                <table>
                    <tr>
                        <td><strong>Collectes</strong><br><?= (int) ($cards['collectes'] ?? 0) ?></td>
                        <td><strong>Score moyen</strong><br><?= number_format((float) ($cards['score_moyen'] ?? 0), 2, ',', ' ') ?></td>
                        <td><strong>Croissance moyenne</strong><br><?= number_format((float) ($cards['growth_moyen'] ?? 0), 2, ',', ' ') ?>%</td>
                        <td><strong>Performance journaliere</strong><br><?= number_format((float) ($cards['daily_moyen'] ?? 0), 2, ',', ' ') ?></td>
                    </tr>
                </table>
            </div>

            <div class="section">
                <h3>Top publications</h3>
                <?php foreach (array_slice($top, 0, 5) as $item): ?>
                    <div class="row">- <?= htmlspecialchars((string) ($item['publication_titre'] ?? 'Publication')) ?> · Score <?= number_format((float) ($item['score_moyen'] ?? 0), 2, ',', ' ') ?></div>
                <?php endforeach; ?>
                <?php if (empty($top)): ?><div class="row">Aucune publication a afficher.</div><?php endif; ?>
            </div>

            <div class="section">
                <h3>Points faibles</h3>
                <?php foreach (array_slice($weak, 0, 5) as $item): ?>
                    <div class="row">- <?= htmlspecialchars((string) ($item['publication_titre'] ?? 'Publication')) ?> · Score <?= number_format((float) ($item['score_moyen'] ?? 0), 2, ',', ' ') ?></div>
                <?php endforeach; ?>
                <?php if (empty($weak)): ?><div class="row">Aucun point faible majeur detecte.</div><?php endif; ?>
            </div>

            <div class="section">
                <h3>Comparaison des reseaux</h3>
                <?php foreach ($network as $row): ?>
                    <div class="row">- <?= htmlspecialchars((string) ($row['reseau_label'] ?? $row['reseau'] ?? 'Reseau')) ?> · Score <?= number_format((float) ($row['performance_globale'] ?? 0), 2, ',', ' ') ?> · Collectes <?= (int) ($row['collectes'] ?? 0) ?></div>
                <?php endforeach; ?>
                <?php if (empty($network)): ?><div class="row">Aucune comparaison reseau disponible.</div><?php endif; ?>
            </div>

            <div class="section">
                <h3>Recommandations</h3>
                <?php foreach ($insights as $insight): ?>
                    <div class="row">- <?= htmlspecialchars((string) $insight) ?></div>
                <?php endforeach; ?>
                <?php if (empty($insights)): ?><div class="row">Aucune recommandation automatique pour cette periode.</div><?php endif; ?>
            </div>
        </body>
        </html>
        <?php
        return (string) ob_get_clean();
    }

    private static function buildReportHtml($title, array $global, array $items, array $fields, array $sections, array $byPublication, array $byNetwork, array $recommendations) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #1e293b; margin: 20px; }
                .hero { background: linear-gradient(135deg, #0ea5e9 0%, #1d4ed8 100%); color: #fff; border-radius: 12px; padding: 16px 18px; margin-bottom: 12px; }
                .hero h1 { margin: 0 0 4px; font-size: 18px; }
                .hero p { margin: 0; font-size: 10px; opacity: 0.96; }
                .stats { margin: 10px 0 14px; }
                .stats table { width: 100%; border-collapse: separate; border-spacing: 8px; }
                .stats td { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 8px; }
                .card { border: 1px solid #cbd5e1; border-radius: 10px; padding: 10px; margin-bottom: 8px; background: #f8fafc; }
                .field { margin: 3px 0; }
                .label { font-weight: 700; color: #334155; }
            </style>
        </head>
        <body>
            <div class="hero">
                <h1><?= htmlspecialchars((string) $title) ?></h1>
                <p>Rapport PDF professionnel · <?= date('d/m/Y H:i') ?></p>
            </div>
            <?php if (in_array('global', $sections, true)): ?>
                <div class="stats">
                    <table>
                        <tr>
                            <td><strong>Total contenus</strong><br><?= (int) ($global['total_contenus'] ?? 0) ?></td>
                            <td><strong>Videos</strong><br><?= (int) ($global['videos'] ?? 0) ?></td>
                            <td><strong>Visuels</strong><br><?= (int) ($global['visuels'] ?? 0) ?></td>
                            <td><strong>Types non qualifies</strong><br><?= (int) ($global['unknown_type'] ?? 0) ?></td>
                            <td><strong>Avec message</strong><br><?= (int) ($global['with_message'] ?? 0) ?></td>
                            <td><strong>Avec script</strong><br><?= (int) ($global['with_script'] ?? 0) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Date planifiee</strong><br><?= (int) ($global['scheduled'] ?? 0) ?></td>
                            <td><strong>Reseaux uniques</strong><br><?= (int) ($global['network_count'] ?? 0) ?></td>
                            <td><strong>Taux message</strong><br><?= (float) ($global['message_rate'] ?? 0) ?>%</td>
                            <td><strong>Taux script video</strong><br><?= (float) ($global['script_rate'] ?? 0) ?>%</td>
                            <td colspan="2"></td>
                        </tr>
                    </table>
                </div>
            <?php endif; ?>

            <?php if (in_array('publication', $sections, true)): ?>
                <?php foreach ($byPublication as $item): ?>
                    <div class="card">
                        <?php foreach ($fields as $field): ?>
                            <div class="field"><span class="label"><?= htmlspecialchars((string) $field) ?>:</span> <?= nl2br(htmlspecialchars((string) ($item[$field] ?? ''))) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (in_array('network', $sections, true) && !empty($byNetwork)): ?>
                <div class="card">
                    <div class="field"><span class="label">Impact par reseau</span></div>
                    <?php foreach ($byNetwork as $networkStats): ?>
                        <div class="field">
                            <?= htmlspecialchars((string) ($networkStats['reseau'] ?? 'Non defini')) ?>:
                            total=<?= (int) ($networkStats['total'] ?? 0) ?>,
                            videos=<?= (int) ($networkStats['videos'] ?? 0) ?>,
                            visuels=<?= (int) ($networkStats['visuels'] ?? 0) ?>,
                            avec message=<?= (int) ($networkStats['with_message'] ?? 0) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (in_array('recommendations', $sections, true) && !empty($recommendations)): ?>
                <div class="card">
                    <div class="field"><span class="label">Recommandations</span></div>
                    <?php foreach ($recommendations as $recommendation): ?>
                        <div class="field">- <?= htmlspecialchars((string) $recommendation) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($items) && empty($byNetwork) && empty($recommendations)): ?>
                <div>Aucune donnee.</div>
            <?php endif; ?>
        </body>
        </html>
        <?php
        return (string) ob_get_clean();
    }

    private static function renderSimplePdfLines(array $lines, $fileName) {
        $pdf = new SimplePdfBuilder();
        $pdf->addPage();
        foreach ($lines as $line) {
            $pdf->writeLine((string) $line);
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo $pdf->build();
        exit;
    }
}

class SimplePdfBuilder {
    private $pages = [];
    private $currentPage = -1;
    private $currentY = 800;

    public function addPage() {
        $this->pages[] = '';
        $this->currentPage = count($this->pages) - 1;
        $this->currentY = 800;
    }

    public function writeLine($text) {
        if ($this->currentPage < 0) {
            $this->addPage();
        }

        $encodedText = $this->toPdfTextEncoding((string) $text);
        $lines = $this->wrapText($encodedText, 110);
        foreach ($lines as $line) {
            if ($this->currentY < 60) {
                $this->addPage();
            }

            $escaped = $this->escapePdfText($line);
            $this->pages[$this->currentPage] .= "BT /F1 10 Tf 40 {$this->currentY} Td ({$escaped}) Tj ET\n";
            $this->currentY -= 14;
        }
    }

    public function build() {
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';

        $pageCount = count($this->pages);
        $fontObjectId = 3 + ($pageCount * 2);

        $kids = [];
        for ($i = 0; $i < $pageCount; $i++) {
            $pageObjectId = 3 + ($i * 2);
            $kids[] = $pageObjectId . ' 0 R';
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $pageCount . ' >>';

        for ($i = 0; $i < $pageCount; $i++) {
            $pageObjectId = 3 + ($i * 2);
            $contentObjectId = $pageObjectId + 1;
            $stream = $this->pages[$i];

            $objects[$pageObjectId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 ' . $fontObjectId . ' 0 R >> >> /Contents ' . $contentObjectId . ' 0 R >>';
            $objects[$contentObjectId] = '<< /Length ' . strlen($stream) . ' >>' . "\nstream\n" . $stream . "endstream";
        }

        $objects[$fontObjectId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefPosition = strlen($pdf);
        $maxId = max(array_keys($objects));
        $pdf .= 'xref' . "\n";
        $pdf .= '0 ' . ($maxId + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($id = 1; $id <= $maxId; $id++) {
            $offset = $offsets[$id] ?? 0;
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }

        $pdf .= 'trailer << /Size ' . ($maxId + 1) . ' /Root 1 0 R >>' . "\n";
        $pdf .= 'startxref' . "\n" . $xrefPosition . "\n%%EOF";

        return $pdf;
    }

    private function wrapText($text, $maxChars) {
        $text = str_replace(["\r\n", "\r"], "\n", (string) $text);
        $rawLines = explode("\n", $text);
        $lines = [];

        foreach ($rawLines as $line) {
            $line = trim($line);
            if ($line === '') {
                $lines[] = '';
                continue;
            }

            while (strlen($line) > $maxChars) {
                $chunk = substr($line, 0, $maxChars);
                $lastSpace = strrpos($chunk, ' ');
                if ($lastSpace !== false && $lastSpace > 20) {
                    $lines[] = substr($line, 0, $lastSpace);
                    $line = ltrim(substr($line, $lastSpace + 1));
                } else {
                    $lines[] = $chunk;
                    $line = ltrim(substr($line, $maxChars));
                }
            }

            $lines[] = $line;
        }

        return $lines;
    }

    private function escapePdfText($text) {
        $text = str_replace('\\', '\\\\', (string) $text);
        $text = str_replace('(', '\\(', $text);
        $text = str_replace(')', '\\)', $text);
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text) ?? '';
    }

    private function toPdfTextEncoding($text) {
        $text = str_replace("\xC2\xA0", ' ', (string) $text);
        $text = str_replace(["\xE2\x80\x98", "\xE2\x80\x99", "\xE2\x80\x9A", "\xE2\x80\x9B"], "'", $text);
        $text = str_replace(["\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\x9E", "\xE2\x80\x9F"], '"', $text);
        $text = str_replace(["\xE2\x80\x93", "\xE2\x80\x94", "\xE2\x88\x92"], '-', $text);

        if (function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
            if ($converted !== false) {
                return $converted;
            }
        }

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
            if ($converted !== false) {
                return $converted;
            }
        }

        return preg_replace('/[^\x20-\x7E]/', '?', $text) ?? '';
    }
}
