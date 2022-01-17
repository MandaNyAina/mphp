<?php

namespace Core\Lib\Plugins;
use \Core\Interfaces\MessageInterface;

class Message implements MessageInterface {

    public function info($value, $width = "100%", $class = "", $style = ""): void {
        echo '<div class="alert alert-primary ' . $class . '" style="width:' . $width . ';' . $style . '">' . $value . '</div>';
    }

    public function error($value,$width="100%",$class="",$style=""): void {
        echo '<div class="alert alert-danger '.$class.'" style="width:'.$width.';'.$style.'">'.$value.'</div>';
    }

    public function warning($value,$width="100%",$class="",$style=""): void {
        echo '
            <div class="alert alert-warning '.$class.'" style="width:'.$width.';'.$style.'">
                '.$value.'
            </div>
        ';
    }

    public function success($value,$width="100%",$class="",$style=""): void {
        echo '
            <div class="alert alert-success '.$class.'" style="width:'.$width.';'.$style.'">
                '.$value.'
            </div>
        ';
    }
}
?>