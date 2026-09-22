<?php

$template = $setup->tpl->load('step1.twig');
echo $template->render([
    'step' => 1,
    'phpversion' => phpversion(),
    'status' => $setup->requirementsCheck()
]);
