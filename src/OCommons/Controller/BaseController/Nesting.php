<?php

namespace OCommons\Controller\BaseController;

/**
 * Description of NestingLevel
 *
 * @author oprokidnev
 */
final class Nesting
{

    protected $index    = null;
    protected $template = null;

    public static function factory($index, $template)
    {
        $self =  new static;
        $self->setIndex($index);
        $self->setTemplate($template);
        return $self;
    }

    public function getIndex()
    {
        return $this->index;
    }
    

    public function getTemplate()
    {
        return $this->template;
    }

    public function setIndex($index)
    {
        $this->index = $index;
        return $this;
    }

    public function setTemplate($template)
    {
        $this->template = $template;
        return $this;
    }

}
