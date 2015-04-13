<?php

namespace OCommons;

class Module
{

    public function getConfig()
    {
        return include dirname(dirname(__DIR__)) . '/config/module.config.php';
    }

    public function onBootstrap(\Zend\Mvc\MvcEvent $mvcEvent)
    {
        $application    = $mvcEvent->getApplication();
        $serviceManager = $application->getServiceManager();

        $controllerLoader = $serviceManager->get('ControllerLoader');
        /* @var $controllerLoader \Zend\Mvc\Service\ControllerLoaderFactory */
        $controllerLoader->addInitializer(array($this, 'controllerInitialize'));
    }

    /**
     * 
     * @param \Zend\Mvc\Controller\AbstractController $instance
     */
    public function controllerInitialize($instance)
    {
        if ($instance instanceof Controller\InitializableInterface) {
            $instance->initialize();
        }
    }

    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ ,
                ),
            ),
        );
    }

}
