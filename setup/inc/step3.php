<?php
$admins = $setup->checkAdmins();
$error = $admins === null;

if (isset($_POST['username']) || isset($_POST['password'])) {
    if ($admins !== false || !$setup->createAdmin(
        $_POST['username'] ?? null,
        $_POST['password'] ?? null
    )) {
        $error = true;
    } else {
        Setup::markInstalled();
        include(__DIR__ . '/step4.php');
        return;
    }
}

$admins = $setup->checkAdmins();
if ($admins === true) {
    Setup::markInstalled();
}
$template = $setup->tpl->load('step3.twig');
echo $template->render([
    'step' => 3,
    'admins' => $admins === true,
    'can_create' => $admins === false,
    'error' => $error
]);
