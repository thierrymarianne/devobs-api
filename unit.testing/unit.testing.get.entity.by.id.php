<?php

$class_dumper = $class_application::getDumperClass();

$class_entity = $class_application::getEntityClass();

$class_dumper::log(
    __METHOD__,
    array(
        '$class_entity::getById( 202 )',
        $class_entity::getById( 202 )
    ),
    $verbose_mode
);
