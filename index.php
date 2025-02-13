<?php
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'Helper.php');
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php');

$files = parse_ini_file(__DIR__ . DIRECTORY_SEPARATOR . 'files.ini', true);
$templates = __DIR__ . DIRECTORY_SEPARATOR . 'templates';

$entities = Helper::getData();

foreach($entities as $entity)
{
    $entity = array_merge($entity, Helper::getFields($entity['g_table']));
    $entity['is_complex'] = sizeof($entity['complex']) > 0;

    foreach($files as $key => $file)
    {
        if(!$entity['is_complex'] && $key == 'helper') { continue; }
        $mustache = new Mustache_Engine(); 

        $name = str_replace('{{name}}', $entity['g_class'], $file['name']);
        $path = str_replace('{{name}}', $entity['g_object'], $file['path']);
        if(!is_dir($path)) { mkdir($path, 0777, true); }
        $_file = $path . DIRECTORY_SEPARATOR . $name;
        file_put_contents($_file, $mustache->render(file_get_contents($templates . DIRECTORY_SEPARATOR . $file['template']), $entity));
    }
}