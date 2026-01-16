<?php
declare(strict_types=1);

namespace App\Core;

/**
 * 轻量级依赖注入容器（无 Composer / 无第三方 DI）
 *
 * 特性：
 * - 基于 Reflection 的自动装配（auto-wiring）
 * - 支持单例注册与复用（适合 DB / 配置等）
 */
class Container
{
    /**
     * 已注册的单例实例
     *
     * @var array<string, object>
     */
    private array $instances = [];

    /**
     * 绑定（抽象 => 具体）
     *
     * @var array<string, string>
     */
    private array $bindings = [];

    /**
     * 注册单例实例
     */
    public function singleton(string $id, object $instance): void
    {
        $this->instances[$id] = $instance;
    }

    /**
     * 绑定抽象到具体类
     */
    public function bind(string $abstract, string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    /**
     * 获取实例（自动装配）
     *
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        $class = $this->bindings[$id] ?? $id;
        $object = $this->make($class);

        return $object;
    }

    /**
     * 创建实例（反射自动注入构造函数参数）
     *
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    private function make(string $class): object
    {
        if (isset($this->instances[$class])) {
            return $this->instances[$class];
        }

        if (!class_exists($class)) {
            throw new \RuntimeException("Container: 类不存在: {$class}");
        }

        $ref = new \ReflectionClass($class);
        if (!$ref->isInstantiable()) {
            throw new \RuntimeException("Container: 类不可实例化: {$class}");
        }

        $ctor = $ref->getConstructor();
        if ($ctor === null || $ctor->getNumberOfParameters() === 0) {
            return $ref->newInstance();
        }

        $args = [];
        foreach ($ctor->getParameters() as $param) {
            $type = $param->getType();

            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $depId = $type->getName();
                $args[] = $this->get($depId);
                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            $name = $param->getName();
            throw new \RuntimeException("Container: 无法解析 {$class} 构造函数参数: {$name}");
        }

        return $ref->newInstanceArgs($args);
    }
}
