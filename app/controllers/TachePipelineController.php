<?php
class TachePipelineController extends CrudController {
    protected $moduleKey = 'tache-pipeline';

    public function __construct() {
        parent::__construct();
        $this->model = new TaskPipelineModel();
    }

    public function index() {
        $this->flash('error', 'Les taches pipeline se pilotent depuis le calendrier projet.');
        $this->redirect('/calendrier');
    }

    public function create() {
        $this->flash('error', 'La creation manuelle de taches pipeline est desactivee pour eviter les doublons UX.');
        $this->redirect('/calendrier');
    }

    public function show($id) {
        $this->redirect('/calendrier/task/' . $id);
    }

    public function edit($id) {
        $this->redirect('/calendrier/task/' . $id);
    }

    public function delete($id) {
        $this->flash('error', 'Supprime ou ajuste les taches via le pipeline projet, pas via le CRUD technique.');
        $this->redirect('/calendrier/task/' . $id);
    }

    private function buildTaskHint($context) {
        if (!$context || empty($context['type_livrable'])) {
            return null;
        }

        if ($context['type_livrable'] === 'Video') {
            return 'Livrable video: le suivi doit distinguer le script, le tournage et le montage. Assigne si besoin des personnes differentes entre captation et post-production.';
        }

        if (strcasecmp((string) ($context['sous_type'] ?? ''), 'Carrousel') === 0) {
            $pages = max(1, (int) ($context['nombre_pages'] ?? 1));
            return 'Livrable carrousel: attends ' . $pages . ' visuel(x), un PDF de validation et un fichier source PSD/PSB pour les retouches urgentes.';
        }

        return 'Livrable visuel: ajoute les exports finaux PNG/JPEG/PDF utiles ainsi que le fichier source PSD/PSB quand la tache est livree.';
    }

    private function validateTaskFiles(array $payload, array $record, $context) {
        if (!$context || ($payload['statut'] ?? $record['statut'] ?? '') !== 'Terminee') {
            return;
        }

        if (($context['type_livrable'] ?? null) !== 'Visuel') {
            return;
        }

        if (!in_array((string) ($context['type_tache'] ?? ''), ['Production', 'Validation interne', 'Validation client'], true)) {
            return;
        }

        $files = $this->decodeFiles($payload['fichiers_livres'] ?? ($record['fichiers_livres'] ?? null));
        if (empty($files)) {
            throw new RuntimeException('Cette tache visuelle ne peut pas etre terminee sans fichiers livres.');
        }

        $extensions = array_map(function ($file) {
            return strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        }, $files);

        $imageCount = 0;
        $hasPdf = false;
        $hasSource = false;
        foreach ($extensions as $extension) {
            if (in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], true)) {
                $imageCount++;
            }
            if ($extension === 'pdf') {
                $hasPdf = true;
            }
            if (in_array($extension, ['psd', 'psb'], true)) {
                $hasSource = true;
            }
        }

        if (!$hasSource) {
            throw new RuntimeException('Ajoute au moins un fichier source PSD ou PSB avant de terminer cette tache.');
        }

        if (strcasecmp((string) ($context['sous_type'] ?? ''), 'Carrousel') === 0) {
            $expectedPages = max(1, (int) ($context['nombre_pages'] ?? 1));
            if ($imageCount < $expectedPages) {
                throw new RuntimeException('Le carrousel attend au moins ' . $expectedPages . ' image(s) exportee(s).');
            }
            if (!$hasPdf) {
                throw new RuntimeException('Le carrousel doit inclure un PDF de validation avant de terminer la tache.');
            }
            return;
        }

        if ($imageCount < 1 && !$hasPdf) {
            throw new RuntimeException('Ajoute au moins un export PNG/JPG/JPEG ou PDF avant de terminer cette tache visuelle.');
        }
    }

    private function decodeFiles($value) {
        if (empty($value)) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
