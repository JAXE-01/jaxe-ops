<?php
class PricingController extends Controller {
    public function index(){if($this->currentUser())$this->redirect('/account');$this->render('pricing/index',['pageTitle'=>'Tarifs fondateurs']);}
}
