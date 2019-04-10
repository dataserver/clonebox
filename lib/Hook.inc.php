<?php
class Hook
{

    /**
     * Mediator Pattern
     * https://avenir.ro/authentication-system-with-ion-auth-and-ci3/alter-the-way-ion-auth-works-by-using-hooks-get-a-gravatar-after-the-user-logs-in/
     * https://blog.ircmaxell.com/2012/03/handling-plugins-in-php.html
     */
    protected $_hooks;

    public function __construct()
    {
        $this->_hooks = new stdClass;
    }

    /**
     * Attach or Set a hook to event. Later called by trigger()
     *
     * @param string  $event
     * @param string  $name     call label
     * @param string  $class    instance  (used on call_user_func_array)
     * @param string  $method   method of instance
     * @param array   $args     args for instance method
     * @return void
     */
    public function attach(string $event, string $name, object $class, string $method, array $args)
    {
        $this->_hooks->{$event}[$name]            = new stdClass;
        $this->_hooks->{$event}[$name]->class     = $class;
        $this->_hooks->{$event}[$name]->method    = $method;
        $this->_hooks->{$event}[$name]->args      = $args;
    }

    /**
     * Remove hooks will call label named $name
     *
     * @param string $event
     * @param string $name
     * @return void
     */
    public function remove_hook(string $event, string $name)
    {
        if (isset($this->_hooks->{$event}[$name])) {
            unset($this->_hooks->{$event}[$name]);
        }
    }

    /**
     * Remove all hooks relate to event
     *
     * @param string $event
     * @return void
     */
    public function remove_hooks(string $event)
    {
        if (isset($this->_hooks->$event)) {
            unset($this->_hooks->$event);
        }
    }

    /**
     * Trigger associated $events. string or array of values
     *
     * @param mixed  Either a string name of the event or an array with string values
     * @return void
     */
    public function trigger(/* mixed */ $events)
    {
        if (!is_array($events)) {
            $events = array($events);
        }
        if (is_array($events) && !empty($events)) {
            foreach ($events as $event) {
                if (isset($this->_hooks->$events) && !empty($this->_hooks->$events)) {
                    foreach ($this->_hooks->$events as $name => $hook) {
                        $this->_callback_hook($events, $name);
                    }
                }
            }
        }
    }

    /**
     * callback
     *
     * @param string $event
     * @param string $name
     * @return bool|mixed
     */
    protected function _callback_hook(string $event, string $name)
    {
        if (isset($this->_hooks->{$event}[$name])
            &&
            method_exists($this->_hooks->{$event}[$name]->class, $this->_hooks->{$event}[$name]->method)
        ) {
            $hook = $this->_hooks->{$event}[$name];

            return call_user_func_array([$hook->class, $hook->method], $hook->args);
        }

        return false;
    }
}
