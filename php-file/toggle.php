<?php
if (!file_exists("/sys/class/gpio/gpio44")) {
    file_put_contents("/sys/class/gpio/export", "44");
    file_put_contents("out", "/sys/class/gpio/gpio44/direction");
}

file_put_contents(("1" == file_get_contents("/sys/class/gpio/gpio44/")) ? "0" : "1");

