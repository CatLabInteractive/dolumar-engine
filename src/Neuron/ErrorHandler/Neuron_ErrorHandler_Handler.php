<?php

class Neuron_ErrorHandler_Handler
{
    /**
     * @return Neuron_ErrorHandler_Handler
     */
    public static function getInstance()
    {
        static $in;
        if (!isset($in)) {
            $in = new self();
        }
        return $in;
    }

    /**
     * Neuron_ErrorHandler_Handler constructor.
     */
    protected function __construct()
    {

    }

    /**
     * @param Throwable $e
     * @throws Exception
     */
    public function notify(Throwable $e)
    {
        error_log ($e->getMessage());
        if (class_exists(\Airbrake\Instance::class) && defined ('AIRBRAKE_TOKEN')) {
            \Airbrake\Instance::notify($e);
        }
    }
}