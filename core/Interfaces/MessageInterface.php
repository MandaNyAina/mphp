<?php

namespace Core\Interfaces;

interface MessageInterface {
    public function info($value, $width = "100%", $class = "", $style = ""): void;
    public function error($value,$width="100%",$class="",$style=""): void;
    public function warning($value,$width="100%",$class="",$style=""): void;
    public function success($value,$width="100%",$class="",$style=""): void;
}