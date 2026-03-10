<?php
echo "GD loaded? ";
var_dump(extension_loaded('gd'));
echo "<br>Functions: ";
var_dump(function_exists('imagecreatetruecolor'));