<?php

/**
 * Bricks Framework & Bricks CMS
 * http://bricks-cms.org
 *
 * The MIT License (MIT)
 * Copyright (c) 2015 bricks-cms.org
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace Bricks\Acl;

use Bricks\Cms\Controller\IndexController;
use Zend\ModuleManager\Feature\ConfigProviderInterface;
use Zend\ModuleManager\Feature\AutoloaderProviderInterface;
use Zend\Mvc\MvcEvent;

class Module implements ConfigProviderInterface, AutoloaderProviderInterface {

    const VERSION = '0.1.1';

    public function getConfig(){
        return array_replace_recursive(
            require(__DIR__.'/../config/module.config.php'),
            require(__DIR__.'/../config/bricks.config.php')
        );
    }

    public function getAutoloaderConfig()
    {
        return [
            'Zend\Loader\StandardAutoloader' => [
                'namespaces' => [
                    __NAMESPACE__ => __DIR__
                ]
            ]
        ];
    }

    public function onBootstrap(MvcEvent $event){
        $loader = $event->getApplication()->getServiceManager()->get('Bricks\Loader\Loader');
        $config = $event->getApplication()->getServiceManager()->get('Bricks\Config\Config');

        /** @var \Zend\Permissions\Acl\Acl $acl */
        $acl = $loader->get('Zend\Permissions\Acl\Acl');

        foreach(array_keys($config->get('bricks-acl.roles')) AS $role){
            $role = $loader->get('Zend\Permissions\Acl\Role\GenericRole',array($role));
            $acl->addRole($role);
        }

        foreach($config->get('bricks-acl.roles') AS $role => $parents){
            $role = $loader->get('Zend\Permissions\Acl\Role\GenericRole',array($role));
            if($acl->hasRole($role)){
                $acl->removeRole($role);
            }
            $acl->addRole($role,$parents);
        }

        foreach($config->get('bricks-acl.resources') AS $resource => $roles){
            $acl->addResource($resource);
            foreach($roles AS $role){
                $acl->allow($role,$resource);
            }
        }

        $config = $event->getApplication()->getServiceManager()->get('Config');
        if(isset($config['navigation'])){
            foreach(array_keys($config['navigation']) AS $name){
                $nav = $event->getApplication()->getServiceManager()->get($name);
                foreach($nav AS $page) {
                    $this->setPermission($page, $acl);
                }
            }
        }
    }

    public function setPermission($page,$acl){
        $page->setResource(IndexController::class);
        $page->setPrivilege('index');
        if(isset($page->pages)){
            foreach($page->pages AS $_page){
                $this->setPermission($_page,$acl);
            }
        }
    }

}