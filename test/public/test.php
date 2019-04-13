<?php
//1: gif, 2: jpg, 3: png, 其他: bmp
$t = 2;
echo $t == 1 ? 'gif' : ($t == 2 ? 'jpg' : ($t == 3 ? 'png' : 'bmp'));
