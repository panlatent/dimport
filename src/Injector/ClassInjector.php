<?php
/**
 * Container - A depend inject container
 *
 * @author  panlatent@gmail.com
 * @link    https://github.com/panlatent/container
 * @license https://opensource.org/licenses/MIT
 */

namespace Panlatent\Container\Injector;

use Panlatent\Container\Injector;
use Psr\Container\ContainerInterface;
use ReflectionClass;

class ClassInjector extends Injector
{
    const WITH_CONSTRUCTOR = 128;
    const WITH_INTERFACE = 256;
    const WITH_SETTER = 512;
    const WITH_SETTER_ANNOTATE = 1024;

    protected $class;

    protected $instance;

    protected $parameterTypeCache = [];

    protected $interfaces = [];

    protected $setters = [];

    protected $isConstructor;

    protected $isInterface;

    protected $isSetter;

    protected $isSetterAnnotate;

    public function __construct(ContainerInterface $container, $context)
    {
        parent::__construct($container, $context);

        if (is_object($context)) {
            $this->instance = $context;
        }
        $this->class = new ReflectionClass($context);
        $this->withOption(static::WITH_CONSTRUCTOR);
    }

    public function withConstructor()
    {
        $this->isConstructor = true;

        return $this;
    }

    public function withoutConstructor()
    {
        $this->isConstructor = false;

        return $this;
    }

    public function withInterface($name)
    {
        if( ! array_search($name, $this->interfaces)) {
            $this->interfaces[] = $name;
        }

        return $this;
    }

    public function withInterfaces($names)
    {
        $this->interfaces = array_merge($this->interfaces, $names);

        return $this;
    }

    public function withSetter($name)
    {
        if ( ! array_search($name, $this->setters)) {
            $this->setters[] = $name;
        }

        return $this;
    }

    public function withSetters($names)
    {
        $this->setters = array_merge($this->setters, $names);

        return $this;
    }

    public function getOption()
    {
        return parent::getOption() |
            $this->isConstructor * static::WITH_CONSTRUCTOR |
            $this->isInterface * static::WITH_INTERFACE |
            $this->isSetter * static::WITH_SETTER |
            $this->isSetterAnnotate * static::WITH_SETTER_ANNOTATE;
    }

    /**
     * @param $option
     * @return ClassInjector
     */
    public function setOption($option)
    {
        parent::setOption($option);

        $this->isConstructor = (($option & static::WITH_CONSTRUCTOR) ==
            static::WITH_CONSTRUCTOR);
        $this->isInterface = (($option & static::WITH_INTERFACE) ==
            static::WITH_INTERFACE);
        $this->isSetter = (($option & static::WITH_SETTER) ==
            static::WITH_SETTER);
        $this->isSetterAnnotate = (($option & static::WITH_SETTER_ANNOTATE) ==
            static::WITH_SETTER_ANNOTATE);
        
        return $this;
    }

    public function handle()
    {
        if ( ! is_object($this->context)) {
            if ($this->isConstructor) {
                $this->injectConstructor();
            } else {
                $this->instance = $this->class->newInstanceWithoutConstructor();
            }
        }

        if ($this->isInterface) {
            $this->injectInterface();
        }

        if ($this->isSetter) {
            $this->injectSetter();
        }
    }

    public function getInstance()
    {
        return $this->instance;
    }

    public function getReturn($name, $extraParameterValues = [])
    {
        if ( ! isset($this->parameterTypeCache[$name])) {
            $method = $this->class->getMethod($name);
            if ( ! ($parameters = $method->getParameters())) {
                $this->parameterTypeCache[$name] = false;

                return call_user_func([$this->instance, $name]);
            }

            $parameterTypes = $this->getParameterTypes($parameters);
            $this->parameterTypeCache[$name] = $parameterTypes;
        } elseif ( ! $this->parameterTypeCache[$name]) {
            return call_user_func([$this->instance, $name]);
        } else {
            $parameterTypes = $this->parameterTypeCache[$name];
        }
        $dependValues = $this->getParameterDependValues($parameterTypes,
            $extraParameterValues);

        return call_user_func_array([$this->instance, $name], $dependValues);
    }

    protected function injectConstructor()
    {
        $parameterTypes = $this->getConstructorParameterTypes();
        if (false === $parameterTypes) {
            $this->instance = $this->class->newInstanceWithoutConstructor();
        } elseif (empty($parameterTypes)) {
            $this->instance = $this->class->newInstance();
        } else {
            $dependValues = $this->getParameterDependValues($parameterTypes);
            $this->instance = $this->class->newInstanceArgs($dependValues);
        }
    }

    protected function injectInterface()
    {
        // @TODO
    }

    protected function injectSetter()
    {
        // @TODO
    }

    protected function getConstructorParameterTypes()
    {
        if ( ! ($constructor = $this->class->getConstructor())) {
            return false;
        }

        if ( ! ($parameters = $constructor->getParameters())) {
            return [];
        }

        return $this->getParameterTypes($parameters);
    }
}