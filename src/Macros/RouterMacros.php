<?php

namespace Livewire\Macros;

use Illuminate\Routing\RouteRegistrar;

class RouterMacros
{
    public function layout()
    {
        return function ($layout) {
           return (new RouteRegistrarWithAllowedAttributes($this))
                ->allowAttributes('layout', 'section')
                ->layout($layout);
        };
    }

    public function section()
    {
        return function ($section) {
           return (new RouteRegistrarWithAllowedAttributes($this))
                ->allowAttributes('layout', 'section')
                ->section($section);
        };
    }

    public function livewire()
    {
        return function ($uri, $component) {
            return $this->get($uri, function () use ($component) {
                $componentClass = app('livewire')->getComponentClass($component);
                $reflected = new \ReflectionClass($componentClass);

                return app('view')->file(__DIR__ . '/livewire-view.blade.php', [
                    'layout' => $this->current()->getAction('layout') ?? 'layouts.app',
                    'section' => $this->current()->getAction('section') ?? 'content',
                    'component' => $component,
                    'componentOptions' => $reflected->hasMethod('mount')
                        ? (new PretendClassMethodIsControllerMethod($reflected->getMethod('mount'), $this))->retrieveBindings()
                        : [],
                ]);
            });
        };
    }
}
