<?php
class ExportDocumentController extends Controller {
    private $calendrierModel;

    public function __construct() {
        parent::__construct();
        $this->requireAuth();
        $this->requirePermission('calendar.view');
        $this->calendrierModel = new CalendrierModel();
    }

    public function index() {
        $preset = trim((string) ($_GET['period'] ?? 'current_month'));
        $filters = [
            'client_id' => trim((string) ($_GET['client_id'] ?? '')),
            'from' => trim((string) ($_GET['from'] ?? '')),
            'to' => trim((string) ($_GET['to'] ?? '')),
            'period' => $preset,
        ];

        if ($preset !== '' && $filters['from'] === '' && $filters['to'] === '') {
            $range = $this->resolvePeriodPreset($preset);
            if ($range) {
                $filters['from'] = $range['from'];
                $filters['to'] = $range['to'];
            }
        }

        $availableFields = [
            'client',
            'projet',
            'periode_mois',
            'titre',
            'type_livrable',
            'date_prevue',
            'reseau',
            'impact_global',
            'sujet',
            'message',
            'script_contenu',
            'plan_script',
            'texte_script',
        ];
        $defaultFields = ['client', 'projet', 'periode_mois', 'titre', 'type_livrable', 'date_prevue', 'reseau', 'sujet', 'message'];
        $availableReportSections = ['global', 'publication', 'network', 'recommendations'];
        $defaultReportSections = ['global', 'publication', 'network', 'recommendations'];

        if ($this->isPost()) {
            $planIds = array_values(array_filter(array_map('intval', (array) ($_POST['plan_ids'] ?? []))));
            $action = (string) ($_POST['action'] ?? '');
            $selectedFields = $this->normalizeSelectedFields((array) ($_POST['selected_fields'] ?? []), $availableFields, $defaultFields);
            $selectedReportSections = $this->normalizeSelectedSections((array) ($_POST['report_sections'] ?? []), $availableReportSections, $defaultReportSections);

            if ($action === 'export_calendar') {
                $rows = $this->calendrierModel->getCalendarExportRows($planIds, !empty($_POST['include_scripts']));
                $rows = $this->selectFieldsFromRows($rows, $selectedFields, $defaultFields);
                $this->downloadCsv($this->documentFilename('calendrier-editorial','csv',$rows), $rows, $selectedFields);
                return;
            }
            if ($action === 'export_scripts') {
                $rows = $this->calendrierModel->getScriptsExportRows($planIds);
                $scriptDefaults = ['client', 'projet', 'periode_mois', 'titre', 'script_contenu'];
                $scriptFields = $this->normalizeSelectedFields((array) ($_POST['selected_fields'] ?? []), $availableFields, $scriptDefaults);
                $rows = $this->selectFieldsFromRows($rows, $scriptFields, $scriptDefaults);
                $this->downloadCsv($this->documentFilename('scripts-calendriers','csv',$rows), $rows, $scriptFields);
                return;
            }
            if ($action === 'export_scripts_pdf') {
                $rows = $this->calendrierModel->getScriptsExportRows($planIds);
                PdfExportService::outputBriefsPdf('Briefs et scripts éditoriaux', $rows, $this->documentFilename('briefs-scripts','pdf',$rows));
                return;
            }
            if ($action === 'export_reports') {
                $data = $this->calendrierModel->getReportsExportData($planIds);
                $this->downloadReportsTxt('rapport-calendriers.txt', $data, $selectedFields, $selectedReportSections);
                return;
            }
            if ($action === 'export_calendar_pdf') {
                $rows = $this->calendrierModel->getCalendarExportRows($planIds, !empty($_POST['include_scripts']));
                PdfExportService::outputCalendarPdf('Calendrier éditorial', $rows, $this->documentFilename('calendrier-editorial','pdf',$rows));
                return;
            }
            if ($action === 'export_reports_pdf') {
                $data = $this->calendrierModel->getReportsExportData($planIds);
                PdfExportService::outputReportPdf('Rapport des calendriers', $data, $selectedFields, $this->documentFilename('rapport-calendriers','pdf',(array)($data['by_publication']??[])), $selectedReportSections);
                return;
            }
        }

        $this->render('export-document/index', [
            'pageTitle' => 'Export documents',
            'clients' => $this->calendrierModel->getAllClientsSimple(),
            'filters' => $filters,
            'plans' => $this->calendrierModel->getExportableCalendars($filters),
            'availableFields' => $availableFields,
            'defaultSelectedFields' => $defaultFields,
            'availableReportSections' => $availableReportSections,
            'defaultReportSections' => $defaultReportSections,
        ]);
    }

    private function downloadCsv($fileName, array $rows, array $headers = []) {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            exit;
        }

        if (!empty($rows)) {
            $headerRow = !empty($headers) ? $headers : array_keys($rows[0]);
            fputcsv($out, $headerRow);
            foreach ($rows as $row) {
                $line = [];
                foreach ($headerRow as $key) {
                    $line[] = (string) ($row[$key] ?? '');
                }
                fputcsv($out, $line);
            }
        } else {
            fputcsv($out, ['message']);
            fputcsv($out, ['Aucune donnee']);
        }

        fclose($out);
        exit;
    }

    private function downloadReportsTxt($fileName, array $data, array $fields, array $sections) {
        header('Content-Type: text/plain; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');

        $global = (array) ($data['global'] ?? []);
        if (in_array('global', $sections, true)) {
            echo "IMPACT GLOBAL\n";
            echo "- Total contenus: " . (int) ($global['total_contenus'] ?? 0) . "\n";
            echo "- Videos: " . (int) ($global['videos'] ?? 0) . "\n";
            echo "- Visuels: " . (int) ($global['visuels'] ?? 0) . "\n";
            echo "- Types non qualifies: " . (int) ($global['unknown_type'] ?? 0) . "\n";
            echo "- Avec message: " . (int) ($global['with_message'] ?? 0) . "\n";
            echo "- Avec script: " . (int) ($global['with_script'] ?? 0) . "\n\n";
            echo "- Date planifiee renseignee: " . (int) ($global['scheduled'] ?? 0) . "\n";
            echo "- Reseaux uniques: " . (int) ($global['network_count'] ?? 0) . "\n";
            echo "- Taux contenus avec message: " . (float) ($global['message_rate'] ?? 0) . "%\n";
            echo "- Taux videos avec script: " . (float) ($global['script_rate'] ?? 0) . "%\n\n";
        }

        if (in_array('publication', $sections, true)) {
            echo "IMPACT PAR PUBLICATION\n";
            foreach ((array) ($data['by_publication'] ?? []) as $index => $item) {
                echo "\n----------------------------------------\n";
                echo 'Publication ' . ($index + 1) . "\n";
                foreach ($fields as $field) {
                    echo ucfirst(str_replace('_', ' ', $field)) . ': ' . (string) ($item[$field] ?? '') . "\n";
                }
            }
            echo "\n";
        }

        if (in_array('network', $sections, true)) {
            echo "IMPACT PAR RESEAU\n";
            foreach ((array) ($data['by_network'] ?? []) as $networkStats) {
                echo "- " . (string) ($networkStats['reseau'] ?? 'Non defini')
                    . ': total=' . (int) ($networkStats['total'] ?? 0)
                    . ', videos=' . (int) ($networkStats['videos'] ?? 0)
                    . ', visuels=' . (int) ($networkStats['visuels'] ?? 0)
                    . ', avec message=' . (int) ($networkStats['with_message'] ?? 0)
                    . "\n";
            }
            echo "\n";
        }

        if (in_array('recommendations', $sections, true)) {
            echo "RECOMMANDATIONS\n";
            foreach ((array) ($data['recommendations'] ?? []) as $recommendation) {
                echo '- ' . (string) $recommendation . "\n";
            }
            echo "\n";
        }

        exit;
    }

    private function normalizeSelectedSections(array $submitted, array $allowed, array $fallback) {
        $allowedMap = array_fill_keys($allowed, true);
        $selected = [];
        foreach ($submitted as $section) {
            $section = trim((string) $section);
            if ($section !== '' && isset($allowedMap[$section]) && !in_array($section, $selected, true)) {
                $selected[] = $section;
            }
        }

        return !empty($selected) ? $selected : $fallback;
    }

    private function normalizeSelectedFields(array $submitted, array $allowed, array $fallback) {
        $allowedMap = array_fill_keys($allowed, true);
        $selected = [];
        foreach ($submitted as $field) {
            $field = trim((string) $field);
            if ($field !== '' && isset($allowedMap[$field]) && !in_array($field, $selected, true)) {
                $selected[] = $field;
            }
        }

        return !empty($selected) ? $selected : $fallback;
    }

    private function selectFieldsFromRows(array $rows, array $fields, array $fallback) {
        $selectedFields = !empty($fields) ? $fields : $fallback;
        $result = [];

        foreach ($rows as $row) {
            $filtered = [];
            foreach ($selectedFields as $field) {
                $filtered[$field] = (string) ($row[$field] ?? '');
            }
            $result[] = $filtered;
        }

        return $result;
    }

    private function documentFilename(string$type,string$extension,array$rows): string {
        $clients=[];foreach($rows as$row){$name=trim((string)($row['client']??$row['client_name']??''));if($name!=='')$clients[$name]=true;}
        $client=count($clients)===1?(string)array_key_first($clients):(count($clients)>1?'multi-clients':'selection');
        $slug=trim(strtolower((string)preg_replace('/[^a-z0-9]+/','-',iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$client)?:$client)),'-')?:'selection';
        return $slug.'-'.$type.'-'.date('Y-m-d').'.'.$extension;
    }
}
