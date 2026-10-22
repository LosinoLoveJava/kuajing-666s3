<?php

namespace App\Auth;

use Illuminate\Auth\SessionGuard;

class PortalSessionGuard extends SessionGuard
{
    public function getName()
    {
        return parent::getName().'_'.$this->request->attributes->get('portal', 'customer');
    }

    public function getRecallerName()
    {
        return parent::getRecallerName().'_'.$this->request->attributes->get('portal', 'customer');
    }
}
