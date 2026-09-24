<?php

namespace App\AdminModule\Presenters;

use Nette\Application\UI\Form;

class SlidersPresenter extends BaseModulePresenter
{
    /** @var \SlidersRepository @inject */
    public $repository;

    /** @var \SlidersService @inject */
    public $service;


    /**
     *
     */
    public function actionDefault()
    {
        $this['filters-form']->addText('name', 'admin.terms.name');

        parent::actionDefault();
    }


    /**
     * @param Form $form
     * @return Form
     */
    protected function setFormFields(Form $form): Form
    {
        $form->addText('name', 'admin.terms.name')->setRequired(TRUE);
        $form->addUpload('image', 'admin.terms.image');
        $form->addTextArea('text', 'admin.terms.text');
        $form->addText('button_text', 'admin.terms.buttonText1');
        $form->addText('button_link', 'admin.terms.buttonLink1');
        $form->addText('button_text_2', 'admin.terms.buttonText2');
        $form->addText('button_link_2', 'admin.terms.buttonLink2');

        return parent::setFormFields($form);
    }
}
