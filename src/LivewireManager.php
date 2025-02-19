<?php

namespace Livewire;

use Exception;
use Livewire\Connection\ComponentHydrator;
use Livewire\Exceptions\ComponentNotFoundException;
use Livewire\Testing\TestableLivewire;

class LivewireManager
{
    protected $prefix = 'wire';
    protected $componentAliases = [];
    protected $middlewaresFilter;

    public function prefix($prefix = null)
    {
        // Yes, this is both a getter and a setter. Fight me.
        return $this->prefix = $prefix ?: $this->prefix;
    }

    public function component($alias, $viewClass)
    {
        $this->componentAliases[$alias] = $viewClass;
    }

    public function getComponentClass($alias)
    {
        $finder = app()->make(LivewireComponentsFinder::class);

        $class = $this->componentAliases[$alias]
            ?? $finder->find($alias);

        throw_unless($class, new ComponentNotFoundException(
            "Unable to find component: [{$alias}]"
        ));

        return $class;
    }

    public function activate($component)
    {
        $componentClass = $this->getComponentClass($component);

        throw_unless(class_exists($componentClass), new Exception(
            "Component [{$component}] class not found: [{$componentClass}]"
        ));

        return new $componentClass;
    }

    public function assets($options = null)
    {
        $options = $options ? json_encode($options) : '';
        $manifest = json_decode(file_get_contents(__DIR__.'/../dist/mix-manifest.json'), true);
        $versionedFileName = $manifest['/livewire.js'];

        $csrf = csrf_token();

        return <<<EOT
<!-- Livewire Assets-->
<style>[wire\:loading] { display: none; }</style>
<script src="/livewire{$versionedFileName}"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        window.livewire = new Livewire({$options});
        window.livewire_token = "{$csrf}";
    });
</script>
EOT;
    }

    public function mount($name, ...$options)
    {
        $instance = $this->activate($name);
        $instance->mount(...$options);
        $dom = $instance->output();
        $id = str_random(20);
        $properties = ComponentHydrator::dehydrate($instance);
        $events = $instance->getEventsBeingListenedFor();
        $children = $instance->getRenderedChildren();
        $checksum = md5($name.$id);

        $middlewareStack = $this->currentMiddlewareStack();
        if ($this->middlewaresFilter) {
            $middlewareStack = array_filter($middlewareStack, $this->middlewaresFilter);
        }
        $middleware = encrypt($middlewareStack, $serialize = true);

        return new InitialResponsePayload([
            'id' => $id,
            'dom' => $dom,
            'data' => $properties,
            'name' => $name,
            'checksum' => $checksum,
            'children' => $children,
            'events' => $events,
            'middleware' => $middleware,
        ]);
    }

    public function currentMiddlewareStack()
    {
        if (app()->runningUnitTests()) {
            // There is no "request->route()" to access in unit tests.
            return [];
        }

        return request()->route()->gatherMiddleware();
    }

    public function filterMiddleware($filter)
    {
        return $this->middlewaresFilter = $filter;
    }

    public function dummyMount($id, $tagName)
    {
        return "<{$tagName} wire:id=\"{$id}\"></{$tagName}>";
    }

    public function test($name, ...$params)
    {
        return new TestableLivewire($name, $this->prefix, $params);
    }
}
