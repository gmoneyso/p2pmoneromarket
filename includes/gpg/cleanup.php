<?php
function gpg_cleanup(string $dir): void
{
    exec("rm -rf " . escapeshellarg($dir));
}
