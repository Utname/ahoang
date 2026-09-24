<?php

namespace App\AdminModule\Presenters;


class HomepagePresenter extends BasePresenter
{
    public function startup()
    {
        parent::startup();
        $this->redirect('Pages:');
    }
}
