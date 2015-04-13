<?php

namespace OCommons\Controller;

/**
 * @author Andrey Oprokidnev
 */
abstract class BaseController extends \Zend\Mvc\Controller\AbstractActionController implements InitializableInterface
{

    const NESTING_LAYOUT           = 'layout';
    const NESTING_BODY             = 'body';
    const NESTING_ACTION           = 'action';
    const EVENT_VIEW_MODEL__CREATE = 'view_model.create';
    const EVENT_ACTION_RESPONSE    = 'action_response';

    protected $options        = [];
    protected $optionDefaults = [
        'nesting' => [
            'templates' => [
                self::NESTING_LAYOUT => 'o-commons/layout/layout',
                self::NESTING_BODY => 'o-commons/layout/body',
                self::NESTING_ACTION => null,
            ],
            'order' => [
                self::NESTING_LAYOUT, self::NESTING_BODY, self::NESTING_ACTION
            ],
        ],
    ];

    public function onDispatch(\Zend\Mvc\MvcEvent $e)
    {
        $actionResponse = parent::onDispatch($e);
        if (is_array($actionResponse)) {
            /** @var $vm \Zend\View\Model\ViewModel */
            $vm = $this->handleActionResponse($actionResponse);
            $e->setResult($vm);
        }
        return $actionResponse;
    }

    public function initialize()
    {
        $eventManager = $this->getEventManager();

        $eventManager->attach(self::EVENT_VIEW_MODEL__CREATE, array($this, 'onViewModelCreate'), 500);
        $eventManager->attach(self::EVENT_ACTION_RESPONSE, array($this, 'onActionResponse'), 500);
    }

    /**
     *
     * @var \Doctrine\Common\Annotations\AnnotationReader
     */
    protected $annotationReader = null;

    /**
     * 
     * @return \Doctrine\Common\Annotations\AnnotationReader
     */
    protected function getAnnotationReader()
    {
        if ($this->annotationReader === null) {
            $this->annotationReader = new \Doctrine\Common\Annotations\AnnotationReader();
        }
        return $this->annotationReader;
    }

    protected $nestings = null;

    /**
     * 
     * @return BaseController\Nesting[]
     */
    protected function getNestings()
    {
        if ($this->nestings === null) {

            $nestingOptions = $this->getNestingOptions();
            $nestings       = [];
            foreach ($nestingOptions['order'] as $level) {
                $nesting = BaseController\Nesting::factory($level, $nestingOptions['templates'][$level]);
                array_push($nestings, $nesting);
            }
            $this->nestings = $nestings;
        }
        return $this->nestings;
    }

    public function setNestingOptions($options)
    {
        return $this->options = \Zend\Stdlib\ArrayUtils::merge($this->optionDefaults, $options);
    }

    public function getNestingOptions()
    {
        if (!isset($this->options['nesting'])) {
            $this->setNestingOptions([]);
        }
        return $this->options['nesting'];
    }

    /**
     * 
     * @param type $actionResponse
     * @return \Zend\View\Model\ViewModel
     */
    public function handleActionResponse($actionResponse)
    {
        $eventManager = $this->getEventManager();

        if (is_array($actionResponse) || ( $actionResponse instanceof \Traversable && !( $actionResponse instanceof \Zend\View\Model\ViewModel))) {
            if (is_array($actionResponse)) {
                $actionResponse = new \Zend\Stdlib\ArrayObject($actionResponse);
            }
        }
        /**
         * Setting additional data for ActionResponse
         */
        $eventManager->trigger(self::EVENT_ACTION_RESPONSE, $actionResponse);

        $nestings = $this->getNestings();

        $parentViewModel   = null;
        $reverseViewModels = [];
        foreach ($nestings as $nesting) {
            if ($nesting->getIndex() === self::NESTING_LAYOUT) {
                $this->layout()->setTemplate($nesting->getTemplate());
                continue;
            }
            $viewModel = new \Zend\View\Model\ViewModel($actionResponse);
            $eventManager->trigger(self::EVENT_VIEW_MODEL__CREATE, $viewModel, compact('nesting'), function($result) {
                return gettype($result) === 'object' && $result instanceof \Zend\View\Model\ViewModel;
            });
            array_push($reverseViewModels, $viewModel);
            /**
             * Nest view models
             */
            if ($parentViewModel === null) {
                $parentViewModel = $viewModel;
            } else {
                $parentViewModel->addChild($viewModel);
                $parentViewModel = $viewModel;
            }
        }

        return current($reverseViewModels);
    }

    public function onViewModelCreate(\Zend\EventManager\EventInterface $event)
    {
        $viewModel = $event->getTarget();
        extract($event->getParams());
        /* @var $nesting BaseController\Nesting */

        if ($nesting->getIndex() === self::NESTING_ACTION) {
            return $this->onActionViewModelCreate($event);
        }

        $viewModel->setTemplate($nesting->getTemplate());
        return $viewModel;
    }

    function onActionViewModelCreate(\Zend\EventManager\EventInterface $event)
    {
        $viewModel = $event->getTarget();
        extract($event->getParams());
        /* @var $nesting BaseController\Nesting */

        $viewModel->setTemplate($this->getActionTemplate());
        return $viewModel;
    }

    protected function getActionTemplate()
    {
        $routeMatch     = $this->getEvent()->getRouteMatch();
        $moduleName     = $routeMatch->getParam('module', null);
        $namespace      = $routeMatch->getParam('__NAMESPACE__');
        $controllerName = $routeMatch->getParam('__CONTROLLER__');
        $controller     = $routeMatch->getParam('controller');
        $action         = $routeMatch->getParam('action');

        if ($this instanceof ComponentInterface) {
            $templateName = $routeMatch->getParam('template', null);
        } else {
            $templateName = null;
        }
        $inflector = new \Zend\Filter\Word\CamelCaseToDash();

        /**
         * Parse prepaired data
         */
        if ($namespace !== null && $controllerName !== null) {
            $controllerPath      = '';
            $controllerClassName = $namespace . '\\' . $controllerName;
            //Выясняем требуюемую область видимости
            $controllerSubNs     = array_slice(explode('\\', $controllerClassName), 2);

            foreach ($controllerSubNs as $nsIndex => $ns) {
                $controllerPath.=$ns . ($nsIndex == (count($controllerSubNs) - 1) ? '' : '/');
            }
        } else {
            if (preg_match('/^(?<moduleName>[^\\\\]+)\\\\Controller\\\\(?<controllerPath>.+)$/siU', $controller, $pregs)) {
                extract($pregs);
            }
        }

        $path = [$moduleName, $controllerPath, $templateName, $action];

        /**
         * Inflect
         */
        foreach ($path as $key => &$part) {
            if ($part === null) {
                unset($path[$key]);
            }
            $part = strtolower($inflector->filter($part));
        }

        return implode($path, '/');
    }
    /**
     * 
     * @param \Zend\EventManager\EventInterface $event
     */
    public function onActionResponse(\Zend\EventManager\EventInterface $event){
        $actionResponse = $event->getTarget();
        /* @var $actionResponse \Zend\Stdlib\ArrayObject */
        if(method_exists($this, 'getCommons')){
            $commons = $this->getCommons();
            foreach ($commons as $key => $value) {
                if(!isset($actionResponse[$key])){
                    $actionResponse[$key] = $value;
                }
            }
        }
    }
}
