<?php

namespace App\AdminModule\Presenters;

use Nette\Application\UI\Form;

class BranchesPresenter extends BaseModulePresenter
{
    /** @var \BranchesRepository @inject */
    public $repository;

    /** @var \SlidersService @inject */
    public $service;


    /**
     *
     */
    public function actionDefault()
    {
        $this['filters-form']->addText('name', 'admin.terms.name');
        $this['filters-form']->addText('custom__address', '');

        $this['filters']->setCustomFilters(function ($selection, $filters) {
            if (isset($filters['address']) && $filters['address']) {
                $address = '%' . $filters['address'] . '%';

                $selection->where('street LIKE ? OR city LIKE ? OR zip LIKE ?', $address, $address, $address);
            }
        });

        parent::actionDefault();
    }


    /**
     * @param Form $form
     * @return Form
     */
    protected function setFormFields(Form $form): Form
    {
        $form->addText('name', 'admin.terms.name')->setRequired(TRUE);
        $form->addText('slug', 'admin.terms.slug')->setRequired(TRUE);
        $form->addText('street', 'admin.terms.street');
        $form->addText('city', 'admin.terms.city');
        $form->addText('zip', 'admin.terms.zipcode');
        $form->addText('phone', 'admin.terms.phone');
        $form->addText('email', 'admin.terms.email');
        $form->addText('lat', 'admin.terms.lat');
        $form->addText('lng', 'admin.terms.lng');
        $form->addText('reg_number', 'admin.terms.regNumber');
        $form->addText('vat_number', 'admin.terms.vatNumber');
        $form->addText('google_profile_url', 'admin.terms.googleProfile');
        $form->addText('fb_url', 'admin.terms.fbPageUrl');
        $form->addTextArea('google_maps_link', 'admin.terms.googleMapsLink');
        $form->addTextArea('text', 'admin.terms.text');
        $form->addTextArea('address', 'admin.terms.address');
        $form->addTextArea('partner', 'admin.terms.partner');
        $form->addTextArea('chain_store', 'admin.terms.chain_store');
        $form->addTextArea('sale_text', 'admin.terms.text');
        $form->addTextArea('detail_text', 'admin.terms.howToUs');
		$form->addUpload('image', 'admin.terms.image');
		$form->addUpload('image_detail', 'admin.terms.imageDetail');

        return parent::setFormFields($form);
    }
}
