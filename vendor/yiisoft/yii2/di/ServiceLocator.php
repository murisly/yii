<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\di;

use Yii;
use Closure;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * ServiceLocator implements a [service locator](http://en.wikipedia.org/wiki/Service_locator_pattern).
 *
 * To use ServiceLocator, you first need to register component IDs with the corresponding component
 * definitions with the locator by calling [[set()]] or [[setComponents()]].
 * You can then call [[get()]] to retrieve a component with the specified ID. The locator will automatically
 * instantiate and configure the component according to the definition.
 *
 * For example,
 *
 * ```php
 * $locator = new \yii\di\ServiceLocator;
 * $locator->setComponents([
 *     'db' => [
 *         'class' => 'yii\db\Connection',
 *         'dsn' => 'sqlite:path/to/file.db',
 *     ],
 *     'cache' => [
 *         'class' => 'yii\caching\DbCache',
 *         'db' => 'db',
 *     ],
 * ]);
 *
 * $db = $locator->get('db');  // or $locator->db
 * $cache = $locator->get('cache');  // or $locator->cache
 * ```
 *
 * Because [[\yii\base\Module]] extends from ServiceLocator, modules and the application are all service locators.
 *
 * @property array $components The list of the component definitions or the loaded component instances (ID =>
 * definition or instance).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class ServiceLocator extends Component
{
    /**
     * 用于缓存服务、组件等的实例
     * @var array shared component instances indexed by their IDs
     */
    private $_components = [];
    /**
     * 用于保存服务和组件的定义，通常为配置数组，可以用来创建具体的实例
     * @var array component definitions indexed by their IDs
     */
    private $_definitions = [];


    /**
     * Getter magic method.
     * This method is overridden to support accessing components like reading properties.
     * @param string $name component or property name
     * @return mixed the named property value
     */
    public function __get($name)
    {
        if ($this->has($name)) {
            return $this->get($name);
        } else {
            return parent::__get($name);
        }
    }

    /**
     * 重写的父类方法，不存在就调用父类的 __set 函数
     * Checks if a property value is null.
     * This method overrides the parent implementation by checking if the named component is loaded.
     * @param string $name the property name or the event name
     * @return boolean whether the property value is null
     */
    public function __isset($name)
    {
        if ($this->has($name, true)) {
            return true;
        } else {
            return parent::__isset($name);
        }
    }

    /**
     * Returns a value indicating whether the locator has the specified component definition or has instantiated the component.
     * This method may return different results depending on the value of `$checkInstance`.
     *
     * - If `$checkInstance` is false (default), the method will return a value indicating whether the locator has the specified
     *   component definition.
     * - If `$checkInstance` is true, the method will return a value indicating whether the locator has
     *   instantiated the specified component.
     *
     * @param string $id component ID (e.g. `db`).
     * @param boolean $checkInstance whether the method should check if the component is shared and instantiated.
     * @return boolean whether the locator has the specified component definition or has instantiated the component.
     * @see set()
     */
    public function has($id, $checkInstance = false)
    {
        return $checkInstance ? isset($this->_components[$id]) : isset($this->_definitions[$id]);
    }

    /**
     * 返回组件列表的特定实例
     * Returns the component instance with the specified ID.
     *
     * @param string $id component ID (e.g. `db`).
     * @param boolean $throwException whether to throw an exception if `$id` is not registered with the locator before.
     * @return object|null the component of the specified ID. If `$throwException` is false and `$id`
     * is not registered before, null will be returned.
     * @throws InvalidConfigException if `$id` refers to a nonexistent component ID
     * @see has()
     * @see set()
     */
    public function get($id, $throwException = true)
    {
        if (isset($this->_components[$id])) {
            return $this->_components[$id];
        }

        if (isset($this->_definitions[$id])) {
            $definition = $this->_definitions[$id];
            if (is_object($definition) && !$definition instanceof Closure) {
                return $this->_components[$id] = $definition;
            } else {
                return $this->_components[$id] = Yii::createObject($definition);
            }
        } elseif ($throwException) {
            throw new InvalidConfigException("Unknown component ID: $id");
        } else {
            return null;
        }
    }

    /**
     * 注册一个组件的定义方式
     * Registers a component definition with this locator.
     *
     * For example,
     *
     * ```php
     * // a class name 注册一个类
     * $locator->set('cache', 'yii\caching\FileCache');
     *
     * // a configuration array 一个配置数组
     * $locator->set('db', [
     *     'class' => 'yii\db\Connection',
     *     'dsn' => 'mysql:host=127.0.0.1;dbname=demo',
     *     'username' => 'root',
     *     'password' => '',
     *     'charset' => 'utf8',
     * ]);
     *
     * // an anonymous function 注册一个匿名函数
     * $locator->set('cache', function ($params) {
     *     return new \yii\caching\FileCache;
     * });
     *
     * // an instance 注册一个实例
     * $locator->set('cache', new \yii\caching\FileCache);
     * ```
     *
     * If a component definition with the same ID already exists, it will be overwritten. 覆盖之前的配置
     *
     * @param string $id component ID (e.g. `db`).
     * @param mixed $definition the component definition to be registered with this locator.
     * It can be one of the following:
     *
     * - a class name
     * - a configuration array: the array contains name-value pairs that will be used to
     *   initialize the property values of the newly created object when [[get()]] is called.
     *   The `class` element is required and stands for the the class of the object to be created.
     * - a PHP callable: either an anonymous function or an array representing a class method (e.g. `['Foo', 'bar']`).
     *   The callable will be called by [[get()]] to return an object associated with the specified component ID.
     * - an object: When [[get()]] is called, this object will be returned.
     *
     * @throws InvalidConfigException if the definition is an invalid configuration array
     */
    public function set($id, $definition)
    {
        // 删除组件
        if ($definition === null) {
            unset($this->_components[$id], $this->_definitions[$id]);
            return;
        }

        // 准备覆盖之前组件
        unset($this->_components[$id]);

        if (is_object($definition) || is_callable($definition, true)) {
            // an object, a class name, or a PHP callable
            $this->_definitions[$id] = $definition;
        } elseif (is_array($definition)) {
            // a configuration array
            if (isset($definition['class'])) {
                $this->_definitions[$id] = $definition; // 直接保存数组
            } else {
                throw new InvalidConfigException("The configuration for the \"$id\" component must contain a \"class\" element.");
            }
        } else {
            throw new InvalidConfigException("Unexpected configuration type for the \"$id\" component: " . gettype($definition));
        }
    }

    /**
     * 清除一个组件，包括实例和定义
     * Removes the component from the locator.
     * @param string $id the component ID
     */
    public function clear($id)
    {
        unset($this->_definitions[$id], $this->_components[$id]);
    }

    /**
     * 返回组件的列表
     * Returns the list of the component definitions or the loaded component instances.
     * @param boolean $returnDefinitions whether to return component definitions instead of the loaded component instances.
     * @return array the list of the component definitions or the loaded component instances (ID => definition or instance).
     */
    public function getComponents($returnDefinitions = true)
    {
        return $returnDefinitions ? $this->_definitions : $this->_components;
    }

    /**
     * 注册一组系统组件
     * Registers a set of component definitions in this locator.
     *
     * This is the bulk version of [[set()]]. The parameter should be an array
     * whose keys are component IDs and values the corresponding component definitions.
     *
     * For more details on how to specify component IDs and definitions, please refer to [[set()]].
     *
     * 如果存在相同的id组件，之前的将会被覆盖
     * If a component definition with the same ID already exists, it will be overwritten.
     *
     * The following is an example for registering two component definitions:
     *
     * ```php
     * [
     *     'db' => [
     *         'class' => 'yii\db\Connection',
     *         'dsn' => 'sqlite:path/to/file.db',
     *     ],
     *     'cache' => [
     *         'class' => 'yii\caching\DbCache',
     *         'db' => 'db',
     *     ],
     * ]
     * ```
     *
     * @param array $components component definitions or instances
     */
    public function setComponents($components)
    {
        foreach ($components as $id => $component) {
            $this->set($id, $component);
        }
    }
}
