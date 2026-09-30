<?php

declare(strict_types=1);

namespace NEvents\Core;

class Container
{
    private array $bindings   = [];
    private array $singletons = [];
    private array $resolved   = [];

    public function bind(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = $factory;
    }

    public function singleton(string $abstract, callable $factory): void
    {
        $this->singletons[$abstract] = $factory;
    }

    public function resolve(string $abstract): mixed
    {
        if (isset($this->resolved[$abstract])) {
            return $this->resolved[$abstract];
        }

        if (isset($this->singletons[$abstract])) {
            $this->resolved[$abstract] = ($this->singletons[$abstract])($this);
            return $this->resolved[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            return ($this->bindings[$abstract])($this);
        }

        return $this->autoResolve($abstract);
    }

    private function autoResolve(string $abstract): mixed
    {
        if (!class_exists($abstract)) {
            throw new \RuntimeException("Cannot resolve [{$abstract}] — not bound and class not found.");
        }

        $ref = new \ReflectionClass($abstract);
        $constructor = $ref->getConstructor();

        if ($constructor === null) {
            return $ref->newInstance();
        }

        $params = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $params[] = $this->resolve($type->getName());
            } elseif ($param->isDefaultValueAvailable()) {
                $params[] = $param->getDefaultValue();
            } else {
                throw new \RuntimeException("Cannot auto-resolve parameter [{$param->getName()}] in [{$abstract}].");
            }
        }

        return $ref->newInstanceArgs($params);
    }
}
