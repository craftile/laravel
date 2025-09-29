<?php

namespace Craftile\Laravel\View;

class BladeDirectives
{
    /**
     * children directive - renders a block children
     * Usage: @children
     */
    public static function children(): string
    {
        return '<?php if(isset($children) && is_callable($children)) { echo $children(); } ?>';
    }
}
