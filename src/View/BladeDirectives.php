<?php

namespace Craftile\Laravel\View;

class BladeDirectives
{
    /**
     * Region directive - renders a region view
     * Usage: @craftileRegion('region_name').
     */
    public static function region($expression): string
    {
        return "<?php echo view('regions.' . {$expression})->render(); ?>";
    }

    /**
     * children directive - renders a block children
     * Usage: @children
     */
    public static function children(): string
    {
        return '<?php if(isset($children) && is_callable($children)) { echo $children(); } ?>';
    }
}
