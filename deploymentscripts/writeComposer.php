<?php


function writePHP7Config() {
    $dir = __DIR__ . "/../";
    file_put_contents($dir . "/composer.json", file_get_contents(__DIR__ . "/packagefiles/php7composer.json"));
}


function writePHP8Config() {
    $dir = __DIR__ . "/../";
    file_put_contents($dir . "/composer.json", file_get_contents(__DIR__ . "/packagefiles/php8composer.json"));
}



function incrementLastTag($version) {
    $tag_file = __DIR__ . "/packagefiles/tags.json";
    $tags = json_decode(file_get_contents($tag_file));
    $tag = $tags->$version;
    $exp = explode(".", $tag);
    $count = count($exp);
    $exp[$count - 1] = (int) $exp[$count - 1] + 1;
    $tags->$version = implode(".", $exp);
    file_put_contents($tag_file, json_encode($tags));
    return $tags->$version;
}



if ($argc < 2) {
    echo "You need to enter a version";
}

if ($argv[1] == 7) {
    writePHP7Config();
} else if ($argv[1] == 8) {
    writePHP8Config();
}


$tag = incrementLastTag("v" . $argv[1]);
echo "Set tag to " . $tag;

