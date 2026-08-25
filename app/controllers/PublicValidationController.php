<?php
class PublicValidationController extends Controller {
    private $calendrierModel;

    public function __construct() {
        parent::__construct();
        $this->calendrierModel = new CalendrierModel();
    }

    public function index($token = null) {
        $token = trim((string) $token);
        if ($token === '') {
            http_response_code(404);
            echo 'Lien invalide.';
            return;
        }

        try {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                $submission = $this->calendrierModel->applyPublicValidationDecision(
                    $token,
                    (int) ($_POST['task_id'] ?? 0),
                    (string) ($_POST['decision'] ?? ''),
                    (string) ($_POST['comment'] ?? ''),
                    $_POST['note_sur_10'] ?? null
                );

                $typedEmail = trim((string) ($_POST['client_email'] ?? ''));
                if ($typedEmail !== '' && filter_var($typedEmail, FILTER_VALIDATE_EMAIL)) {
                    $submission['client_email'] = $typedEmail;
                }
                $submission['public_url'] = route_url('/public-validation/index/' . $token);

                $mailResult = EmailNotificationService::sendPublicValidationNotifications($submission);
                $_SESSION['public_validation_confirmation'] = [
                    'submission' => $submission,
                    'mail' => $mailResult,
                ];

                header('Location: ' . route_url('/public-validation/confirmation/' . $token));
                exit;
            }

            $workspace = $this->calendrierModel->getPublicValidationWorkspace($token);
            if (!$workspace) {
                http_response_code(404);
                echo 'Lien invalide ou expire.';
                return;
            }

            $this->render('public/validation', [
                'pageTitle' => 'Validation client',
                'workspace' => $workspace,
                'token' => $token,
            ]);
        } catch (Throwable $exception) {
            http_response_code(400);
            echo htmlspecialchars($exception->getMessage());
        }
    }

    public function confirmation($token = null) {
        $token = trim((string) $token);
        if ($token === '') {
            http_response_code(404);
            echo 'Lien invalide.';
            return;
        }

        $workspace = $this->calendrierModel->getPublicValidationWorkspace($token);
        if (!$workspace) {
            http_response_code(404);
            echo 'Lien invalide ou expire.';
            return;
        }

        $confirmation = $_SESSION['public_validation_confirmation'] ?? null;
        unset($_SESSION['public_validation_confirmation']);

        $this->render('public/validation-confirmation', [
            'pageTitle' => 'Confirmation validation client',
            'workspace' => $workspace,
            'confirmation' => $confirmation,
            'token' => $token,
        ]);
    }
}
