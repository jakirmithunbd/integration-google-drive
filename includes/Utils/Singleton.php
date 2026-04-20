<?php

namespace CodeConfig\IGD\Utils;

defined('ABSPATH') || exit('No direct script access allowed');

trait Singleton
{
    /**
     * The singleton instance.
     *
     * @var static|null
     */
    private static $instance = null;

    /**
     * Private constructor to prevent direct instantiation.
     */
    public function __construct($args = null)
    {
        // Prevent instantiation.
    }

    /**
     * Returns an instance of the class. If the class has not yet been instantiated, it will be instantiated first.
     * If an instance exists but the identifier differs, a new instance will be created.
     *
     * @param mixed|null $args Optional arguments to pass to the constructor.
     * @param string $argsName The name of the argument to check for instance uniqueness. Defaults to 'accountId'.
     *
     * @return static
     */
    final public static function getInstance($args = null, string $argsName = 'accountId')
    {
        if (null === static::$instance || static::$instance->getInstanceIdentifier($argsName) !== $args) {
            static::$instance = new static($args);
            if (method_exists(static::$instance, 'doHooks')) {
                static::$instance->doHooks();
            }
        }

        return static::$instance;
    }

    /**
     * Returns the identifier value used to determine instance uniqueness.
     * Override this method in child classes to customize identifier retrieval.
     *
     * @param string $argsName The name of the identifier property or key.
     * @return mixed|null The identifier value or null if not set.
     */
    protected function getInstanceIdentifier(string $argsName)
    {
        return property_exists($this, $argsName) ? $this->{$argsName} : null;
    }
}
