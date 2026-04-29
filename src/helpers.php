<?php

use Craftile\Laravel\Craftile;

if (! function_exists('craftile')) {
    /**
     * Get the Craftile manager instance.
     *
     * @return Craftile
     */
    function craftile()
    {
        return app('craftile');
    }
}
