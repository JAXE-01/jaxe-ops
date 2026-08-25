<?php
class ClientController extends CrudController {
    protected $moduleKey = 'client';

    public function show($id) {
        $this->requireModulePermission('view');
        $record = $this->model->getById($id);
        if (!$record) {
            $this->flash('error', 'Element introuvable.');
            $this->redirect('/' . $this->module['route']);
        }

        $socialModel = new ClientSocialAccountModel();
        $accounts = $socialModel->getByClientId((int) $id);
        $editAccountId = (int) ($_GET['social_edit_id'] ?? 0);
        $editingAccount = $editAccountId > 0 ? $socialModel->getById($editAccountId, (int) $id) : null;

        $returnTo = $this->resolveReturnTo('/' . $this->module['route']);

        $this->render('crud/show', [
            'pageTitle' => $this->module['label'],
            'module' => $this->module,
            'record' => $record,
            'options' => $this->loadOptions(),
            'returnTo' => $returnTo,
            'backLabel' => $this->buildBackLabel($returnTo, '/' . $this->module['route']),
            'clientSocialAccounts' => $accounts,
            'editingSocialAccount' => $editingAccount,
        ]);
    }

    public function saveSocialAccount($clientId) {
        $this->requireModulePermission('manage');
        $clientId = (int) $clientId;
        if ($clientId <= 0 || !$this->isPost()) {
            $this->redirect('/client');
        }

        try {
            $model = new ClientSocialAccountModel();
            $model->save([
                'id' => (int) ($_POST['social_account_id'] ?? 0),
                'client_id' => $clientId,
                'reseau' => (string) ($_POST['reseau'] ?? ''),
                'compte_label' => (string) ($_POST['compte_label'] ?? ''),
                'identifiant_compte' => (string) ($_POST['identifiant_compte'] ?? ''),
                'page_id' => (string) ($_POST['page_id'] ?? ''),
                'page_nom' => (string) ($_POST['page_nom'] ?? ''),
                'access_token' => (string) ($_POST['access_token'] ?? ''),
                'refresh_token' => (string) ($_POST['refresh_token'] ?? ''),
                'statut' => (string) ($_POST['statut'] ?? 'Actif'),
                'is_default' => !empty($_POST['is_default']) ? 1 : 0,
                'notes' => (string) ($_POST['notes'] ?? ''),
            ]);
            $this->flash('success', 'Compte social enregistre.');
        } catch (Throwable $exception) {
            $this->flash('error', $exception->getMessage());
        }

        $this->redirect('/client/show/' . $clientId);
    }

    public function deleteSocialAccount($clientId, $accountId) {
        $this->requireModulePermission('manage');
        $clientId = (int) $clientId;
        $accountId = (int) $accountId;
        if ($clientId <= 0 || $accountId <= 0) {
            $this->redirect('/client');
        }

        try {
            $model = new ClientSocialAccountModel();
            $deleted = $model->delete($accountId, $clientId);
            if ($deleted > 0) {
                $this->flash('success', 'Compte social supprime.');
            } else {
                $this->flash('error', 'Compte social introuvable.');
            }
        } catch (Throwable $exception) {
            $this->flash('error', $exception->getMessage());
        }

        $this->redirect('/client/show/' . $clientId);
    }
}
