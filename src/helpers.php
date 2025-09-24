<?php

if (! function_exists('craftile')) {
    /**
     * Get the Craftile manager instance.
     *
     * @return \Craftile\Laravel\Craftile
     */
    function craftile()
    {
        return app('craftile');
    }
}
